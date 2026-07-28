## Why

生產批號詳情頁的「出口市場審查」分頁目前只顯示攤平過的一句話 finding（例如「市場必備文件　缺失/過期：ORIGIN_CERT、CMRT」），使用者看到問題卻無法知道「缺失」跟「過期」分別是哪些文件、也沒有任何連結可以直接前往補件。底層 `MarketComplianceChecker::check()` 其實已經算出每份文件各自的 `doc_type`/`status`/`expires_at`/`supplier_id` 等結構化資訊，只是在 `BatchExportReviewService::checkMarketDocs()` 存檔前被壓成單一逗號字串，資訊在半路遺失。這次要把這份既有資料重新結構化保留下來並呈現，讓審查結果從「看到問題」變成「能直接處理」。

## What Changes

- `BatchExportReviewService::checkMarketDocs()` 不再把每份文件的狀態壓成一句逗號字串 finding；改成每份文件各自一筆 finding，帶 `doc_type`、細分後的 `status`（`missing`/`expiring_soon`/`expired`/`pass`）、`supplier_id`、`supplier_name`、`expires_at`。**BREAKING**：`batch_export_reviews.findings` 這個 JSON 欄位的 schema 改變（原本部分 finding 是聚合字串，改成逐筆結構化物件），任何直接讀取舊 `detail` 字串格式的地方（前端顯示、DDS 草稿組裝）需要跟著調整。
- 前端「出口市場審查」分頁（`ProductionBatchDetailView.vue`）：每筆缺件/過期 finding 顯示可點擊連結，導向對應供應商的合規文件上傳頁（`/compliance/suppliers/:id`，`SupplierComplianceDetailView.vue` 合規管理分頁），並用 `doc_type` 讓使用者知道要補哪一份文件。
- 缺失、即將到期、已過期三種狀態在畫面上要能視覺區分（不再全部塞進同一顆 `fail` 紅點）。

## Capabilities

### New Capabilities
- `export-market-review-findings`：出口市場審查結果的資料結構（逐份文件 finding）與前端呈現／補件導引行為。目前查過 `openspec/specs/` 下沒有任何既有 spec 涵蓋 `BatchExportReview`/`checkMarketDocs` 這塊邏輯，視為補寫遺漏的既有功能規格，非全新業務能力。

### Modified Capabilities
（無——沒有找到既有 spec 涵蓋這塊行為，故列在 New Capabilities 補寫）

## Impact

- **後端**：`esgchain-api/app/Services/ProductionBatch/BatchExportReviewService.php`（`checkMarketDocs()` 邏輯改寫）。不改 `MarketComplianceChecker`（資料源頭已經夠用，只是下游沒接住）。
- **前端**：`esgchain-web/src/views/compliance/ProductionBatchDetailView.vue`（review tab 的 finding 呈現方式）、`esgchain-web/src/api/modules/productionBatch.ts`（`BatchExportReview.findings` 型別要跟著改）。
- **資料庫**：`batch_export_reviews.findings`（JSON 欄位，既有資料的舊字串格式 finding 混雜新結構化 finding；不做資料回填遷移，舊資料只在被使用者重新執行審查後才會變成新格式，讀取端需能兼容兩種形狀直到自然汰換完畢）。
- **明確排除範圍**：審查歷史留存/稽核軌跡（`BatchExportReview::updateOrCreate()` 覆蓋式儲存維持不變，不新增版本歷史）、DDS 草稿真的產出 PDF/下載檔案（`ddsDraft()` 維持純 JSON 顯示，不做文件產出）。這兩項不在本次變更範圍內。
