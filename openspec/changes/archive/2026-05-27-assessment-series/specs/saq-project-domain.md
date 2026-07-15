# Spec Delta: saq-project-domain

## MODIFIED Requirements

### Requirement: SaqProject 建立支援 series_id

SaqProject 建立 API SHALL 接受可選的 `series_id` 欄位。

**更新行為：**
- `series_id` 為選填；不帶此欄位時行為與原規格相同
- 指定 `series_id` 時，系統 SHALL 驗證 series 存在且 status = 'active'
- 指定 `series_id` 時，快照完成後由 `ProjectQuestionService` 從 `assessment_series_weights` 填入 `project_questions.weight`

#### Scenario: 建立 Project 並加入 Series
WHEN POST `/api/v1/saq-projects`，帶有有效 series_id
THEN project 建立，series_id 記錄，快照後 project_questions.weight 依 series weight schema 填入

#### Scenario: 指定已封存 Series
WHEN POST `/api/v1/saq-projects`，series_id 指向 status = 'archived' 的 series
THEN 系統回傳 422，message: 'Series 已封存，無法加入新專案'
