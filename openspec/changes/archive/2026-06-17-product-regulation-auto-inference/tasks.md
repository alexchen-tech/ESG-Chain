## 1. 資料庫 Migration

- [x] 1.1 新增 migration：`buyer_products` 表加 `inferred_regulations JSON NULL DEFAULT NULL`
- [x] 1.2 執行 `php artisan migrate` 套用至本地 DB
- [x] 1.3 同步 migration 至 Docker 容器並執行

## 2. 後端 Service 推算邏輯

- [x] 2.1 在 `SupplierComplianceStatusService` 定義 `DOC_TYPE_TO_REGULATION` 常數（eudr→EUDR, uflpa→UFLPA, cmrt→CMRT, sds→SDS, ce→CE）
- [x] 2.2 新增 `syncProductInferredRegulations(BuyerProduct $product): array` method：載入 bomLines.materialGroup，推算法規清單，更新 `inferred_regulations`，回傳結果
- [x] 2.3 新增 `syncAllProductsInferredRegulations(): int` method：chunk(100) 遍歷所有產品，呼叫 2.2，回傳處理筆數

## 3. API Endpoint

- [x] 3.1 在 `ComplianceDashboardController` 新增 `syncProductRegulations(BuyerProduct $buyerProduct)` action
- [x] 3.2 在 `routes/api.php` 新增 `POST compliance/products/{buyerProduct}/sync-regulations` 路由
- [x] 3.3 同步 Controller + routes 至 Docker 並 `docker restart esgchain-api` 驗證

## 4. Artisan Command + Scheduler

- [x] 4.1 新增 `app/Console/Commands/SyncProductRegulations.php`，呼叫 Service 的 `syncAllProductsInferredRegulations()`，輸出「已更新 N 筆產品法規」
- [x] 4.2 在 `app/Console/Kernel.php` 的 `schedule()` 加入 `->command('sync:product-regulations')->daily()`
- [x] 4.3 手動執行 `php artisan sync:product-regulations` 驗證全量推算正確

## 5. BuyerProduct Model + API Response

- [x] 5.1 確認 `BuyerProduct` model 的 `$fillable` 含 `inferred_regulations`，`$casts` 加 `'inferred_regulations' => 'array'`
- [x] 5.2 確認 `ComplianceDashboardController::products()` 回傳的資料包含 `inferred_regulations` 欄位

## 6. 前端顯示調整

- [x] 6.1 在 `esgchain-web/src/api/modules/compliance.ts` 的 `DppProduct` interface 新增 `inferred_regulations: string[]`
- [x] 6.2 在 `BuyerProductsView.vue` 產品列表的法規標籤區分顯示：推算來源加小標記（如「系統」chip），人工聲明標記（如「手動」chip）
- [x] 6.3 在 `MaterialComplianceView.vue` DPP 頁籤 drawer 的法規區塊加同樣視覺區分
- [x] 6.4 在 `BuyerProductsView.vue` 產品 edit modal 的 applicable_regulations 改為 checkbox list（EUDR/UFLPA/CMRT/SDS/CE/ESPR 六個選項）
- [x] 6.5 在 `BuyerProductsView.vue` 產品詳情或列表加「重新計算法規」按鈕，呼叫 `POST sync-regulations` endpoint

## 7. 驗證

- [x] 7.1 建立含不同 MaterialGroup 的測試 BomLine，執行 sync，確認 `inferred_regulations` 正確
- [x] 7.2 手動在 UI 勾選 ESPR，確認 `applicable_regulations` 更新且不影響 `inferred_regulations`
- [x] 7.3 執行 `php artisan sync:product-regulations`，確認所有產品更新且 Console 輸出筆數
- [x] 7.4 前端確認法規標籤顯示正確區分推算 vs 人工來源
