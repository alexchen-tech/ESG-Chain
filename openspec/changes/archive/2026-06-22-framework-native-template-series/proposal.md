## Why

`assessment_series` 目前以 `domain` 文字欄位（而非 `template_id`）描述框架，導致系列下各 Project 可自由選擇不同範本，破壞跨期可比性；同時範本題目缺乏框架 TAG 約束，造成 ESG 範本混入 ISO20400 題目、評分時 pillar 歸屬不明。

## What Changes

- **BREAKING** `assessment_series` 新增 `template_id`（FK → `saq_templates`），系列層級綁定唯一範本版本；`domain` 欄位廢棄。
- `assessment_series` 新增 `template_version_at_creation`（記錄建立時的版本快照），升版時新系列才能使用新範本。
- `saq_projects` 新增 `is_comparable BOOLEAN DEFAULT TRUE`，同一系列升版後標記 `false`；新增 `template_version VARCHAR(20)` 快照。
- `saq_questions`（範本題）新增 `framework_pillar VARCHAR(100) NULL`，建立時從 TAG l2_pillar 快照。
- MySQL INSERT TRIGGER `trg_saq_questions_framework_check`：驗證新增範本題至少有一個 TAG 的 `l1_domain` 符合範本的 `scoring_framework`，違反時 SIGNAL SQLSTATE。
- 清除 dev 環境所有系列、專案、評核、回覆、Disclosure 及 CAP 資料，重新以正確框架建立 Seed。

## Capabilities

### New Capabilities
- `template-framework-constraint`：範本題目框架 TAG 約束（DB TRIGGER + Model 驗證），確保範本題目的 TAG l1_domain 與範本 scoring_framework 一致。

### Modified Capabilities
- `assessment-series-management`：系列改為綁定 template_id，升版策略（is_comparable 旗標），廢棄 domain 欄位。
- `saq-project-domain`：Project 繼承系列 template_id，移除自選範本能力，新增 template_version 快照欄。
- `question-tag-library`：範本題新增 framework_pillar 欄（建立時快照），補齊 ISO26000 / Geo-Risk TAG 覆蓋缺口（45+ 題）。
- `questionnaire-template-management`：範本建立後 scoring_framework 不可修改，UI 顯示框架 badge 並提示唯讀。

## Impact

- **esgchain-api**：3 個 Migration、1 個 TRIGGER、`AssessmentSeriesService`、`SaqProjectService`、`SaqQuestionService` 邏輯調整；路由無新增，但 `POST /api/v1/series` 需驗證 `template_id`。
- **esgchain-web**：「新增系列」Modal 改為選擇範本（取代文字 domain 欄位）；Project 列表不再顯示「選擇範本」步驟。
- **資料清除**：assessment_series（3 筆）、saq_projects（12 筆）、saqs（41 筆）、saq_responses（322 筆）、supplier_disclosures（36 筆）、cap_findings（6 筆）、caps（6 筆）全部清除，由新 Seed 重建。
- **MySQL 8.4 限制**：TRIGGER 取代 CHECK CONSTRAINT（跨表驗證不支援）。
