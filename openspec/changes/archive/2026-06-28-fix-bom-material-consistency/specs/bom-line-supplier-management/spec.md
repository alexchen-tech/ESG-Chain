## MODIFIED Requirements

### Requirement: BomLineSupplier hs_code / material_name 讀取順序

系統在讀取 BomLine 的 `hs_code` 與 `material_name` 供合規申報、CBAM 計算等用途時，SHALL 採用「主檔優先（effective）」順序：`materialItem?.hs_code ?? bomLine->hs_code`，`materialItem?.name ?? bomLine->material_name`。

快照欄位（`bomLine->hs_code`、`bomLine->material_name`）SHALL 僅在 `material_item_id` 為 null 時作為 fallback 使用。

#### Scenario: BomLine 有 material_item_id 時讀取 hs_code

- **WHEN** 系統讀取某 BomLine 的 hs_code（如用於合規申報）
- **THEN** SHALL 優先使用 `bomLine->materialItem->hs_code`，忽略快照欄位

#### Scenario: BomLine 無 material_item_id 時讀取 hs_code

- **WHEN** BomLine 的 `material_item_id` 為 null
- **THEN** SHALL fallback 使用 `bomLine->hs_code` 快照欄位

#### Scenario: MaterialItem hs_code 更新後 BomLineSupplier 讀取值反映更新

- **WHEN** 物料主檔的 `hs_code` 更新，且 BomLine 已關聯該 MaterialItem
- **THEN** 後續所有讀取（含 BomLineSupplier 合規輸出）SHALL 反映最新主檔值，不使用舊快照
