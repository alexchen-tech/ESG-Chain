## ADDED Requirements

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
