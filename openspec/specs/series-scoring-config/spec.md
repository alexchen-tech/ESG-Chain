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

---

## ADDED Requirements（series-scoring-config 變更新增）

### Requirement: Series 計分設定欄位
`assessment_series` 表新增兩個可為 null 的 JSON 欄位：
- `pillar_weights`：以 slug prefix（不含結尾點）為 key 的浮點加權，各值合計須為 1.0（容差 ±0.01）
- `grade_thresholds`：等級閾值 `{A, B, C, D}`，須滿足 A > B > C > D > 0

#### Scenario: 儲存 pillar_weights 加總驗證
- **WHEN** PUT /api/v1/assessment-series/{id}/scoring-config 傳入 pillar_weights，各值合計不在 0.99–1.01 之間
- **THEN** 回傳 422，錯誤訊息說明「pillar_weights 合計須等於 1.0」

#### Scenario: 儲存 grade_thresholds 遞減驗證
- **WHEN** PUT /api/v1/assessment-series/{id}/scoring-config 傳入 grade_thresholds，不滿足 A > B > C > D > 0
- **THEN** 回傳 422，錯誤訊息說明「閾值須遞減且 D > 0」

#### Scenario: 取得計分設定
- **WHEN** GET /api/v1/assessment-series/{id}/scoring-config
- **THEN** 回傳 `{ pillar_weights, grade_thresholds, available_pillars }`
- **AND** `available_pillars` 依 series 綁定的 template.scoring_framework 列出可設定的 slug prefix 清單

#### Scenario: 未設定時回傳 null
- **WHEN** series 從未設定計分設定
- **THEN** GET 回傳 `{ pillar_weights: null, grade_thresholds: null, available_pillars: [...] }`

### Requirement: 計分設定 Tab UI
Series 詳情頁新增第四個 Tab「計分設定」。

#### Scenario: 顯示框架對應的 pillar 清單
- **WHEN** 使用者切換到「計分設定」Tab
- **THEN** 依 series 的 scoring_framework 顯示對應 pillar 的中文名稱與輸入欄位（%）
- **AND** 若 pillar_weights 已設定，顯示現有值；否則顯示等權平均作為 placeholder

#### Scenario: 合計驗證
- **WHEN** 使用者輸入 pillar 加權
- **THEN** 即時顯示合計百分比，不等於 100% 時顯示紅色警示，儲存按鈕禁用

#### Scenario: 重設為等權
- **WHEN** 使用者點擊「重設為等權」
- **THEN** 各 pillar 自動填入 100/n（n = pillar 數量），合計顯示 100%

#### Scenario: 修改後提示歷史分數不重算
- **WHEN** 使用者儲存計分設定成功
- **THEN** 顯示提示：「設定已儲存。已完成的問卷分數不會自動重算。」

### Requirement: DispatchSaqScoringJob 傳遞 series 計分設定
計分 Job 觸發時，將 series 的 pillar_weights 與 grade_thresholds 一併傳給 AI service。

#### Scenario: 傳遞 series 設定
- **WHEN** DispatchSaqScoringJob 執行
- **AND** SAQ 所屬 project 有 series_id
- **THEN** payload 包含 `series_pillar_weights` 與 `series_grade_thresholds`（可為 null）

#### Scenario: 無 series 時不傳
- **WHEN** project 無 series_id
- **THEN** payload 中 `series_pillar_weights` 與 `series_grade_thresholds` 均為 null

---

## ADDED Requirements（e4-country-risk-integration 變更新增）

### Requirement: Series E4 客觀比例設定欄位

`assessment_series` 表 SHALL 新增 `e4_objective_ratio` DECIMAL(3,2) DEFAULT 0.40 nullable 欄位，代表 E4 維度計分時客觀國家風險資料的佔比 α（0.00–1.00）。

驗證規則：值須介於 0.00 與 1.00 之間（含）。null 表示「使用系統預設 0.40」。

#### Scenario: 儲存合法的 e4_objective_ratio

- **WHEN** PUT /api/v1/assessment-series/{id}/scoring-config 傳入 `{ "e4_objective_ratio": 0.60 }`
- **THEN** 系統 SHALL 儲存並回傳 200 及更新後的設定

#### Scenario: 傳入超出範圍的值

- **WHEN** PUT /api/v1/assessment-series/{id}/scoring-config 傳入 `{ "e4_objective_ratio": 1.5 }`
- **THEN** 系統 SHALL 回傳 422，錯誤訊息說明「e4_objective_ratio 須介於 0 至 1 之間」

#### Scenario: 傳入 null 重設為系統預設

- **WHEN** PUT /api/v1/assessment-series/{id}/scoring-config 傳入 `{ "e4_objective_ratio": null }`
- **THEN** 系統 SHALL 將欄位設為 null，計分時使用預設值 0.40

#### Scenario: 取得計分設定包含 e4_objective_ratio

- **WHEN** GET /api/v1/assessment-series/{id}/scoring-config
- **THEN** 回傳 payload 包含 `e4_objective_ratio`（有值則回傳數值，null 時回傳 null 並附 `e4_objective_ratio_effective: 0.40` 表示實際使用值）

### Requirement: 計分設定 Tab 顯示 E4 α 滑桿

計分設定 Tab 的「E4 地緣風險」區塊 SHALL 顯示 α 滑桿，讓 admin 調整客觀資料佔比。

滑桿規格：

- 範圍 0%–100%，步進 5%
- 預設值顯示 40%（對應系統預設 0.40）
- 滑桿左側標籤「客觀（國家評等）」，右側標籤「主觀（SAQ 自填）」
- 顯示即時百分比數值

#### Scenario: 顯示 E4 α 滑桿

- **WHEN** 使用者切換到「計分設定」Tab
- **THEN** E4 區塊 SHALL 顯示 α 滑桿，目前值若 null 顯示 40%（預設），否則顯示已設定值

#### Scenario: 調整滑桿後儲存

- **WHEN** 使用者拖動滑桿至 60% 並點擊「儲存」
- **THEN** 系統 SHALL 送出 PUT 請求含 `{ "e4_objective_ratio": 0.60 }`，儲存成功後顯示提示「設定已儲存。已完成的問卷分數不會自動重算。」

#### Scenario: 無國家評等資料時顯示警示

- **WHEN** 計分設定頁面載入，發現 `country_risk_ratings` 表中無任何 sub_scores 資料
- **THEN** E4 α 滑桿區塊 SHALL 顯示橘色提示：「尚無國家風險 sub_scores 資料，純客觀路徑與混合路徑將 fallback 至 geo_risk 欄位。」

### Requirement: DispatchSaqScoringJob 傳遞 e4_objective_ratio

計分 Job 觸發時，SHALL 將 series 的 `e4_objective_ratio` 一併傳給 AI service。

#### Scenario: 傳遞 e4_objective_ratio

- **WHEN** DispatchSaqScoringJob 執行，SAQ 所屬 project 有 series_id
- **THEN** payload SHALL 包含 `e4_objective_ratio`（series 有設定值則用該值，否則用 0.40）

#### Scenario: 無 series 時使用預設值

- **WHEN** project 無 series_id
- **THEN** payload 中 `e4_objective_ratio = 0.40`

---

## ADDED Requirements（six-dim-scoring 變更）

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
