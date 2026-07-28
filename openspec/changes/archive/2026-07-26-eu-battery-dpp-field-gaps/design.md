## Context

`eu-textile-dpp-field-gaps` 已建立「產品層一對一補充表」（`product_packagings`）與「物料層人工填報欄位」（`material_items.microfiber_release_risk`）兩種擴充模式，以及 `BatchExportReviewService::checkDppFields()` 逐項獨立 finding 的既有慣例。電池類別的技術規格（化學系統、容量、關鍵原料回收比例、循環壽命）在概念上跟紡織品完全不同，也跟既有 `product_circularity_snapshots`（由 BOM 物料成分計算而來的泛用再生料比例）語意不同，需要一組新的、電池專屬的資料模型，而非硬套現有欄位。

系統目前沒有任何機制表達「這個產品屬於哪個 DPP 類別」，`cbam_category` 是 CBAM 專用的判定（鋼鐵/水泥/鋁/氫/肥料/電力六類，跟 DPP 類別是兩個不同的分類體系，不可混用）。

## Goals / Non-Goals

**Goals:**
- 建立可依 HS Code 自動判定、且可人工覆寫的 `dpp_category` 欄位，作為後續「這個產品該套用哪一組類別專屬 DPP 欄位」的判斷依據
- 補齊電池類別三大專屬欄位群（分類與化學系統、關鍵原料回收含量、效能與耐久性）的最小可用資料模型
- 電池專屬的出口審查與批次護照輸出，僅在產品被判定為電池類別時觸發，不影響非電池產品既有行為

**Non-Goals:**
- 不做電池以外類別（電子電器/鋼鐵鋁料/家具/輪胎）的欄位盤點
- 不做電池管理系統（BMS）數據的自動化匯入，循環壽命/SoH/效能數據皆為人工填報
- 不重新設計 `product_circularity_snapshots`，電池關鍵原料含量是全新、獨立的欄位群
- 不做外部 DPP API 認證機制

## Decisions

**1. `dpp_category`：`sales_products` 新增欄位，判定邏輯比照 `checkCbamApplicability()` 但獨立實作，不共用 CBAM 對照表**

新增 `SalesProduct::checkDppCategory(string $hsCode): ?string`，回傳 `battery`/`null`（本次僅實作電池判定，其餘類別留 `null`，未來要擴充其他類別時再擴充這個方法/對照表，不預先建通用規則引擎）。電池 HS Code 對照表以 8507 系列為主（鉛酸蓄電池 8507.10-8507.20、鎳氫/鎳鎘 8507.30-8507.50、鋰離子 8507.60、其他 8507.80）。`dpp_category` 允許人工覆寫（`sales_products` 表新增欄位為一般 nullable string，非唯讀衍生欄位，寫入時機比照 `applicable_regulations` 的「AI 推算 + 人工確認」模式：ERP/HS Code 同步時自動判定寫入，前端提供覆寫入口）。

理由：不跟 `cbam_category` 合併成同一欄位，因為兩者分類體系與觸發規則完全不同（CBAM 依進口商品範疇，DPP 依 ESPR 授權法案的產品類別範疇），未來電子電器/家具等類別會有 HS Code 前綴與 CBAM 六類完全不重疊的情況，混用會造成語意混亂。

**2. `product_battery_specs`：一對一掛 `SalesProduct`，比照 `product_packagings` 既有模式，不掛 `MaterialItem`**

理由：這份 DPP 資料表描述的是「電池作為最終上市產品」的規格（EU 電池法規的申報主體是電池本身），對應 ESG-Chain 的 `SalesProduct`（最終銷售品項），不是 BOM 裡的某個物料成分——跟紡織品案例中「微纖維釋放風險是物料層屬性」的情況不同。若未來需要支援「一個產品內含多顆不同規格電池」（電池作為某產品的零組件，而非產品本身），才需要改成 `MaterialItem` 層級或一對多，本次範圍內判斷 ESG-Chain 現有客群（紡織供應鏈）若涉及電池多半是「電池本身作為出口商品」，一對一已足夠，過度設計為一對多不是本次目標。

