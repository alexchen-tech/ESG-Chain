## Why

ESG·Chain 前端各頁面的主要行動按鈕（新增、匯入、匯出）目前有兩種位置：多數頁面將按鈕放在 `page-header` 的 flex 右側，少數設定子頁則將按鈕獨立放在 `tab-action-bar` 中。這種不一致導致視覺節奏破碎，且在視窗較窄時按鈕可能被標題文字壓縮。統一為獨立 action bar 可讓所有頁面的操作入口位置可預期、樣式一致。

## What Changes

- 將 `page-action-bar` 樣式移至全局 `components.css`（取代各頁 scoped 的 `tab-action-bar`）
- 以下 6 個頁面將頁首按鈕移至 `page-header` 下方獨立的 `<div class="page-action-bar">`：
  - `SuppliersView` — 批次匯入、新增供應商
  - `SaqProjectsView` — 建立問卷專案
  - `SeriesListView` — 建立系列
  - `BuyerProductsView` — CSV 匯入、新增產品
  - `CAPView` — 新增 CAP
  - `ReportsView` — 匯出
- 已完成的設定子頁（MaterialItemsView、QuestionBankView、ScoringModelView）將 scoped `tab-action-bar` 改為全局 `page-action-bar`
- TagLibraryView 確認現況（無頁首按鈕），不需調整

## Capabilities

### New Capabilities

- `page-action-bar`: 全局 CSS class，定義頁面主要行動按鈕列的版面規則（flex, justify-end, gap, margin）

### Modified Capabilities

（無 spec 層級的行為變更，僅 UI 版面調整）

## Impact

- **前端**：`esgchain-web/src/assets/components.css`（新增 `.page-action-bar`）；上述 9 個 Vue 檔案的 template 與 style scoped 區塊
- **API / 後端**：無影響
- **使用者體驗**：所有主頁面的 CTA 按鈕統一出現在標題列下方同一位置
