## Why

SAQ 問卷發送目前有兩個並存的系統（`QuestionnaireController` 舊版多供應商、`SAQController` 新版單供應商），且 `SaqProject` 雖有完整後端 API 卻完全沒有前端 UI。使用者無法透過 UI 建立問卷專案或批次發送問卷，業務流程斷鏈。

## What Changes

- **New**: `/questionnaires/projects` 列表頁，含狀態 Tab（草稿/進行中/已結案）與建立 Modal
- **New**: `/questionnaires/projects/:id` 詳情頁，顯示 SAQ 回覆進度、發送供應商清單
- **New**: 「發送給供應商」Modal：支援群組批次選擇 + 個別搜尋，重複發送警告
- **New**: `SaqProjectController::send()` 改為多供應商版，支援 `supplier_ids[]` 批次建立 SAQ
- **New**: `SaqProject.status` 狀態機：`draft` → `active`（首次發送）→ `closed`（手動結案）
- **Deprecate**: `POST /questionnaires/send` 舊版路由（保留相容，標記 deprecated）

## Capabilities

### New Capabilities

- `saq-project-ui`: SaqProject 列表與詳情頁面，含建立 Project 和發送 Modal
- `saq-project-multi-send`: 多供應商批次發送 SAQ，支援群組選擇與個別搜尋
- `saq-project-status-machine`: SaqProject 狀態流轉（draft/active/closed）及防護邏輯

### Modified Capabilities

（無現有 spec 需修改）

## Impact

- **後端**: `SaqProjectController`（send 改多供應商）、`SaqProject` Model（status 狀態機）、routes/api.php
- **前端**: 新增 `SaqProjectsView.vue`、`SaqProjectDetailView.vue`、`router/index.ts`、`api/modules/saq.ts`
- **路由**: `/questionnaires` 現有路由需調整 Tab 結構以容納 Projects 入口
- **依賴**: 無新依賴
