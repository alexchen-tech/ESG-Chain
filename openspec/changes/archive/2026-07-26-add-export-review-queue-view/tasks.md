## 1. 後端：跨批號查詢服務

- [x] 1.1 新增 `App\Services\ProductionBatch\ExportReviewQueueService::list(array $filters): LengthAwarePaginator`：以 `ProductionBatch` 為主體查詢，`with()` 帶最新一筆對應 market 的 `BatchExportReview`
- [x] 1.2 實作 `status=unreviewed` 篩選（`whereDoesntHave('exportReviews', ...)`，視是否指定 market 決定條件）
- [x] 1.3 實作 `market`/`status`（pending/pass/warning/fail/unreviewed）/生產日期區間篩選
- [x] 1.4 分頁：預設 `per_page=20`，回傳 Laravel paginator

## 2. 後端：Controller 與路由

- [x] 2.1 新增 `App\Http\Controllers\Api\ProductionBatch\ExportReviewQueueController::index(Request $request): JsonResponse`
- [x] 2.2 回傳格式比照 `CustomerController::index()` 慣例（`data` + `pagination: {total, per_page, current_page, last_page}`）
- [x] 2.3 `routes/api.php` 新增 `GET /api/v1/export-reviews`（curl 驗證 market/status/unreviewed 篩選與分頁皆正確）

## 3. 前端：清單頁

- [x] 3.1 擴充既有 `productionBatch.ts`：新增 `ExportReviewQueueItem`/`Pagination` 型別與 `exportReviewQueueApi.list()`
- [x] 3.2 新增 `ExportReviewsView.vue`：server-side pagination（20 筆/頁）、market/status/日期區間篩選 UI，沿用既有 `.data-table`/`.pagination`/`.filter-bar` 樣式
- [x] 3.3 清單每列點擊導向 `production-batches/{id}?tab=review`；`ProductionBatchDetailView.vue` 原本不支援 query param 定位分頁，已補上（`mounted()` 讀取 `$route.query.tab`）

## 4. 前端：選單與路由

- [x] 4.1 `AppSidebar.vue` 新增「出口審查」選單項（掛在既有「商品合規管理」群組下）
- [x] 4.2 `router/index.ts` 新增 `/compliance/export-reviews` 路由，與選單分組前綴一致
- [x] 4.3 `vue-tsc` 全專案型別檢查通過

## 5. 部署與驗證

- [x] 5.1 Laravel 檔案同步至 esgchain-api 與 esgchain-queue-worker，restart + config:cache + route:cache
- [x] 5.2 Vue 檔案同步至 esgchain-web，觸發 HMR
- [x] 5.3 以真實資料驗證：curl 確認「未審查」批號與「market=EU status=fail」批號皆正確顯示、分頁 page=2 正確回傳第 21-40 筆；`vue-tsc` 全專案通過
