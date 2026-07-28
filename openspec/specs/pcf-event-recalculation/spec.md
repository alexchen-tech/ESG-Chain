## ADDED Requirements

### Requirement: MaterialItemEmission 建立後自動觸發 PCF 重算
系統 SHALL 在每次 `MaterialItemEmission` 記錄建立後，非同步（Celery job）找出所有在 BOM 中以該 (material_item_id × supplier_id) 為 primary 的 `BuyerProduct`，逐一執行 `PcfCalculationService::snapshot()`。Seeder / 批次植入應使用 `Model::withoutEvents()` 跳過此觸發。

#### Scenario: 填報後自動重算相關產品
- **WHEN** 供應商填報 MaterialItemEmission（material = M1，supplier = S1）
- **THEN** 系統 SHALL 找出所有 BomLine(M1, primary: S1) 所屬的 BuyerProduct，各自建立新 PcfSnapshot，舊快照保留

#### Scenario: 同一產品短時間多次觸發去重
- **WHEN** 同一 BuyerProduct 在 5 秒內被多個 MaterialItemEmission 觸發重算
- **THEN** 系統 SHALL 只執行一次 snapshot()（後續觸發 de-duplicate），避免重算風暴

#### Scenario: 無相關 BuyerProduct 時安靜跳過
- **WHEN** MaterialItemEmission 的 (M1, S1) 沒有任何 BomLine 使用
- **THEN** 系統 SHALL 不執行任何 snapshot()，job 正常完成

### Requirement: PCF 快照 append-only 版本管理
系統 SHALL 每次 `PcfCalculationService::snapshot()` 都建立新的 `PcfSnapshot` 記錄，不覆蓋舊快照。`BuyerProduct` 透過 `latest_pcf_snapshot_id` 指向最新快照，舊版本可查。

#### Scenario: 重算後最新快照更新
- **WHEN** PCF 重算完成建立新 PcfSnapshot
- **THEN** `BuyerProduct.latest_pcf_snapshot_id` SHALL 更新為新快照 ID，舊快照仍可透過 `pcf_snapshots` 表查詢

#### Scenario: 歷史快照不可刪除
- **WHEN** 嘗試刪除非最新的 PcfSnapshot
- **THEN** 系統 SHALL 拒絕（soft delete 亦不允許），回傳 403

> **已移除**：原「Shipment 建立時鎖定 PCF 快照」需求隨出口申報（Shipment）模組一併移除。系統邊界止於出口前合規檢查，出口交易執行（含正式送件的 PCF 數字鎖定）屬 ERP 範疇。

### Requirement: PcfRequestLine 填報完成後更新狀態
系統 SHALL 在 `MaterialItemEmission` 建立後，自動查找對應的 `PcfRequestLine`（匹配 material_item_id + pcf_request.supplier_id），將其 status 更新為 `submitted`，`fulfilled_emission_id` 設為新建立的 emission id。當 PcfRequest 下所有 line 均 submitted 時，PcfRequest.status 自動升為 `submitted`。

#### Scenario: 單一物料填報後 line 狀態更新
- **WHEN** 供應商填報 MaterialItemEmission（M1 × S1）
- **THEN** 對應 PcfRequestLine(M1) 的 status SHALL 更新為 submitted，fulfilled_emission_id 設為新 emission id

#### Scenario: 所有物料填報後請求狀態升級
- **WHEN** PcfRequest 下 3 條 PcfRequestLine 全部 submitted
- **THEN** PcfRequest.status SHALL 自動更新為 submitted
