## MODIFIED Requirements

### Requirement: ERP BOM 匯入（CSV/Excel）
系統 SHALL 提供 `POST /api/v1/buyer-products/{id}/bom-lines/import` endpoint 支援 multipart/form-data 上傳 CSV 或 Excel 檔案。欄位對應包含新增的供應商配對欄位：`primary_supplier_code`（主要供應商 ERP 代碼）與 `alternate_supplier_code`（替代供應商，可空）。

CSV 必填欄位：`erp_line_id`、`material_name`
CSV 選填欄位：`hs_code`、`material_code`、`primary_supplier_code`、`alternate_supplier_code`、`quantity`、`unit`

匯入後系統 SHALL 依 `primary_supplier_code` 查找 `suppliers.code`，找到則建立 `bom_line_suppliers`（`role = 'primary'`，`source = 'erp_designated'`）；`alternate_supplier_code` 同理建立 `role = 'alternate'` 關聯。

#### Scenario: CSV 成功上傳並配對供應商
- **WHEN** 上傳 CSV，`primary_supplier_code` 能解析到現有 Supplier
- **THEN** 系統 SHALL 解析並執行冪等 upsert，建立 BomLine 並自動建立 BomLineSupplier，回傳匯入摘要含 `linked_suppliers: N`

#### Scenario: 格式錯誤
- **WHEN** CSV 缺少必填欄位 `erp_line_id` 或 `material_name`
- **THEN** 系統 SHALL 回傳 422 並說明缺少的欄位名稱

#### Scenario: supplier_code 無法解析
- **WHEN** `primary_supplier_code` 在 suppliers 表中不存在
- **THEN** 系統 SHALL 繼續建立 BomLine，`bom_line_suppliers` 關聯跳過，並在 response `warnings` 陣列中列出無法解析的 supplier_code

#### Scenario: 重複匯入冪等性
- **WHEN** 相同 erp_line_id 再次匯入
- **THEN** 系統 SHALL 更新 ERP 控制欄位，不覆蓋 ESG 標註（`notes`，`material_group_source = 'manual'` 的欄位），BomLineSupplier 若已存在則跳過，若新增則建立
