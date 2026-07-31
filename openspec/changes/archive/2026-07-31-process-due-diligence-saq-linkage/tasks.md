## 1. 後端服務

- [x] 1.1 新增 `App\Services\ProductionBatch\BatchProcessDueDiligenceService`，含 `PROCESS_RISK_DIM_MAP` 常數（dyeing/wet_processing/printing → dim_e1；garment_assembly → dim_e3）
- [x] 1.2 `build(ProductionBatch $batch): array` 方法：呼叫 `ProductUpstreamResolver::batchProcessTypes()`，篩選對應表內製程類型，依 `confirmed` 狀態分流查 `Supplier::latestRiskAssessment()`，輸出 `status`（assessed/not_assessed/pending_selection）、`risk_level`、`score`、`dimension`、`dimension_label`

## 2. API

- [x] 2.1 新增 controller action（可掛在 `BatchProcessFacilityController` 或新增獨立 controller，依實作時判斷）`dueDiligence(string $batchId)`
- [x] 2.2 `routes/api.php` 新增路由 `GET production-batches/{batchId}/process-due-diligence`

## 3. 批次護照

- [x] 3.1 `BatchPassportService::build()` 新增 `process_due_diligence` 區塊，呼叫 `BatchProcessDueDiligenceService::build()`

## 4. 前端

- [x] 4.1 `esgchain-web/src/api/modules/productionBatch.ts` 新增型別與 `processDueDiligenceApi.list()` 方法
- [x] 4.2 `ProductionBatchDetailView.vue` 製程卡片（`batch-process-facility-selection` 已建立的區塊）已選定供應商旁新增風險等級徽章，沿用既有 risk-level badge 樣式；徽章旁提供連結導向供應商詳情頁
- [x] 4.3 `vue-tsc --noEmit` 全專案型別檢查通過

## 5. 部署與驗證

- [x] 5.1 Laravel 檔案部署（`esgchain-api`，`docker restart`，`route:cache` 重建）
- [x] 5.2 Vue 檔案部署（`esgchain-web`，`touch` 觸發 HMR）
- [x] 5.3 真實資料驗證：挑一個染整/成衣縫製製程已選定供應商、且該供應商有 `RiskAssessment` 紀錄的批次，確認徽章正確顯示風險等級；挑一個供應商無評分紀錄的情境，確認顯示「尚未完成評分」；確認 `gateCheck()`/出口審查結果不受影響；確認 `passport.process_due_diligence` 正確輸出
