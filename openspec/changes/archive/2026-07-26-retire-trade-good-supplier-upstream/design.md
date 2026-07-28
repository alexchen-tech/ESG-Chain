## Context

`MarketComplianceChecker::check()`/`checkBatch()` 目前透過 `$good->tradeGoodSuppliers`（material_group 粒度、需使用者手動登記）取得兩件事：(1) 該產品需要哪些必備文件類型（`collectMaterialDocTypes()`，走 `tradeGoodSuppliers.materialGroup.required_doc_types`），(2) 該產品的上游供應商 ID 清單（用來查合規文件與 ESG 風險分數）。`CalculatePathRiskJob`／`SupplierComplianceDocPathRiskObserver` 也直接查 `TradeGoodSupplier` 做地緣風險路徑計算與快取失效。`TradeGoodService::getUpstreamCompliance()` 則是「上游供應商」分頁本身的資料來源。

本次工作階段已新建物料層級核可供應商清單（`material_item_suppliers`），比 `TradeGoodSupplier` 更細緻、去重、跨產品共用。BOM 明細（`product_bom_lines`）本身已有 `material_group_id`／`material_item_id`，足以推導出「這個產品需要哪些文件」與「這個產品的上游供應商是誰」，不需要再仰賴使用者手動維護的 `TradeGoodSupplier`。

## Goals / Non-Goals

**Goals:**
- 讓「上游供應商」相關查詢（必備文件收集、供應商 ID 清單、地緣風險路徑）改為從 BOM 明細＋物料核可清單衍生，不再要求手動登記
- 製程廠區欄位遷移到語意正確的位置（物料核可清單，而非 TradeGoodSupplier）
- 新增/既有服務的輸出格式盡量與現有前端消費者相容，減少連鎖修改

**Non-Goals:**
- 不刪除 `TradeGoodSupplier` 資料表/model，不遷移或清除其既有資料
- 不重新設計「上游供應商」分頁的視覺呈現，只改資料來源與精簡入口
- 不處理 `TradeGoodSupplierEmission`（供應商碳排確認）相關流程，該功能維持現狀

## Decisions

**1. 新增 `App\Services\Compliance\ProductUpstreamResolver`，集中封裝 BOM 衍生邏輯**

不在 `MarketComplianceChecker`/`TradeGoodService`/`CalculatePathRiskJob` 各自重寫一次查詢邏輯，而是新增一個小型 resolver service，提供：
- `materialGroupDocTypes(SalesProduct $product): array` — 走訪 BOM 明細，取 `materialGroup?->required_doc_types`（`material_group_id` 為空時 fallback `materialItem->materialGroup`，比照 `TradeGoodService::isEudrApplicable()` 既有寫法），去重回傳
- `supplierIds(SalesProduct $product): array` — 走訪 BOM 明細的 `bomLineSuppliers`（優先）或該物料的 `approvedSuppliers`（`material_item_suppliers`，若 BOM 行尚未套用核可清單），去重回傳
- `supplierSummaries(SalesProduct $product): Collection` — 給「上游供應商」分頁用，回傳去重後的供應商清單，含 `supplier_id`、`supplier_name`、`material_group_name`（來自該供應商在哪些 BOM 行出現）、`supplier_facility`（來自 `material_item_suppliers.supplier_facility_id`）、`doc_statuses`

理由：四個消費者（`MarketComplianceChecker`、`TradeGoodService`、`CalculatePathRiskJob`、`SupplierComplianceDocPathRiskObserver`）都需要「這個產品的供應商是誰」，集中一處可避免邏輯漂移，日後若 BOM 衍生規則調整只需改一處。

**2. `material_item_suppliers` 新增 `supplier_facility_id`，取代 `TradeGoodSupplier.supplier_facility_id`**

比照既有欄位新增慣例（nullable FK → `supplier_facilities`）。理由已在 proposal 說明：製程地點是供應商＋物料的固定屬性。`TradeGoodSupplier.supplier_facility_id` 欄位保留在資料表但不再是新資料的寫入目標。

**3. `MarketComplianceChecker::check()`/`checkBatch()` 簽章不變**

兩個方法目前接受 `TradeGood $good`（實質是 `SalesProduct`），回傳結構（`market`/`required`/`results`/`overall`/`supplier_risk_context`）維持不變，只替換內部取得 `materialDocTypes`/`supplierIds` 的方式（改呼叫 `ProductUpstreamResolver`），呼叫端（`CalculatePathRiskJob`、`TradeGoodMarketComplianceController` 等）不需要跟著修改。

