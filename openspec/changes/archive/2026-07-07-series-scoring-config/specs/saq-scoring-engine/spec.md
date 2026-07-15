## ADDED Requirements

### Requirement: calculate_saq_score 接受 series 層設定參數
`calculate_saq_score()` 新增兩個可選參數：
- `series_pillar_weights: dict | None`：以 slug prefix 為 key 的 pillar 加權
- `series_grade_thresholds: dict | None`：`{A, B, C, D}` 等級閾值

#### Scenario: series pillar_weights 優先於等權平均
- **WHEN** `series_pillar_weights` 不為 null
- **THEN** pillar 總分以 `series_pillar_weights` 中各 pillar 的加權值計算加權平均
- **AND** slug prefix → pillar name 轉換：`prefix + "."` 查 `SLUG_PREFIX_TO_PILLAR`

#### Scenario: series grade_thresholds 優先於 ScoringModel
- **WHEN** `series_grade_thresholds` 不為 null
- **THEN** 以 `series_grade_thresholds` 判定等級，不查詢 ScoringModel
- **AND** 仍記錄 `scoring_model_id = null`

#### Scenario: 無 series 設定時維持現行行為
- **WHEN** `series_pillar_weights` 與 `series_grade_thresholds` 均為 null
- **THEN** pillar 加權使用等權平均，閾值查詢 ScoringModel（含 fallback DEFAULT_THRESHOLDS）

### Requirement: celery/saq-scoring endpoint 接受新參數
`CelerySaqScoringRequest` schema 新增：
- `series_pillar_weights: dict | None = None`
- `series_grade_thresholds: dict | None = None`

#### Scenario: 新參數傳遞至 Celery task
- **WHEN** POST /ai/v1/scoring/celery/saq-scoring 帶有 series_pillar_weights
- **THEN** Celery task `calculate_saq_score` 接收並傳入 scoring_service
