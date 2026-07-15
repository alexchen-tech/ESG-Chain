## Why

六維風險熱圖目前只能依單一維度排序，無法過濾、無法橫向比較多家供應商、也無法看出分數隨時間的變化趨勢。永續長與法遵人員需要快速鎖定高風險子集、並排比較替代供應商、以及追蹤改善進度，才能驅動 CAP 決策。

## What Changes

- **新增篩選列（Filter bar）**：國家多選 + 風險等級篩選（全部 / 需關注 / 高風險）+ 需關注 toggle，純前端過濾，不改 API
- **新增多選比較浮層（Compare Panel）**：勾選最多 5 家供應商後，底部浮出比較列，顯示各維度分數長條圖 + 差距最大維度提示
- **新增歷史對比模式（History Diff）**：切換「最新 / 與 30 天前比較 / 與 90 天前比較」，維度格子顯示 delta 箭頭與差值；需後端 API 回傳指定天數前的 RA 快照
- **維度閾值高亮（Threshold Highlight）**：整合至「需關注」篩選，點擊維度標頭可高亮該維度低於閾值的列

## Capabilities

### New Capabilities

- `heatmap-filter-bar`: 國家 + 風險等級的前端過濾列，含需關注 toggle
- `heatmap-compare-panel`: 多選供應商底部浮層比較，最多 5 家，含差距摘要
- `heatmap-history-diff`: 熱圖歷史對比模式，支援 30/90 天前快照對比，含 delta 顯示

### Modified Capabilities

- `six-dim-risk-heatmap`: 熱圖 API 新增 `?before_days=30` 參數，回傳歷史 RA 快照供 diff 使用

## Impact

- **esgchain-web**：`SixDimHeatmapView.vue` 新增篩選、比較、歷史對比 UI
- **esgchain-api**：`RiskHeatmapController` 新增 `before_days` query param 分支
- **無資料庫異動**：使用現有 `risk_assessments` 資料
