## 1. 後端：before_days 參數支援

- [x] 1.1 `RiskHeatmapController::index()`：讀取 `?before_days` query param，若有值則 validate（正整數 1–365，否則 422），並在 `$latestIds` subquery 加入 `WHERE assessed_at <= NOW() - INTERVAL $days DAY` 篩選
- [x] 1.2 同步更新 `routes/api.php` 或 Controller 層不需路由異動（GET 參數）；確認 before_days=0 或負數回傳 422

## 2. 前端：篩選列（Filter Bar）

- [x] 2.1 `SixDimHeatmapView.vue` 新增 `filterCountries`（Array）與 `filterRisk`（'all'|'critical'|'extreme'）data 屬性
- [x] 2.2 新增篩選列 UI：國家多選標籤（從 rawRows 動態萃取不重複 country_code）、風險等級三選一按鈕組、篩選計數顯示「X / N 家」
- [x] 2.3 新增 `filteredRows` computed，套用 filterCountries + filterRisk 過濾（extreme = 任意 ≥ 3 維度低於閾值），`sortedRows` 改為基於 `filteredRows` 排序
- [x] 2.4 「清除篩選」按鈕：僅在有篩選條件時顯示，點擊重置所有篩選

## 3. 前端：多選比較浮層（Compare Panel）

- [x] 3.1 新增 `compareMode`（Boolean）、`compareIds`（Array，上限 5）data 屬性；compareMode 切換時清空 compareIds
- [x] 3.2 比較模式啟用時，表格每列左側顯示 checkbox；勾選邏輯：超過 5 家時移除最舊的一筆並 toast 提示
- [x] 3.3 底部浮層 `ComparePanel`：fixed bottom，z-index 100；顯示條件 `compareMode && compareIds.length > 0`；包含關閉按鈕與「清除全部」
- [x] 3.4 ComparePanel 內容：每維度一行，並排各供應商分數長條（寬度 = score%，顏色沿用 dimCellClass）+ 分數數字；面板底部計算並顯示「差距最大維度：EX（差 N 分）」
- [x] 3.5 頁面右上角新增「比較模式」切換按鈕（`btn-secondary`，啟用時加 `active` class）

## 4. 前端：歷史對比模式（History Diff）

- [x] 4.1 新增 `diffMode`（'latest'|'30d'|'90d'）data 屬性與 `historyCache`（Object，key = before_days）
- [x] 4.2 頁面右上角新增顯示模式切換按鈕組：「最新」/ 「▲▼ 30 天」/ 「▲▼ 90 天」；切換時若 cache 無對應資料則呼叫 `riskApi.sixDimHeatmap({ before_days: N })`，存入 historyCache
- [x] 4.3 新增 `historyMap` computed：`{ supplierId: { dim_e1: delta, ..., dim_e6: delta } }`，delta = 當前分數 - 歷史分數（歷史無資料 → undefined）
- [x] 4.4 維度格子在 diffMode 非 'latest' 時，分數下方渲染 delta 徽章：`▲ +N`（綠）/ `▼ -N`（紅）/ `→`（灰）；無歷史資料的列顯示行尾「無歷史」淡字
- [x] 4.5 切換回「最新」模式時，delta 徽章消失，格子恢復正常

## 5. 樣式調整

- [x] 5.1 篩選列樣式：與現有 `dim-sort-bar` 同高，標籤使用 `--accent` 底色，統一間距
- [x] 5.2 ComparePanel 樣式：fixed bottom 0，高度 auto（最高 280px），backdrop blur，長條動畫（`transition: width 0.3s`）
- [x] 5.3 比較模式 checkbox 欄位：表格新增第一欄（寬 32px），非比較模式時 `v-show=false` 不佔空間
- [x] 5.4 delta 徽章樣式：font-size 10px，行內顯示於分數右下方
