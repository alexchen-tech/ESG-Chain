## Why

現行計分模型（ScoringModel）是全域設定，以 SASB 產業代碼為索引，所有評核系列共用同一組 pillar 加權與等級閾值。這導致兩個問題：

1. **非 ESG 框架無法設定 pillar 加權**：ISO20400 / ISO26000 / Geo-Risk 框架的 pillar 目前一律等權平均，品牌客戶無法依產業特性調整（如採礦業 ISO20400 偏重「風險管理」）。
2. **閾值無法依框架區分**：ISO20400 的 A 級標準可能不同於 ESG 框架，但目前兩者共用同一組閾值。

品牌客戶需要在 **AssessmentSeries 層**自訂計分設定，反映該系列所採用框架與產業重點的組合。

## What Changes

在 `assessment_series` 表加入兩個 JSON 欄位：
- `pillar_weights`：以 slug prefix 為 key 的 pillar 加權比例
- `grade_thresholds`：五等級（A/B/C/D/E）的分數閾值

品牌客戶在 Series 詳情頁新增的「計分設定」Tab 中自訂，設定後的值在 `DispatchSaqScoringJob` 時一起傳給 AI 計分引擎。AI service 使用 series 設定優先，無設定時 fallback 至全域 ScoringModel。

## Capabilities

### New Capabilities
- `series-scoring-config`: Series 層的 pillar 加權與等級閾值自訂，含 API、DB schema、計分引擎整合與前端 Tab UI

### Modified Capabilities
- `saq-scoring-engine`: 計分引擎新增 `pillar_weights` 與 `grade_thresholds` 參數，優先使用傳入值而非僅依賴 ScoringModel 查詢

## Impact

- **esgchain-api**：`assessment_series` migration、`AssessmentSeriesService`、`AssessmentSeriesController`、`DispatchSaqScoringJob`（新增 eager load series + 傳參）
- **esgchain-ai**：`scoring_service.py` 的 `calculate_saq_score()`、`celery/saq-scoring` endpoint schema
- **esgchain-web**：`SeriesDetailView.vue` 新增計分設定 Tab、`assessmentSeriesApi` 新增端點
