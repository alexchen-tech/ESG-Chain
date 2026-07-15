## MODIFIED Requirements

### Requirement: 供應商入口採購需求 API 從 BomLineSupplier 查詢
`GET /api/v1/portal/procurement-requirements` SHALL 改從 `bom_line_suppliers` 查詢，回傳該供應商在所有產品 BomLine 中的採購義務（含角色、物料群組、所需合規文件類型）。

#### Scenario: 登入供應商取得採購需求
- **WHEN** 供應商使用 JWT 呼叫 `GET /api/v1/portal/procurement-requirements`
- **THEN** 回傳該供應商參與的所有 BomLine 採購需求，每條包含：`product_name`、`material_name`、`bom_line_type`、`required_doc_types`、`role`（primary/alternate）、現有文件狀態

#### Scenario: 供應商不在任何 BomLine
- **WHEN** 供應商呼叫採購需求 API 但未在任何 BomLine 中
- **THEN** 回傳 `{ success: true, data: [] }`
