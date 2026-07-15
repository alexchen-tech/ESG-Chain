## ADDED Requirements

### Requirement: BomLine 供應商透過 bom_line_suppliers 管理

BomLine 的供應商 SHALL 透過 `bom_line_suppliers` 關聯表管理（多供應商 AVL）。`product_bom_lines` 表 SHALL 移除 `designated_supplier_id` 欄位。`product_bom_lines` 表 SHALL 新增 `bom_line_type` 欄位（`material` | `service`，預設 `material`）。

#### Scenario: 取得 BomLine 所有供應商

- **WHEN** 系統查詢某 BomLine 的供應商資訊
- **THEN** 透過 `bomLineSuppliers()` relation 取得所有關聯記錄，按 `sort_order` 排序，primary 在前

#### Scenario: BomLine 不再直接持有單一供應商 FK

- **WHEN** 查詢 `product_bom_lines` 表
- **THEN** 不存在 `designated_supplier_id` 欄位；供應商資訊透過 JOIN `bom_line_suppliers` 取得

## REMOVED Requirements

### Requirement: designated_supplier_id 單一供應商

**Reason**: 改用 `bom_line_suppliers` 多供應商 AVL，支援主要 + 替代供應商。原單一 FK 無法表達 AVL 語義。

**Migration**: 執行 migration `migrate_designated_supplier_to_bom_line_suppliers`，將現有 `designated_supplier_id` 資料插入 `bom_line_suppliers`（role: primary, source: erp_designated），然後 DROP COLUMN `designated_supplier_id`。

---

### Requirement: ProductBomLine 資料模型
系統 SHALL 提供 `product_bom_lines` 資料表，以物料為主體記錄產品的 BOM 結構。每條明細包含物料名稱、HS Code、物料群組、指定供應商、數量、單價、貨幣，以及來源追蹤欄位。

欄位定義：
- `id` UUID PK
- `buyer_product_id` FK → `buyer_products.id`
- `erp_line_id` string nullable（ERP 系統的行項目識別碼，同產品下唯一）
- `material_name` string
- `hs_code` string nullable
- `material_group_id` FK → `material_groups.id` nullable
- `material_group_source` enum `'erp_imported'|'hs_inferred'|'manual'` nullable
- `designated_supplier_id` FK → `suppliers.id` nullable
- `supplier_source` enum `'erp_designated'|'manual'` nullable
- `quantity` decimal nullable
- `unit` string nullable
- `unit_price` decimal nullable
- `currency` char(3) nullable
- `notes` text nullable
- `created_at`, `updated_at`

#### Scenario: 建立 BomLine
- **WHEN** 採購商 POST `/api/v1/buyer-products/{id}/bom-lines` 並提供必要欄位
- **THEN** 系統 SHALL 建立一筆 BomLine 並回傳 201，`material_group_source` 預設 `'manual'`

#### Scenario: 查詢產品 BomLines
- **WHEN** GET `/api/v1/buyer-products/{id}/bom-lines`
- **THEN** 系統 SHALL 回傳該產品的所有 BomLines，含 `materialGroup` 與 `designatedSupplier` 關聯

#### Scenario: 更新 BomLine
- **WHEN** PATCH `/api/v1/buyer-products/{id}/bom-lines/{lineId}` 修改 `material_group_id`
- **THEN** 系統 SHALL 更新資料並將 `material_group_source` 設為 `'manual'`

#### Scenario: 刪除 BomLine
- **WHEN** DELETE `/api/v1/buyer-products/{id}/bom-lines/{lineId}`
- **THEN** 系統 SHALL 軟刪除該 BomLine 並回傳 200

### Requirement: material_group_source 優先級規則
當系統自動推斷物料群組時，SHALL 遵循優先級：`manual` > `erp_imported` > `hs_inferred`。已被手動設定（`manual`）的物料群組，不得被 ERP 匯入或 HS Code 推斷覆蓋。

#### Scenario: 手動設定不被 ERP 覆蓋
- **WHEN** `material_group_source = 'manual'` 且發生 ERP 匯入（重複 erp_line_id）
- **THEN** `material_group_id` 與 `material_group_source` SHALL 保持不變

#### Scenario: ERP 值可被手動更新覆蓋
- **WHEN** 使用者手動 PATCH `material_group_id`
- **THEN** `material_group_source` SHALL 更新為 `'manual'`

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

## MODIFIED Requirements

### Requirement: store()/update() 自動同步 material_group_id

當 API 呼叫端傳入 `material_item_id` 時，系統 SHALL 自動從 MaterialItem 帶入 `material_group_id` 與 `material_group_source='erp_imported'`，**除非**呼叫端同時明確傳入 `material_group_id`（此時尊重呼叫端的值，`material_group_source='manual'`）。

#### Scenario: 傳入 material_item_id 且未指定 material_group_id

- **WHEN** POST 或 PATCH BomLine 帶入有效 `material_item_id`，且未傳入 `material_group_id`
- **THEN** 系統 SHALL 自動將 `material_group_id` 設為 `materialItem.material_group_id`，`material_group_source` 設為 `'erp_imported'`

#### Scenario: 傳入 material_item_id 且同時指定 material_group_id

- **WHEN** POST 或 PATCH BomLine 同時帶入 `material_item_id` 與 `material_group_id`
- **THEN** 系統 SHALL 使用呼叫端傳入的 `material_group_id`，`material_group_source` 設為 `'manual'`
