## MODIFIED Requirements

### Requirement: 供應商風險歷史使用 E1–E6 六維資料
供應商風險歷史記錄 SHALL 以 dim_e1–e6 為唯一有效風險維度顯示，移除 `buildDimension()` 輸出，並依 source_type 標示事件來源。

#### Scenario: 風險歷史 API 回傳 E1–E6
- **WHEN** 呼叫 `GET /api/v1/suppliers/{id}/risk-history`
- **THEN** 每筆 RA 事件包含：assessed_at、assessment_version、source_type（saq/geo_event/manual_review）、dim_e1–dim_e6、risk_score
- **AND** SHALL NOT 回傳 e_probability、e_impact、axis1_score 等四軸欄位
- **AND** source_type='saq' 時額外包含 `saq_id` 供前端連結問卷詳情
- **AND** source_type='geo_event' 時額外包含 `geo_event_name`（join geo_events.name）

#### Scenario: 移除 buildDimension() 輸出
- **WHEN** `RiskAssessment::toRiskSummary()` 被呼叫
- **THEN** 回傳結構 SHALL NOT 包含 `dimensions.e`、`dimensions.s`、`dimensions.g`、`dimensions.gp` 等四軸物件
- **AND** 回傳結構 SHALL 包含 `six_dims: {E1, E2, E3, E4, E5, E6}`（null 維度亦明確列出為 null）

#### Scenario: 無 RA 記錄的供應商
- **WHEN** 供應商尚未有任何 RiskAssessment
- **THEN** `GET /api/v1/suppliers/{id}/risk-history` 回傳 `{"data": [], "meta": {"total": 0}}`

#### Scenario: 混合 source_type 的歷史清單
- **WHEN** 供應商有 saq 驅動與 geo_event 驅動的 RA 記錄
- **THEN** 列表按 assessed_at 降序排列，source_type 不同的記錄混合顯示
- **AND** 每筆記錄皆有 source_type 標籤，前端可依此顯示不同 badge 樣式
