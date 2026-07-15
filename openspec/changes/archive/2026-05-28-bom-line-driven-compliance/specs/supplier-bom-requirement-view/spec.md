## MODIFIED Requirements

### Requirement: 供應商 BOM 需求視圖從 BomLineSupplier 查詢
`getSupplierBomRequirements()` SHALL 改從 `bom_line_suppliers` JOIN `product_bom_lines` 查詢，取得該供應商參與的所有 BomLine 及其物料群組合規需求。

#### Scenario: 供應商看到自己在各 BomLine 的角色
- **WHEN** 供應商查詢自己的採購需求
- **THEN** response 包含每條 BomLine 的：產品名稱、物料名稱、`bom_line_type`、物料群組、所需文件類型、自己的角色（primary/alternate）

#### Scenario: 供應商不在任何 BomLine 時回傳空列表
- **WHEN** 供應商未出現在任何 `bom_line_suppliers` 記錄
- **THEN** 回傳空陣列，不報錯
