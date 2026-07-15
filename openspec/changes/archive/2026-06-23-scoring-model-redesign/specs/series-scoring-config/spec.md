## MODIFIED Requirements

### Requirement: getScoringConfig 預設值來源改為框架預設加權
`AssessmentSeriesService::getScoringConfig()` 當 `series.pillar_weights IS NULL` 時，從 `framework_default_weights` 讀取對應框架的預設值，取代原本的等權平均。

#### Scenario: Series 未設定加權時回傳框架預設
- **WHEN** GET /api/v1/assessment-series/{id}/scoring-config
- **AND** series.pillar_weights IS NULL
- **AND** framework_default_weights 有對應框架的記錄
- **THEN** 回傳 `pillar_weights` 填入框架預設值（而非 null 或等權平均）
- **AND** `pillar_weights` 欄位標記 `is_default: true`，讓前端知道這是預設值非使用者設定

#### Scenario: framework_default_weights 無對應框架
- **WHEN** framework_default_weights 沒有該 scoring_framework 的記錄
- **THEN** 回傳 `pillar_weights: null`（維持現行行為）

#### Scenario: Series 已有自訂加權
- **WHEN** series.pillar_weights IS NOT NULL
- **THEN** 回傳 series 自訂值，不查 framework_default_weights
