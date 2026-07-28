## Why

出口審查（`BatchExportReview`）目前只能從單一生產批號詳情頁的分頁裡查看，使用者要巡查所有待處理批號時必須逐一點開每個批號，沒有跨批號的彙總視角；完全沒有審查記錄的批號（最需要被巡查的對象）更是完全看不到。新增一個獨立的「出口審查」清單功能入口，讓採購商/永續團隊/法遵部門能一次看到所有批號的審查狀態並篩選、排查待辦事項。

## What Changes

- 新增跨批號的出口審查清單查詢端點：以 `ProductionBatch LEFT JOIN BatchExportReview` 查詢（確保完全沒有審查記錄的批號也會出現，視為「未審查」狀態），一併帶出產品名稱、供應商資訊；支援依市場（EU/US/UK/JP/GLOBAL）、狀態（pending/pass/warning/fail/未審查）、生產日期區間篩選，server-side pagination 每頁 20 筆
- 新增前端 `ExportReviewsView.vue` 列表頁，沿用既有 `.data-table` 樣式與篩選 UI 慣例
- 側邊欄新增「出口審查」選單項與對應路由；清單每列點擊後導回對應生產批號詳情頁既有的「出口審查」分頁區塊處理，不重複實作審查表單或審查邏輯
- 不新增/修改 `BatchExportReview` 資料表結構，不改變單一批號詳情頁既有的審查功能（新增/刪除審查記錄、dds-draft、passport 等既有端點與流程不變）

## Capabilities

### New Capabilities
- `export-review-queue`: 跨批號的出口審查清單查詢（含篩選、分頁）與對應前端列表頁

### Modified Capabilities

（無——不修改既有 `production-batch-management` 或市場合規檢核相關 requirement，純粹新增一個獨立的讀取彙總入口）

## Impact

- 新增後端 API：`GET /api/v1/export-reviews`（或等同路徑），新增 Controller method / Service method
- 新增前端頁面：`esgchain-web/src/views/.../ExportReviewsView.vue`
- 修改：`AppSidebar.vue`（新增選單項）、`router/index.ts`（新增路由）
- 不影響：`BatchExportReview` 資料表、既有 `BatchExportReviewController::index/store/destroy/ddsDraft/passport`、`ProductionBatchDetailView.vue` 既有審查分頁
