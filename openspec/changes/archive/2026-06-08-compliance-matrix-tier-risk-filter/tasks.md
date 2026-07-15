## 1. 後端：SupplierComplianceStatusService 擴充

- [x] 1.1 `getMatrixData()` 新增 `tier` 與 `risk_score_min` 參數接收，在 `$supplierQuery` 加入對應 `when()` 過濾子句
- [x] 1.2 `getMatrixDrill()` 新增 `tier` 與 `risk_score_min` 參數接收，`$supplierQuery` 加入相同 `when()` 過濾子句
- [x] 1.3 `getMatrixDrill()` 的 `map()` 回傳每筆新增 `tier`、`risk_score`、`onboarding_stage` 三個欄位

## 2. 後端：ComplianceDashboardController 擴充

- [x] 2.1 `matrixData()` 從 `$request->query()` 接收 `tier` 與 `risk_score_min`，傳入 `getMatrixData()`
- [x] 2.2 `matrixDrill()` 從 `$request->query()` 接收 `tier` 與 `risk_score_min`，傳入 `getMatrixDrill()`

## 3. 前端：型別定義擴充

- [x] 3.1 `src/api/modules/compliance.ts` — `MatrixDrillSupplier` interface 新增 `tier: number`、`risk_score: number | null`、`onboarding_stage: string`
- [x] 3.2 `complianceDashboardApi.matrix()` params 型別新增 `tier?: number | string`、`risk_score_min?: number | string`
- [x] 3.3 `complianceDashboardApi.matrixDrill()` params 型別新增 `tier?: number | string`、`risk_score_min?: number | string`

## 4. 前端：MaterialComplianceView 矩陣篩選列

- [x] 4.1 `data()` 新增 `selectedTier: ''` 與 `riskScoreMin: ''` 兩個響應式欄位
- [x] 4.2 矩陣篩選列 template 新增 Tier 下拉（選項：全部 Tier / Tier 1 / Tier 2 / Tier 3），`@change="loadMatrixData"`
- [x] 4.3 矩陣篩選列 template 新增風險分數下限 input（type="number" min=0 max=100 placeholder="風險分數下限（未評分不列入）"），`@input` 加 300ms debounce 呼叫 `loadMatrixData`
- [x] 4.4 `loadMatrixData()` 建立 params 時帶入 `selectedTier` 與 `riskScoreMin`（空字串時不帶）
- [x] 4.5 `openDrill()` 建立 params 時帶入 `selectedTier` 與 `riskScoreMin`，保持 drill-down 母體與矩陣一致

## 5. 前端：Drill-down 清單欄位顯示

- [x] 5.1 Drill-down 清單 template 每列新增 Tier badge（`T{{ s.tier }}`），同 SupplierCombobox 的 `.tier-badge` 樣式
- [x] 5.2 每列新增 risk_score 數值顯示（`font-mono`，null 時顯示「—」）
- [x] 5.3 每列新增 onboarding_stage chip（`certified` 顯示綠色，其他顯示灰色）

## 6. Docker 同步與驗證

- [x] 6.1 `docker cp esgchain-api/app/. esgchain-api:/app/app/ && docker restart esgchain-api`
- [x] 6.2 `docker cp esgchain-web/src/. esgchain-web:/app/src/`
- [x] 6.3 curl 驗證 `GET /api/v1/compliance/matrix?tier=1` 回傳正確（分母縮小）
- [x] 6.4 curl 驗證 `GET /api/v1/compliance/matrix?risk_score_min=50` 回傳正確
- [x] 6.5 curl 驗證 `GET /api/v1/compliance/matrix/drill` 回傳含 `tier`、`risk_score`、`onboarding_stage`
- [x] 6.6 瀏覽器確認：矩陣篩選列顯示三個控制項，切換 Tier 後矩陣數字變化
- [x] 6.7 瀏覽器確認：點擊格子展開 drill-down，每列顯示 Tier badge、risk_score、onboarding_stage chip
