## 1. MySQL Migration（Laravel）

- [x] 1.1 建立 `add_sasb_industry_id_to_saq_templates` migration（nullable UUID FK → sasb_industries.id）
- [x] 1.2 建立 `create_sasb_disclosure_topics_table` migration（id/sasb_industry_id FK/topic_name/topic_code/esg_category enum(E,S,G)/description/timestamps）
- [x] 1.3 建立 `add_sasb_fields_to_saq_questions` migration（sasb_topic_id nullable FK → sasb_disclosure_topics / sasb_metric_code nullable string）
- [x] 1.4 建立 `create_saq_template_industries_table` migration（template_id FK / industry_id FK / PK 複合）
- [x] 1.5 docker cp + `php artisan migrate`

## 2. Laravel Model & Seeder

- [x] 2.1 建立 `SasbDisclosureTopic` Model（HasUuids、table=sasb_disclosure_topics、fillable、`industry()` belongsTo）
- [x] 2.2 更新 `SAQTemplate` Model（加 `sasb_industry_id` fillable、`industries()` belongsToMany via saq_template_industries、`sasbIndustry()` belongsTo）
- [x] 2.3 更新 `SAQQuestion` Model（加 `sasb_topic_id`/`sasb_metric_code` fillable、`sasbTopic()` belongsTo）
- [x] 2.4 建立 `SasbDisclosureTopicSeeder`：植入 20 個優先產業的 Topics（EM-IS/TC-ES/RC-CH/TC-SI/FB-AG 等，每個約 4 個 Topics，共 ≥ 60 筆）
- [x] 2.5 更新 `DatabaseSeeder` 呼叫 SasbDisclosureTopicSeeder
- [x] 2.6 docker cp models/seeders + `php artisan db:seed --class=SasbDisclosureTopicSeeder`

## 3. Laravel API（SASB Topics & Scoring Model Proxy）

- [x] 3.1 建立 `SasbDisclosureTopicController`（`index` 支援 industry_id + esg_category filter）
- [x] 3.2 建立 `ScoringModelProxyController`（index/store/update/destroy，Guzzle 轉發到 FastAPI `/ai/v1/scoring-models`）
- [x] 3.3 在 `routes/api.php` 新增：`GET settings/sasb-topics`、`GET/POST/PUT/DELETE settings/scoring-models`
- [x] 3.4 更新 `SAQQuestionController::store/update`：允許 sasb_topic_id + sasb_metric_code

## 4. Laravel 問卷服務更新

- [x] 4.1 在 `QuestionnaireService` 新增 `recommendTemplates(array $supplierIds): array`（依 Industry 匹配，回傳含 match_type 的範本列表）
- [x] 4.2 建立 `RecommendTemplatesController`，`POST /api/v1/questionnaires/recommend-templates`
- [x] 4.3 更新 `SAQService::triggerScoring()`：從 Supplier 取得 sasb_industry_code，附加到計分 payload
- [x] 4.4 docker cp + `php artisan route:cache`

## 5. PostgreSQL / FastAPI 升級

- [x] 5.1 Alembic migration：`scoring_models` 補 `sasb_industry_code VARCHAR(20) nullable`，加 unique index `(sasb_industry_code, is_active)` where is_active=true
- [x] 5.2 更新 `ScoringModel` SQLAlchemy model，補 `sasb_industry_code` Column
- [x] 5.3 更新 `SAQResponseItem` schema：補 `sasb_topic: Optional[str]`（Topic name，用於計算 topic_scores）
- [x] 5.4 更新 `SAQScoringResult` schema：補 `topic_scores: dict[str,float]`、`industry_code: Optional[str]`、`scoring_model_id: Optional[str]`
- [x] 5.5 更新 `SAQScoringRequest` schema：補 `sasb_industry_code: Optional[str]`
- [x] 5.6 重構 `scoring_service.py`：新增 `_get_scoring_model(industry_code)` async DB 查詢（含 fallback 邏輯）；新增 `_compute_topic_scores()` 從 responses 的 sasb_topic 分組計算
- [x] 5.7 新增 FastAPI routes `GET/POST/PUT/DELETE /ai/v1/scoring-models`（CRUD）
- [x] 5.8 docker cp FastAPI 檔案 + 執行 `alembic upgrade head`（在 esgchain-ai 容器）

## 6. 前端型別與 API 模組

- [x] 6.1 `settings.ts` 新增 `SasbDisclosureTopic` interface + `sasbTopicsApi`（list with filters）
- [x] 6.2 `settings.ts` 新增 `ScoringModel` interface + `scoringModelsApi`（list/create/update/remove）
- [x] 6.3 `questionnaire.ts` 新增 `recommendTemplatesApi`，`TopicScore` type，更新 `Questionnaire` type 補 topic_scores

## 7. TemplateDetailView.vue 更新

- [x] 7.1 題目列表加「SASB Topic」欄（顯示 topic_name 或 "—"）
- [x] 7.2 題目 Modal 加「SASB 揭露主題」下拉（依 template industry 過濾，loadTopics()）及「Metric 代碼」input
- [x] 7.3 選擇 Topic 時自動填入 Metric code 前綴

## 8. 計分模型管理頁

- [x] 8.1 建立 `src/views/settings/ScoringModelView.vue`：列表（name/industry/E%/S%/G%/狀態）、新增/編輯 Modal（權重合計驗證、Industry 下拉含「通用」）、停用確認
- [x] 8.2 路由加 `/settings/scoring-models` → ScoringModelView.vue（meta: admin only）
- [x] 8.3 系統設定頁 Tab 列表加「計分模型」入口，或 AppSidebar 加連結

## 9. 問卷發送 Modal 改版

- [x] 9.1 `QuestionnaireView.vue` 發送 Modal：加配對模式 radio（自動/手動）
- [x] 9.2 自動模式：選完供應商後呼叫 recommendTemplatesApi，顯示推薦範本卡片（含 match_type badge）
- [x] 9.3 手動模式：顯示所有 active 範本 + 相容性標籤（✓/⚠/○/✗），移除舊的 UUID input
- [x] 9.4 送出時帶入 template_id（自動模式取第一推薦，手動模式取選取值）

## 10. 驗證

- [x] 10.1 `npx vue-tsc --noEmit` 無錯誤
- [x] 10.2 新增含 SASB Topic 的題目，確認 DB 寫入 sasb_topic_id
- [x] 10.3 供應商設 SASB Industry，發送問卷後計分結果含 topic_scores
- [x] 10.4 建立 Industry-specific ScoringModel，計分使用新權重（非預設 E:40%）
- [x] 10.5 發送 Modal 自動模式：有匹配範本時推薦顯示，無匹配時顯示通用範本
