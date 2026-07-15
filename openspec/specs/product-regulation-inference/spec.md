## ADDED Requirements

### Requirement: BOM 驅動法規自動推算
系統 SHALL 根據 SalesProduct 的 BomLine 組成，自動推算適用法規清單，並儲存至 `inferred_regulations` 欄位。推算邏輯：遍歷所有 BomLine → 取得 effective 物料群組（優先採用 `materialItem->materialGroup`，若該 BomLine 未關聯 `material_item_id` 或其 MaterialItem 未綁定物料群組，則 fallback 採用 BomLine 自身的 `materialGroup`）→ 取得該物料群組的 `required_doc_types` → 將 doc_type 對應至法規 key（EUDR/UFLPA/CMRT/SDS/CE），去重後存入。兩種來源皆無物料群組的 BomLine 跳過（不推算）。

#### Scenario: 有 BomLine 含 EUDR doc_type 的產品
- **WHEN** SalesProduct 有至少一條 BomLine 的 effective 物料群組 `required_doc_types` 含 'EUDR_DDS'
- **THEN** 該產品的 `inferred_regulations` 包含 'EUDR'

#### Scenario: 無任何 BomLine 有物料群組的產品
- **WHEN** 所有 BomLine 的 effective 物料群組（`materialItem->materialGroup` 與自身 `materialGroup`）皆為 null
- **THEN** `inferred_regulations` 為空陣列

#### Scenario: 多個 BomLine 指向相同法規
- **WHEN** 多條 BomLine 的 effective 物料群組皆含相同 doc_type
- **THEN** `inferred_regulations` 中該法規 key 不重複出現

#### Scenario: BomLine 關聯物料主檔但物料群組不同於自身欄位
- **WHEN** 某 BomLine 的 `material_item_id` 關聯的 MaterialItem 有 `material_group_id`，且與該 BomLine 自身的 `material_group_id` 不同
- **THEN** 法規推算 SHALL 採用 MaterialItem 的物料群組（effective 來源），而非 BomLine 自身欄位

#### Scenario: BomLine 關聯的物料主檔未設定物料群組
- **WHEN** 某 BomLine 的 `material_item_id` 關聯的 MaterialItem 的 `material_group_id` 為 null，但該 BomLine 自身的 `material_group_id` 有值
- **THEN** 法規推算 SHALL fallback 採用 BomLine 自身的物料群組

### Requirement: 手動觸發法規重算
系統 SHALL 提供 API endpoint 讓採購商手動觸發單一產品的法規重算，並立即回傳推算結果。

#### Scenario: 成功觸發重算
- **WHEN** 使用者呼叫 `POST /api/v1/compliance/products/{id}/sync-regulations`
- **THEN** 系統執行推算、更新 `inferred_regulations`、回傳最新產品資料（含兩個法規欄位）

#### Scenario: 產品不存在
- **WHEN** 使用者以不存在的 product id 呼叫觸發 endpoint
- **THEN** 系統回傳 404

### Requirement: 每日排程批量推算
系統 SHALL 每日凌晨自動執行所有 BuyerProduct 的法規推算，確保 `inferred_regulations` 資料不超過 T+1 偏差。

#### Scenario: 排程正常執行
- **WHEN** 每日排程觸發 `sync:product-regulations` Artisan Command
- **THEN** 所有 BuyerProduct 的 `inferred_regulations` 更新至最新 BOM 狀態

#### Scenario: 手動執行 Artisan Command
- **WHEN** 管理員執行 `php artisan sync:product-regulations`
- **THEN** 全部產品重算完成，Console 輸出處理筆數
