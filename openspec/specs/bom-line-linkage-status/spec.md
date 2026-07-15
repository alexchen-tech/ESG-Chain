# bom-line-linkage-status

## ADDED Requirements

### Requirement: BomLine linkage_status 欄位

`product_bom_lines` 表 SHALL 新增 `linkage_status` enum 欄位（`'linked'` | `'unlinked'`），用於記錄每條 BOM 明細是否已關聯物料主檔。

欄位規格：
- `linkage_status` enum `'linked'|'unlinked'`，預設 `'unlinked'`
- 不可由 API 呼叫端直接傳入；由後端根據 `material_item_id` 自動設定
- `material_item_id IS NOT NULL` → `'linked'`；`material_item_id IS NULL` → `'unlinked'`
- migration 執行時 SHALL 回填所有既有記錄

#### Scenario: 建立 BomLine 且有 material_item_id

- **WHEN** POST `/api/v1/sales-products/{id}/bom-lines` 帶入有效 `material_item_id`
- **THEN** 系統 SHALL 自動將 `linkage_status` 設為 `'linked'`

#### Scenario: 建立 BomLine 且無 material_item_id

- **WHEN** POST `/api/v1/sales-products/{id}/bom-lines` 未傳入 `material_item_id`
- **THEN** 系統 SHALL 自動將 `linkage_status` 設為 `'unlinked'`

#### Scenario: 更新 BomLine 補上 material_item_id

- **WHEN** PATCH `/api/v1/sales-products/{id}/bom-lines/{lineId}` 傳入有效 `material_item_id`
- **THEN** 系統 SHALL 將 `linkage_status` 更新為 `'linked'`

#### Scenario: 更新 BomLine 移除 material_item_id

- **WHEN** PATCH `/api/v1/sales-products/{id}/bom-lines/{lineId}` 傳入 `material_item_id: null`
- **THEN** 系統 SHALL 將 `linkage_status` 更新為 `'unlinked'`

### Requirement: 前端 BOM 列表顯示 linkage_status 警告

BOM 明細列表 SHALL 對 `linkage_status='unlinked'` 的列顯示視覺警告標籤，提示供應鏈管理員需補連結物料主檔。

#### Scenario: unlinked BomLine 顯示警告

- **WHEN** BOM 列表載入含有 `linkage_status='unlinked'` 的明細
- **THEN** 該列 SHALL 在 material_name 欄位旁顯示橘色「未連結主檔」badge

#### Scenario: linked BomLine 無警告

- **WHEN** BOM 列表載入 `linkage_status='linked'` 的明細
- **THEN** 該列 SHALL 不顯示任何警告 badge，呈現正常樣式
