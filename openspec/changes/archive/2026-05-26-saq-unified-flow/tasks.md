# Tasks: saq-unified-flow

## Backend

- [x] Migration: ALTER saqs.status ENUM 加入 `sent`，移除 `not_started`
- [x] QuestionnaireController::send() 返回 410 Gone
- [x] QuestionnaireService::TRANSITIONS 加入 `sent` 作為起點（`start_fill` → `in_progress`，`submit` → `submitted`）
- [x] QuestionnaireService::update() `not_started` check 改為 `sent`
- [x] QuestionnaireService::list() 加入 `project_id` filter，with() 帶 project relation
- [x] 新增 SAQController（或複用）處理 `/saqs/{saq}/start-review`、`complete-review`、`return-review`、`mark-reviewed`
- [x] routes/api.php 新增 4 條 /saqs/{saq}/ 審核路由

## Frontend

- [x] 新增 SAQ status label 常數：`src/utils/saqStatus.ts`（sent→待填寫，移除 not_started）
- [x] QuestionnaireView：移除發送 Modal 相關 code（sendForm、matchMode、recommendations、send()、loadRecommendations()、filteredSendSuppliers 等）
- [x] QuestionnaireView：審核 mode 加入 project_id 篩選下拉（API: GET /saq-projects）
- [x] QuestionnaireView：SAQ 列表加入「所屬專案」欄位
- [x] AppSidebar：移除「問卷發送」子項目（questionnaires-send）
- [x] SaqProjectDetailView：SAQ 列表加入審核動作按鈕（開始審核/通過/退回/複核確認）
- [x] SaqProjectDetailView：退回動作 Modal（comment 必填）
- [x] SaqProjectDetailView：呼叫新 /saqs/{saq}/ 路由（新增 api module 方法）
- [x] SupplierSurveyView（Portal）：status label 更新（sent→待填寫，移除 not_started）
