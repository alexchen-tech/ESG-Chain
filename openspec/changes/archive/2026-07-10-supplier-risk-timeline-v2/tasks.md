## 1. Backend：SupplierTimelineService 補 year 欄位

- [x] 1.1 在 `SupplierTimelineService::getEvents()` 中，每個事件 payload 補上 `year: (int) Carbon::parse($date)->year`

## 2. Frontend：computed 重構

- [x] 2.1 新增 `groupedTimeline` computed：從 `timeline.events` 依 `year` 聚合，每組含 `year` 與 `events` 陣列，年份降冪
- [x] 2.2 新增 `trendRows` computed：取每年 assessed_at 最大的 RA 事件，年份降冪，含 delta 計算（與前一年 score 差值）
- [x] 2.3 廢棄 `pairedRows` computed（保留程式碼但不再使用，待確認後刪除）

## 3. Frontend：趨勢摘要列 UI

- [x] 3.1 在風險歷史 tab 的時間軸上方新增 `risk-trend-table`：表頭為「年份 / E / S / G / GP / 軸1 / 軸2 / 軸3」，依 `trendRows` 渲染
- [x] 3.2 delta 標記：分數上升顯示綠色 `↑+N`，下降顯示紅色 `↓-N`，持平不顯示
- [x] 3.3 三軸欄位條件顯示：無資料時顯示「—」
- [x] 3.4 加入 `risk-trend-table` CSS（與既有設計系統一致）

## 4. Frontend：單欄事件流 UI

- [x] 4.1 移除兩欄佈局 HTML（`tl-cols`、`tl-col-header-row`、左右雙欄 div）
- [x] 4.2 以 `v-for="group in groupedTimeline"` 渲染年度分組，每組顯示年份分隔標題
- [x] 4.3 RA 卡：維持現有維度 bar + level badge + 三軸 chip + linked_saq embed，改為全寬單欄
- [x] 4.4 SAQ-only 事件（`linked_ra === null`）：渲染為輕量單行標記（grade chip + score + 日期 + 查看連結）
- [x] 4.5 pending_saq 卡維持現有設計，移到年份分組外（最頂端）
- [x] 4.6 更新 CSS：移除兩欄相關樣式（`.tl-cols`、`.tl-col-header-row`、`.tl-cell-empty`、`.tl-saq-compact` 等），新增 `.tl-year-group`、`.tl-year-divider`、`.tl-saq-inline` 樣式

## 5. 驗證

- [x] 5.1 確認同一 SAQ 對應 2 筆 RA（宏遠 source_saq_id=17273b6a 情境）不再錯位
- [x] 5.2 確認 2024 / 2025 / 2026 年度分組與趨勢摘要列顯示正確
- [x] 5.3 確認 CAP badge（2024 RA 有 2 CAPs）正常顯示
- [x] 5.4 確認 pending_saq 卡仍顯示在頂端
