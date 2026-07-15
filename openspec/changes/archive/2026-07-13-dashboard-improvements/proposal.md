## Why

主儀表板存在四個已確認問題：sustain 角色的 EsgScores widget 資料從未載入（bug）、永續風險概覽頁面無側欄入口（死路頁面）、高風險供應商 KPI 使用舊的 probability×impact 計算而非現行三軸模型（定義不一致）、analyst 角色沒有對應的 KPI 卡定義（fall into admin 卡組）。這些問題影響各角色的日常使用體驗與資料可信度。

## What Changes

- **修 Bug**：DashboardView `loadData()` 補上 esgScores API call，讓 sustain 角色的 ESG 分數 widget 正常顯示
- **加側欄入口**：`/dashboard/sustainability-risk`（永續風險概覽）加入 AppSidebar 「風險稽核」子選單
- **統一高風險定義**：DashboardService 「高風險供應商」KPI 改以三軸 `axis1/2/3_level IN ('high','extreme')` 計算，取代舊的 `probability × impact ≥ 15`
- **補 analyst KPI 卡**：DashboardService 加入 `analyst` 角色的 match case，顯示 SAQ 待審核、高風險供應商兩張卡

## Capabilities

### New Capabilities

（無新能力，均為既有儀表板功能的修正與補完）

### Modified Capabilities

- `dashboard-role-widgets`：sustain EsgScores 補資料載入；analyst 角色加入 KPI 卡定義；高風險供應商定義改為三軸 level；永續風險概覽加側欄入口

## Impact

- **前端**：`AppSidebar.vue`（加選單項目）、`DashboardView.vue`（補 esgScores API call 與 analyst 角色 widget）
- **後端**：`DashboardService.php`（`getSummary()` 改高風險 query、補 analyst case）
- **無 DB migration**：不新增欄位，純邏輯修正
