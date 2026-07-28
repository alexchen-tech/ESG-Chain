## Why

先前的稽核發現：生產批號的出口合規調查（`BatchExportReviewService`/`BatchPassportService`）計算合規文件時，是用 `ProductUpstreamResolver::supplierIds()` 取得整個產品 BOM 全部上游供應商，完全沒有讀取該批次在原料溯源實際選定的供應商——等於「批號→選供應商」這一步被合規調查邏輯繞過，也讓「供應鏈合規」分頁只能看到一份跟這批貨無關、混雜所有可能供應商的合規文件平鋪清單，使用者無法在畫面上完成「這個物料選了誰、資料夠不夠、缺什麼文件」的最後確認。

## What Changes

- 新增 `ProductUpstreamResolver::batchSupplierIds()`：批次合規調查優先採用 `raw_material_origins.supplier_id` 已選定的實際供應商，未選定的物料才退回物料核可清單，取代原本一律套用產品全部上游供應商的做法
- `MarketComplianceChecker::check()` 新增可選的 `$supplierIdsOverride` 參數，讓 `BatchExportReviewService::checkMarketDocs()` 能傳入批次範圍的供應商，不影響其他既有呼叫端（未傳入時行為不變）
- `BatchPassportService` 新增 `supply_chain_compliance`：以 BOM 表為主軸，逐一物料呈現「選擇供應商 → 溯源調查 → 合規調查」完整鏈路，取代原本互不關聯的「合規文件」平鋪表格與「原料溯源」清單
- 生產批號詳情頁「批號資訊」長頁拆成 4 個分頁：批號資訊／碳足跡與循環經濟／有害物質揭露／供應鏈合規（含新的供應鏈合規調查卡片 ＋ 原物料溯源管理，更名為「原物料合規與溯源管理」）
- 新增 `Supplier::defaultFacility()`：供應商僅有一個廠區時視為預設廠區，套用於「物料核可供應商清單新增」與「原物料合規與溯源管理」表單（選定供應商後自動帶入設施名稱／原產國，已填寫欄位不覆蓋）
- 一次性 migration 回填既有 `material_item_suppliers.supplier_facility_id`（僅供應商剛好只有一個廠區者）

## Capabilities

### New Capabilities
- `batch-supply-chain-compliance`：生產批號的供應鏈合規調查（BOM 驅動、批次範圍供應商、溯源＋合規文件整合呈現）

### Modified Capabilities
（無——不修改既有 `production-batch-management`/`export-review-queue` 的 requirement，本次是在既有生產批號詳情頁架構上新增分頁與服務邏輯）

## Impact

- 後端：`ProductUpstreamResolver`、`MarketComplianceChecker`、`BatchExportReviewService`、`BatchPassportService`、`MaterialItemSupplierController`、`Supplier` model
- 前端：`ProductionBatchDetailView.vue`（分頁拆分、供應鏈合規調查卡片、原物料合規與溯源管理表單自動帶入邏輯）
- 資料庫：一次性 migration 回填 `material_item_suppliers.supplier_facility_id`
- 不影響：`BatchExportReview` 資料表結構、出口審查清單頁（`ExportReviewsView.vue`）既有行為
