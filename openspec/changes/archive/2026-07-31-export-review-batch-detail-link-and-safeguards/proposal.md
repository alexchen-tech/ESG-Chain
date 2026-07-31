## Why

`make-export-review-self-contained` 刻意讓出口審查頁面自成一體、不依賴批號詳情頁完成審查動作。但後續 `merge-supply-chain-compliance-origin-cards` 把原料溯源／實際供應商確認的豐富編輯體驗都做進了批號詳情頁「供應鏈合規」分頁，出口審查頁面卻完全沒有連回去的路徑——使用者看到「尚無原料溯源資料，DDS 佐證力不足」時無處可補。另外盤點到兩個次要缺口：重跑審查會直接覆蓋舊結果且無確認提示，切換市場後舊的 DDS 草稿會誤導使用者以為是新市場的內容。

## What Changes

- 出口審查清單的批號欄位、展開面板都新增連往生產批號詳情頁的連結（開新分頁，不打斷審查頁面的操作狀態）
- 「執行審查」若該市場已有審查結果，改為需要使用者確認才會覆蓋（提示不會保留歷史紀錄）
- 展開面板切換市場時，自動收起先前市場的 DDS 草稿，避免顯示對不上目前選定市場的舊內容

## Capabilities

### Modified Capabilities
- `export-review-queue`：新增「導向批號詳情頁補資料」與「重跑審查覆蓋確認」兩項行為，仍不改變「清單自帶審查操作面板」的核心設計（不需要跳頁也能完成審查，跳頁是額外選項而非必要路徑）

## Impact

- 前端：`ExportReviewsView.vue`（新增批號詳情頁連結、`runReview()` 覆蓋確認、`onPanelMarketChanged()` 新增）
- 不影響：後端 API、`ProductionBatchDetailView.vue`
