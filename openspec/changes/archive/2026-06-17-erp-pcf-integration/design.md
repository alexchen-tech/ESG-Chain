## Context

目前 ESG-Chain 的 BOM、供應商、物料資料全靠 Seeder 手動植入，缺乏正式 ERP 同步機制。`PcfRequest` 以供應商為粒度，`pcf_request_lines` 只存自由文字 material_name，無法對應 `MaterialItemEmission`，導致碳排填報後無法自動觸發 PCF 重算。`MaterialItemEmission` 建立後目前需手動呼叫 `pcf-recalc` 端點。

三個核心問題：
1. ERP 資料進不來（無同步層）
2. BOM 匯入後碳排請求不自動發出
3. 供應商填報後 PCF 不自動重算

## Goals / Non-Goals

**Goals:**

- 建立三種 ERP 同步路徑（CSV / Webhook / Scheduled）的統一入口，欄位歸屬嚴格保護
- BOM 匯入時自動 upsert MaterialItem + BomLineSupplier，匯入後掃描碳排缺口並建立 PcfRequest
- `PcfRequestLine` 以 `material_item_id × supplier_id` 為粒度，填報後自動觸發 PCF 重算
- PCF 快照 append-only，Shipment 鎖定建立當下的 snapshot_id

**Non-Goals:**

- ERP 系統側的連接器開發（只定義 ESG-Chain 接收端）
- 完整的 CBAM / EUDR 申報生成（獨立 change）
- 多租戶 ERP 連線管理 UI

## Decisions

### D1：BOM 匯入時直接 upsert MaterialItem，不走獨立映射步驟

**決策**：`material_code`（ERP 物料編碼）直接成為 `MaterialItem.item_code`，匯入時呼叫 `MaterialItem::updateOrCreate(['item_code' => $code], [...])`。

**理由**：ERP 物料主檔是唯一可信來源，不需要 ESG-Chain 維護一套獨立的物料編碼體系。映射步驟會增加維護負擔且易出錯。

**替代方案**：維護 ERP code → MaterialItem 的映射表 — 否決，增加複雜度且映射本身就是人工誤差來源。

---

### D2：PcfRequest 以供應商為通知單位，PcfRequestLine 以 MaterialItem 為填報粒度

**決策**：一個 supplier 一張 PcfRequest，其下多條 PcfRequestLine（每條對應一個 material_item_id）。

**理由**：Portal 通知以供應商為單位（一封通知說「你有 N 個物料需填報」），追蹤以物料為粒度（每個物料填報狀態獨立）。供應商 UX 和系統追蹤需求都滿足。

**替代方案**：每個 (material, supplier) pair 一張 PcfRequest — 否決，供應商會收到大量獨立通知，UX 差。

---

### D3：MaterialItemEmission Observer 觸發 PCF 重算（非同步 Celery）

**決策**：在 `MaterialItemEmission` Model 上掛 `created` Observer，dispatch Celery job `recalc_pcf_for_affected_products`，job 找出所有使用此 (material_item × supplier) 的 BuyerProduct，逐一呼叫 `PcfCalculationService::snapshot()`。

**理由**：重算可能涉及多個產品，需非同步執行避免 HTTP timeout。Observer pattern 讓觸發邏輯集中在模型層，不散落各 Controller。

**替代方案**：在 Portal 填報 Controller 直接同步重算 — 否決，timeout 風險高；邏輯分散。

---

### D4：ERP 欄位保護 — `erp_sync_source` 欄位標記

**決策**：`product_bom_lines` 新增 `erp_sync_source: csv|webhook|scheduled|manual`，`erp_synced_at`。upsert 時只更新 ERP 擁有欄位（quantity, hs_code, unit_price）；ESG 標注欄位（notes, material_group_source = manual）跳過。

**理由**：防止 ERP 同步覆蓋 ESG 團隊手動標注的資料。欄位級別的保護比表級別的保護更細緻。

---

### D5：Shipment.pcf_snapshot_id 在建立時鎖定，不隨後續更新變動

**決策**：Shipment 建立時取 `BuyerProduct.latest_pcf_snapshot_id` 寫入 `shipments.pcf_snapshot_id`，此後 PCF 重算不影響已建立的 Shipment。

**理由**：出口申報需要「申報當下」的 PCF 值，後續更新不應追溯影響已申報的批次。符合 ISO 14067 時間界定要求。

## Risks / Trade-offs

- **ERP 同步衝突**：兩個同步源（Webhook + Scheduled）可能同時更新同一筆記錄 → Mitigation：upsert 加資料庫層 unique constraint + updated_at 比較，較舊資料跳過
- **碳排缺口掃描效能**：BOM 有大量物料行時，掃描全部 (material × supplier) 可能慢 → Mitigation：掃描改為 Celery job 非同步執行，BOM 匯入 API 立即返回，掃描後台進行
- **PcfRequestLine migration**：現有 `pcf_request_lines` 資料 material_name 為自由文字，無法自動補 material_item_id → Mitigation：migration 新增欄位 nullable，現有資料保留不動，新建立的 line 才強制填 FK
- **Observer 重算風暴**：批次植入大量 MaterialItemEmission（如 Seeder）會觸發大量重算 job → Mitigation：Seeder 改用 `Model::withoutEvents()` 包裹，或在 job 端加 de-duplicate 邏輯（同一 product 5 秒內只重算一次）

## Migration Plan

1. 新增 migration：`pcf_request_lines.material_item_id`（nullable FK）、`pcf_request_lines.fulfilled_emission_id`（nullable FK）、`pcf_requests.trigger_source`
2. 新增 migration：`product_bom_lines.erp_synced_at`、`product_bom_lines.erp_sync_source`
3. 部署新 Laravel code（Observer、GapScanService、ERP sync endpoints）
4. 部署新 Celery task（`recalc_pcf_for_affected_products`）
5. 現有資料：執行一次性腳本，為已有 MaterialItemEmission 的產品補建 PCF 快照

Rollback：migration 可 rollback；Observer 可透過 Feature Flag 關閉（`ERP_PCF_OBSERVER_ENABLED=false`）。

## Open Questions

- Webhook 的驗證機制：HMAC-SHA256 signature header 還是 Bearer token？（建議 HMAC，與主流 ERP 廠商一致）
- 排程拉取的 ERP adapter 介面：先定義抽象介面讓各 ERP 廠商實作，還是第一版只支援標準 REST JSON？
- PcfRequest due_date 自動計算邏輯：BOM 匯入後預設給幾天？是否可在系統設定頁調整？
