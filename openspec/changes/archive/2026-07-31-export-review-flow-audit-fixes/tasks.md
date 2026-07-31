## 1. C1（高）：BOM 異動即時觸發法規重算

- [x] 1.1 `ProductBomLineService` 新增 `update()`/`delete()` 方法（原本只有 `create()`），三方法異動後統一呼叫 `$product->syncInferredRegulations()`
- [x] 1.2 `ProductBomLineController::update()`/`destroy()` 改為呼叫 Service 層方法，不再直接操作 model
- [x] 1.3 `BomLineImportService::importFromArray()` 有新增/更新資料時，於 dispatch 掃描 job 前同步呼叫 `syncInferredRegulations()`
- [x] 1.4 部署驗證：新增/刪除 BOM 行前後比對產品 `inferred_regulations` 即時變化

## 2. C3+C4+C6（中）：審查結果彙總端點一致性

- [x] 2.1 `ProductUpstreamResolver` 新增 `hasNewerComplianceDocsSince()` 共用方法
- [x] 2.2 `BatchExportReviewService::isPossiblyStale()` 改為委派呼叫共用方法
- [x] 2.3 `BatchPassportService::buildExportReviews()` 補上 `program`／`possibly_stale` 欄位
- [x] 2.4 `BatchExportReviewController` 新增 `gateCheck()` action；`routes/api.php` 新增 `GET production-batches/{batchId}/gate-check` 路由並重建 route cache
- [x] 2.5 部署驗證：`passport`/`gate-check` 回傳正確欄位，`export-reviews` 清單行為不變

## 3. C8（中）：DPP 檢查資料粒度標注

- [x] 3.1 `BatchExportReviewService` 各 DPP 子檢查方法加註產品級/批次級標注；`checkDppFields()`/`checkBatteryDppFields()` 加統籌說明
- [x] 3.2 微調易誤導的 finding 文字（明確指向產品層級而非批次）
- [x] 3.3 `BatchPassportService::buildProcessLocations()` 加註解澄清資料來源為產品層級供應商清單
- [x] 3.4 部署 smoke test：`passport` API 回傳結構不變

## 4. C2/C5/C7/C10（低）：功能邊界與語意標注

- [x] 4.1 `MarketComplianceChecker::checkBatch()` 補上可選 `program` 參數，向下相容既有呼叫端
- [x] 4.2 `BatchExportReviewService::review()` cbam 分支加註解說明目前僅檢查文件齊備度
- [x] 4.3 `MarketComplianceChecker`/`BatchPassportService` 的 `trade_good_id` 查詢分支加註解說明保留但實務未使用
- [x] 4.4 `ExportReviewQueueService::format()`／前端 `ExportReviewQueueItem.market` 型別加註解澄清多市場審查摘要語意
- [x] 4.5 部署驗證：`export-reviews`／`checkBatch()` 呼叫端（`market-compliance-batch`）皆正常運作，`vue-tsc` 無新增錯誤

## 5. 文件

- [x] 5.1 proposal/design/specs 記錄本次稽核發現與修正範圍
