## 1. 連往批號詳情頁

- [x] 1.1 清單批號欄位改為連往 `/compliance/production-batches/{id}` 的連結，新分頁開啟，不觸發列展開
- [x] 1.2 展開面板 header 新增「📝 前往補齊溯源資料」按鈕，同樣新分頁開啟

## 2. 覆蓋確認

- [x] 2.1 `runReview()` 新增：若該市場已有審查記錄，`confirm()` 提示會覆蓋舊結果才繼續

## 3. DDS 草稿市場一致性

- [x] 3.1 新增 `onPanelMarketChanged()`：切換市場時清空 `ddsOpen`/`ddsDraft`
- [x] 3.2 市場下拉綁定 `@change` 呼叫此方法

## 4. 部署與驗證

- [x] 4.1 `vue-tsc` 全專案型別檢查通過
- [x] 4.2 部署至 esgchain-web，觸發 HMR
