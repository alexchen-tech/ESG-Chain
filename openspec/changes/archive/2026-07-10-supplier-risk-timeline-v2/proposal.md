## Why

供應商風險歷史 tab 目前採兩欄配對佈局（左：SAQ 計分卡 / 右：風險評估卡），當同一 SAQ 對應多筆 RA 時發生垂直錯位；且所有事件壓縮在同一頁面，無法直覺看出年度間的風險趨勢變化。隨著 Demo 資料跨越 2024–2026 三年，需要一個能支援「歷史比較」與「因果追溯」雙重閱讀的佈局。

## What Changes

- **移除**兩欄配對佈局（`tl-cols` / `tl-col-header-row` / 左右雙欄邏輯）
- **新增**頂部趨勢摘要列：各年度 E/S/G/GP 分數 + 三軸分數的橫向對比 table，附 ↑↓ delta 標記
- **重構**時間軸為單欄事件流：依年度分組，每張 RA 卡直接 embed 來源 SAQ 資訊（不再需要左欄 SAQ 卡）
- **保留** pending_saq 提示卡、CAP badge、三軸 chip 等現有元素
- **精簡** SAQ 計分事件：在時間流中以輕量標記呈現（grade + score），不重複顯示已 embed 在 RA 卡內的資訊

## Capabilities

### New Capabilities

- `supplier-risk-trend-summary`: 風險歷史頂部趨勢摘要列，以年度為單位橫向比較 E/S/G/GP 與三軸分數，含 delta 標記（↑↓）

### Modified Capabilities

- `supplier-risk-timeline`: 現有時間軸 API 與 UI 規格更新——事件流改為單欄年度分組；RA 卡需 embed 來源 SAQ 資訊（`linked_saq` 已在 API 中，前端調整展示方式）

## Impact

- `esgchain-web/src/views/suppliers/SupplierDetailView.vue`：風險歷史 tab 的時間軸 HTML + CSS + computed（`pairedRows` 廢棄，改為 `groupedTimeline`）
- `esgchain-api/app/Services/Suppliers/SupplierTimelineService.php`：需在 RA 事件中補上 `year` 欄位供前端分組；現有 `axis1_score / axis2_score / axis3_score` 已補齊（上一個 change 完成）
- 不影響後端 API endpoint、資料庫 schema、其他 tab
