## 1. 資料清除（Dev 環境）

- [x] 1.1 Artisan Command `dev:reset-series-data`：依序清除 cap_findings、caps、supplier_disclosures、saq_responses、saqs、saq_projects、assessment_series、assessment_series_weights
- [x] 1.2 執行清除並驗證（各表筆數應為 0）

## 2. Schema Migrations

- [x] 2.1 Migration A：`assessment_series` 新增 `template_id UUID NULL` FK → `saq_templates.id`，新增 `template_version_at_creation VARCHAR(20) NULL`
- [x] 2.2 Migration B：`saq_projects` 新增 `is_comparable BOOLEAN NOT NULL DEFAULT TRUE`、`template_version VARCHAR(20) NULL`
- [x] 2.3 Migration C：`saq_questions` 新增 `framework_pillar VARCHAR(100) NULL`
- [x] 2.4 Migration D：建立 TRIGGER `trg_saq_questions_framework_check`（BEFORE INSERT，驗證 bank question 的 TAG l1_domain ≥ 1 個符合 template.scoring_framework；通用型範本跳過）
- [x] 2.5 Migration E：補齊 ISO26000 bank questions TAG assignments（15 筆，l1_domain = 'ISO26000'）
- [x] 2.6 Migration F：補齊 Geo-Risk bank questions TAG assignments（ISO28000 + GPR 共 33 筆，l1_domain = 'Geo-Risk'）
- [x] 2.7 Migration G：（合併至 2.6，ISO28000 + GPR 同批補齊）
- [x] 2.8 Migration A 完成後，`assessment_series.template_id` 加 NOT NULL constraint（單獨 Migration 或在清除後同 Migration 處理）

## 3. esgchain-api：Model 與 Service 更新

- [x] 3.1 `AssessmentSeries` Model：加入 `template()` belongsTo 關聯；fillable 加 `template_id`、`template_version_at_creation`
- [x] 3.2 `SaqProject` Model：fillable 加 `is_comparable`、`template_version`
- [x] 3.3 `SaqQuestion` Model：fillable 加 `framework_pillar`
- [x] 3.4 `AssessmentSeriesService::create()`：必填驗證 `template_id`，自動填入 `template_version_at_creation`
- [x] 3.5 `AssessmentSeriesService::show()`：回傳含 `has_mixed_versions`、`comparable_versions_count`
- [x] 3.6 `SaqProjectService::create()`：從 series 繼承 `template_id`，記錄 `template_version`，計算並寫入 `is_comparable`（實作於 SaqProjectController::store()）
- [x] 3.7 `SaqQuestionService::addToTemplate()`：加入框架 TAG 驗證（應用層）；自動計算並填入 `framework_pillar`（實作於 ImportFromBankController）

## 4. esgchain-api：Controller 與路由

- [x] 4.1 `AssessmentSeriesController::store()`：validate `template_id` 必填
- [x] 4.2 `AssessmentSeriesController::update()`：禁止修改 `template_id`（若與既有值不同回傳 422）
- [x] 4.3 `QuestionnaireTemplateController::update()`：若 `scoring_framework` 有值且與既有值不同，回傳 422
- [x] 4.4 `QuestionnaireTemplateController::show()`：回傳 `series_count` 與 `series` 陣列
- [x] 4.5 `QuestionnaireTemplateController::destroy()`：有關聯 series 時回傳 422
- [x] 4.6 `SaqProjectController::store()`：移除 `domain` 參數接受，series_id 改為必填，template_id 從 series 繼承

## 5. Seed 重建

- [x] 5.1 `SaqProjectSeeder` 重寫：同時建立 4 個系列（ESG/ISO20400/ISO26000/Geo-Risk）並綁定正確框架範本
- [x] 5.2 各系列建立 Project，繼承 template_id，`is_comparable = true`
- [x] 5.3 驗證：assessment_series 4 筆、saq_projects 7 筆，各 project 的 template_id 正確、framework_pillar 有值

## 6. esgchain-web：UI 更新

- [x] 6.1 「新增評核系列」Modal：domain 選單改為範本選擇器（`/api/v1/questionnaire-templates` 下拉，顯示 name + scoring_framework badge）
- [x] 6.2 評核系列列表：顯示範本名稱與框架 badge；移除 domain 欄
- [x] 6.3 建立 SaqProject Modal：移除「選擇範本」步驟（已由 series 決定）；顯示繼承的範本與框架（唯讀）
- [x] 6.4 SaqProject 列表：`is_comparable = false` 的 Project 顯示「⚠ 升版」badge
- [x] 6.5 範本編輯 Modal：`scoring_framework` 改為唯讀 badge + 提示文字

## 7. 驗收

- [x] 7.1 TRIGGER 驗收：透過 Tinker 對 scoring_framework = 'ESG' 的範本插入無 ESG TAG 的題目，應拋 SQLSTATE 45000
- [x] 7.2 API 驗收：`POST /api/v1/assessment-series` 不帶 template_id 應回 422
- [x] 7.3 API 驗收：`PUT /api/v1/settings/questionnaire-templates/{id}` 帶不同 scoring_framework 應回 422
- [x] 7.4 API 驗收：`GET /api/v1/assessment-series/{id}` 回傳 has_mixed_versions
- [x] 7.5 Seed 驗收：執行 `php artisan db:seed` 後，各系列 template_id 正確，projects 繼承正確 template_id
