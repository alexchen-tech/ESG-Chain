## MODIFIED Requirements

### Requirement: 六維熱圖 API
系統 SHALL 提供 `GET /api/v1/risk/six-dim-heatmap` 端點，支援可選的 `before_days` 查詢參數，用於歷史對比模式下取得指定天數前的 RA 快照。

#### Scenario: 正常回傳熱圖資料（無 before_days）
- **WHEN** 已認證用戶發送 `GET /api/v1/risk/six-dim-heatmap`（不帶 before_days）
- **THEN** 回傳每家供應商的最新 RA（以 `assessed_at` 取最新，無日期限制）
- **AND** 回應包含：supplier_id、supplier_name、supplier_code、country_code、assessed_at、source_type、dim_e1–dim_e6、open_cap_count、risk_score
- **AND** 回應包含 `thresholds` 物件（從 system_settings.cap_thresholds 讀取）
- **AND** 回應包含 `summary.total` 與 `summary.any_dim_critical`

#### Scenario: 帶 before_days 回傳歷史快照
- **WHEN** 已認證用戶發送 `GET /api/v1/risk/six-dim-heatmap?before_days=30`
- **THEN** 僅考慮 `assessed_at <= NOW() - 30 days` 的 RA 記錄
- **AND** 每家供應商取符合條件中最新的一筆 RA
- **AND** 無符合條件 RA 的供應商不出現於回應 data 陣列中
- **AND** 回應結構與無 before_days 時相同（含 thresholds、summary）

#### Scenario: before_days 參數驗證
- **WHEN** `before_days` 不為正整數或超過 365
- **THEN** 回傳 422 Unprocessable Entity，錯誤訊息說明合法範圍（1–365）

#### Scenario: 無供應商有 RA 時回傳空陣列
- **WHEN** 資料庫中無符合條件的 risk_assessments 記錄
- **THEN** 回傳 `{"data": [], "thresholds": {...}, "summary": {"total": 0, "any_dim_critical": 0}}`

#### Scenario: dim_e6 為 null 時不計入 critical 判斷
- **WHEN** 供應商的 dim_e6 為 null，其餘維度皆達標
- **THEN** 該供應商 SHALL NOT 被計入 `any_dim_critical`
