## Why

E4（地緣風險）維度目前純靠供應商自填 SAQ 問卷，容易受主觀填答影響而失真。引入第三方客觀的國家風險評等作為 E4 混合計分的基礎，可提升地緣風險維度的可信度，並在供應商尚未完成問卷時仍能提供初步評分。

## What Changes

- **新增** `country_risk_ratings` 表的 `sub_scores` JSON 欄位，對應地緣風險四個 L2 支柱（political, environmental, social, regulatory）
- **新增** `assessment_series.e4_objective_ratio`（α 值，預設 0.40）欄位，admin 可於計分設定頁調整
- **新增** `supplier.country` → `country_risk_ratings` 查詢邏輯，將 `country_defense_score`（= 100 − risk_rating）傳入 AI 計分任務
- **修改** esgchain-ai `six_dim_scoring_tasks.py` 中 E4 計算邏輯，依三種狀態機路徑計算混合分數：
  - 有國家風險評等 + SAQ 完成 → `dim_e4 = country_defense_score × α + saq_geo_risk_score × (1−α)`
  - 有國家風險評等 + SAQ 未完成 → `dim_e4 = country_defense_score × 1.0`（純客觀快照）
  - 無國家風險評等 → 維持現有純 SAQ georisk.* 計算，行為不變
- **修改** 計分設定 Tab UI，加入 E4 α 滑桿（0–100%，步進 5%）
- **新增** Settings 頁面國家風險評等管理介面新增 `sub_scores` 四欄位顯示與編輯

## Capabilities

### New Capabilities

- `e4-mixed-scoring`: E4 維度混合計分邏輯——依國家風險評等有無、SAQ 完成狀態，決定使用純客觀、純 SAQ 或混合公式計算 dim_e4

### Modified Capabilities

- `country-risk-ratings`: 現有規格新增 `sub_scores` JSON 欄位（political, environmental, social, regulatory），對應地緣風險 L2 支柱
- `series-scoring-config`: 現有規格新增 `e4_objective_ratio` 欄位及 UI α 滑桿設定

## Impact

- **esgchain-api（Laravel）**：`country_risk_ratings` Migration 新增 `sub_scores`、`assessment_series` Migration 新增 `e4_objective_ratio`；`ScoringJobDispatchService` 查詢並傳遞 `country_defense_score` 與 `e4_objective_ratio`
- **esgchain-ai（FastAPI/Celery）**：`six_dim_scoring_tasks.py` E4 計算路徑重寫，新增三狀態機 branch
- **esgchain-web（Vue 3）**：計分設定 Tab 新增 α 滑桿；Settings 國家風險評等頁新增 sub_scores 欄位
- **依賴**：`six-dim-scoring` 變更須先合併（提供 `e4_objective_ratio` 欄位基礎）
