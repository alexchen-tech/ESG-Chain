## MODIFIED Requirements

### Requirement: 合規計算從 BomLine 而非 ProductSupplier 出發
`SupplierComplianceStatusService` SHALL 重寫為：以 BomLine 為迭代單位，對每條 BomLine 的所有 BomLineSupplier 評估文件狀態。系統 SHALL 不再使用 `getProductCompliance()` 中的 ProductSupplier 路徑。

#### Scenario: 供應商只在 BomLine 而不在 ProductSupplier
- **WHEN** 供應商 X 出現在 `bom_line_suppliers` 但不在 `buyer_product_suppliers`
- **THEN** 合規引擎 SHALL 正確評估供應商 X 的文件狀態（不再因 ProductSupplier 缺席而被忽略）

#### Scenario: ProductSupplier 路徑不再執行
- **WHEN** 合規狀態計算被觸發
- **THEN** `buyer_product_suppliers` 表不被查詢於合規計算流程（僅用於 AVL 管理功能）

### Requirement: 合規結果按 BomLine 維度回傳
API response SHALL 提供按 BomLine 分組的合規結果，每個 BomLine 包含：`bom_line_id`、`material_name`、`bom_line_type`、`material_group`、`required_doc_types`、`suppliers`（陣列，每個含 `supplier_id`、`role`、`doc_status`、`docs`）。

#### Scenario: 回傳結構包含 BomLine 維度
- **WHEN** 請求產品合規詳情
- **THEN** response 包含 `bom_lines` 陣列，每個 BomLine 項目包含其所有供應商的文件狀態

#### Scenario: 合規狀態向上聚合
- **WHEN** 請求產品整體合規狀態
- **THEN** response 包含聚合後的 `overall_status`，為所有 BomLine 所有 primary 供應商的最差狀態
