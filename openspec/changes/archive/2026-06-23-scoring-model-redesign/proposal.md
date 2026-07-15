## Why

現有「計分模型管理」頁面（`ScoringModelView.vue`）以 SASB 產業代碼索引 E/S/G 加權與等級閾值，定位模糊且有以下問題：

1. **框架覆蓋不完整**：只有 ESG 框架有 weight_e/s/g，ISO20400 / ISO26000 / Geo-Risk 的 pillar 加權寫死為等權平均，無從管理。
2. **使用者找不到設定入口**：品牌客戶透過 Series 計分設定 Tab 自訂加權，但初始預設值來自等權平均，沒有業務意義的起始點。
3. **SASB 的實際用途未實現**：SASB 產業代碼存在於供應商主檔，但從未被用來驅動 ESG 問卷的必填邏輯。

本次重新設計將「計分模型管理」頁轉型為：
- **框架預設 Pillar 加權**：管理所有框架的系統預設比例，作為 Series 計分設定的初始值
- **SASB 必調題目設定**：管理各 SASB 產業對應的 ESG L3 topic 必填清單，驅動供應商問卷的必填標記

原有 ScoringModel（PostgreSQL，AI service）的等級閾值功能退場，閾值由 Series 計分設定直接管理。

## What Changes

### 新增
- MySQL 表 `framework_default_weights`：框架 → pillar slug → 預設比例
- MySQL 表 `sasb_required_topics`：SASB code → ESG L3 tag_slug 多對多
- Laravel CRUD API for 兩張新表
- 重新設計的 `ScoringModelView.vue`：兩個區塊（框架預設加權 + SASB 必調設定）

### 修改
- `AssessmentSeriesService::getScoringConfig()`：預設值從 `framework_default_weights` 讀取，取代等權平均
- 供應商 Portal SAQ 填答：標記 `is_sasb_required` 題目（由 `sasb_required_topics` 驅動）

### 退場
- `ScoringModel`（PostgreSQL，AI service）：不再查詢此表的 pillar 加權；等級閾值改由 Series 自行管理

## Capabilities

### New Capabilities
- `framework-default-weights`: 框架層級的 pillar 預設加權管理，CRUD API + 前端管理 UI
- `sasb-required-topics`: SASB 產業 → ESG L3 topic 必調設定，CRUD API + 前端管理 UI + Portal 必填標記

### Modified Capabilities
- `series-scoring-config`: `getScoringConfig()` 改從 `framework_default_weights` 讀取初始預設值

## Impact

- **esgchain-api**：新增 migration、Model、Service、Controller、routes；修改 `AssessmentSeriesService`
- **esgchain-web**：重寫 `ScoringModelView.vue`；修改 `SeriesDetailView.vue` 的預設值來源；供應商 Portal 填答頁標記必調題目
- **esgchain-ai**：移除 `_get_scoring_model_sync()` 對 pillar 加權的依賴（等級閾值 fallback 保留 DEFAULT_THRESHOLDS）
