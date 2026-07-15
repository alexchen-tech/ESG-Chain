## ADDED Requirements

### Requirement: 題庫題目 CRUD API
`GET/POST /api/v1/settings/question-bank` 及 `PUT/DELETE /api/v1/settings/question-bank/:id` SHALL 管理 `template_id IS NULL` 的題庫題目，欄位包含 question_text / category / question_type / options / weight / is_required / sasb_topic_id / sasb_metric_code / tags（JSON array）。

#### Scenario: 新增題庫題目
- **WHEN** `POST /api/v1/settings/question-bank` 帶合法 question_text、category、question_type
- **THEN** 建立 template_id=NULL、source_bank_question_id=NULL 的 saq_questions 記錄，回傳 201

#### Scenario: 查詢題庫（含過濾）
- **WHEN** `GET /api/v1/settings/question-bank?category=E&tag=ISO-環境`
- **THEN** 回傳 template_id IS NULL 且符合過濾條件的題目，每筆附帶 usage_count

#### Scenario: 刪除有引用的題庫題目
- **WHEN** 題目的 usage_count > 0 時呼叫 DELETE
- **THEN** 回傳 422「此題目已被 N 個範本引用，請先確認後刪除」（仍允許強制刪除，警告即可）

### Requirement: usage_count 顯示
每個題庫題目回應 SHALL 包含 `usage_count`（integer），值為 `saq_questions WHERE source_bank_question_id = this.id AND template_id IS NOT NULL` 的筆數。

#### Scenario: 從未被引用的題目
- **WHEN** 題目 usage_count = 0
- **THEN** 回傳 usage_count: 0，允許刪除

#### Scenario: 已被 3 個範本引用
- **WHEN** 題目被複製到 3 個不同範本
- **THEN** 回傳 usage_count: 3

### Requirement: Tag 預設清單
系統 SHALL 提供 `GET /api/v1/settings/question-bank/tags` 回傳預設 10 個 tag 清單：E / S / G / 地域風險 / ISO-組織治理 / ISO-人權 / ISO-勞工 / ISO-環境 / ISO-公平營運 / ISO-消費者 / ISO-社區。

#### Scenario: 取得 tag 清單
- **WHEN** 呼叫 `GET /api/v1/settings/question-bank/tags`
- **THEN** 回傳 11 個 string 的陣列
