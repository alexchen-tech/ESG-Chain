## 1. 資料庫 migration

- [x] 1.1 `material_item_suppliers` 新增 `supplier_facility_id`（nullable FK → supplier_facilities）

## 2. ProductUpstreamResolver 服務

- [x] 2.1 新增 `App\Services\Compliance\ProductUpstreamResolver`：`materialGroupDocTypes(SalesProduct): array`
- [x] 2.2 `supplierIds(SalesProduct): array`（優先 bom_line_suppliers，退回 material_item_suppliers）
- [x] 2.3 `supplierSummaries(SalesProduct): Collection`（供「上游供應商」彙總顯示用，含物料群組、製程廠區）
- [x] 2.4 單元驗證：至少一個「BOM 已套用核可清單」與一個「BOM 未套用、退回物料清單」的產品，確認三個方法回傳正確

## 3. MarketComplianceChecker 改用 resolver

- [x] 3.1 讀取 `MarketComplianceChecker::check()`/`checkBatch()`/`collectMaterialDocTypes()` 現況全文
- [x] 3.2 改用 `ProductUpstreamResolver` 取得 materialDocTypes 與 supplierIds，移除對 `tradeGoodSuppliers` 的直接依賴
- [x] 3.3 確認回傳結構（market/required/results/overall/supplier_risk_context）不變，呼叫端不需修改
- [x] 3.4 以真實資料 curl 驗證：至少一個產品的合規檢查結果與改動前一致

## 4. TradeGoodService::getUpstreamCompliance() 改寫

- [x] 4.1 讀取現況全文與所有呼叫端（SalesProductController::show()/suppliers()）
- [x] 4.2 改用 `ProductUpstreamResolver::supplierSummaries()`，回傳結構調整（物料群組彙總、新增製程廠區欄位）
- [x] 4.3 同步更新呼叫端與對應 TS 型別（回傳欄位名稱不變，型別相容，無需修改）

## 5. 地緣風險路徑計算改寫

- [x] 5.1 `CalculatePathRiskJob::handle()` 移除 `tradeGoodSuppliers.materialGroup`/`.supplier` 的 eager load（`check()` 內部已改用 resolver）
- [x] 5.2 `SupplierComplianceDocPathRiskObserver::invalidateRelatedCache()` 改用 BOM-based 查詢（透過 bom_line_suppliers/material_item_suppliers 找出供應商參與的產品），取代 `TradeGoodSupplier::where('supplier_id', ...)`

## 6. 批次護照／出口審查製程地點檢查改讀新欄位

- [x] 6.1 `BatchExportReviewService::checkProcessLocation()` 改讀 `material_item_suppliers.supplier_facility_id`（透過 BOM 明細關聯），不再讀 `TradeGoodSupplier.supplier_facility_id`
- [x] 6.2 `BatchPassportService::buildProcessLocations()` 同步改寫
- [x] 6.3 以真實資料驗證：curl 批次護照端點回傳 200，`process_locations` 在尚無廠區資料時正確回傳空陣列（不視為錯誤），符合 design.md 的「無歷史資料遷移」決策

## 7. 前端調整

- [x] 7.1 `SalesProductDetailView.vue` 移除「上游供應商」分頁（TABS 常數、對應 template 區塊、addSupplierForm/allSuppliers/supplierFacilityOptions 等相關 data/methods）
- [x] 7.2 「BOM 明細」分頁底部新增唯讀的上游供應商彙總表（沿用 `.data-table` 樣式），資料來源改為新版 `getUpstreamCompliance()` API
- [x] 7.3 `TradeGoodsView.vue` 對應調整（手動新增/移除供應商入口移除，改為唯讀彙總表）
- [x] 7.4 `vue-tsc` 全專案型別檢查通過

## 8. 部署與驗證

- [x] 8.1 Laravel 檔案同步至 esgchain-api 與 esgchain-queue-worker，restart + migrate + config:cache
- [x] 8.2 Vue 檔案同步至 esgchain-web，觸發 HMR
- [x] 8.3 以真實資料驗證整條鏈路：`GET /sales-products/{id}/suppliers`（BOM 衍生上游供應商彙總）、`POST /trade-goods/market-compliance-batch`（市場合規檢查）、`GET /production-batches/{id}/passport`（批次護照製程地點）皆回傳 200 且資料正確；`vue-tsc` 全專案通過
