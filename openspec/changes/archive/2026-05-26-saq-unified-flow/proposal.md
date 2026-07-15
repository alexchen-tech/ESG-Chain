# Proposal: saq-unified-flow

## Why

目前存在兩條並存的問卷發送路徑（`QuestionnaireController` 舊版 vs `SaqProjectController` 新版），造成：
1. SAQ `status` enum 有舊七狀態，但新版發送寫入 `'sent'`（不在 enum）→ DB 會 silent truncate 或 error
2. 問卷審核頁（`QuestionnaireView`）無法審核從新版專案發出的 SAQ
3. 供應商看到的問卷缺少專案脈絡
4. 「問卷發送」和「問卷審核」與「問卷專案」三個頁面各自獨立，業務流程斷鏈

業務決策確認（2026-05-26）：
- **審核一定要有專案**（project_id NOT NULL，DB 已強制）
- 每個專案有自己的審核人
- 整合後供應商顯示「某個專案的問卷」

## What Changes

### Backend

- **Migration**：`saqs.status` enum 加入 `sent`（initial state），移除 `not_started`，保留 `in_progress`（供應商填寫中）
- **廢棄** `POST /questionnaires/send`（返回 410 Gone，前端改走 SaqProject 路徑）
- **`QuestionnaireService::TRANSITIONS`**：`not_started` → `sent` 作為起點
- **新增** `POST /saqs/{saq}/start-review`、`complete-review`、`return-review`、`mark-reviewed` 路由（審核動作從 questionnaires 命名空間搬到 saqs 命名空間）
- **`QuestionnaireController::index()`**：加入 `project_id` 篩選，response 帶 project 關聯

### Frontend

- **Sidebar**：「問卷發送」改為直接連結 `/questionnaires/projects`（不另開頁面）
- **QuestionnaireView（問卷發送 mode）**：移除 Modal，改為 redirect 提示「請透過問卷專案發送」
- **QuestionnaireView（問卷審核 mode）**：加入 project 篩選下拉，顯示每筆 SAQ 所屬專案名稱
- **SaqProjectDetailView**：SAQ 列表加入審核動作按鈕（開始審核/通過/退回/複核）
- **StatusLabel 更新**：所有 status mapping 加入 `sent`（顯示「待填寫」），移除 `not_started`

### 清理

- `QuestionnaireView` 發送 Modal 相關 code（sendForm、matchMode、recommendations）整體移除

## Capabilities

### New Capabilities
- `saq-review-in-project`: 在 SaqProjectDetailView 執行審核動作

### Modified Capabilities
- `saq-project-ui`: 詳情頁 SAQ 列表加入審核按鈕
- `saq-project-status-machine`: status enum 統一（sent 取代 not_started）

## Impact

- **DB Migration**：`ALTER TABLE saqs MODIFY status ENUM(...)` — 0 筆資料，無風險
- **後端**：`QuestionnaireService`、`QuestionnaireController`、`routes/api.php`、`SAQController`
- **前端**：`QuestionnaireView.vue`、`SaqProjectDetailView.vue`、`AppSidebar.vue`
- **Portal**：`SupplierSurveyView.vue` status label 需更新（`not_started` → `sent`）
- **SAQ count=0**：不需要 data migration
