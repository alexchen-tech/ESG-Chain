# Tasks: project-question-snapshot

## 1. DB Migrations

- [x] 1.1 建立 `project_questions` table migration
- [x] 1.2 建立 `assessment_series` table migration（僅建表，Change 2 使用）
- [x] 1.3 建立 `assessment_series_weights` table migration（僅建表，Change 2 使用）
- [x] 1.4 修改 `saq_templates`：加 `status ENUM`、`draft_of`、`version` 改整數；現有資料 status 依 archived_at 設值
- [x] 1.5 修改 `saq_projects`：加 `series_id`、`template_ref_id`、`template_ref_version`
- [x] 1.6 修改 `saq_responses`：加 `project_question_id`、`raw_score`
- [x] 1.7 docker cp 所有 migration + `php artisan migrate`

## 2. Backend Models

- [x] 2.1 建立 `ProjectQuestion` Model（HasUuids，fillable，casts，scopeByProject）
- [x] 2.2 建立 `AssessmentSeries` Model（HasUuids，fillable）
- [x] 2.3 更新 `SAQTemplate` Model：fillable 加 `status`、`draft_of`；scope `published()`、`draft()`；relation `draft()`（HasOne，draft_of=id）
- [x] 2.4 更新 `SaqProject` Model：fillable 加 `series_id`、`template_ref_id`、`template_ref_version`；relation `projectQuestions()`（HasMany ProjectQuestion）
- [x] 2.5 更新 `SAQResponse` Model：fillable 加 `project_question_id`、`raw_score`
- [x] 2.6 更新 `SAQ` Model：加 relation `projectQuestionsViaProject()`（through project）

## 3. 快照服務

- [x] 3.1 建立 `ProjectQuestionService::snapshot(SaqProject, SAQTemplate): void`
      讀取範本所有 questions（含 tagAssignments），批次 INSERT project_questions

## 4. SaqProject Controller & Routes

- [x] 4.1 `SaqProjectController::store()`：建立 project 後呼叫 `ProjectQuestionService::snapshot()`；記錄 `template_ref_id`、`template_ref_version`
- [x] 4.2 `SaqProjectController::show()` / `indexByProject()`：load `project.projectQuestions` 取代 `template.questions`
- [x] 4.3 routes/api.php 確認路由（無需新增）

## 5. 範本 Draft/Publish 狀態機

- [x] 5.1 `QuestionnaireTemplateController::update()`（基本資訊）：無 draft 時先複製建立 draft，在 draft 上操作
- [x] 5.2 `SAQQuestionController`（題目 CRUD）：操作前呼叫 `ensureDraft(template)` helper；所有 write 操作轉向 draft 的題目
- [x] 5.3 新增 `POST /settings/questionnaire-templates/{template}/publish`：draft 升版、舊版封存
- [x] 5.4 `QuestionnaireTemplateController::index()`：`status=draft` 的範本不列出（已存在的 `is_archived` filter 加上排除 draft）
- [x] 5.5 `QuestionnaireTemplateController::show()`：response 加 `has_draft: bool`、`draft_id: uuid|null`
- [x] 5.6 routes/api.php 新增 publish 路由
- [x] 5.7 docker cp + route:clear

## 6. SAQ 讀取路徑切換

- [x] 6.1 `QuestionnaireController::show()`：load 改為 `project.projectQuestions`
- [x] 6.2 `SAQController::show()`：load 改為 `project.projectQuestions`
- [x] 6.3 `QuestionnaireService::update()`（供應商填寫）：response upsert key 改為 `project_question_id`
- [x] 6.4 `SAQController::scoreCallback()`：接收 `question_scores[]`，批次更新 `saq_responses.raw_score`

## 7. Frontend

- [x] 7.1 `SaqProjectsView.vue`：建立 Modal 加「快照確認」提示（「將快照 N 道題目」）
- [x] 7.2 `TemplateDetailView.vue`：加 draft banner（「草稿中，尚未發佈」）+ [確認發佈] 按鈕
- [x] 7.3 `TemplateDetailView.vue`：發佈確認 Modal（「發佈後版本將升至 vN，目前版本封存。」）
- [x] 7.4 `TemplateDetailView.vue`：mounted 時 `GET template` 若 `has_draft` 則自動切換到 draft 編輯模式
- [x] 7.5 Portal `SupplierSurveyView.vue`：題目讀取從 `questionnaire.template?.questions` 改為 `questionnaire.project?.project_questions`
- [x] 7.6 `settings.ts` API 新增 `templates.publish(id)`
- [x] 7.7 `QuestionnaireTemplatesView.vue`：建立範本選單過濾 draft 狀態範本

## 8. esgchain-ai（選填，此 change 可 stub）

- [x] 8.1 `scoreCallback` 接收 `question_scores[]` schema 更新（Laravel 驗證規則）
- [x] 8.2 FastAPI `ScoringResponse` Pydantic schema 加 `question_scores` 欄位（optional，向後相容）

## 9. 驗證

- [ ] 9.1 建立專案後確認 `project_questions` 筆數正確
- [ ] 9.2 修改範本題目不影響已建立專案的 project_questions
- [ ] 9.3 TemplateDetailView 顯示 draft banner，確認發佈後版本遞增
- [ ] 9.4 Portal 供應商問卷顯示正確題目
- [ ] 9.5 `php artisan route:list` 確認 publish 路由存在
