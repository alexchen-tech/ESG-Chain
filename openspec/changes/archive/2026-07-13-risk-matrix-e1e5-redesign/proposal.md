## Why

風險矩陣目前使用四個語意分組 Tab（E環境 / S社會 / G治理 / GP地緣政治），但其底層 dim 對應有根本性錯誤：G治理 → dim_e1（環境管理）而非 dim_e5（公司治理）；E環境 → dim_e2（氣候與碳排）而非 dim_e1（環境管理）；dim_e5、dim_e6 從未進入矩陣。此外 E6 全為 null，實際可用維度為 E1–E5。

前端 SixDimHeatmapView 的 DIMS 標籤（ESG整體、永續採購、供應鏈安全、產品合規）與 six-dim-scoring 規格不符，導致 UI 呈現語意錯誤。

## What Changes

1. 矩陣 Tab 從 4 個語意分組（E/S/G/GP）改為 6 個直接對應維度的 Tab：綜合最差 + E1–E5
2. 「綜合最差」Tab：每個供應商取 min(E1–E5) 作為 Probability，找出最薄弱環節
3. 右側供應商面板高亮邏輯：從 E4 硬碼改為動態對應 active Tab 的維度；綜合最差 Tab 高亮各供應商實際最低分的維度（worst_dim_key）
4. SixDimHeatmapView DIMS 標籤修正為規格定義

## Capabilities

### Modified Capabilities
- `risk-matrix`: 矩陣維度軸從 E/S/G/GP 改為 E1–E5 + COMPOSITE，新增 COMPOSITE 計算邏輯與 worst_dim_key 回傳

## Impact

- 後端：`RiskMatrixController.php`（VALID_DIMENSIONS、DIM_SCORE_FIELD、buildMatrix、matrixSuppliers）
- 前端：`risk.ts`（RiskDimension type）、`RiskMatrix5x5.vue`（DIMENSIONS、高亮邏輯）、`SixDimHeatmapView.vue`（DIMS labels）
