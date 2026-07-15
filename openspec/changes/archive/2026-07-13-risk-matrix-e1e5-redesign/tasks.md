## 1. 後端：更新 RiskMatrixController

- [x] 1.1 更新 `VALID_DIMENSIONS` 為 `['E1','E2','E3','E4','E5','COMPOSITE']`，validation rule 同步更新
- [x] 1.2 更新 `DIM_SCORE_FIELD`：`{e1=>'dim_e1', e2=>'dim_e2', e3=>'dim_e3', e4=>'dim_e4', e5=>'dim_e5', composite=>null}`
- [x] 1.3 修改 `buildMatrix()`：dim = 'composite' 時改用 `DB::raw('LEAST(dim_e1,dim_e2,dim_e3,dim_e4,dim_e5) as dim_score')`，其餘維度取對應欄位
- [x] 1.4 修改 `matrixSuppliers()`：同步套用 COMPOSITE LEAST 邏輯
- [x] 1.5 新增 `getWorstDimKey(RiskAssessment $ra): string` private method：回傳 dim_e1–dim_e5 中值最低的欄位名稱
- [x] 1.6 在 `matrixSuppliers()` 回傳的每個供應商資料中加入 `worst_dim_key`：composite 時呼叫 `getWorstDimKey()`，其他維度回傳該維度欄位名稱（如 `'dim_e3'`）

## 2. 前端：更新 TypeScript 型別

- [x] 2.1 更新 `esgchain-web/src/api/modules/risk.ts` 的 `RiskDimension` type：改為 `'E1' | 'E2' | 'E3' | 'E4' | 'E5' | 'COMPOSITE'`

## 3. 前端：更新 RiskMatrix5x5.vue

- [x] 3.1 更新 `DIMENSIONS` array：6 項（COMPOSITE + E1–E5），label 使用規格定義
- [x] 3.2 更新 `activeDim` 預設值為 `'COMPOSITE'`
- [x] 3.3 移除 `SIX_DIMS` 的硬碼 E4 高亮（`:class="{ 'sc-dim-e4': d.key === 'dim_e4' }"`），改為 `:class="{ 'sc-dim-active': s.worst_dim_key === d.key }"`
- [x] 3.4 更新 `SIX_DIMS` chip 的 CSS class：`.sc-dim-e4` → `.sc-dim-active`（樣式不變，只改 class 名稱）
- [x] 3.5 確認 API response 中 `worst_dim_key` 欄位已正確傳遞至 `panelSuppliers` 資料

## 4. 前端：修正 SixDimHeatmapView.vue 標籤

- [x] 4.1 更新 `DIMS` 常數標籤：E1→環境管理、E2→氣候與碳排、E3→社會責任（不變）、E4→地緣風險、E5→公司治理、E6→供應鏈透明度

## 5. 同步容器並驗收

- [x] 5.1 `docker cp` RiskMatrixController.php 並 `docker restart esgchain-api`
- [x] 5.2 API 測試：`GET /api/v1/risk/matrix?dimension=COMPOSITE` 回傳正確矩陣資料
- [x] 5.3 API 測試：`GET /api/v1/risk/matrix/suppliers?dimension=COMPOSITE&probability=2&impact=4` 回傳含 `worst_dim_key` 的供應商
- [x] 5.4 `docker cp` 前端 Vue 檔並觸發 Vite HMR，確認矩陣 Tab 顯示 6 個選項
- [x] 5.5 確認綜合最差 Tab 各供應商卡片的橘框高亮為各自最低分維度
- [x] 5.6 確認 SixDimHeatmapView 標籤正確（E5 顯示「公司治理」而非「供應鏈安全」）
