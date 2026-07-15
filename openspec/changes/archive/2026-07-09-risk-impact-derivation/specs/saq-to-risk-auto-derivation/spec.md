## MODIFIED Requirements

### Requirement: SAQ 評分完成後自動建立 RiskAssessment

當 `scoreCallback()` 成功更新 SAQ 分數且 SAQ 包含維度分數（score_e / score_s / score_g 至少一項非 null）時，系統 SHALL 自動建立一筆 `risk_assessments` 記錄。

**Probability 換算規則**（不變）：
- `{dim}_probability = max(1, ceil((100 - score_{dim}) / 20))`

**Impact 換算規則**（修改）：
```
tier_weight = { 1→2, 2→1, 3→0, null→0 }[supplier.tier]

s_impact  = clamp(country.labor_risk + tier_weight, 1, 5)
e_impact  = clamp(country.env_risk   + tier_weight, 1, 5)
g_impact  = clamp(tier_weight + 2,                  1, 5)
gp_impact = clamp(country.geo_risk,                 1, 5)
```

`country` 指 `country_risk_ratings` 中 `country_code = supplier.country_code` 的記錄。若查無記錄，三個維度 risk 均 fallback 為 3，並寫入 Log::info。

若 score_e / score_s / score_g 全為 null，SHALL 跳過自動建立，不建立 RiskAssessment。

#### Scenario: BD tier 1 供應商 S 維度高風險

- **WHEN** SAQ 的 supplier.country_code = 'BD'（labor_risk=5）、tier=1、score_s=20
- **THEN** `s_probability = ceil(80/20) = 4`，`s_impact = clamp(5+2,1,5) = 5`，S 維度分數 = 20（extreme）

#### Scenario: TW tier 2 供應商 E 維度中風險

- **WHEN** SAQ 的 supplier.country_code = 'TW'（env_risk=2）、tier=2、score_e=60
- **THEN** `e_probability = ceil(40/20) = 2`，`e_impact = clamp(2+1,1,5) = 3`，E 維度分數 = 6（low）

#### Scenario: 國家不在評等表時使用 fallback

- **WHEN** supplier.country_code 在 `country_risk_ratings` 中無記錄
- **THEN** 系統 SHALL 使用 labor_risk=3 / env_risk=3 / geo_risk=3 作為 fallback，並寫入 Log::info 記錄該 country_code

#### Scenario: score_e = 100 時 probability 最低

- **WHEN** score_e = 100（最高分）
- **THEN** `e_probability = max(1, ceil(0/20)) = 1`

#### Scenario: score_e = 0 時 probability 最高

- **WHEN** score_e = 0（最低分）
- **THEN** `e_probability = max(1, ceil(100/20)) = 5`

#### Scenario: SAQ 無維度分數時跳過

- **WHEN** scoreCallback 的 score_e / score_s / score_g 均為 null
- **THEN** 系統 SHALL 不建立 RiskAssessment，正常回傳評分結果

#### Scenario: GP 維度由 geo_risk 推導

- **WHEN** supplier.country_code = 'VN'（geo_risk=3）、tier=1
- **THEN** `gp_impact = clamp(3,1,5) = 3`（GP 不加 tier_weight，因地緣風險與採購層級無直接關係）