欄位：`sales_product_id`（unique FK）、`battery_category`（enum: `portable`/`industrial`/`ev`/`lmt`）、`chemistry`（string，如 `Li-ion NMC`/`LFP`/`lead_acid`）、`rated_capacity_ah`（float）、`rated_voltage_v`（float）、`weight_kg`（float）。

**3. 關鍵原料回收含量欄位，併入 `product_battery_specs`，不獨立成表**

新增 `lithium_recycled_content_ratio`／`cobalt_recycled_content_ratio`／`nickel_recycled_content_ratio`／`lead_recycled_content_ratio`（皆 float，百分比，nullable）於同一張表。理由：這些欄位跟電池規格是同一份申報資料、同一個填寫時機（人工一次性填報，不像 `product_circularity_snapshots` 是「重新計算」的快照概念），沒有版本演進或重算需求，獨立成表只會增加不必要的 join。

**4. 效能與耐久性欄位，併入 `product_battery_specs`**

新增 `cycle_life`（integer，循環壽命次數）、`expected_lifetime_years`（integer）、`discharge_efficiency_ratio`（float，百分比）、`initial_capacity_soh_note`（string，初始容量/SoH 說明，因法規只要求揭露方法而非強制單一數值格式，用文字欄位而非結構化數值）、`operating_temp_range`（string，如 `-20°C ~ 60°C`）。理由同上，跟電池規格同一份人工填報表單。

**5. `checkDppFields()` 不擴充，新增平行的 `checkBatteryDppFields()`，僅電池類別觸發**

`BatchExportReviewService::review()` 在既有 `$market === 'EU' ? $this->checkDppFields(...) : []` 之後，新增 `$product->dpp_category === 'battery' ? $this->checkBatteryDppFields($product) : []`。理由：紡織品六大類別跟電池三大類別是平行的類別專屬檢查，不是同一組欄位的擴充，混在同一個方法會讓 `checkDppFields()` 職責混亂（要先判斷類別才知道檢查哪些欄位）；分開成獨立方法，未來每個類別各自一個方法，呼叫端依 `dpp_category` 決定要不要呼叫，結構清楚。

**6. `BatchPassportService` 新增 `battery_spec` 輸出區塊，非電池產品回傳 `null`**

沿用既有 `packaging`/`circularity` 欄位「無資料回傳 `null`，非報錯」的既有慣例。

## Risks / Trade-offs

- [風險] HS Code 8507 系列前綴映射可能不夠精確（電池以外的電子零組件也可能落在鄰近前綴）→ 緩解：`dpp_category` 允許人工覆寫，判定錯誤時使用者可手動修正，不影響其他欄位
- [風險] `product_battery_specs` 目前欄位皆人工填報，初期資料完整度會低，`checkBatteryDppFields()` 初期會大量顯示缺失 → 這是設計目的（提示使用者補齊），比照 `eu-textile-dpp-field-gaps` 同樣的既有取捨
- [取捨] 一對一設計無法表達「一個產品含多顆不同規格電池」的情境 → 本次範圍內判斷非目標，若未來出現真實需求再評估改為一對多或搬到 `MaterialItem` 層級

## Migration Plan

1. Migration：`sales_products` 新增 `dpp_category`；新建 `product_battery_specs`
2. `SalesProduct::checkDppCategory()` 新增，ERP 同步/HS Code 變更時觸發自動判定
3. 新增 `App\Models\ProductBatterySpec`
4. `BatchExportReviewService` 新增 `checkBatteryDppFields()` 與對應私有檢查方法
5. `BatchPassportService` 新增 `battery_spec` 輸出區塊
6. 前端：銷售產品詳情頁新增電池規格填寫表單（僅 `dpp_category = battery` 顯示）；`dpp_category` 顯示與人工覆寫入口
7. 部署後以真實資料驗證：手動建立一筆 HS Code 屬於 8507 系列的測試產品，確認 `dpp_category` 自動判定為 `battery`、填寫電池規格後批次護照正確輸出 `battery_spec`、出口審查正確產生對應 finding

## Open Questions

- 電池規格填寫入口是否需要開放供應商透過 Portal 自行填報，或先僅限內部人員（buyer/comply）——比照 `eu-textile-dpp-field-gaps` 同樣的既有 Open Question，本次同樣先做內部填寫，Portal 開放留待需求方確認後再評估
