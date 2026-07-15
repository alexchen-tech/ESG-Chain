## ADDED Requirements

### Requirement: Series 計分設定 Tab 六維度加權區塊
Series 詳情頁「計分設定」Tab 新增「六維度加權」區塊，顯示 E1–E6 加權設定，可繼承系統預設或自訂覆蓋。

#### Scenario: 顯示當前 dim_weights
- **WHEN** 使用者進入 Series 計分設定 Tab
- **THEN** 顯示六維度加權區塊，包含 E1–E6 各維度中文名稱與當前百分比
- **AND** 若 `dim_weights_source = 'default'`，顯示「目前使用系統預設加權」標籤
- **AND** 若 `dim_weights_source = 'custom'`，顯示「自訂加權」標籤

#### Scenario: 自訂覆蓋加權
- **WHEN** 使用者修改任一維度數值並點擊「儲存」
- **THEN** PUT /api/v1/assessment-series/{id}/scoring-config 傳入 dim_weights
- **AND** series.dim_weights_source 更新為 `'custom'`

#### Scenario: 恢復系統預設加權
- **WHEN** 使用者點擊「恢復系統預設」
- **THEN** 前端讀取 GET /api/v1/settings/dim-weight-defaults 並填入欄位
- **AND** 儲存後 dim_weights_source 更新為 `'default'`

#### Scenario: 即時合計驗證
- **WHEN** 使用者修改任一維度數值
- **THEN** 即時顯示六維度合計百分比
- **AND** 合計不等於 100%（容差 ±1%）時，儲存按鈕禁用並顯示紅色警示

### Requirement: Series 計分設定 API 支援 dim_weights
`PUT /api/v1/assessment-series/{id}/scoring-config` 接受並驗證 `dim_weights` 欄位。

#### Scenario: 儲存合法的 dim_weights
- **WHEN** PUT /api/v1/assessment-series/{id}/scoring-config，body 包含 `dim_weights: {"E1":0.30,...}` 合計為 1.0
- **THEN** 回傳 200，更新 `assessment_series.dim_weights` 與 `dim_weights_source = 'custom'`

#### Scenario: dim_weights 合計驗證
- **WHEN** PUT 傳入 dim_weights 合計不在 0.99–1.01 之間
- **THEN** 回傳 422，訊息「dim_weights 合計須等於 1.0」

#### Scenario: dim_weights 缺少維度 key
- **WHEN** PUT 傳入 dim_weights 缺少任一 E1–E6 key
- **THEN** 回傳 422，訊息「dim_weights 必須包含 E1–E6 全部六個維度」

#### Scenario: GET scoring-config 回傳 dim_weights
- **WHEN** GET /api/v1/assessment-series/{id}/scoring-config
- **THEN** 回傳包含 `dim_weights`、`dim_weights_source`、`e4_objective_ratio` 欄位
