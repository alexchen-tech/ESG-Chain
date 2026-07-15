## ADDED Requirements

### Requirement: AVL CSV 匯入建立供應商與合格廠商配對
系統 SHALL 提供 `POST /api/v1/suppliers/import-avl` endpoint，接受 multipart/form-data 上傳 CSV 檔案，解析後執行冪等 upsert：依 `supplier_code` 找到既有 Supplier 或建立新 Supplier，並建立或更新對應的 `BomLineSupplier` 關聯（`source = 'erp_designated'`）。

CSV 必填欄位：`supplier_code`、`supplier_name`
CSV 選填欄位：`country_code`（ISO 2碼）、`tier`（1/2/3）、`material_group_code`、`approved_items`（逗號分隔物料代碼）

#### Scenario: 全新供應商匯入
- **WHEN** CSV 包含 supplier_code 在系統中不存在
- **THEN** 系統 SHALL 建立新 Supplier，`onboarding_stage = 'potential'`，並記錄稽核日誌

#### Scenario: 既有供應商更新
- **WHEN** CSV 包含 supplier_code 在系統中已存在
- **THEN** 系統 SHALL 更新 `name`、`country`、`tier` 等欄位，不重置 `onboarding_stage` 與現有認證資料

#### Scenario: approved_items 建立 BomLineSupplier
- **WHEN** CSV 的 `approved_items` 包含可解析的物料代碼
- **THEN** 系統 SHALL 查找對應的 `material_items.item_code`，找到後建立 `bom_line_suppliers` 關聯（`source = 'erp_designated'`，`role = 'primary'`），已存在則跳過

#### Scenario: 物料代碼無法解析
- **WHEN** `approved_items` 中的某代碼在 `material_items` 表中不存在
- **THEN** 系統 SHALL 繼續處理其他記錄，並在 response 的 `warnings` 陣列中列出無法解析的代碼

#### Scenario: 匯入結果摘要
- **WHEN** 匯入完成
- **THEN** 系統 SHALL 回傳 `{ created_suppliers: N, updated_suppliers: N, created_bom_links: N, warnings: [] }`

### Requirement: AVL 匯入權限控制
`POST /api/v1/suppliers/import-avl` SHALL 僅允許 `admin`、`buyer`、`sustain` 角色存取。

#### Scenario: 無權限角色存取
- **WHEN** `supplier` 或 `analyst` 角色嘗試呼叫此 endpoint
- **THEN** 系統 SHALL 回傳 403 Forbidden
