## Why

目前合規文件的「審核」動作是一鍵即過（single-click verify），審核員無法在操作過程中確認文件內容、補填缺漏的有效期，也無法留下審核意見。這導致「審核」只是形式蓋章，無法履行實質文件稽查責任。

供應商上傳文件後，常見三類問題：
1. 上傳錯誤檔案（文件類型不符）
2. 未填有效期（`expires_at = null`，即 `missing_expiry: true`）
3. 文件已過期卻未補件

審核員需要先看文件、確認有效期、留下意見，才算完成一次有意義的審核作業。

## What Changes

- 後端新增 `GET /compliance-docs/:id/download` 端點，串流回傳文件
- 後端擴充 `POST /compliance-docs/:id/verify`，接收 `expires_at`（`missing_expiry` 時必填）與 `notes`（選填）
- 前端將所有「審核」按鈕改為開啟驗證 Modal，Modal 顯示文件摘要、下載連結、有效期輸入、備註輸入
- 影響範圍：`MaterialComplianceView`（待審核 Tab）、`SupplierComplianceDetailView`（供應商文件明細頁）

## Capabilities

### New Capabilities

- `compliance-doc-download`：合規文件下載端點
- `compliance-verify-modal`：驗證 Modal，含文件下載、expires_at 補填、備註、確認防誤觸

### Modified Capabilities

- `compliance-verification-workbench`：待審核工作台「審核」按鈕改為開啟 Modal

## Impact

- **後端**：`SupplierComplianceDocController` 新增 `download()` action、擴充 `verify()` action；路由新增一條 GET
- **前端**：`MaterialComplianceView.vue` 新增 Modal data/method/template；`SupplierComplianceDetailView.vue` 同步套用；`compliance.ts` 擴充 verify 與新增 download API
- **無資料庫異動**：`notes`、`expires_at`、`verified_at`、`verified_by` 欄位均已存在