**4. `TradeGoodService::getUpstreamCompliance()` 回傳結構調整（BREAKING）**

原本每筆是一個 `TradeGoodSupplier` 登記（material_group 粒度），改成每筆是「BOM 衍生的供應商」，`material_group` 欄位改回傳該供應商在 BOM 中實際供應的物料群組名稱（可能對應多筆 BOM 行時取第一個或逗號串接，實作時依前端顯示需求決定）；新增 `supplier_facility_name`/`facility_type` 欄位（原本這次工作階段才加的，資料來源從 `TradeGoodSupplier.supplierFacility` 改為 `material_item_suppliers.supplierFacility`）。前端 `SalesProductDetailView.vue` 因為整個分頁下線、改到 BOM 明細分頁重新顯示，屬於同一批修改，不會有「後端改了前端沒跟上」的中間態。

**5. 前端「上游供應商」分頁下線的呈現方式**

不是直接刪除分頁後留白，而是把「這個產品的上游供應商」彙總表移到「BOM 明細」分頁最下方，作為只讀摘要區塊（沿用現有 `.data-table` 樣式），資料來自新的 `getUpstreamCompliance()` 輸出。使用者從 BOM 明細分頁就能同時看到「配方」與「配方衍生的供應商清單」，不需要切換分頁對照。

## Risks / Trade-offs

- [風險] BOM 明細若尚未套用物料核可清單（`bom_line_suppliers` 為空、`material_item_suppliers` 也還沒維護），衍生出的供應商清單會是空的，`MarketComplianceChecker` 判定會從「有登記但缺文件」變成「完全查不到供應商」→ 緩解：`collectMaterialDocTypes()`／`supplierIds()` 在兩個來源都查無資料時，回傳空陣列＋讓既有 `emptyResult()`/`unconfigured` 狀態邏輯照常運作（不視為錯誤，維持既有「尚未設定」語意）
- [風險] `getUpstreamCompliance()` 輸出格式改變是 BREAKING，若有測試或其他消費者假設舊格式會壞 → 緩解：實作前先搜尋所有呼叫端（`SalesProductController::show()`/`suppliers()`），一併更新
- [風險] `CalculatePathRiskJob`／`SupplierComplianceDocPathRiskObserver` 目前直接查 `TradeGoodSupplier`，換成 BOM-based 查詢後，若某供應商同時存在於 `bom_line_suppliers` 與 `material_item_suppliers`，需避免重複觸發路徑風險重算 → 緩解：`ProductUpstreamResolver::supplierIds()` 內部已去重（`unique()->values()`），觀察者查詢時同樣以去重後的供應商 ID 找相關產品
- [取捨] 不刪除 `TradeGoodSupplier` 表意味著資料庫中會長期存在「已經沒人維護、也不是權威來源」的舊資料 → 接受此取捨，作為後續獨立清理任務，避免本次改造範圍失控

## Migration Plan

1. Migration：`material_item_suppliers` 新增 `supplier_facility_id`（nullable FK）
2. 新增 `ProductUpstreamResolver` service
3. 重寫 `MarketComplianceChecker::collectMaterialDocTypes()`／`check()`／`checkBatch()` 改用 resolver
4. 重寫 `TradeGoodService::getUpstreamCompliance()` 改用 resolver；同步更新其呼叫端
5. 重寫 `CalculatePathRiskJob::handle()` 的 eager-load 與 `SupplierComplianceDocPathRiskObserver::invalidateRelatedCache()` 改用 BOM-based 供應商查詢
6. 重寫 `BatchExportReviewService::checkProcessLocation()`／`BatchPassportService::buildProcessLocations()` 改讀 `material_item_suppliers.supplier_facility_id`
7. 前端：`SalesProductDetailView.vue` 移除「上游供應商」分頁與新增/移除操作，BOM 明細分頁新增彙總顯示區塊；`TradeGoodsView.vue` 對應調整
8. 部署後以真實資料驗證：至少一個有 BOM 核可供應商設定的產品，其市場合規檢查結果與遷移前一致；批次護照/出口審查的製程地點檢查改讀新欄位後結果正確

## Open Questions

- `getUpstreamCompliance()` 的 `material_group` 欄位在一個供應商對應多筆 BOM 行（不同物料群組）時如何呈現，留待實作時依前端顯示效果決定（逗號串接 vs 取第一筆 vs 改成陣列）
