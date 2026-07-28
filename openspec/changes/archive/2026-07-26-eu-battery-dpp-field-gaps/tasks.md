## 1. 產品 DPP 類別判定

- [x] 1.1 Migration：`sales_products` 新增 `dpp_category`（nullable string）
- [x] 1.2 `SalesProduct::checkDppCategory(string $hsCode): ?string` 新增，電池 HS Code 8507 系列對照表
- [x] 1.3 HS Code 建立或變更時觸發自動判定並寫入；`dpp_category` 可在 update 請求中明確指定為人工覆寫，不被後續無關欄位更新覆蓋（curl 驗證：覆寫為 null 後，僅更新 name 不會被重新判定覆蓋回 battery）
- [x] 1.4 前端銷售產品詳情頁「電池規格」分頁僅 `dpp_category=battery` 顯示（`visibleTabs` computed 依 dpp_category 過濾）

## 2. 電池規格資料模型

- [x] 2.1 Migration：新建 `product_battery_specs`（`sales_product_id` unique FK、`battery_category` enum、`chemistry`、`rated_capacity_ah`、`rated_voltage_v`、`weight_kg`）
- [x] 2.2 同一張表新增關鍵原料回收含量欄位：`lithium_recycled_content_ratio`／`cobalt_recycled_content_ratio`／`nickel_recycled_content_ratio`／`lead_recycled_content_ratio`
- [x] 2.3 同一張表新增效能耐久性欄位：`cycle_life`／`expected_lifetime_years`／`discharge_efficiency_ratio`／`initial_capacity_soh_note`／`operating_temp_range`
- [x] 2.4 新增 `App\Models\ProductBatterySpec`（`$fillable`/`$casts`/`salesProduct()` 關聯），`SalesProduct` 新增 `batterySpec()` hasOne 關聯 + `ProductBatterySpecController`（GET/PUT `/sales-products/{id}/battery-spec`，curl 驗證儲存成功）

## 3. 出口審查與批次護照

- [x] 3.1 `BatchExportReviewService` 新增 `checkBatteryDppFields(SalesProduct $product): array`，逐項獨立 finding（規格完整度、關鍵原料含量、效能耐久性）
- [x] 3.2 `review()` 新增 `($market === 'EU' && $product->dpp_category === 'battery') ? $this->checkBatteryDppFields($product) : []`
- [x] 3.3 `BatchPassportService` 新增 `battery_spec` 輸出區塊，非電池產品回傳 `null`

## 4. 前端

- [x] 4.1 銷售產品詳情頁新增「電池規格」分頁/區塊（僅 `dpp_category = battery` 顯示），含電池類別/化學系統/容量/電壓/重量表單
- [x] 4.2 同區塊新增關鍵原料回收含量與效能耐久性欄位表單
- [x] 4.3 生產批號詳情頁「碳足跡與循環經濟」分頁新增電池規格顯示區塊（`passport.battery_spec` 存在時才渲染）
- [x] 4.4 `vue-tsc` 全專案型別檢查通過

## 5. 部署與驗證

- [x] 5.1 Laravel 檔案與 migration 同步至 esgchain-api 與 esgchain-queue-worker，restart + migrate + config:cache
- [x] 5.2 Vue 檔案同步至 esgchain-web，觸發 HMR
- [x] 5.3 以真實資料驗證：建立 HS Code 8507 系列測試產品，`dpp_category` 自動判定為 `battery`（curl 驗證）；電池規格 GET/PUT 端點皆正常（curl 驗證新建/儲存）；`dpp_category` 人工覆寫後不被無關欄位更新覆蓋（curl 驗證）；測試資料已清除
