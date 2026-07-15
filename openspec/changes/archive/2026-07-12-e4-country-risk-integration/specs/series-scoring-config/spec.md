## ADDED Requirements

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
