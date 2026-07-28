## Context

`add-export-review-queue-view` 已建好跨批號清單與 `GET /api/v1/export-reviews`，清單列點擊目前 `$router.push('/compliance/production-batches/{id}?tab=review')` 導向批號詳情頁。使用者要求進一步拆乾淨：生產批號詳情頁不該有出口審查功能，出口審查頁面本身要能獨立完成審查操作。

既有 batch-scoped API（`BatchExportReviewController::index/store/destroy/ddsDraft/passport`，路由 `/production-batches/{batchId}/export-reviews`、`/dds-draft`、`/passport`）已足夠支撐這個需求，不需要新增或修改後端。

## Goals / Non-Goals

**Goals:**
- 出口審查頁面可直接對任一批號執行審查、刪除審查記錄、產出 DDS 草稿、查看批次護照 JSON，不必離開此頁面
- 生產批號詳情頁移除出口審查相關 UI 與邏輯，只保留批號資訊與原料溯源

**Non-Goals:**
- 不修改後端 API（既有 batch-scoped 端點的路徑、參數、回傳格式都不變）
- 不改變審查邏輯本身（`MarketComplianceChecker`、`BatchExportReviewService` 不動）
- 不做跨批號的「批次操作」（例如一次對多筆批號執行審查），仍是逐筆操作

## Decisions

**1. 清單列展開面板，而非路由跳轉或跳出 Modal**

點擊列 → 該列下方展開一塊面板（沿用原本批號詳情頁「出口市場審查」分頁的 markup：市場選擇、執行審查按鈕、審查卡片列表、DDS 草稿、批次護照 JSON），跟 `SuppliersView.vue` 目前用路由跳轉到詳情頁的模式不同，這裡刻意選展開面板：因為出口審查是「逐批號快速處理很多筆」的工作流程，跳轉頁面來回成本較高，展開面板可以在清單頁連續處理多筆。

**2. 面板內容與邏輯搬移，不重寫**

直接把 `ProductionBatchDetailView.vue` 裡「出口市場審查」分頁的 template 區塊與對應 data/methods（`exportReviews`/`reviewMarket`/`reviewing`/`ddsOpen`/`ddsLoading`/`ddsDraft`/`passport`/`passportLoading` 及 `loadReviews`/`runReview`/`removeReview`/`openDdsDraft`/`loadPassport`/`otherFindings`/`dppFindings`/`findingDotClass`/`docTypeLabel`）搬到 `ExportReviewsView.vue`，用 `Record<batchId, {...}>` 的形式讓每一列各自維護自己的展開狀態與資料（比照 `TradeGoodsView.vue` 用 `Record<string, T>` 管理多筆展開面板的既有寫法）。

**3. `ProductionBatchDetailView.vue` 的 `mounted()` 移除 query param 讀取**

上一版為了支援清單列跳轉而加的 `$route.query.tab` 讀取邏輯，因為清單列不再跳轉而變得沒有用途，一併移除，避免留下死程式碼。

## Risks / Trade-offs

- [風險] 清單頁一次展開多筆面板時，每筆各自獨立 fetch，可能造成多個並發請求 → 緩解：沿用既有「點擊才載入」的 lazy load 模式（比照原本 `switchTab` 內的 if 判斷），不會一次全部載入
- [風險] 移除生產批號詳情頁的審查分頁是 BREAKING 變更，任何外部書籤／文件連結到 `?tab=review` 會失效 → 緩解：目前只有這次剛加的清單頁會連過去，範圍內已一併修正；系統內部無其他連結依賴

## Migration Plan

1. `ExportReviewsView.vue` 新增展開面板：市場選擇、執行審查、刪除審查記錄、DDS 草稿、批次護照 JSON（沿用既有 API：`productionBatchApi.exportReviews/runExportReview/deleteExportReview/ddsDraft/passport`）
2. 清單列點擊行為改為展開/收合，移除 `$router.push` 導向詳情頁
3. `ProductionBatchDetailView.vue` 移除「出口市場審查」分頁：TABS 常數、template 區塊、對應 data/methods、query param 讀取邏輯
4. `vue-tsc` 全專案型別檢查通過
5. 部署後以真實資料驗證：在出口審查清單頁展開任一批號、執行審查、查看批次護照 JSON 皆正常運作；批號詳情頁確認只剩批號資訊與原料溯源兩個分頁

## Open Questions

（無）
