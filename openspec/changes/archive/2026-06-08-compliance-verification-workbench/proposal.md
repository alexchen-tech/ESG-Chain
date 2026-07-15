## Why

目前合規文件的「審核」動作散落在各供應商的 drill-down 頁面（`/compliance/suppliers/:id`），沒有集中的待審清單。供應商透過 Portal 上傳文件後，sustain / comply 角色無從得知哪些文件等待確認，必須逐一進入每個供應商頁面才能找到待審項目，作業效率低落。

審核的業務語意為：確認供應商已完成文件上傳，且有效期已正確填寫。合規計算（矩陣 / 產品合規）不受 `verified_at` 影響，維持以 `status`（時效）為判斷依據。

## What Changes

- 後端新增 `GET /api/v1/compliance/pending-verifications` 端點，回傳所有 `verified_at = null` 的文件，含供應商名稱、文件類型、上傳日期、到期日、缺漏有效期警告
- 前端合規看板（`MaterialComplianceView`）新增「待審核」Tab，顯示待審工作台
- 工作台支援按供應商、文件類型、缺漏有效期篩選
- 每列提供「審核」inline 按鈕，呼叫既有 `POST /compliance-docs/:id/verify` 端點，審核後從清單移除
- 工作台頂部顯示待審總數 KPI badge

## Capabilities

### New Capabilities

- `compliance-verification-workbench`：集中式待審合規文件工作台，含清單、篩選、inline 審核

### Modified Capabilities

無

## Impact

- **後端**：新增 `ComplianceDashboardController::pendingVerifications()` action；`SupplierComplianceStatusService::getPendingVerifications()` 方法；路由新增一條 GET
- **前端**：`MaterialComplianceView.vue` 新增 Tab 與對應 data/method；`complianceDashboardApi` 新增 `pendingVerifications()` 呼叫
- **RBAC**：僅 `sustain` / `comply` / `admin` 可存取（與合規看板現有路由一致）
- **無資料庫異動**：`verified_at` 欄位已存在
