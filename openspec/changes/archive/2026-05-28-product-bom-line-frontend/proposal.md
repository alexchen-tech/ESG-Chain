## Why

後端 `ProductBomLine` 資料層建立後（product-bom-line-backend），採購商合規管理員需要在 ESG-Chain 介面中管理產品 BOM 明細、匯入 ERP 資料，並在供應商合規詳情頁中看到「哪些採購產品需要哪些合規文件」的整合視圖。供應商（Portal）也需要一個頁面，讓他們了解自己被哪些客戶產品引用、各需要準備哪些合規文件——但不得看到採購商的實際產品名稱。

## What Changes

- 在 `BuyerProductsView`（產品清單）的產品卡片中，新增 BOM 明細管理 section（可展開），支援新增/編輯/刪除 BomLine，以及 ERP CSV 匯入
- 在 `SupplierComplianceDetailView`（供應商合規詳情）新增「關聯採購產品」section，列出哪些產品 BomLines 指定了此供應商，以及對應的合規文件需求
- 在供應商 Portal（`/supplier/portal`）新增「採購需求」頁面，以匿名化方式呈現「客戶產品 #N」→ 指定物料 → 需備文件，並顯示目前合規缺口
- 更新 `compliance.ts` API module 以支援 BomLine CRUD 與匯入 endpoints

## Capabilities

### New Capabilities

- `buyer-product-bom-view`: 採購商端的 BOM 明細管理 UI（BuyerProductsView 擴充），含 CRUD 與 CSV 匯入
- `supplier-bom-requirement-view`: 供應商合規詳情頁的「關聯採購產品需求」section
- `portal-procurement-requirements`: 供應商 Portal 新頁面「採購需求」，匿名化顯示被引用的客戶產品與合規缺口

### Modified Capabilities

- `buyer-product-registry`: BuyerProductsView 新增 BOM 管理功能（展開 section + CSV 匯入按鈕）
- `supplier-compliance-status`: SupplierComplianceDetailView 新增關聯採購產品 section

## Impact

- **修改**：`esgchain-web/src/views/compliance/BuyerProductsView.vue`（新增 BOM section）
- **修改**：`esgchain-web/src/views/compliance/SupplierComplianceDetailView.vue`（新增關聯產品 section）
- **新增**：`esgchain-web/src/views/supplier/PortalProcurementView.vue`（供應商 Portal 採購需求頁）
- **修改**：`esgchain-web/src/api/modules/compliance.ts`（BomLine CRUD + import API）
- **修改**：`esgchain-web/src/router/index.ts`（新增 Portal 路由）
- **相依**：`product-bom-line-backend` change 必須先部署（API endpoints 存在）
