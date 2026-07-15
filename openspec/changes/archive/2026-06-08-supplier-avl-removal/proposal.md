## Why

採購商的所有供應商與 BOM 指派資料均從 ERP 匯入（`source=erp_designated`），ERP 匯入流程完全繞過 AVL 驗證，導致 `buyer_product_suppliers`（AVL）長期為空、從未發揮守門功能。現有 AVL 架構只對「手動新增供應商」這個極少發生的操作有約束，卻增加了 UI 維護成本與資料模型複雜度，且存在 `syncApplicableRegulations()` 未定義的 runtime bug。移除 AVL 可簡化架構，並以「供應商認證狀態（status=certified）」作為唯一、更有業務意義的門檻。

## What Changes

- **BREAKING** 移除 `buyer_product_suppliers` 資料表及所有相關記錄
- **BREAKING** 移除 `BuyerProductSupplierController` 及其 REST 端點（`GET/POST/DELETE /api/v1/buyer-products/{id}/suppliers`）
- **BREAKING** 移除 `BuyerProduct.productSuppliers()` Eloquent 關聯
- **BREAKING** 移除 `BomLineSupplierController` 中的 AVL 驗證邏輯（改為允許任何 `status=certified` 的供應商手動指派）
- 移除 `BuyerProductsView.vue` 的 AVL 管理區塊（UI 底部的「已認可供應商」清單）
- 變更 BOM 線供應商選單：從「僅顯示 AVL 成員」改為「顯示所有 certified 供應商，支援關鍵字搜尋 + Tier 篩選」
- 修復三處 `syncApplicableRegulations()` 錯誤呼叫 → 改為 `syncInferredRegulations()`
- 移除 `BuyerProductImportController` 中的 AVL 寫入邏輯

## Capabilities

### New Capabilities

- `bom-line-supplier-open-picker`：BOM 線供應商選單改為開放選取，候選池為全系統 certified 供應商，支援關鍵字搜尋與 Tier 篩選

### Modified Capabilities

- `bom-line-supplier-avl`：移除整個 AVL 機制（buyer_product_suppliers 表與 AVL 管理 UI），BomLine 供應商指派不再受 AVL 約束
- `bom-line-supplier-management`：移除 AVL 驗證約束，手動新增 BomLineSupplier 改為驗證供應商 `status=certified`

## Impact

- **後端**：`BuyerProductSupplierController`、`BuyerProduct` model、`BomLineSupplierController`（AVL 驗證區塊）、`BuyerProductImportController`（AVL 寫入）
- **前端**：`BuyerProductsView.vue`（移除 AVL 區塊，改寫供應商選單元件）
- **資料庫**：drop `buyer_product_suppliers` table（需 migration）
- **API**：移除 `/api/v1/buyer-products/{id}/suppliers` 系列端點（breaking change）
- **Seeder**：`BuyerProductSeeder` 中的 AVL 填充邏輯需移除
