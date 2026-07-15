# Proposal: project-question-snapshot

## Why

目前問卷範本與 SAQ 存在運行時耦合：供應商填寫問卷時讀取的是範本「當下」的題目內容，管理員修改範本題目會直接影響已發送的 SAQ，造成：

1. **稽核失真**：審核人員看到的題目與供應商填寫當下的題目不同
2. **法遵風險**：ESG 問卷須能重現「當時問了什麼」，現行架構無法保證
3. **範本管理危險**：任何範本編輯都是破壞性操作，無法安全迭代
4. **Weight 無脈絡**：題目 weight 存在題庫，但不同專案對同一題的重要性可能不同

業務決策確認（2026-05-27）：
- 專案建立時，範本題目**完整快照**進 `project_questions`，之後與範本無耦合
- Weight 從題庫移至**專案層設定**，跨期比較使用 `raw_score`（不受 weight 差異影響）
- 範本採 **draft/publish** 版本模式：任何編輯進入 draft，使用者確認後發佈為新版
- 舊版範本自動封存，可檢視不可編輯

## What Changes

### DB（3 個新 table，2 個修改）

**新增：**
- `project_questions`：專案獨立題目快照（不依賴範本外鍵）
- `assessment_series`：評核系列實體，供 Change 2 使用（此 change 僅建 table，不含 UI）
- `assessment_series_weights`：系列預設 weight schema（Change 2 填充）

**修改：**
- `saq_templates`：加 `status ENUM('draft','published','archived')`、`draft_of UUID`（自參照）、`version` 改整數
- `saq_projects`：加 `series_id FK`（nullable）、`template_ref_id`、`template_ref_version`
- `saq_responses`：加 `project_question_id FK`、`raw_score`；`question_id` 保留但 deprecated

### Backend

- **`project_questions` Model + Repository**
- **`SaqProjectController::store()`**：建立專案時觸發快照流程（讀範本題目 → INSERT project_questions，帶入 source_question_id）
- **`SaqProjectController::show()`**：SAQ 列表讀取改用 `project_questions`
- **`QuestionnaireTemplateController`**：新增 draft/publish 狀態機
  - `PUT /settings/questionnaire-templates/{template}`（任何編輯）→ 若無 draft 則複製建立 draft，回傳 draft record
  - `POST /settings/questionnaire-templates/{template}/publish`→ draft 升版為 published，前版封存
  - `GET /settings/questionnaire-templates/{template}`→ 回傳 published 版，若有 draft 附帶 `has_draft: true`
- **`SAQController::scoreCallback()`**：接收 `question_scores[]`，寫入 `saq_responses.raw_score`
- **`QuestionnaireController::show()`**：SAQ 詳情讀取 `project_questions`（含 responses）

### Frontend

- **`TemplateDetailView.vue`**：加入 draft banner（「草稿中，尚未發佈」）+ [確認發佈] 按鈕；發佈確認 Modal（說明舊版將封存）
- **`SaqProjectsView.vue`**（建立 Modal）：建立專案時額外顯示「快照確認」——顯示將快照幾道題，使用者確認
- **`SaqProjectDetailView.vue`**：SAQ 題目讀取來源改為 `project_questions`；加入 weight 欄位顯示（此 change 唯讀，Change 2 才加編輯）
- **Portal `SupplierSurveyView.vue`**：題目讀取路徑從 `template.questions` 改為 `project.questions`

### esgchain-ai

- `POST /ai/v1/saq-score` response 加 `question_scores: [{ project_question_id, source_question_id, raw_score }]`（向後相容，加欄位不改現有）

## Capabilities

### New Capabilities
- `project-question-snapshot`：專案建立時快照範本題目，SAQ 讀取 project_questions
- `template-draft-publish`：範本 draft/publish 版本管理，舊版封存可檢視

### Modified Capabilities
- `saq-project-ui`：建立專案加快照確認步驟；詳情頁題目來源切換
- `questionnaire-template-management`：TemplateDetailView 加 draft banner 與發佈按鈕
- `saq-review-in-project`：SAQ 詳情讀取 project_questions（不影響審核流程）

## Impact

- **DB Migration**：4 個 migration（新 3 table + 修改 saq_templates/saq_projects/saq_responses）
- **資料遷移**：現有 SAQ count = 0，`saq_responses` 無歷史資料，無風險
- **範本資料**：現有範本自動設 `status = 'published'`，`version` 數值保持原值（已有 archived_at 的設為 'archived'）
- **Portal 題目讀取路徑變更**：需同步修改，避免 supplier 問卷顯示空白
- **esgchain-ai**：response schema 加欄位，向後相容

## Out of Scope（Change 2）

- `assessment_series` 管理 UI（建立、編輯系列）
- Series weight schema 設定與繼承流程
- 專案 weight 手動編輯介面
- 跨期比較 Dashboard
