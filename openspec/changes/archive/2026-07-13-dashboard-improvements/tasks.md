## 1. 後端：高風險定義改為三軸

- [x] 1.1 修改 `DashboardService::getSummary()` 中 `$highRiskIds` query：改用 `axis1_score >= 60 OR axis2_score >= 60 OR axis3_score >= 60`（對應 high/extreme level，依 `RiskAssessment::axisToLevel()` 閾值）

## 2. 後端：analyst KPI 卡

- [x] 2.1 在 `DashboardService::getSummary()` 的 `match` 補上 `'analyst'` case，cards 包含：SAQ 待審核、高風險供應商（使用步驟 1 的新定義）

## 3. 後端：esgScores endpoint

- [x] 3.1 確認 `DashboardController` 是否有 `/api/v1/dashboard/esg-scores` endpoint；若無則新增
- [x] 3.2 在 `DashboardService` 新增 `getEsgScores()` method，彙總所有供應商最新 RA 的 axis1/axis2/axis3 平均分數與 level 分布
- [x] 3.3 在 `DashboardController` 新增對應 action，路由 `GET /api/v1/dashboard/esg-scores`

## 4. 前端：補 esgScores API call

- [x] 4.1 在 `dashboard.ts` API module 新增 `EsgScores` interface 與 `esgScores()` function（GET `/api/v1/dashboard/esg-scores`）
- [x] 4.2 在 `DashboardView.loadData()` 補上：`role === 'sustain'` 時呼叫 `dashboardApi.esgScores()`，結果存入 `this.esgScores`

## 5. 前端：側欄加入永續風險概覽入口

- [x] 5.1 在 `AppSidebar.vue` 的 `risk-group` 子選單（`children` 陣列）加入：
  `{ name: 'sustainability-risk', path: '/dashboard/sustainability-risk', label: '永續風險概覽', roles: ['admin','sustain','comply','analyst'] }`

## 6. 驗收

- [x] 6.1 以 sustain 角色登入，確認 ESG 分數 widget 有資料顯示
- [x] 6.2 以 analyst 角色登入，確認 KPI 卡顯示 SAQ 待審核 + 高風險供應商
- [x] 6.3 以任一風險相關角色登入，確認側欄「風險稽核」下有「永續風險概覽」且可點擊進入
- [x] 6.4 確認主儀表板「高風險供應商」數字與 `/risk` 頁三軸 high/extreme 供應商數量一致
