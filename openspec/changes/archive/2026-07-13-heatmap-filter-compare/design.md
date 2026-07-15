## Context

`SixDimHeatmapView.vue` 全量載入 27 筆 RA（`GET /api/v1/risk/six-dim-heatmap`），目前只有維度排序，無法縮小供應商母集、橫向比較、或看歷史差異。所有新增功能均以現有資料為基礎。

## Goals / Non-Goals

**Goals:**
- 純前端篩選（國家、風險等級），無需新 API
- 底部浮層多選比較（最多 5 家），不遮擋熱圖表格
- 歷史對比：`?before_days=N` 讓後端回傳 N 天前最近一筆 RA，前端計算 delta
- 所有新功能保持 Options API 風格

**Non-Goals:**
- 維度自訂閾值篩選（「E1 < 60」組合條件）— 留給後續
- 比較結果匯出 PDF/CSV
- Server-side 篩選（資料量不超過 100 筆，前端過濾足夠）

## Decisions

**D1：歷史對比使用獨立 API 參數，不擴充現有端點回傳**

`GET /api/v1/risk/six-dim-heatmap?before_days=30` 回傳同結構但限制在 `assessed_at <= NOW()-30d` 的資料。前端各 loadData() 兩次（latest + historical），merge 出 delta map `{ supplier_id → { E1: +5, E2: -3, ... } }`。

替代方案考慮：在同一端點回傳 prev_dims 欄位 → 複雜且 payload 翻倍，不採用。

**D2：比較浮層使用絕對定位 fixed 底部，不用 router**

比較是暫態操作，不需要 URL 狀態。fixed bottom panel，z-index 高於表格，checkbox 勾選後即時更新。最多 5 家：超過時新勾選的取代最舊勾選。

**D3：篩選 + 比較 + 歷史對比的資料流**

```
rawRows（API 回傳）
  ↓ filteredRows（computed：國家 + 風險等級）
    ↓ sortedRows（computed：維度排序）
      ↓ 表格渲染（加 delta overlay）

compareRows（selectedRows 子集，最多 5）
  ↓ 底部 ComparePanel
```

歷史對比 delta：`historyMap = { supplierId: { dim_e1: -3, dim_e2: +5, ... } }`，在 dimCellClass 中加入 delta badge。

**D4：後端 before_days 分支**

`RiskHeatmapController::index()` 讀取 `?before_days` 參數，若有值則 `whereDate('assessed_at', '<=', now()->subDays($days))` 篩選後取最新。複用現有邏輯，無 migration。

## Risks / Trade-offs

- **30天內無舊 RA** → delta 顯示「無歷史資料」，格子不加 delta badge（graceful degradation）
- **比較 5 家上限** → 超過視覺擠壓嚴重，fixed 上限並以 toast 提示
- **前端篩選後比較面板的勾選** → 若篩選後某家被過濾掉，compare panel 仍保留該供應商（來自 rawRows），並在面板中標示「已隱藏於篩選」
