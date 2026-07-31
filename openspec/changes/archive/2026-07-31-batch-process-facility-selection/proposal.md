## Why

「製程導向盡職調查」（染整→環境足跡、成衣縫製→勞動條件）要能落地，第一步是批號要能記錄「這個批次的每個相關製程，實際是哪個供應商/廠區做的」。目前「供應鏈製程級地點」區塊列出的是**產品 BOM 全部核可供應商**的廠區（product 層級，見 `merge-supply-chain-compliance-origin-cards` 之後的稽核發現），使用者無法從畫面上得知這批貨的染整或成衣縫製實際在哪個工廠執行，之後也就無法針對「這個工廠」掛上對應的環境/勞動盡職調查文件。這次先把「批次×製程→實際供應商」這個選擇動作做出來，後續的製程導向合規檢查（環境/勞動文件類型與檢查邏輯）才有資料基礎，屬於分階段實作的第一步。

## What Changes

- 新增 `BatchProcessFacility` 資料模型：記錄「某生產批號 × 某製程類型 → 使用者選定的實際供應商/廠區」，比照 `RawMaterialOrigin`（批次×BOM行→選定供應商）的既有模式，但這次是批次×製程類型（不綁單一 BOM 行，因為製程類型是跨物料的橫向維度）
- 「這個批次相關的製程類型」由該批次 BOM 涉及的**核可供應商 `facility_type` 聯集**決定（沿用 `ProductUpstreamResolver::supplierSummaries()` 已有的核可供應商推導邏輯，取其 `facility_type` 去重）
- 新增 API：查詢批次相關製程清單（含每個製程類型的候選供應商/廠區、目前選定狀態）、新增/更新/刪除某製程的選定供應商
- 前端「生產批號詳情頁」供應鏈合規分頁，新增可編輯的「製程實際供應商」區塊（比照既有原料溯源卡片的 inline-edit 模式）
- `BatchPassportService::buildProcessLocations()` 改為優先顯示批次已選定的製程供應商，未選定的製程類型標示「待選定」，不再直接等同產品層級全部核可供應商清單

## Capabilities

### New Capabilities
- `batch-process-facility-selection`：批次層級的「製程類型 → 實際供應商/廠區」選定功能

### Modified Capabilities
- `batch-supply-chain-compliance`：「供應鏈製程級地點」呈現邏輯改為批次層級已選定資料為主

## Impact

- 資料庫：新增 `batch_process_facilities` 表（`production_batch_id`/`process_type`/`supplier_id`/`supplier_facility_id`，唯一鍵 `production_batch_id`+`process_type`）
- 後端：新增 `BatchProcessFacility` model、`BatchProcessFacilityController`、`BatchProcessFacilityService`（或掛在既有 Service）；`ProductUpstreamResolver` 新增「批次相關製程類型清單」推導方法；`BatchPassportService::buildProcessLocations()` 改寫
- 前端：`ProductionBatchDetailView.vue` 供應鏈合規分頁新增製程選定 UI；`productionBatch.ts` API 模組新增對應方法與型別
- 不影響：既有原料溯源（`RawMaterialOrigin`）、既有出口審查邏輯（`checkProcessLocation()` DPP 檢查這次不動，只動呈現用的 `buildProcessLocations()`）
- 明確排除範圍：本次不做「染整→環境文件」「成衣縫製→勞動文件」的實際合規檢查邏輯與新 doc_type，那是下一階段基於這次選定資料才能做的事
