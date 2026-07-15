## ADDED Requirements

### Requirement: BomLine 優先的合規計算路徑
`SupplierComplianceStatusService` SHALL 在計算產品合規狀態時，優先使用 `ProductBomLine` 路徑：若產品含有指向某供應商的 BomLines，以各 BomLine 的 `materialGroup.required_doc_types` 計算該供應商的合規需求；若無 BomLines，退回現有 `BuyerProductSupplier` 路徑。

API response 中 SHALL 包含 `compliance_basis` 欄位指示使用的計算路徑：
- `'bom_line'`：使用 BomLine 路徑
- `'product_supplier'`：使用 BuyerProductSupplier 路徑
- `'unconfigured'`：兩者均無資料

#### Scenario: 有 BomLine 時使用 BomLine 路徑
- **WHEN** 產品有指向供應商 A 的 ProductBomLines（各綁定不同 materialGroup）
- **THEN** 合規需求 SHALL 為所有 BomLines 的 materialGroup.required_doc_types 聯集，`compliance_basis = 'bom_line'`

#### Scenario: 無 BomLine 退回 product_supplier 路徑
- **WHEN** 產品無任何 ProductBomLine，但有 BuyerProductSupplier 記錄
- **THEN** 系統 SHALL 使用 BuyerProductSupplier 路徑計算，`compliance_basis = 'product_supplier'`

#### Scenario: 兩者均無
- **WHEN** 產品既無 BomLines 也無 BuyerProductSupplier
- **THEN** overall_status SHALL 為 `'unconfigured'`，`compliance_basis = 'unconfigured'`

### Requirement: 供應商合規摘要支援 BomLine 路徑
`getSupplierSummary()` 計算缺漏文件時，SHALL 同時考慮：
1. 現有 TradeGoods→MaterialGroup 路徑
2. ProductBomLines 中 `designated_supplier_id = supplier.id` 的物料群組需求

兩條路徑的 required_doc_types 取聯集後與已提交文件比對。

#### Scenario: BomLine 帶來額外合規需求
- **WHEN** 供應商被某 BomLine 指定為 designated_supplier，且該 BomLine 的 materialGroup 有 required_doc_types
- **THEN** missing_required_types SHALL 包含這些需求（若尚未提交）
