# Spec Delta: project-question-snapshot

## MODIFIED Requirements

### Requirement: 快照時填入 weight

**原規格**：`project_questions.weight` 快照時固定為 null，由 Change 2 處理。

**更新後**：`ProjectQuestionService::snapshot()` SHALL 在快照完成後，若 project 有關聯 series，查詢 `assessment_series_weights` 並依 `source_template_question_id` 填入 `project_questions.weight`。

**行為規則：**
- 有 series_id 且 series 有 weight schema → weight 填入對應值
- 有 series_id 但 series 無 weight schema（空表）→ weight 保持 null，不阻擋快照
- 無 series_id → weight 保持 null（獨立 project 行為不變）

#### Scenario: 有 Weight Schema 的 Series
WHEN 建立 SaqProject，series_id 對應 series 有 weight 設定
THEN 快照後，project_questions 中每道題的 weight 依 source_template_question_id 對應填入；無對應的題目 weight 保持 null

#### Scenario: 無 Weight Schema 的 Series
WHEN 建立 SaqProject，series_id 對應 series 的 assessment_series_weights 為空
THEN 快照正常完成，project_questions.weight 全部為 null
