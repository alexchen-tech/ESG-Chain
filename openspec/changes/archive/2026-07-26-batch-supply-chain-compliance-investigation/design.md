## Context

生產批號詳情頁已有「批號→選供應商」的落地位置（`RawMaterialOrigin.supplier_id`，見 `retire-trade-good-supplier-upstream` 變更建立的 `ProductUpstreamResolver`），但合規調查邏輯（`checkMarketDocs()`／`buildComplianceDocuments()`）沒有真正讀取這個欄位，一律套用產品全部上游供應商，等於選供應商這一步在合規判斷上沒有作用。同時「供應鏈合規」分頁把「合規文件」與「原料溯源」拆成兩份不相關的清單，使用者看不出這個物料選了誰、缺什麼。

## Goals / Non-Goals

**Goals:**
- 批次的市場文件合規調查／批次護照的合規文件，範圍改為該批次實際選定的供應商，未選定的物料才退回物料核可清單
- 「供應鏈合規」分頁以 BOM 表為主軸，同一張卡片同時看到「選了誰、溯源夠不夠、缺什麼文件」
- 供應商的選定與確認只有一個入口（原物料合規與溯源管理），不重複建立平行的快速確認機制
- 廠區資料只有一個選項時自動預設，減少不必要的手動選擇

**Non-Goals:**
- 不修改 `BatchExportReview` 資料表結構
- 不改變市場合規規則（`MarketComplianceRule`）本身的定義方式
- 不做批次層級的供應商核可（供應商是否「能」用於某物料，仍由物料層 `material_item_suppliers` 決定；批次只決定「這次實際用哪一個」）

## Decisions

**1. `ProductUpstreamResolver::batchSupplierIds(batch, product)`：批次範圍供應商解析，不是新的供應商來源**

沿用既有 `effectiveSuppliersByLine()` 的 BOM 行×供應商對應表，只是在其上疊加「若此 BOM 行在批次原料溯源已有選定供應商，優先採用」的規則。理由：避免新增另一套「批次供應商」概念與資料表，仍以 `raw_material_origins.supplier_id` 為唯一事實來源。

**2. `MarketComplianceChecker::check()` 用可選參數而非新增方法**

`$supplierIdsOverride = null` 保持向後相容——`TradeGoodMarketComplianceController`、`CalculatePathRiskJob` 等既有呼叫端行為完全不變，只有 `BatchExportReviewService` 主動傳入批次範圍供應商。避免為了批次情境複製一份平行的檢查邏輯。

**3. `BatchPassportService::buildSupplyChainCompliance()` 取代平鋪的 `compliance_documents`／`traceability` 顯示**

保留原本 `compliance_documents`／`traceability`／`process_locations` 欄位（其他消費者如 DPP 概念驗證可能還會用到原始平鋪格式），新增 `supply_chain_compliance` 作為前端「供應鏈合規」分頁實際採用的資料結構，逐 BOM 行帶出：`selected_supplier`（實際選定或建議的供應商）、`supplier_confirmed`（是否為批次已選定，而非物料層建議）、`traceability`（該行溯源資料）、`doc_statuses`（該供應商是否具備此物料類別要求的文件）。

**4. 供應商選定與確認只保留一個入口**

原本額外做了一個「確認供應商」快速按鈕＋對應的 `POST /confirm-supplier` 端點，讓使用者不用填完整溯源表單就能確認供應商。上線後判斷這造成兩套維護入口（快速確認 vs. 完整表單）容易長期不一致，予以撤除，改為卡片上的「前往原物料合規與溯源管理確認」導引按鈕（捲動並預選 BOM 物料），供應商的選定與確認統一在「原物料合規與溯源管理」（原「原料溯源」，本次更名並擴大職責範圍）完成。

**5. `Supplier::defaultFacility()`：僅一個廠區時才視為預設，多廠區時回傳 null**

避免多廠區供應商被錯誤假設用哪一個。套用於：
- `MaterialItemSupplierController::store()`：新增核可供應商未指定廠區時自動帶入
- 原物料合規與溯源管理表單：選定實際供應商後自動帶入設施名稱／原產國（不覆蓋已填寫欄位）
- 一次性 migration：回填既有 `material_item_suppliers.supplier_facility_id`

## Risks / Trade-offs

- [風險] `batchSupplierIds()` 對尚未選定實際供應商的物料退回核可清單全部供應商，可能讓合規判斷偏樂觀（誤判「有文件」實際上是別的候選供應商的文件）→ 緩解：前端「供應鏈合規調查」卡片明確標示「建議（未確認）」，提示使用者應盡快完成確認
- [風險] 一次性 migration 回填廠區可能誤判 → 緩解：僅在供應商剛好只有一個廠區時才回填，多廠區一律不動

## Migration Plan

1. `ProductUpstreamResolver::batchSupplierIds()` 新增
2. `MarketComplianceChecker::check()` 新增可選參數，`BatchExportReviewService::checkMarketDocs()` 改用批次範圍供應商
3. `BatchPassportService::buildComplianceDocuments()` 改用批次範圍供應商；新增 `buildSupplyChainCompliance()`
4. 前端：生產批號詳情頁拆分 4 分頁，供應鏈合規調查卡片改用新的 `supply_chain_compliance` 資料
5. `Supplier::defaultFacility()` 新增，套用於核可供應商新增與原物料合規與溯源管理表單
6. 一次性 migration 回填既有 `material_item_suppliers.supplier_facility_id`
7. 撤除快速確認端點與按鈕，改為導引至原物料合規與溯源管理（更名）
8. 部署後以真實資料驗證：batchSupplierIds 對已選定/未選定供應商的物料行為皆正確；migration 回填數量與候選數量一致；供應商選單自動帶入單一廠區資料

## Open Questions

（無）
