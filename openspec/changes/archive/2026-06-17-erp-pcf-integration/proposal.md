## Why

ESG-Chain 的核心資料（供應商、物料、產品 BOM、出口裝運）全部來自 ERP，但目前系統缺乏正式的 ERP 同步機制，導致資料需手動維護、BOM 與 PCF 計算脫鉤、碳排填報請求無法自動觸發。需建立完整的 ERP → ESG-Chain 同步鏈與 PCF 事件驅動計算架構。

## What Changes

- **ERP 同步層**：支援三種同步路徑（CSV 上傳、Webhook push、排程拉取），統一正規化後 upsert 至 suppliers / material_items / buyer_products / product_bom_lines / shipments，並嚴格區分 ERP 擁有欄位與 ESG-Chain 擁有欄位
- **BOM 匯入自動建立 MaterialItem**：匯入時以 `material_code` 直接 upsert `MaterialItem.item_code`，同步建立 `BomLineSupplier`（source = erp_designated），移除手動物料映射步驟
- **碳排缺口掃描**：BOM 匯入完成後自動掃描 (material_item_id × supplier_id) 缺口，建立 `PcfRequest` + `PcfRequestLine`（trigger_source = system_bom_import），供應商切換時同樣觸發
- **PcfRequest 模型重構**：`pcf_request_lines` 新增 `material_item_id`（FK）、`fulfilled_emission_id`，`pcf_requests` 新增 `trigger_source`，移除與 SAQ 的耦合欄位 `saq_round_id`
- **事件驅動 PCF 重算**：`MaterialItemEmission` 建立後自動找出所有相關 `BuyerProduct`，觸發 `PcfCalculationService::snapshot()`，新快照 append-only，舊版本保留
- **Shipment 快照鎖定**：Shipment 建立時記錄當下的 `pcf_snapshot_id`，不隨後續 PCF 更新自動變動

## Capabilities

### New Capabilities

- `erp-sync-gateway`：統一三種 ERP 同步路徑（CSV / Webhook / Scheduled）的正規化 upsert 層，含欄位歸屬保護邏輯
- `pcf-emission-gap-scan`：BOM 匯入後自動掃描碳排缺口並建立 PcfRequest/PcfRequestLine，供應商切換時重新觸發
- `pcf-event-recalculation`：MaterialItemEmission 建立後的事件驅動 PCF 重算鏈，含 snapshot append-only 版本管理

### Modified Capabilities

- `erp-bom-import`：新增 BOM 匯入時自動 upsert MaterialItem 邏輯，及匯入後觸發碳排缺口掃描
- `erp-avl-import`：新增 AVL 匯入後觸發缺口掃描，對新建 BomLineSupplier 補齊 PcfRequest
- `pcf-request-management`：pcf_request_lines 加 material_item_id FK + fulfilled_emission_id；pcf_requests 加 trigger_source；移除 saq_round_id
- `portal-pcf-submission`：供應商填報 MaterialItemEmission 後，自動更新 PcfRequestLine.status 並觸發 PCF 重算事件

## Impact

- **資料庫**：`pcf_requests` + `pcf_request_lines` migration（加欄位、加 FK）；`product_bom_lines` 加 `erp_synced_at` / `erp_sync_source`
- **Laravel**：新增 ERP sync endpoints（CSV upload / webhook receiver）；新增 `PcfEmissionGapScanService`；`MaterialItemEmission` Observer 觸發重算；`PcfCalculationService` 補 append-only 邏輯
- **Celery**：`material_emission_tasks` 調整為內網 `/celery/` 路由（已完成），重算任務改為 event-driven
- **Vue**：BOM 明細頁補「發送填報請求」手動觸發按鈕；Portal 首頁區分 SAQ tasks / PCF tasks 兩區
