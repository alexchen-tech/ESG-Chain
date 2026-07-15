## Context

`SupplierDetailView.vue` 風險歷史頁的時間軸卡片目前有三種形態：
1. **評估週期卡**（SAQ 推導 RA）：分為「問卷評分」+「推導風險評估」兩個子區塊
2. **地緣事件 / 手動 RA 卡**：單一區塊，顯示六維分數
3. **SAQ-only 卡**：無對應 RA 時顯示，底部有「尚未推導風險評估」提示

問題在第一種形態：兩個子區塊都來自同一個 SAQ，六維分數完全相同，「推導風險評估」分隔行只多顯示一個日期（通常與問卷提交日相同），無附加資訊。

## Goals / Non-Goals

**Goals:**
- 評估週期卡合併為單一區塊：標頭列（問卷資訊 + CAP badge）+ 六維分數橫條
- 地緣事件卡與手動 RA 卡維持現狀
- 確認 SAQ-only 卡的「尚未推導風險評估」提示已無出現場景，移除該文字

**Non-Goals:**
- 不改變後端 timeline API 回傳資料結構
- 不改變 RA 記錄的建立邏輯
- 不調整趨勢表（trendRows）

## Decisions

**D1：六維橫條位置**
移至 `tl-period-saq` 區塊內，緊接問卷評分標頭之後。不需要額外 wrapper，沿用現有 `tl-six-dims-grid` class。
六維分數從 `ev.risk.six_dims` 讀取（RA 資料），保留與現有 groupedTimeline 邏輯一致。

**D2：CAP badge 位置**
從「推導風險評估」分隔行移至問卷評分標頭列右側（`margin-left:auto` 前插入），保持可見性。

**D3：移除分隔行條件**
整個 `.tl-period-divider`（含 `⚡ 推導風險評估` span）在 SAQ 推導 RA 情境下完全移除。地緣事件 / 手動 RA 的 `v-else-if` 分支維持不動。

**D4：SAQ-only 卡的「尚未推導風險評估」**
已透過 `SupplierTimelineService` 補上 `source_saq_id` 修正，正常情況下 SAQ-only 卡不再出現。保留卡片結構但移除底部提示文字，避免誤導。

## Risks / Trade-offs

- [風險] 若未來 SAQ 推導 RA 時六維分數被人工調整（RA ≠ SAQ），合併後無法對比差異 → 暫不處理，待有「RA 手動覆蓋」功能時再拆回雙區塊
- [風險] CAP badge 移位可能在標頭列過長時溢出 → 標頭已有 `flex` 佈局，`margin-left:auto` 前的元素自動壓縮，不影響
