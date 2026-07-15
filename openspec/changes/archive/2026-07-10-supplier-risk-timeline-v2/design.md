## Context

`SupplierDetailView.vue` 風險歷史 tab 使用 `pairedRows` computed（SAQ ↔ RA 配對）渲染兩欄佈局。當同一 SAQ 對應多筆 RA（如重算、multi-framework 補分）時，第二筆 RA 無法配對左欄 SAQ 卡，造成視覺錯位。現有資料已跨越 2024–2026，但兩欄佈局缺乏年度分組與趨勢比較能力。

## Goals / Non-Goals

**Goals:**
- 消除錯位：移除兩欄配對邏輯，改為單欄時間流
- 年度分組：以「2024 / 2025 / 2026」分隔線區分事件
- 趨勢摘要列：在時間流上方新增橫向對比 table（各年最新 RA 的 E/S/G/GP + 三軸分數，加 ↑↓ delta）
- RA 卡 embed 來源 SAQ：`linked_saq` 已在 API 中，直接顯示在 RA 卡底部，不再需要左欄 SAQ 卡
- 保留 pending_saq 卡、CAP badge、三軸 chip

**Non-Goals:**
- 不修改後端 API endpoint 或 schema
- 不加 Sparkline 圖表元件（留給下一階段）
- 不影響其他 tab（永續績效、合規管理等）
- 不處理 SAQ-only 事件（已計分但無對應 RA 的 SAQ 僅在 RA 卡底部顯示，不單獨成卡）

## Decisions

### 決策 1：廢棄 `pairedRows`，改用 `groupedTimeline`

```
廢棄：
  pairedRows: Array<{ saq: any|null, ra: any|null }>
  → 兩欄配對，錯位根源

新增：
  groupedTimeline: Array<{
    year: number,
    events: Array<{ type: 'risk_assessment'|'saq_scored', ... }>
  }>
  → 依 date 的年份分組，RA 事件為主，SAQ-only 事件標記顯示
```

`groupedTimeline` 從 `timeline.events` 計算，按 `date` 降冪排序後依 `year` 聚合。

### 決策 2：趨勢摘要列取「各年最新一筆 RA」

趨勢比較只取每年最新一筆 RA（assessed_at 最大），避免同年多筆 RA 造成歧義。delta 標記（↑↓）比較相鄰年份的 score 差值：

```
trendRows = 每年最新 RA（年份降冪）
delta(dim) = trendRows[i][dim].score - trendRows[i+1][dim].score
```

### 決策 3：SAQ 計分事件的呈現方式

已計分 SAQ 若已 embed 在 RA 卡（`linked_saq`）中，不另外渲染為獨立卡片。
若 SAQ **無對應 RA**（scored 但未觸發 RA），則以輕量標記列（非卡片）顯示在同年事件中。

```
年份內事件排序：RA 卡（主）> SAQ-only 標記（次）> pending 卡（最頂）
```

### 決策 4：後端補 year 欄位

`SupplierTimelineService` 在每個事件 payload 補上 `year: int`（從 `date` 解析），讓前端免於字串操作。

## Risks / Trade-offs

| 風險 | 緩解 |
|------|------|
| `pairedRows` 被其他地方引用 | grep 確認僅 SupplierDetailView 使用，移除不影響其他元件 |
| 同年多筆 RA 趨勢取最新，可能遮蔽中間惡化 | 時間流仍完整顯示所有 RA，趨勢列只是摘要 |
| SAQ-only 事件消失 | 改為輕量標記列，資訊保留但不佔主視覺 |
