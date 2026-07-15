## MODIFIED Requirements

### Requirement: 比較 API 回傳 scoring_model 資訊

系統 SHALL 在 `GET /api/v1/assessment-series/{id}/comparison` 的 `projects` 陣列中，為每個 project 加入最近一次計分使用的 `scoring_model_id`。

#### Scenario: 回傳 scoring_model_id
WHEN 取得系列比較資料
THEN `projects` 陣列每筆 SHALL 包含 `scoring_model_id: string | null`（取該 project 最新 saq_score_snapshot 的 scoring_model_id）

#### Scenario: 不同 scoring_model 的一致性旗標
WHEN `projects` 陣列中存在相鄰兩個 project 的 `scoring_model_id` 不同（且皆不為 null）
THEN API SHALL 在 response root 加入 `scoring_model_inconsistent: true`

### Requirement: 前端不同 scoring_model 警示

系統 SHALL 在折線圖中以視覺方式標示 scoring_model 不一致的波次間隔。

#### Scenario: 不同 model 波次之間顯示虛線
WHEN 相鄰兩波次的 `scoring_model_id` 不同
THEN 折線圖中兩點之間的連線 SHALL 改為虛線（SVG `stroke-dasharray`），並在中點附近顯示警示圖示（⚠）

#### Scenario: Tooltip 說明
WHEN 使用者滑鼠移至虛線段或警示圖示
THEN Tooltip SHALL 顯示「此段波次使用不同計分模型，分數不直接可比」
