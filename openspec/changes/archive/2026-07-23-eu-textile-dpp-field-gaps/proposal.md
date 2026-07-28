## Why

依 EU ESPR/CIRPASS 紡織品 DPP（Digital Product Passport）最小強制揭露欄位規範稽核 ESG-Chain 現有產品資料模型與出口市場審查流程後，發現六大強制類別中有三項完全缺漏（塑膠微纖維釋放風險、包材資訊、供應鏈製程級地點追溯、運輸方式與距離），兩項雖有底層資料但未貫通到可用層（有害物質揭露、再生料比例/可回收性未實際用於審查判定），且出口審查裡名為 `checkDppFields()` 的方法實際只檢查與 DPP 六大類別無關的三個欄位（`model_no`/`hs_code`/`embedded_emissions`），名實不符。這些落差意味著未來若歐盟將紡織品 DPP 揭露列為出口強制要求，系統目前無法產出、也無法審查合規所需的關鍵欄位。

## What Changes

- 新增 `sales_products` 有害物質揭露彙總欄位，由既有 `material_item_chemicals`/`chemical_compliance_alerts` 資料衍生計算，不重複建置有害物質判定邏輯
- 新增塑膠微纖維釋放風險欄位（`material_items` 層級，人工填報，無自動判定來源）
- 新增產品包材資訊資料模型（再生料比例、可回收性、可重複使用性），為全新概念，目前系統完全沒有包材相關資料
- 新增供應鏈製程級地點追溯：在 `trade_good_suppliers` 增加製程類型欄位，並擴充 `supplier_facilities.facility_type` enum 涵蓋紡織關鍵製程（織布、針織、染整、印花、濕製程、成衣製造），使系統能表達「同一產品的不同製程發生在不同地點」
- 新增運輸方式與距離資料模型，掛在 `raw_material_origins` 或新增獨立追蹤表
- 將既有的 `product_circularity_snapshots`（再生料比例、可回收性）實際接入 `BatchExportReviewService` 的出口審查判定與 `BatchPassportService` 的批次護照輸出，而非僅靜態顯示
- 重寫 `BatchExportReviewService::checkDppFields()`：**BREAKING**（審查結果 finding 內容改變）— 不再檢查與 DPP 無關的欄位，改為檢查前述新增/新接入的欄位（有害物質揭露狀態、微纖維風險、包材資訊、製程地點、再生料/可回收性、運輸資訊）是否具備

## Capabilities

### New Capabilities
- `product-hazard-microplastic-disclosure`：SalesProduct 有害物質揭露彙總（衍生自既有化學物質資料）與 MaterialItem 塑膠微纖維釋放風險欄位
- `product-packaging-disclosure`：產品包材再生料比例、可回收性、可重複使用性資料模型
- `supply-chain-process-traceability`：供應鏈製程級（織布/針織/染整/印花/濕製程/成衣製造）地點追溯，擴充既有供應商-產品關聯與廠區類型
- `product-transport-tracking`：原料/成品運輸方式與距離資料

### Modified Capabilities
- `trade-good-market-compliance`：`BatchExportReviewService::checkDppFields()` 檢查邏輯全面改寫，新增再生料比例/可回收性/有害物質/微纖維/包材/製程地點/運輸五類判定，取代原本檢查 model_no/hs_code/embedded_emissions 的邏輯；`BatchPassportService` 批次護照輸出新增這些欄位區塊

## Impact

- 資料庫：新增 migrations（`sales_products` 新增彙總欄位或改由既有服務即時計算不落地、`material_items` 新增微纖維欄位、新增 `product_packagings` 表、`trade_good_suppliers` 新增製程類型欄位、`supplier_facilities.facility_type` enum 擴充、新增運輸追蹤表或欄位）
- 後端：`App\Models\SalesProduct`、`App\Models\MaterialItem`、`App\Models\TradeGoodSupplier`、`App\Models\SupplierFacility`、新增 `App\Models\ProductPackaging`；`App\Services\ProductionBatch\BatchExportReviewService`、`App\Services\ProductionBatch\BatchPassportService`
- 前端：物料/產品/供應商相關表單新增對應輸入欄位；生產批號詳情頁的出口審查/批次護照顯示新增對應區塊（沿用既有 `.detail-grid` 共用樣式，不另開視覺化儀表板）
- 明確排除：不建立通用規則引擎（`market_compliance_rules` 維持 doc_type 單一原語，新檢查邏輯以明確 Service method 撰寫，比照現有 `checkEudrOrigins`/`checkUflpaOrigins` 慣例）；不做外部 DPP 對外 API 認證機制；不做前端視覺化儀表板
