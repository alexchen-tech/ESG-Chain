## Why

延續 `eu-textile-dpp-field-gaps`（已完成，補齊紡織品類別 DPP 六大強制揭露類別），這次依 EU 電池法規 (EU) 2023/1542 與 ESPR 電池 DPP 資料表盤點電池類別的欄位缺口。跨類別通用的「產品識別／製造商資訊／合規文件／資料管理」四大類系統已有對應欄位可承接，但電池專屬的三大類別（電池類別與化學系統、關鍵原料含量、效能與耐久性）完全沒有資料模型，且系統目前也沒有機制判斷「這個產品屬於 DPP 哪個類別」，無從決定該套用哪一組類別專屬欄位。

## What Changes

- 新增 `SalesProduct::checkDppCategory()`：比照既有 `checkCbamApplicability()` 依 HS Code 前綴判定 CBAM 類別的模式，依 HS Code（電池主要落於 8507 系列）自動判定 `dpp_category`，並允許人工覆寫應付前綴映射覆蓋不到的邊界案例
- 新增 `product_battery_specs` 資料表（一對一掛 `SalesProduct`，比照 `product_packagings` 既有模式）：電池類別（可攜式/工業用/電動車/LMT）、化學系統、額定容量、額定電壓、重量
- 於同一張表新增關鍵原料回收料含量欄位：鋰／鈷／鎳／鉛回收料比例（法規指定金屬，欄位定義與既有紡織品「再生料比例」不同，不沿用 `product_circularity_snapshots`）
- 於同一張表新增效能與耐久性欄位：循環壽命次數、預期使用年限、放電效率、初始容量/SoH、操作溫度範圍（人工填報，無自動判定來源，比照微纖維釋放風險欄位的既有慣例）
- `BatchExportReviewService` 新增電池類別專屬審查方法（僅在 `dpp_category = battery` 時觸發），沿用「每個檢查項目獨立成一個 finding」既有慣例
- `BatchPassportService` 新增電池對應輸出區塊（`battery_spec`），僅在產品屬於電池類別時輸出

## Capabilities

### New Capabilities
- `product-dpp-category-classification`：依 HS Code 自動判定產品所屬 DPP 類別，可人工覆寫
- `battery-dpp-disclosure`：電池類別與化學系統、關鍵原料回收料含量、效能與耐久性資料模型與審查邏輯

### Modified Capabilities
（無——不修改既有紡織品 DPP 相關 requirement，本次是新增電池類別的平行資料模型）

## Impact

- 資料庫：`sales_products` 新增 `dpp_category` 欄位；新增 `product_battery_specs` 表
- 後端：`App\Models\SalesProduct`、新增 `App\Models\ProductBatterySpec`；`App\Services\ProductionBatch\BatchExportReviewService`、`App\Services\ProductionBatch\BatchPassportService`
- 前端：銷售產品詳情頁新增電池規格填寫區塊（僅 `dpp_category = battery` 的產品顯示）；生產批號詳情頁的批次護照/供應鏈合規顯示新增電池對應區塊
- 明確排除：本次僅聚焦電池類別，電子電器/鋼鐵鋁料/家具/輪胎四個類別不在範圍內；不做電池充放電循環數據的自動化匯入；不做外部 DPP 對外 API 認證機制
