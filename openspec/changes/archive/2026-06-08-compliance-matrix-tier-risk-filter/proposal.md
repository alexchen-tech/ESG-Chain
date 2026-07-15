## Why

合規矩陣目前僅支援「供應商群組」單一篩選維度，無法按 Tier 或風險分數切片；採購商需要優先處理 Tier 1 × 高風險供應商的合規缺口，現有矩陣無法直接呈現此類分析視角。

## What Changes

- 矩陣篩選列新增 **Tier 下拉**（全部 / T1 / T2 / T3）
- 矩陣篩選列新增 **風險分數下限 input**（`risk_score ≥ N`，空白時不篩選）
- 後端 `getMatrixData()` 及 `getMatrixDrill()` 新增 `tier` 與 `risk_score_min` 查詢參數，縮小供應商母體
- Drill-down 清單每列新增 **Tier badge**、**風險分數**（font-mono）、**onboarding_stage chip**，便於識別未認證供應商
- 前端 `complianceDashboardApi.matrix()` / `matrixDrill()` 帶入新 params

## Capabilities

### New Capabilities

無新能力，本次為現有矩陣的篩選強化。

### Modified Capabilities

- `compliance-matrix`：矩陣篩選新增 `tier` / `risk_score_min` 參數；drill-down 回傳欄位新增 `tier`、`risk_score`、`onboarding_stage`

## Impact

- **後端**：`SupplierComplianceStatusService::getMatrixData()` / `getMatrixDrill()` 各新增 2 個 when() 過濾子句；`ComplianceDashboardController::matrixData()` / `matrixDrill()` 各新增 2 個 query param 的接收與傳遞
- **前端**：`MaterialComplianceView.vue` 矩陣篩選列新增 2 個控制項；`loadMatrixData()` / `openDrill()` 帶入新 params；drill-down 清單 template 新增 3 個欄位顯示
- **API**：`GET /api/v1/compliance/matrix` 與 `GET /api/v1/compliance/matrix/drill` 新增可選 query params `tier`、`risk_score_min`（向後相容，不帶參數行為不變）
- **型別定義**：`complianceDashboardApi.matrix()` / `matrixDrill()` params 型別擴充；`MatrixDrillSupplier` interface 新增 `tier`、`risk_score`、`onboarding_stage` 欄位
