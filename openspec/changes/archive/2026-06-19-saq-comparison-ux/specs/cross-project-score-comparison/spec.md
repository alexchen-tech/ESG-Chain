## MODIFIED Requirements

### Requirement: 跨 Project 分數比較 API — source_template_question_id 回傳值

系統 SHALL 在 `GET /api/v1/assessment-series/{id}/comparison` 回傳的 `question_trends[].source_template_question_id` 欄位中，使用唯一穩定鍵值：

- 若 `project_questions.source_template_question_id` 不為 null：使用原始 UUID
- 若為 null（seeded data 或舊資料）：使用 fallback 字串 `"order:<order>"`（例如 `"order:1"`）

#### Scenario: source_template_question_id 為 null 的題目
WHEN `project_questions.source_template_question_id = null`
THEN 回傳的 `source_template_question_id` SHALL 為 `"order:<order>"` 字串，前端可用此值作為唯一 Map key，矩陣 SHALL 顯示所有題目列

#### Scenario: source_template_question_id 有值的題目
WHEN `project_questions.source_template_question_id` 為有效 UUID
THEN 回傳的 `source_template_question_id` 維持原 UUID 不變
