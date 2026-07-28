## 1. ExportReviewsView.vue：展開面板

- [x] 1.1 清單列點擊行為改為展開/收合（移除 `$router.push` 導向詳情頁），用 `Record<batchId, {...}>` 管理每筆各自的展開狀態與資料
- [x] 1.2 搬移「出口市場審查」面板 template（市場選擇、執行審查、審查卡片列表、findings 顯示）
- [x] 1.3 搬移批次護照 JSON 顯示區塊（此頁面獨立呼叫 `passport` API，與批號詳情頁的批次護照資料互不共用）
- [x] 1.4 搬移 DDS 草稿顯示區塊
- [x] 1.5 搬移對應方法：loadReviews/runReview/removeReview/openDdsDraft/loadPassport/otherFindings/dppFindings/findingDotClass/docTypeLabel，改為以 batchId 為 key 操作對應的 Record
- [x] 1.6 執行審查成功後，同步更新該列在清單資料（`items`）裡的 `status`/`market`/`reviewed_at`，不需整頁重新查詢

## 2. ProductionBatchDetailView.vue：移除審查功能

- [x] 2.1 TABS 常數移除「出口市場審查」項目
- [x] 2.2 移除 template 裡的審查分頁區塊（含審查卡片、批次護照原始 JSON 顯示按鈕、DDS 草稿顯示）
- [x] 2.3 移除審查相關 data（exportReviews/reviewsLoading/reviewMarket/reviewing/ddsOpen/ddsLoading/ddsDraft）與 methods（loadReviews/runReview/removeReview/openDdsDraft/otherFindings/dppFindings/findingDotClass）——**發現 `passport`/`passportLoading`/`loadPassport()`/`docTypeLabel()` 同時被「批號資訊」分頁拿來渲染碳足跡/循環經濟/包材/有害物質/合規文件等結構化資料，並非審查分頁專屬，予以保留**
- [x] 2.4 移除 `mounted()` 裡讀取 `$route.query.tab` 的邏輯（不再有跳轉需求）
- [x] 2.5 確認 `switchTab()` 不再引用已移除的 review 邏輯

## 3. 驗證與部署

- [x] 3.1 `vue-tsc` 全專案型別檢查通過
- [x] 3.2 Vue 檔案同步至 esgchain-web，觸發 HMR
- [x] 3.3 以真實資料驗證：出口審查清單頁展開任一批號、執行審查、查看批次護照 JSON 皆正常；生產批號詳情頁確認只剩批號資訊與原料溯源
