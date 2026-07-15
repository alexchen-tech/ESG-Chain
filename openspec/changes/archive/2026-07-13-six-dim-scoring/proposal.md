## Why

目前 SAQ 計分引擎以 E/S/G 三維度計算總分，但 AI 側 `six_dim_scoring` 已可輸出六個子維度（E1–E6）的細項分數，這些細項尚未被整合進總分計算，品牌採購商無法依各細維度進行差異化加權，導致評分結果缺乏針對特定供應鏈風險的靈活調適能力。

## What Changes

- `assessment_series` 新增三個欄位：
  - `dim_weights JSON`：六維度加權 `{"E1":0.25,"E2":0.15,"E3":0.20,"E4":0.15,"E5":0.10,"E6":0.15}`，合計須為 1.0
  - `dim_weights_source ENUM('default','custom')`：標記加權來源
  - `e4_objective_ratio DECIMAL(3,2) DEFAULT 0.40`：E4 混合計分用（本次建立欄位，E4 country risk 整合另立 change）
- 系統設定新增「維度預設加權」：admin 可在 Settings UI 設定全域預設 `dim_weights`，新建 Series 自動繼承
- Laravel 計分合成：總分 = Σ(dim_eN_score × dim_weights[N])，替代原有三維計算
- `saqs` 表新增六個維度分欄位 `dim_e1`–`dim_e6`（decimal，AI 回寫）
- Series 計分設定 Tab 新增「六維度加權」區塊，可繼承系統預設或自訂覆蓋

## Capabilities

### New Capabilities

- `dim-weights-settings`：系統層級六維度預設加權的 CRUD API 與 Settings UI Tab「維度預設加權」
- `six-dim-scoring`：assessment_series 的 dim_weights 設定、Laravel 端六維度總分合成、saqs dim_e1–e6 欄位回寫

### Modified Capabilities

- `series-scoring-config`：計分設定 Tab 新增六維度加權區段（繼承 or 自訂），並傳遞 dim_weights 給 AI scoring job
- `saq-scoring-engine`：總分計算邏輯由三維（E/S/G）改為六維加權合成；grade 仍從總分換算

## Impact

- **esgchain-api**：`assessment_series` migration；`DispatchSaqScoringJob` payload 新增 dim_weights；`SaqScoringResultService` 合成總分邏輯；Settings CRUD controller/service
- **esgchain-ai**：`six_dim_scoring` 回傳 dim_e1–e6 已實作，確認 payload 欄位名稱對齊；確認寫回 `saqs.dim_e1–dim_e6`
- **esgchain-web**：Settings UI 新增 Tab；Series 計分設定 Tab 新增六維度加權區塊
- **Database**：`assessment_series` 加三欄；`saqs` 加六欄；新增 `dim_weight_defaults` 系統設定表
