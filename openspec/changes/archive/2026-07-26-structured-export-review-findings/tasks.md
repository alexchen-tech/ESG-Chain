## 1. 後端：資料源頭補上 supplier_id

- [x] 1.1 `MarketComplianceChecker::check()` 的 `results[]` 補上 `supplier_id`（`$doc?->supplier_id`），跟既有 `supplier_name` 並列

## 2. 後端：checkMarketDocs() 改成逐份文件 finding

- [x] 2.1 `BatchExportReviewService::checkMarketDocs()` 改寫：全部必備文件皆 `valid` 時維持現行單一摘要 finding（`market_docs`／「必備文件齊備」）；否則對每一筆 `status` 為 `missing`/`expiring_soon`/`expired` 的文件各自產生一筆 finding，`check` 用 `market_doc:{doc_type}`，並帶上 `doc_type`/`status`/`expires_at`/`supplier_id`/`supplier_name` 欄位（`label`/`detail` 沿用現有 `finding()` helper 的可讀文字）
- [x] 2.2 確認 `finding()` helper 或呼叫端能附加額外欄位（不只 `check`/`label`/`status`/`detail`），需要的話擴充該 helper 簽章或改成直接組陣列

## 3. 前端：型別與 API 模組

- [x] 3.1 `esgchain-web/src/api/modules/productionBatch.ts` 的 `BatchExportReview.findings` 型別加上可選欄位 `doc_type?`/`supplier_id?`/`supplier_name?`/`expires_at?`，並允許 `status` 涵蓋 `missing`/`expiring_soon`/`expired`/`valid`（沿用既有 pass/warning/fail 之外的細分值）

## 4. 前端：出口市場審查分頁呈現

- [x] 4.1 `ProductionBatchDetailView.vue` review tab：finding 渲染邏輯改成依 `f.doc_type` 是否存在判斷新舊格式（新格式才有逐筆補件連結，舊格式維持現有純文字顯示，向下相容既有資料）
- [x] 4.2 新格式 finding 且帶 `supplier_id` 時，顯示連到 `/compliance/suppliers/{supplier_id}?tab=compliance&doc_type={doc_type}` 的可點擊連結；缺 `supplier_id` 時只顯示文字
- [x] 4.3 `missing`/`expiring_soon`/`expired` 三種狀態的 `finding-dot` 樣式互相區分（現有 CSS 只有 `finding-dot--pass/warning/fail`，需新增對應樣式或做狀態映射）

## 5. 驗證

- [x] 5.1 curl 直接呼叫 `POST production-batches/{id}/export-reviews` 對一筆已知有缺件的批次執行審查，確認回傳 `findings` 是逐筆結構化資料而非壓平字串
- [x] 5.2 確認全部文件皆合規的市場審查，findings 仍只回傳一筆摘要 finding（不逐筆列出已合規項目）
- [x] 5.3 `vue-tsc` 全專案型別檢查零新增錯誤
- [x] 5.4 用真實有缺件記錄的批次（如 LOT-2607-018）在畫面上確認缺件連結可正確導到對應供應商合規頁面，且三種問題狀態視覺可區分
- [x] 5.5 確認舊格式（尚未重新執行過審查）的既有審查記錄在新前端上仍能正常顯示，不報錯
