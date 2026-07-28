## Why

「上游供應商」資訊目前以 `TradeGoodSupplier`（material_group 粒度）獨立維護，需使用者為每個產品各自登記一次，且製程廠區欄位常態性未填。本次工作階段新建的「物料核可供應商清單」（`material_item_suppliers`）已經是更細緻、去重、跨產品共用的權威資料，「這個產品的上游供應商」其實可以完全從 BOM 明細＋物料核可清單反推得到，不需要再要求使用者重複登記。此變更把「上游供應商」相關的查詢/檢核邏輯改為以 BOM 資料衍生，移除重複維護的負擔，同時把製程廠區欄位遷移到語意正確的位置（供應商＋物料，而非供應商＋特定產品）。

## What Changes

- `MarketComplianceChecker::check()` 收集必備文件類型的邏輯，從 `tradeGoodSuppliers.materialGroup` 改為讀 BOM 明細（`product_bom_lines.material_group_id`）
- `TradeGoodService::getUpstreamCompliance()`（「上游供應商」分頁資料來源）**BREAKING**：改為從 BOM 明細＋物料核可供應商清單反推去重的供應商清單，回傳結構不再是 material_group 粒度的單筆登記，而是依 BOM 明細彙總的清單
- `CalculatePathRiskJob`、`SupplierComplianceDocPathRiskObserver` 的地緣風險路徑計算，改用 BOM-based 供應商查詢取代 `tradeGoodSuppliers` 查詢
- 製程廠區欄位（`supplier_facility_id`）從 `TradeGoodSupplier` 遷移到 `material_item_suppliers`（本次工作階段新建的物料核可供應商清單）
- `BatchExportReviewService::checkProcessLocation()`、`BatchPassportService` 的供應鏈製程級地點檢查，改讀 `material_item_suppliers.supplier_facility_id`（透過 BOM 明細關聯）
- 前端 `SalesProductDetailView.vue` 的「上游供應商」分頁（新增/移除供應商的操作介面）下線，改在「BOM 明細」分頁顯示彙總後的上游供應商清單；`TradeGoodsView.vue` 對應調整
- `TradeGoodSupplier` 資料表與 model **不刪除**，僅停止作為「上游供應商」資訊的權威來源，前端新增/編輯入口下線

## Capabilities

### New Capabilities
- `product-upstream-supplier-derivation`：從 BOM 明細＋物料核可供應商清單衍生產品上游供應商清單的查詢邏輯，取代直接讀取 `TradeGoodSupplier`

### Modified Capabilities
- `trade-good-registry`：「上游供應商 BOM 關聯管理」與「上游供應商合規展開面板」兩項需求的資料來源改變（不再是使用者手動登記的 `TradeGoodSupplier`，改為 BOM 衍生），前端不再提供手動新增/移除入口
- `trade-good-market-compliance`：`MarketComplianceChecker::check()` 收集必備文件類型的資料來源改變

## Impact

- 後端：`App\Services\Compliance\MarketComplianceChecker`、`App\Services\TradeGoods\TradeGoodService`、`App\Jobs\CalculatePathRiskJob`、`App\Observers\SupplierComplianceDocPathRiskObserver`、`App\Services\ProductionBatch\BatchExportReviewService`、`App\Services\ProductionBatch\BatchPassportService`；新增 `material_item_suppliers.supplier_facility_id` 欄位（migration）
- 前端：`esgchain-web/src/views/sales-products/SalesProductDetailView.vue`（上游供應商分頁下線，BOM 明細分頁新增彙總顯示）、`esgchain-web/src/views/trade-goods/TradeGoodsView.vue`
- 明確排除：不刪除 `TradeGoodSupplier` 資料表/model（`TradeGoodSupplierEmission` 等功能可能仍依賴）；不遷移或清除 `TradeGoodSupplier` 既有資料，歷史資料清理留待後續
