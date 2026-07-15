## 1. 後端：Service 層新增 DPP 就緒度計算

- [x] 1.1 `SupplierComplianceStatusService` 新增 `getDppReadinessList(): array` — 遍歷所有 BuyerProduct，計算材料完整性、供應商合規覆蓋率、ESPR 標記，回傳就緒度摘要列表
- [x] 1.2 `SupplierComplianceStatusService` 新增 `getDppReadinessDetail(BuyerProduct $product): array` — 回傳單一產品的三區塊明細（material_list / supplier_compliance / regulations）

## 2. 後端：Controller 與路由

- [x] 2.1 `ComplianceDashboardController` 新增 `dppReadiness()` action，呼叫 `service->getDppReadinessList()`
- [x] 2.2 `ComplianceDashboardController` 新增 `dppReadinessDetail(BuyerProduct $product)` action，呼叫 `service->getDppReadinessDetail()`
- [x] 2.3 `routes/api.php` 新增路由：`GET /api/v1/compliance/dpp-readiness` → `dppReadiness`；`GET /api/v1/compliance/dpp-readiness/{buyerProduct}` → `dppReadinessDetail`

## 3. 前端：API 型別與 module

- [x] 3.1 `api/modules/compliance.ts` 新增 `DppProduct`、`DppDetailSection`、`DppDetail` interface
- [x] 3.2 `complianceDashboardApi` 新增 `dppReadiness()` 與 `dppReadinessDetail(productId)` 方法

## 4. 前端：ESPR/DPP Tab

- [x] 4.1 `MaterialComplianceView.vue` 新增第四個 Tab「ESPR/DPP」，Tab bar 加入 `dpp` 選項
- [x] 4.2 `data()` 新增：`dppData`（產品列表）、`dppLoading`
- [x] 4.3 Tab 切換到 `dpp` 時呼叫 `loadDppData()`（lazy load，首次切換才載入）
- [x] 4.4 實作產品列表 `<table>`：產品名稱、ESPR 標記（✓/—）、整體狀態（badge）、材料完整度（進度條 %）、供應商合規率（進度條 %）

## 5. 前端：DPP Detail Drawer

- [x] 5.1 `data()` 新增：`dppDrawerOpen`、`dppDrawerLoading`、`dppDrawerData`、`dppDrawerTitle`
- [x] 5.2 列表點擊 → 呼叫 `loadDppDetail(productId)`、`dppDrawerOpen = true`
- [x] 5.3 實作 Drawer HTML（寬 420px）：標題、三個 section card（材料清單 / 供應商合規聲明 / 法規標記）、關閉按鈕、overlay
- [x] 5.4 材料清單 section：逐筆顯示材料名稱、HS Code、物料群組是否已設定（✓ 綠 / ✗ 紅）
- [x] 5.5 供應商合規聲明 section：依供應商分組，列出每個 required doc type 狀態（badge）
- [x] 5.6 法規標記 section：顯示 applicable_regulations，ESPR 標記以高亮色顯示

## 6. 前端：樣式

- [x] 6.1 Scoped style 新增：`.dpp-readiness-bar`（進度條）、`.dpp-section`（section card）、`.dpp-section-title`
- [x] 6.2 Scoped style 新增：`.dpp-status-ready`（綠）/ `.dpp-status-partial`（黃）/ `.dpp-status-not-started`（灰）

## 7. 同步與驗證

- [x] 7.1 `docker cp` 修改的 Laravel 檔案至 esgchain-api 容器並 `docker restart esgchain-api`
- [x] 7.2 `docker cp` 修改的 Vue 檔案至 esgchain-web 容器並 touch 觸發 HMR
- [x] 7.3 瀏覽器確認 ESPR/DPP Tab 顯示、產品列表正確渲染、進度條顯示
- [x] 7.4 點擊產品確認 Drawer 展開，三個 section 資料正確
