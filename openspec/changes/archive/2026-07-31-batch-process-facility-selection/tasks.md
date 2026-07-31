## 1. 資料庫與模型

- [x] 1.1 Migration：新增 `batch_process_facilities` 表（`id` uuid、`production_batch_id`、`process_type`、`supplier_id`、`supplier_facility_id`、timestamps；唯一鍵 `production_batch_id`+`process_type`）
- [x] 1.2 `BatchProcessFacility` model（`HasUuids`，`fillable`，`productionBatch()`/`supplier()`/`supplierFacility()` 關聯）
- [x] 1.3 `ProductionBatch` model 新增 `processFacilities()` 關聯

## 2. 製程推導與候選供應商邏輯

- [x] 2.1 `ProductUpstreamResolver` 新增方法：推導批次相關製程類型清單（BOM 涉及核可供應商 `facility_type` 聯集），每個製程類型附帶候選供應商清單（`supplier_id`/`supplier_facility_id`/名稱/國家）
- [x] 2.2 該方法需與批次已選定的 `BatchProcessFacility` 紀錄合併，標記每個製程類型 `confirmed` 狀態

## 3. API

- [x] 3.1 新增 `BatchProcessFacilityController`：`index()`（查詢批次製程清單）、`store()`（新增/更新選定，`updateOrCreate` by production_batch_id+process_type，驗證候選供應商 `facility_type` 相符）、`destroy()`（清除選定）
- [x] 3.2 `routes/api.php` 新增路由：`GET/POST production-batches/{batchId}/process-facilities`、`DELETE production-batches/{batchId}/process-facilities/{id}`

## 4. 批次護照呈現

- [x] 4.1 `BatchPassportService::buildProcessLocations()` 改寫：改用批次已選定的製程供應商為主，未選定標示「待選定」並列候選清單

## 5. 前端

- [x] 5.1 `esgchain-web/src/api/modules/productionBatch.ts` 新增 `BatchProcessFacility` 型別與 `processFacilityApi`（list/select/clear）方法
- [x] 5.2 `ProductionBatchDetailView.vue` 供應鏈合規分頁「供應鏈製程級地點」區塊改為可編輯：每個製程類型顯示候選供應商下拉選單，已選定顯示廠區資訊+可改選，未選定顯示「待選定」+選單
- [x] 5.3 `vue-tsc --noEmit` 全專案型別檢查通過

## 6. 部署與驗證

- [x] 6.1 Migration 部署（`esgchain-api`，`docker restart` 並跑 migrate；本機環境未見獨立 `esgchain-queue-worker` 容器，本次改動未涉及 Job/Listener，不影響）
- [x] 6.2 Laravel/Vue 檔案同步部署，`route:cache` 重建
- [x] 6.3 真實資料驗證：批號 LOT-2603-013（`019f6954-0b7a-71ba-a33c-70857d64ee74`）BOM 涉及 garment_assembly/manufacturing/printing/wet_processing 四種製程，正確列出候選供應商；選定 garment_assembly 後 `confirmed:true` 且 `passport.process_locations` 同步反映；清除後回到待選定狀態並保留候選清單
