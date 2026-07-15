## Context

`RiskAutoDerivationService::deriveFromSaq()` 目前的 impact 計算路徑：

```
SAQ → supplier.country_code
          ↓
    CountryRiskRating (單筆查詢)
          ↓
    s_impact / e_impact / gp_impact
```

`supplier_facilities` 表已存在，欄位 `country`（char 2）+ `is_active`，但從未被 RiskAutoDerivationService 查詢。一家供應商可能在多個國家設有廠址，製造廠址的風險可能遠高於 HQ 登記地。

## Goals / Non-Goals

**Goals:**
- 在 impact 計算時納入所有 active 廠址的 country，取各維度最高風險值（保守原則）
- 不增加 N+1 查詢（一次批次查所有涉及國家的 CountryRiskRating）
- 保留 `supplier.country_code` fallback（廠址可能為空）
- 在推導的 `notes` 欄位記錄實際採用了哪些國家

**Non-Goals:**
- 不修改 `supplier_facilities` 的資料結構
- 不影響手動建立的 RiskAssessment
- 不處理廠址的「加權平均」（保守取最高即可）
- 不將原料原產地（raw_material_origins）納入此次計算

## Decisions

### 決策 1：取各維度最高值，而非加權平均

**理由**：風險評估採保守原則——只要一個生產廠址存在高風險，整體供應商就應被標記。若採加權平均，少數低風險廠址會稀釋主要廠址的真實風險。

```
supplier.country_code = TW  (labor_risk=1, env_risk=2, geo_risk=1)
facilities.country    = VN  (labor_risk=3, env_risk=3, geo_risk=2)
facilities.country    = BD  (labor_risk=5, env_risk=4, geo_risk=3)

結果：
  effective_labor_risk = max(1, 3, 5) = 5
  effective_env_risk   = max(2, 3, 4) = 4
  effective_geo_risk   = max(1, 2, 3) = 3
```

### 決策 2：批次查詢所有國家的 CountryRiskRating

收集 `[supplier.country_code] + facilities.*.country`（去除 null、去重），一次 `whereIn` 查詢，避免迴圈內多次 DB 查詢。

```php
$countryCodes = collect([$countryCode])
    ->merge($facilities->pluck('country'))
    ->filter()->unique()->values();

$ratingMap = CountryRiskRating::whereIn('country_code', $countryCodes)
    ->get()->keyBy('country_code');
```

### 決策 3：notes 記錄採用國家清單

```
自動從 SAQ {id} 推導（tier=1 countries=TW,VN,BD effective=BD）
```

`effective=` 標示最終驅動 impact 的國家（各維度可能不同，記最高那個）。

### 決策 4：廠址為空時行為不變

若 `supplier_facilities` 無任何 active 記錄，邏輯退化為原有的 `supplier.country_code` 單一查詢，完全向後相容。

## Risks / Trade-offs

| 風險 | 說明 | 緩解 |
|------|------|------|
| 廠址資料不完整 | supplier_facilities 可能未填，導致仍只用 HQ 國家 | 可接受，優於現況；未來補齊廠址資料即自動生效 |
| 風險突然升高 | 補齊廠址後重新評分，BD 廠址可能使大量供應商跳至 extreme | 屬正確行為；可加 notes 區分原因 |
| N+1 查詢 | 批次 whereIn 已解決 | — |
