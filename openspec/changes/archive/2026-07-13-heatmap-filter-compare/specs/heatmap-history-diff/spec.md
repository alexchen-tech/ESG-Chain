## ADDED Requirements

### Requirement: 熱圖歷史對比模式（History Diff）
`SixDimHeatmapView.vue` SHALL 支援歷史對比模式，切換後在各維度格子上疊加與歷史 RA 的差值（delta），讓使用者直觀看出分數改善或退步情況。

#### Scenario: 切換歷史對比模式
- **WHEN** 使用者點擊顯示模式切換：「最新」→「與 30 天前比較」→「與 90 天前比較」
- **THEN** 前端對 `GET /api/v1/risk/six-dim-heatmap?before_days=N` 發出請求（N = 30 或 90）
- **AND** 回應結構與一般熱圖相同，但僅包含 assessed_at ≤ 當前時間 - N 天 的 RA 資料（取各供應商最新）

#### Scenario: Delta 徽章顯示
- **WHEN** 歷史對比模式啟用且某供應商有歷史 RA
- **THEN** 各維度格子在分數下方顯示小徽章：`▲ +N`（進步）或 `▼ -N`（退步）或 `→`（無變化）
- **AND** 進步徽章顯示綠色，退步徽章顯示紅色，無變化徽章顯示灰色

#### Scenario: 無歷史資料的處理
- **WHEN** 某供應商在指定天數前無 RA 記錄
- **THEN** 該供應商各維度格子不顯示 delta 徽章，格子樣式不變
- **AND** 供應商列顯示淡色標示「無歷史資料」

#### Scenario: 回到最新模式
- **WHEN** 使用者切換回「最新」模式
- **THEN** 所有 delta 徽章消失，格子恢復僅顯示當前分數
- **AND** 歷史 API 資料快取於 session（同一頁面不重複請求相同 before_days）
