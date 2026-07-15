## MODIFIED Requirements

### Requirement: 供應商 BOM 需求視圖從 BomLineSupplier 查詢

`getSupplierBomRequirements()` SHALL 改從 `bom_line_suppliers` JOIN `product_bom_lines` 查詢，取得該供應商參與的所有 BomLine 及其物料群組合規需求。回傳結構新增 `pcf_status` 欄位（`none` / `pending` / `submitted` / `verified`），反映最新一筆 PCF 請求的申報狀態。

#### Scenario: 供應商看到自己在各 BomLine 的角色

- **WHEN** 供應商查詢自己的採購需求
- **THEN** response 包含每條 BomLine 的：產品名稱、物料名稱、`bom_line_type`、物料群組、所需文件類型、自己的角色（primary/alternate）、`pcf_status`

#### Scenario: 供應商不在任何 BomLine 時回傳空列表

- **WHEN** 供應商未出現在任何 `bom_line_suppliers` 記錄
- **THEN** 回傳空陣列，不報錯

#### Scenario: PCF 申報狀態顯示

- **WHEN** 某 BomLine 存在對應的 `pcf_request_line` 記錄
- **THEN** `pcf_status` SHALL 回傳最新請求的狀態（`pending` / `submitted` / `verified`）；若無任何請求則回傳 `none`
