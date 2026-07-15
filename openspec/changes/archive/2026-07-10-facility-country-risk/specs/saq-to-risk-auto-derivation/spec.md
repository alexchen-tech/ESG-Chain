## MODIFIED Requirements

### Requirement: SAQ 評分完成後自動建立 RiskAssessment

當 `scoreCallback()` 成功更新 SAQ 分數且 SAQ 包含維度分數（score_e / score_s / score_g 至少一項非 null）時，系統 SHALL 自動建立一筆 `risk_assessments` 記錄。

**Probability 換算規則**（不變）：
- `{dim}_probability = max(1, ceil((100 - score_{dim}) / 20))`

**Impact 換算規則**（廠址補強版）：

```text
tier_weight = { 1→2, 2→1, 3→0, null→0 }[supplier.tier]

# 收集所有涉及國家
all_countries = [supplier.country_code] + supplier_facilities(is_active=true).country
             → 過濾 null、去重

# 批次查詢 CountryRiskRating，查無記錄者 fallback = 3
effective_labor_risk = max(rating.labor_risk for each country in all_countries)
effective_env_risk   = max(rating.env_risk   for each country in all_countries)
effective_geo_risk   = max(rating.geo_risk   for each country in all_countries)

s_impact  = clamp(effective_labor_risk + tier_weight, 1, 5)
e_impact  = clamp(effective_env_risk   + tier_weight, 1, 5)
g_impact  = clamp(tier_weight + 2,                    1, 5)
gp_impact = clamp(effective_geo_risk,                 1, 5)
```

`all_countries` 至少包含 `supplier.country_code`。若 supplier_facilities 無 active 記錄，退化為原有單一國家邏輯。若某國家在 `country_risk_ratings` 查無記錄，該國家三個維度 risk 均以 3 計，並寫入 `Log::info`。

其他欄位：

- `gp_probability`：自動設為 3（預設中位值，保留手動覆蓋空間）
- `gp_impact`：依上述 `gp_impact` 公式自動填入
- `assessed_by = null`（系統自動）
- `notes`：格式 `自動從 SAQ {saq_id} 推導（tier={tier} countries={code1,code2,...}）`

若 score_e / score_s / score_g 全為 null，SHALL 跳過自動建立。

#### Scenario: 廠址國家風險高於 HQ 登記地

- **WHEN** `supplier.country_code = 'TW'`（labor_risk=1），active facilities 含 `country='BD'`（labor_risk=5），tier=1
- **THEN** `effective_labor_risk = max(1, 5) = 5`，`s_impact = clamp(5+2,1,5) = 5`（extreme 等級）

#### Scenario: 無廠址記錄時退化為原有邏輯

- **WHEN** 供應商無任何 active supplier_facilities
- **THEN** `all_countries = [supplier.country_code]`，行為與舊版相同

#### Scenario: 廠址國家查無評等時 fallback

- **WHEN** facilities 中有 `country='ZZ'` 但 `country_risk_ratings` 無此記錄
- **THEN** 該國家三個維度 risk 以 3 計算，並寫入 Log::info；不影響其他國家的正常查詢

#### Scenario: notes 記錄涉及國家清單

- **WHEN** supplier.country_code='TW'，facilities=['VN','BD']，tier=1
- **THEN** `notes` 包含 `countries=TW,VN,BD`，方便稽核追蹤
