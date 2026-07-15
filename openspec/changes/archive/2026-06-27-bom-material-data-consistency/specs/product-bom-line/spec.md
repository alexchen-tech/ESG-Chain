## ADDED Requirements

### Requirement: BomLine 顯示優先採用 effective 欄位

查詢 BomLine 清單時，系統 SHALL 為每條明細計算 `effective_material_name`、`effective_hs_code`、`effective_material_group`：若該明細已關聯 `material_item_id`，優先採用對應 `MaterialItem` 的 `name`/`hs_code`/`materialGroup`；若無關聯，fallback 採用 BomLine 自身的快照欄位 `material_name`/`hs_code`/`materialGroup`。前端 BOM 明細清單 SHALL 顯示 effective 欄位而非快照欄位。

#### Scenario: 已關聯物料主檔的明細顯示即時值

- **WHEN** BomLine 的 `material_item_id` 指向某 MaterialItem，且該 MaterialItem 的 `name` 與 BomLine 快照的 `material_name` 不同
- **THEN** 系統 SHALL 顯示 MaterialItem 的 `name`（effective 值），而非 BomLine 快照值

#### Scenario: 未關聯物料主檔的明細顯示快照值

- **WHEN** BomLine 的 `material_item_id` 為 null
- **THEN** 系統 SHALL 顯示 BomLine 快照的 `material_name`/`hs_code`

### Requirement: 手動建立 BomLine 時自動回填快照欄位

手動建立 BOM 明細時，若請求包含 `material_item_id`，系統 SHALL 查詢對應 `MaterialItem`，並以其 `name`/`hs_code` 覆蓋請求中的 `material_name`/`hs_code` 後再寫入，確保快照欄位與關聯物料主檔一致。

#### Scenario: 建立時提供 material_item_id

- **WHEN** 使用者 POST 建立 BomLine，請求包含 `material_item_id` 且該物料主檔的 `name` 為「鋼板 A」
- **THEN** 系統 SHALL 將新建 BomLine 的 `material_name` 設為「鋼板 A」，即使請求中提供了不同的 `material_name`

#### Scenario: 建立時未提供 material_item_id

- **WHEN** 使用者 POST 建立 BomLine，請求未包含 `material_item_id`（如服務類明細）
- **THEN** 系統 SHALL 直接採用請求提供的 `material_name`/`hs_code`，不進行回填

### Requirement: 未綁定物料主檔的視覺提示

BOM 明細清單中，若某筆 `bom_line_type` 為 `material` 且 `material_item_id` 為 null，前端 SHALL 顯示警示標籤，提示此明細尚未綁定物料主檔、可能影響碳排填報功能。

#### Scenario: 物料類型且未綁定物料主檔

- **WHEN** BomLine 的 `bom_line_type` 為 `material`，`material_item_id` 為 null
- **THEN** 該明細列顯示「未綁定物料主檔」警示標籤

#### Scenario: 服務類型不顯示警示

- **WHEN** BomLine 的 `bom_line_type` 為 `service`
- **THEN** 不顯示「未綁定物料主檔」警示標籤（服務類本來就不需要物料主檔）
