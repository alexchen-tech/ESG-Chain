## Why

BuyerProduct 目前的 `applicable_regulations` 欄位完全靠人工填寫，缺乏自動推算機制，導致法規標示漏打或過時。供應商端已有 `syncSupplierApplicableRegulations()`（BomLine → MaterialGroup.required_doc_types → 法規名稱），但產品端沒有對等邏輯，且 ESPR 是市場導向法規，無法純粹從 BOM 推算，必須採混合模式。

## What Changes

- **BuyerProduct** 新增 `inferred_regulations` 欄位（JSON array），存放系統自動從 BOM 推算出的法規
- **BuyerProduct** 保留 `applicable_regulations` 作為人工聲明欄位（改名或重新定義為 `declared_regulations`）
- 新增 `syncProductInferredRegulations(BuyerProduct)` Service method：走訪 BomLine → MaterialGroup.required_doc_types → 推算對應法規清單
- 新增手動觸發 API endpoint：`POST /compliance/products/{id}/sync-regulations`
- 新增 Scheduled Job：每日凌晨批量執行所有產品的法規推算
- 前端產品清單與詳情頁顯示「推算來源」與「人工聲明」視覺區分，ESPR 由人工在產品 modal 勾選

## Capabilities

### New Capabilities
- `product-regulation-inference`: 產品法規自動推算 — 從 BOM 結構推算 BuyerProduct 適用法規，並提供手動覆蓋介面

### Modified Capabilities
- `buyer-products`: BuyerProduct 資料模型新增 `inferred_regulations`，UI 展示邏輯調整（推算 vs 人工）

## Impact

- **esgchain-api**: `BuyerProduct` model 新增欄位、`SupplierComplianceStatusService` 新增推算方法、新增 migration、新增 route + controller action、新增 Scheduled Command
- **esgchain-web**: `BuyerProductsView.vue`、`MaterialComplianceView.vue` DPP 頁签法規標籤顯示調整
- **資料庫**: `buyer_products` 表新增 `inferred_regulations` JSON 欄位
- **無 breaking change**：`applicable_regulations` 保留為人工聲明欄位，不刪除
