## Why

現行業務觸發邏輯（Dashboard 高風險 KPI、CAP 自動生成、供應商替代推薦、法規合規檢核）皆依賴 axis1/axis2/axis3 三軸彙整分數，而非六維原始分（dim_e1–e6）。三軸折疊會掩蓋維度內部的極端不均（如 E1=100 / E2=30 折疊後 axis1=15 顯示 Very Low，但 E2 碳排揭露已嚴重不足），導致高風險誤判與 CAP 觸發方向不精確。六維模型已落地，應同步將業務觸發邏輯遷移至六維，釋放完整的診斷精度。

## What Changes

- **Dashboard 高風險供應商 KPI**：改用六維各自設閾值（任一維度合規分低於閾值即標記高風險），取代 `axis1/2/3 ≥ 60` 的統一判斷
- **CAP 自動生成觸發**：`triggered_by_axis` 從三選一（axis1/axis2/axis3）擴充為六選一（dim_e1–e6），並依觸發維度帶入對應矯正模板
- **供應商替代推薦**：評分模型改為 `total_score × 0.5 + 六維加權分 × 0.5`，並增加「最差維度不得低於最低合格線」的硬性過濾
- **法規合規檢核 `has_data_gap`**：從 `axis1 === null` 改為 `dim_e6 === null AND regulations 非空`，語意直接對應 E6 法規準備狀態
- axis1/axis2/axis3 欄位**保留**（前端 SupplierController 仍消費），僅調整業務觸發邏輯

## Capabilities

### New Capabilities

- `six-dim-risk-thresholds`: 六維度各別風險閾值設定與高風險判定規則（供 Dashboard KPI 與 CAP 觸發使用）

### Modified Capabilities

- `cap-auto-generation`: `triggered_by_axis` 欄位由 3 值擴充為 6 維，觸發判斷改用 dim_eN 合規分
- `supplier-replacement-recommendation`: 評分模型加入六維加權分與最差維度硬性過濾
- `market-compliance-rules`: `has_data_gap` 判斷改用 E6 + regulations，移除對 axis1 的依賴

## Impact

- **esgchain-api**：`CapAutoGenerationService`、`DashboardService`、`SupplierReplacementController`、`MarketComplianceChecker`
- **esgchain-web**：Dashboard 高風險 KPI 組件的閾值邏輯；CAP 觸發標籤顯示
- **DB Migration**：`cap_actions.triggered_by_axis` 欄位型別調整（ENUM 擴充或改 VARCHAR）
- **無 breaking API change**：axis1/2/3 欄位保留，下游前端無需修改
