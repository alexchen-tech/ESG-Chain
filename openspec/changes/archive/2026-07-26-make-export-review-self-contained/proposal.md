## Why

上一個變更（`add-export-review-queue-view`）把出口審查清單獨立成一個頁面，但點擊清單列後仍導回生產批號詳情頁的「出口市場審查」分頁處理，兩個入口實際上仍是同一份 UI 與邏輯。使用者進一步要求徹底分離：生產批號詳情頁不需要出口審查功能，出口審查頁面也不需要依賴生產批號詳情頁——出口審查頁面本身就要能透過既有 API 直接執行審查、產出合規文件與批次護照，不必跳轉。

## What Changes

- **BREAKING**：`ProductionBatchDetailView.vue` 移除「出口市場審查」分頁（含執行審查、DDS 草稿、批次護照 JSON 顯示），該頁面只保留「批號資訊」與「原料溯源」
- `ExportReviewsView.vue` 從純導覽清單，改為可展開列內面板（沿用既有 `production-batches/{batchId}/export-reviews`、`dds-draft`、`passport` API）直接在清單頁完成執行審查、刪除審查記錄、產出 DDS 草稿、查看批次護照 JSON，不再導向批號詳情頁
- 清單列點擊改為「展開/收合」該批號的審查面板，而非路由跳轉

## Capabilities

### New Capabilities
（無）

### Modified Capabilities
- `export-review-queue`: 清單頁從「唯讀導覽入口」改為「自帶審查操作面板」，不再依賴生產批號詳情頁的 UI

## Impact

- `esgchain-web/src/views/compliance/ProductionBatchDetailView.vue`：移除審查分頁與相關 data/methods
- `esgchain-web/src/views/compliance/ExportReviewsView.vue`：新增展開面板與審查操作（執行審查/刪除/DDS 草稿/批次護照）
- 後端 API 不變（`BatchExportReviewController` 既有的 index/store/destroy/ddsDraft/passport 端點原樣沿用，仍是 batch-scoped，只是呼叫方從批號詳情頁改成出口審查清單頁）
