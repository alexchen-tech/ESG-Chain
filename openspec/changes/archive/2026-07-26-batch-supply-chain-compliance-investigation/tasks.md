## 1. 批次範圍供應商解析

- [x] 1.1 `ProductUpstreamResolver::batchSupplierIds(batch, product)` 新增：優先採用批次已選定的實際供應商，未選定則退回物料核可清單
- [x] 1.2 `MarketComplianceChecker::check()` 新增可選參數 `$supplierIdsOverride`，未傳入時行為不變
- [x] 1.3 `BatchExportReviewService::checkMarketDocs()` 改用 `batchSupplierIds()`
- [x] 1.4 `BatchPassportService::buildComplianceDocuments()` 改用 `batchSupplierIds()`
- [x] 1.5 以真實資料驗證：手動指定一筆原料溯源的實際供應商後執行審查，結果正確反映該供應商文件

## 2. 供應鏈合規調查清單

- [x] 2.1 `BatchPassportService::buildSupplyChainCompliance()` 新增：逐 BOM 行組成 selected_supplier/supplier_confirmed/traceability/doc_statuses
- [x] 2.2 前端 `ProductionBatchDetailView.vue` 型別新增 `supply_chain_compliance`
- [x] 2.3 生產批號詳情頁拆分 4 分頁：批號資訊／碳足跡與循環經濟／有害物質揭露／供應鏈合規
- [x] 2.4 供應鏈合規調查卡片：呈現物料名稱、供應商選定狀態、溯源摘要、合規文件 chip
- [x] 2.5 以真實資料驗證：curl 確認 supply_chain_compliance 逐物料資料正確（含已選定與建議未確認兩種狀態）

## 3. 供應商選定與確認入口整合

- [x] 3.1 「原料溯源」更名為「原物料合規與溯源管理」
- [x] 3.2 供應鏈合規調查卡片改為「前往原物料合規與溯源管理確認」導引按鈕（捲動並預選 BOM 物料）
- [x] 3.3 撤除快速確認端點 `POST /production-batches/{id}/confirm-supplier` 與對應前端方法（避免與完整表單形成兩套維護入口）

## 4. 供應商預設廠區

- [x] 4.1 `Supplier::defaultFacility()` 新增：僅一個廠區時視為預設，多廠區回傳 null
- [x] 4.2 `MaterialItemSupplierController::store()` 未指定廠區時套用預設廠區
- [x] 4.3 原物料合規與溯源管理表單：選定供應商後若僅一個廠區自動帶入設施名稱／原產國，不覆蓋已填寫欄位
- [x] 4.4 一次性 migration 回填既有 `material_item_suppliers.supplier_facility_id`（僅單一廠區供應商）
- [x] 4.5 以真實資料驗證：migration 執行前後 null 廠區筆數與候選筆數一致（84 筆全數回填）

## 5. 部署與驗證

- [x] 5.1 Laravel 檔案與 migration 同步至 esgchain-api 與 esgchain-queue-worker，restart + migrate + config:cache + route:cache
- [x] 5.2 Vue 檔案同步至 esgchain-web，觸發 HMR
- [x] 5.3 `vue-tsc` 全專案型別檢查通過
- [x] 5.4 確認撤除的 `confirm-supplier` 端點回傳 404，確認既有 `passport`/`export-reviews` 等端點不受影響
