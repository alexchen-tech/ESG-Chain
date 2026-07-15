## MODIFIED Requirements

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
