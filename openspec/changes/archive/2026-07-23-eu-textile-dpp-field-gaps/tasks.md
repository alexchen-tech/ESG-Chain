## 1. 資料庫 migration

- [x] 1.1 `material_items` 新增 `microfiber_release_risk` enum(low/medium/high/not_rated) default not_rated
- [x] 1.2 新建 `product_packagings`（sales_product_id unique FK、recycled_content_ratio、recyclable、reusable、material_description、notes、timestamps）
- [x] 1.3 `supplier_facilities.facility_type` enum 擴充加入 weaving/knitting/dyeing/printing/wet_processing/garment_assembly
- [x] 1.4 `trade_good_suppliers` 新增 `supplier_facility_id` nullable FK → supplier_facilities.id
- [x] 1.5 `raw_material_origins` 新增 `transport_mode` enum(sea/air/road/rail/multimodal/unknown) 與 `transport_distance_km` float，皆 nullable
- [x] 1.6 於 esgchain-api 容器執行 migrate 並驗證 migrate:status

## 2. Model 更新

- [x] 2.1 `MaterialItem` 新增 `microfiber_release_risk` 至 fillable
- [x] 2.2 新增 `App\Models\ProductPackaging`（HasUuids，belongsTo SalesProduct）
- [x] 2.3 `SalesProduct` 新增 `packaging(): HasOne` 關聯
- [x] 2.4 `SupplierFacility`/`SupplierFacilityController` 驗證規則同步更新（in: 規則新增六項製程值）
- [x] 2.5 `TradeGoodSupplier` 新增 `supplier_facility_id` fillable 與 `supplierFacility(): BelongsTo` 關聯
- [x] 2.6 `RawMaterialOrigin` 新增 `transport_mode`/`transport_distance_km` 至 fillable

## 3. 有害物質即時判定服務

- [x] 3.1 新增 `HazardDisclosureService::checkProduct(SalesProduct): array`，回傳 has_hazardous_substance + 明細清單（查該產品 BOM 物料關聯的 ChemicalComplianceAlert，status != resolved）
- [x] 3.2 單元驗證：以既有測試資料手動 curl 驗證至少一個有警示、一個無警示的產品判定結果正確

## 4. 出口審查 checkDppFields 重寫

- [x] 4.1 讀取 `BatchExportReviewService::checkDppFields()` 現況全文，確認呼叫端（review() 組裝邏輯、前端解析）不會因 finding 內容改變而壞掉
- [x] 4.2 重寫 `checkDppFields()`：新增 6 個私有檢查方法（有害物質、微纖維、包材、製程地點、再生料/可回收性、運輸），比照既有 `checkEudrOrigins`/`checkUflpaOrigins` 逐項 finding 結構
- [x] 4.3 確認非 EU market 不觸發這 6 項檢查（沿用既有 EU-only 判斷）

## 5. BatchPassportService 輸出擴充

- [x] 5.1 `buildProduct()`/新增區塊：包材資訊、有害物質判定、微纖維風險彙總
- [x] 5.2 `buildTraceability()`：origins 輸出新增 transport_mode/transport_distance_km；新增製程級地點區塊（來自 TradeGoodSupplier + SupplierFacility）

## 6. 前端表單與顯示

- [x] 6.1 物料主檔表單（MaterialItem 相關 Vue 元件）新增微纖維風險欄位輸入（MaterialItemDetailView.vue：詳情顯示 + 編輯表單 + TS 型別）
- [x] 6.2 銷售產品詳情頁新增包材資訊區塊（SalesProductDetailView.vue「基本資訊」分頁：獨立編輯狀態、顯示/表單雙模式，re-use `.detail-grid`；新增 `ProductPackaging` 型別與 `packaging`/`upsertPackaging` API client）
- [x] 6.3a 供應商詳情頁新增廠區 CRUD（SupplierDetailView.vue「聯絡資訊」分頁：廠區列表 + 新增/編輯 Modal，比照既有聯絡人 Modal 樣式；`facility_type` 前端型別擴充六項製程值；接上既有但先前從未串接的 `facilityApi`）
- [x] 6.3b 產品-供應商關聯新增廠區選擇（SalesProductDetailView.vue：新增「上游供應商」分頁，含新增表單（供應商→動態載入該供應商廠區→廠區下拉）與已連結供應商列表，補回原本從未渲染的 addSupplier 相關邏輯；`TradeGoodService::getUpstreamCompliance()` 新增 `supplier_facility_id`/`supplier_facility_name`/`facility_type` 輸出欄位；curl 驗證新增/列表/移除皆正確反映廠區資訊，`vue-tsc` 通過）
- [x] 6.4 生產批號原料溯源表單新增運輸方式/距離輸入（ProductionBatchDetailView.vue：新增/顯示 + TS 型別，`vue-tsc` 通過）
- [x] 6.5 `ProductionBatchDetailView.vue` 出口審查分頁：確認新的 6 類 finding 能正確顯示（稽核確認既有 `findingDotClass`/label 邏輯為通用 optional-field 判斷，非 exhaustive type check，新 finding 無需改動即可正確渲染）

## 7. 部署與驗證

- [x] 7.1 Laravel 檔案同步至 esgchain-api 與 esgchain-queue-worker，restart + config:cache
- [x] 7.2 Vue 檔案同步至 esgchain-web，觸發 HMR（每個前端檔案異動後皆已 `docker cp` + `touch`，HMR log 確認無錯誤，`vue-tsc` 全數通過）
- [x] 7.3 以真實資料 curl 驗證：建立/更新包材資訊、微纖維風險、製程廠區關聯、運輸資訊皆可正確寫入與讀出
- [x] 7.4 對至少一個 EU market 批次執行出口審查，確認 findings 包含新的 6 類項目且狀態正確（驗證：填入資料前 5 項皆 warning，填入後 dpp_transport/dpp_process_location 正確轉為 pass）
- [x] 7.5 對至少一個批次呼叫 batch passport endpoint，確認新增欄位正確輸出（packaging/hazard_disclosure/microfiber_release_risks/process_locations/traceability.origins.transport_* 皆正確回傳）
