## ADDED Requirements

### Requirement: E4 維度三狀態機計分路徑

esgchain-ai `six_dim_scoring_tasks.py` 在計算 E4（地緣風險）維度時，SHALL 依下列三條狀態機路徑決定計分公式：

1. **混合路徑**：`country_defense_score` 存在 **且** SAQ 已完成（`saq_completed = true`）
   - `dim_e4 = country_defense_score × α + saq_geo_risk_score × (1 − α)`
   - α = `e4_objective_ratio`（來自 series 設定，預設 0.40）

2. **純客觀快照路徑**：`country_defense_score` 存在 **且** SAQ 未完成
   - `dim_e4 = country_defense_score × 1.0`

3. **純 SAQ 路徑**：`country_defense_score` 不存在
   - `dim_e4` 維持現有純 SAQ georisk.* 計算，行為不變

#### Scenario: 混合計分——有國家評等且 SAQ 已完成

- **WHEN** scoring payload 包含 `country_defense_score = 70`、`e4_objective_ratio = 0.40`、`saq_completed = true`、`saq_geo_risk_score = 60`
- **THEN** `dim_e4 = 70 × 0.40 + 60 × 0.60 = 64.0`

#### Scenario: 純客觀快照——有國家評等但 SAQ 未完成

- **WHEN** scoring payload 包含 `country_defense_score = 70`、`saq_completed = false`
- **THEN** `dim_e4 = 70`（忽略 α，使用 1.0 客觀佔比）

#### Scenario: 純 SAQ——無國家評等

- **WHEN** scoring payload 中 `country_defense_score` 為 null 或缺席
- **THEN** `dim_e4` 依現有 georisk.* SAQ 題目計算，結果與舊版相同

#### Scenario: α 為 0 時等同純 SAQ

- **WHEN** `e4_objective_ratio = 0.0`、`saq_completed = true`、`country_defense_score` 存在
- **THEN** `dim_e4 = saq_geo_risk_score`（客觀資料佔比 0，完全使用 SAQ 分數）

#### Scenario: α 為 1 時等同純客觀

- **WHEN** `e4_objective_ratio = 1.0`、`saq_completed = true`、`country_defense_score` 存在
- **THEN** `dim_e4 = country_defense_score`

### Requirement: country_defense_score 計算規則

`country_defense_score` SHALL 由 esgchain-api 計算後傳入 AI，不由 AI 自行查詢。

計算公式：`country_defense_score = 100 − (country_risk_rating − 1) / 4 × 100`

其中 `country_risk_rating = mean(sub_scores.political, sub_scores.environmental, sub_scores.social, sub_scores.regulatory)`（各值 1–5）。

結果四捨五入至小數點第二位。

#### Scenario: 最低風險國家（rating = 1）

- **WHEN** `country_risk_rating = 1.0`
- **THEN** `country_defense_score = 100.0`

#### Scenario: 最高風險國家（rating = 5）

- **WHEN** `country_risk_rating = 5.0`
- **THEN** `country_defense_score = 0.0`

#### Scenario: 中等風險（rating = 3）

- **WHEN** `country_risk_rating = 3.0`
- **THEN** `country_defense_score = 50.0`

#### Scenario: sub_scores 缺漏時 fallback

- **WHEN** `country_risk_ratings` 記錄存在但 `sub_scores` 為 null
- **THEN** `ScoringJobDispatchService` SHALL 使用 `geo_risk` 欄位值作為 fallback，計算 `country_defense_score = 100 − (geo_risk − 1) / 4 × 100`

#### Scenario: 查無國家評等

- **WHEN** `supplier.country` 未在 `country_risk_ratings` 中登錄
- **THEN** `country_defense_score = null`，採用純 SAQ 路徑

### Requirement: ScoringJobDispatchService 組裝 E4 客觀資料

`ScoringJobDispatchService` 派送計分任務前，SHALL 查詢 `supplier.country` 對應的 `country_risk_ratings` 紀錄，組裝以下欄位傳入 AI payload：

- `country_defense_score`：浮點數或 null
- `e4_objective_ratio`：來自 `series.e4_objective_ratio`，series 無設定時使用 0.40，無 series 時使用 0.40
- `saq_completed`：boolean，`saqs.status = 'submitted'` 或 `saqs.score IS NOT NULL`

#### Scenario: 有 series 且 series 設定了 e4_objective_ratio

- **WHEN** SAQ 所屬 project 有 `series_id`，series 的 `e4_objective_ratio = 0.60`
- **THEN** payload 中 `e4_objective_ratio = 0.60`

#### Scenario: series 未設定 e4_objective_ratio（null）

- **WHEN** `series.e4_objective_ratio IS NULL`
- **THEN** payload 中 `e4_objective_ratio = 0.40`（系統預設）

#### Scenario: SAQ 已送出

- **WHEN** `saqs.status = 'submitted'`
- **THEN** `saq_completed = true`
