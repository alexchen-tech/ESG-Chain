## 1. 全局 CSS

- [x] 1.1 在 `esgchain-web/src/assets/components.css` 新增 `.page-action-bar { display:flex; justify-content:flex-end; gap:8px; margin-bottom:12px; }`

## 2. 頂層列表頁：移出頁首按鈕

- [x] 2.1 `SuppliersView.vue` — 將「批次匯入」「新增供應商」按鈕移至 page-header 下方 `<div class="page-action-bar">`
- [x] 2.2 `SaqProjectsView.vue` — 將「建立問卷專案」按鈕移至 `<div class="page-action-bar">`
- [x] 2.3 `SeriesListView.vue` — 將「建立系列」按鈕移至 `<div class="page-action-bar">`
- [x] 2.4 `BuyerProductsView.vue` — 將「CSV 匯入」「新增產品」按鈕移至 `<div class="page-action-bar">`
- [x] 2.5 `CAPView.vue` — 將「新增 CAP」按鈕移至 `<div class="page-action-bar">`
- [x] 2.6 `ReportsView.vue` — 將「匯出」按鈕移至 `<div class="page-action-bar">`

## 3. 設定子頁：scoped tab-action-bar 改為全局 page-action-bar

- [x] 3.1 `MaterialItemsView.vue` — template 中 `tab-action-bar` 改為 `page-action-bar`；刪除 scoped style 中的 `.tab-action-bar` 定義
- [x] 3.2 `QuestionBankView.vue` — 同上
- [x] 3.3 `ScoringModelView.vue` — 同上

## 4. Docker 同步與驗證

- [x] 4.1 `docker cp` 所有修改的檔案至 esgchain-web 容器並 touch 觸發 HMR
- [x] 4.2 瀏覽器硬刷新（Cmd+Shift+R），逐頁確認按鈕出現在正確位置
- [x] 4.3 確認 page-header 右側無按鈕殘留
