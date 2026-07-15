## 1. 資料庫 Migration

- [x] 1.1 `product_bom_lines` 加欄位：`erp_sync_source` enum(csv/webhook/scheduled/manual)、`erp_synced_at` timestamp nullable
- [x] 1.2 `pcf_requests` 加欄位：`trigger_source` enum(system_bom_import/system_supplier_change/buyer_manual)、`notes` text nullable；status enum 加 `partial`
- [x] 1.3 `pcf_request_lines` 加欄位：`material_item_id` uuid nullable FK → material_items、`fulfilled_emission_id` uuid nullable FK → material_item_emissions
- [x] 1.4 更新 PcfRequest / PcfRequestLine Model `$fillable` 與 `$casts` 反映新欄位

## 2. ERP 同步閘道（erp-sync-gateway）

- [x] 2.1 建立 `ErpAdapterInterface`（app/Contracts/ErpAdapterInterface.php），定義 fetchSuppliers / fetchMaterials / fetchBomLines / fetchShipments 四個方法
- [x] 2.2 建立 `ErpSyncService`（app/Services/Erp/ErpSyncService.php），封裝欄位歸屬保護邏輯（ERP 擁有欄位覆蓋，ESG 欄位跳過）
- [x] 2.3 實作 Webhook 接收端點 `POST /api/v1/erp/webhook/{entity}`，含 HMAC-SHA256 驗證 middleware
- [x] 2.4 建立排程 Job `ErpScheduledSyncJob`（app/Jobs/ErpScheduledSyncJob.php），呼叫 Adapter 增量拉取並執行 upsert

## 3. BOM 匯入擴充（erp-bom-import）

- [x] 3.1 擴充 BOM import 邏輯：依 `material_code` 自動 upsert MaterialItem（item_code、name、hs_code），回寫 BomLine.material_item_id
- [x] 3.2 BOM import 完成後 dispatch `PcfEmissionGapScanJob`（非同步，不阻塞 API 回傳）
- [x] 3.3 BOM import 回傳新增 `erp_sync_source` 寫入與 `erp_synced_at` 更新

## 4. AVL 匯入擴充（erp-avl-import）

- [x] 4.1 AVL import 建立新 BomLineSupplier（role = primary）後，dispatch `PcfEmissionGapScanJob`

## 5. 碳排缺口掃描服務（pcf-emission-gap-scan）

- [x] 5.1 建立 `PcfEmissionGapScanService`（app/Services/PCF/PcfEmissionGapScanService.php）：掃描 (material_item_id × supplier_id) 缺口，建立或更新 PcfRequest / PcfRequestLine
- [x] 5.2 建立 Celery Job `PcfEmissionGapScanJob`（app/Jobs/PcfEmissionGapScanJob.php），呼叫 GapScanService，支援 per-product 或 per-supplier 範圍
- [x] 5.3 BomLineSupplier Observer：primary supplier 變更時 dispatch GapScanJob（supplier change 觸發）
- [x] 5.4 實作手動觸發端點 `POST /api/v1/buyer-products/{id}/bom-lines/{lineId}/request-emission`，`trigger_source = buyer_manual`

## 6. PCF 事件驅動重算（pcf-event-recalculation）

- [x] 6.1 建立 `MaterialItemEmission` Observer（app/Observers/MaterialItemEmissionObserver.php）：created 事件 dispatch `RecalcPcfForAffectedProductsJob`
- [x] 6.2 建立 Celery Job `RecalcPcfForAffectedProductsJob`（app/Jobs/RecalcPcfForAffectedProductsJob.php）：找出相關 BuyerProduct，呼叫 PcfCalculationService::snapshot()，含 5 秒 de-duplicate 邏輯
- [x] 6.3 Observer 建立後 同步更新對應 PcfRequestLine（status = submitted，fulfilled_emission_id）及 PcfRequest 聚合狀態（partial / submitted）
- [x] 6.4 `PcfCalculationService::snapshot()` 確認為 append-only（不覆蓋舊快照），BuyerProduct.latest_pcf_snapshot_id 更新為最新
- [x] 6.5 Shipment 建立時自動取 BuyerProduct.latest_pcf_snapshot_id 寫入 shipments.pcf_snapshot_id

## 7. 供應商 Portal 更新（portal-pcf-submission）

- [x] 7.1 `PortalView.vue` 首頁新增「待填碳排（PCF）」任務區塊，顯示 pending PcfRequestLine 計數與最近截止日
- [x] 7.2 PCF 任務區點擊連結至 `/supplier/portal/material-emissions`（已有的 SupplierCompliancePortalView），確認 pending PcfRequestLine 可見
- [x] 7.3 供應商填報 MaterialItemEmission 後（Portal store 或 Controller），自動呼叫或 Observer 已處理 PcfRequestLine 狀態更新（確認流程）

## 8. 驗證與 Seeder 修正

- [x] 8.1 既有 Seeder（PcfSnapshotSeeder、MaterialItemDefaultEmissionSeeder）改以 `Model::withoutEvents()` 包裹，避免觸發 Observer 重算
- [x] 8.2 撰寫 Feature Test：BOM 匯入 → 缺口掃描 → PcfRequest 建立
- [x] 8.3 撰寫 Feature Test：MaterialItemEmission 建立 → PCF 重算 → 新 PcfSnapshot → PcfRequestLine submitted
- [x] 8.4 Webhook 端點驗證：有效 / 無效 HMAC signature 各一個測試案例
