## Why

ESG-Chain 目前的 `MaterialItemEmission` 儲存的是**已計算完成的碳排強度**（kgCO₂e/unit），而非原始活動資料（如用電度數、燃料使用量）。這使得系統無法：
1. 依 GHG Protocol Scope 3 Category 1 標準追蹤供應商能源消費與排放係數
2. 將原始活動資料推送至外部 Scope 3 計算引擎（如 EcoInvent、客戶 EHS 系統）
3. 在供應商設施層級彙整活動資料，供多產品共用

此 Change 建立「活動資料層」，新增 `SupplierFacility`（供應商生產設施）與 `ActivityDataReport`（活動資料申報，含原始用電量/燃料量）模型，並在 Portal 開放供應商填寫，最終觸發推送至外部 Scope 3 計算服務。

## What Changes

- 新增 `supplier_facilities` 表：供應商的生產設施（廠區），含地址、能源類型清單
- 新增 `activity_data_reports` 表：設施層級的活動資料申報，記錄申報期間、電力(kWh)、燃料(GJ)、熱能等原始數值
- 新增 Portal 活動資料填報頁面：供應商在 Portal 為其設施填報活動資料
- 新增買方審閱介面：永續團隊可查看各供應商已申報的活動資料
- 新增外部推送機制：ActivityDataReport 審核通過後自動推送至外部 Scope 3 計算服務（透過 esgchain-ai Celery Task）

## Capabilities

### New Capabilities

- `supplier-facility-registry`: 供應商設施主檔管理（CRUD，含地址、能源類型、主要生產品項）
- `activity-data-reporting`: 設施活動資料申報（電力、燃料、熱能、用水，申報期間 YYY-Q1~Q4）
- `scope3-push-integration`: 活動資料審核後自動推送至外部 Scope 3 計算服務

### Modified Capabilities

- `supplier-portal-material-reporting`: Portal 首頁新增「活動資料」任務區，顯示待填報設施
