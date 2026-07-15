## 1. 後端：Service 層新增矩陣計算

- [x] 1.1 `SupplierComplianceStatusService` 新增 `getMatrixData(?string $supplierGroupId): array` — 遍歷所有有 required_doc_types 的 MaterialGroup，計算每個 DocType 的 total/compliant/expiring/issues/pct，支援 supplierGroupId 過濾
- [x] 1.2 `SupplierComplianceStatusService` 新增 `getMatrixDrill(string $materialGroupId, string $docType, ?string $supplierGroupId): array` — 回傳特定格子的供應商清單及文件狀態

## 2. 後端：Controller 與路由

- [x] 2.1 `ComplianceDashboardController` 新增 `matrixData()` action，呼叫 `service->getMatrixData()`，接受 `supplier_group_id` query param
- [x] 2.2 `ComplianceDashboardController` 新增 `matrixDrill()` action，呼叫 `service->getMatrixDrill()`，接受 `material_group_id`、`doc_type`、`supplier_group_id` query params
- [x] 2.3 `routes/api.php` 新增路由：`GET /api/v1/compliance/matrix` → `matrixData`；`GET /api/v1/compliance/matrix/drill` → `matrixDrill`

## 3. 前端：API 型別與 module

- [x] 3.1 `api/modules/compliance.ts` 新增 `MatrixRow`、`MatrixCell`、`MatrixDrillSupplier` interface
- [x] 3.2 `complianceDashboardApi` 新增 `matrix(params?)` 與 `matrixDrill(params)` 方法

## 4. 前端：矩陣視角 Tab

- [x] 4.1 `MaterialComplianceView.vue` 新增第三個 Tab「矩陣視角」，Tab bar 加入 `matrix` 選項
- [x] 4.2 `data()` 新增：`matrixData`、`matrixLoading`、`supplierGroups`（下拉選項）、`selectedMatGroupId`（篩選）
- [x] 4.3 Tab 切換到 `matrix` 時呼叫 `loadMatrixData()`，同時呼叫 `settingsApi.groups.list()` 載入供應商群組下拉選項
- [x] 4.4 實作篩選列：供應商群組單選 `<select>` + 狀態 chip（全部/問題/即將到期），切換時重載矩陣
- [x] 4.5 實作矩陣 `<table>`：表頭固定 5 列（EUDR/UFLPA/CMRT/SDS/CE 顯示名稱），每行為一個 MaterialGroup，格子依 pct 套用 `.cell-green`/`.cell-yellow`/`.cell-red`/`.cell-na` class

## 5. 前端：Drill-Down Drawer

- [x] 5.1 `data()` 新增：`drillOpen`、`drillLoading`、`drillData`、`drillTitle`
- [x] 5.2 格子點擊 → 設定 `drillTitle`、呼叫 `loadDrill(materialGroupId, docType)`、`drillOpen = true`
- [x] 5.3 實作 Drawer HTML：右側固定定位、overlay 半透明背景、標題、供應商清單（badge 狀態 + 到期日）、「查看 CAP」連結、關閉按鈕
- [x] 5.4 供應商清單依 status 排序：missing → expired → expiring_soon → valid

## 6. 前端：樣式

- [x] 6.1 Scoped style 新增矩陣格子樣式：`.cell-green`（綠底）/ `.cell-yellow`（黃底）/ `.cell-red`（紅底）/ `.cell-na`（灰底）
- [x] 6.2 Scoped style 新增 Drawer 樣式：`.drawer-overlay`、`.drawer`、`.drawer-header`、`.drill-supplier-row`

## 7. 同步與驗證

- [x] 7.1 `docker cp` 修改的 Laravel 檔案至 esgchain-api 容器並重啟路由快取
- [x] 7.2 `docker cp` 修改的 Vue 檔案至 esgchain-web 容器並 touch 觸發 HMR
- [x] 7.3 瀏覽器確認矩陣 Tab 顯示、格子顏色正確、點擊格子 Drawer 展開
- [x] 7.4 確認供應商群組篩選可正確過濾矩陣資料
