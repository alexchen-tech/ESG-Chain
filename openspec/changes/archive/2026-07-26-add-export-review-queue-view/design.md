## Context

`BatchExportReview`（`production_batch_id`/`market`/`status`/`findings`/`reviewed_at`）已存在，目前只透過 batch-scoped 端點讀寫（`GET/POST/DELETE /production-batches/{batchId}/export-reviews`），對應 `ProductionBatchDetailView.vue` 內的分頁區塊。使用者要跨批號巡查審查狀態時完全沒有入口，且完全沒有審查記錄的批號（最需要巡查的對象）在現有查詢方式下不可見。

分頁慣例參考 `CustomerController::index()`：`$paginator->items()` + `pagination: {total, per_page, current_page, last_page}`，service 層回傳 Laravel paginator。

## Goals / Non-Goals

**Goals:**
- 新增一個跨批號的出口審查清單查詢，讓「未審查」批號與已審查批號同時可見、可篩選
- 沿用既有分頁慣例（20 筆/頁）與既有 `.data-table` 前端樣式
- 清單只做導覽入口，不重複實作審查邏輯（新增/刪除審查記錄仍在批號詳情頁完成）

**Non-Goals:**
- 不修改 `BatchExportReview` 資料表結構
- 不在清單頁本身提供新增/編輯審查記錄的操作（避免兩處維護同一份審查邏輯）
- 不處理批次審查（bulk review）操作，僅讀取彙總

## Decisions

**1. 查詢方式：以 `ProductionBatch` 為查詢主體，`LEFT JOIN`／`with()` 帶出最新一筆 `BatchExportReview`（依 market 篩選時對應該 market 的審查記錄）**

理由：若以 `BatchExportReview` 為主體查詢，完全沒有審查記錄的批號就不會出現在結果集裡——但這些正是最需要被巡查的「待審查」對象。以 `ProductionBatch` 為主體、審查記錄用 `hasMany`/子查詢帶出，可以让「未審查」自然成為一種篩選得到的狀態（`status = null` → 前端顯示「未審查」）。

**2. 篩選邏輯：「未審查」不是 `batch_export_reviews.status` 的合法值，而是「該批號在指定市場沒有任何審查記錄」的衍生狀態**

`status` 篩選參數允許 `pending|pass|warning|fail|unreviewed`，其中 `unreviewed` 轉換成 SQL `whereDoesntHave('exportReviews', fn($q) => $q->where('market', $market))`（若有指定 market）或 `whereDoesntHave('exportReviews')`（若未指定 market，代表任一市場皆無審查記錄）。

**3. 新增 Service 方法，不覆用 `BatchExportReviewService`**

`BatchExportReviewService` 現有職責是「單一批號的審查生成/驗證」（`ddsDraft()`/`checkProcessLocation()` 等），跟「跨批號清單查詢」職責不同。新增 `ExportReviewQueueService::list(array $filters): LengthAwarePaginator`，放在同一個 `App\Services\ProductionBatch` 命名空間下，保持職責分離但方便未來共用（例如清單頁未來若要顯示 checkDppFields 等摘要，可以互相呼叫）。

**4. Controller：新增獨立 Controller `ExportReviewQueueController`，不擴充 `BatchExportReviewController`**

`BatchExportReviewController` 現有方法都是 batch-scoped（`{batchId}` 在路由裡），新增一個非 batch-scoped 的 `index()` 混進去會讓路由與職責混亂，比照「一個 controller 對應一組職責」的慣例另開一個。

**5. 前端路由與選單分組**

新頁面 `ExportReviewsView.vue` 掛在跟「生產批號」同一個功能群組下（`compliance`），路徑取 `/compliance/export-reviews`，比照 CLAUDE.md「側邊欄功能項目改掛到不同功能群組時要同步修改路由 path 前綴」的規則，選單與路由分組保持一致。清單列點擊導向 `/compliance/production-batches/{id}`（既有批號詳情頁路由），並可帶 query param（如 `?tab=export-review`）讓詳情頁自動切到審查分頁（需確認 `ProductionBatchDetailView.vue` 現有 tab 切換機制是否已支援讀取 query param，若無則於此次一併補上，屬於前端小改動不需另外納入 capability）。

## Risks / Trade-offs

- [風險] `whereDoesntHave` 對大量批號可能有效能疑慮 → 緩解：批號量級目前在千筆等級（demo 環境），先用最直接的查詢方式；若未來效能出問題再考慮加索引或 materialized 彙總表
- [風險] 清單頁與批號詳情頁的審查狀態顯示邏輯若沒共用同一份 status 計算規則，容易日後不一致 → 緩解：狀態標籤沿用 `BatchExportReview::STATUSES` 常數與既有 `STATUS_LABELS`，不在清單頁另外定義一份

## Migration Plan

1. 後端新增 `ExportReviewQueueService::list()`（`ProductionBatch` 為主體查詢，帶最新 `BatchExportReview`）
2. 新增 `ExportReviewQueueController::index()`，路由 `GET /api/v1/export-reviews`
3. 前端新增 `ExportReviewsView.vue`（server-side pagination 20 筆/頁，篩選 market/status/日期區間）
4. `AppSidebar.vue` 新增「出口審查」選單項；`router/index.ts` 新增對應路由
5. 清單列點擊導向批號詳情頁並定位到既有審查分頁（視現有 tab 機制決定是否需要小幅補強 query param 支援）
6. 以真實資料驗證：至少一筆「有審查記錄」與一筆「完全無審查記錄」的批號都能在清單中正確顯示與篩選

## Open Questions

- `ProductionBatchDetailView.vue` 現有 tab 切換是否已支援用 query param 直接定位到「出口審查」分頁，若無需要額外補一小段邏輯——留待實作時確認現況再決定
