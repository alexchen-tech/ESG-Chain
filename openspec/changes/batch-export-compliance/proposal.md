## Why

出口申報目前停留在「出貨單層級」（Shipment.target_market + EUDR DDS 草稿），無法回答「**這一批貨**能不能出口到某市場」。實務上同一批號可分路出貨到多個市場，各市場的永續產品規範（EUDR/UFLPA/REACH/CBAM/ESPR-DPP）要求的資料面不同；且外部系統（DPP 平台、報關/出口合規系統）需要一個標準化的批次資料包對接。

## What Changes

- **批號×市場合規審查**：新增 `batch_export_reviews`（一批可多市場，每「批次×市場」一筆審查紀錄），依該市場的合規規則與資料完備度產出 pass/warning/fail 與逐項 findings。
- **市場規範資料整合**：審查引擎按市場整合資料面——文件規則（`market_compliance_rules`×供應商文件）、EUDR 溯源（GPS 地塊/收穫年）、UFLPA 棉花產地佐證、批次 PCF、DPP 欄位完備度（型號/HS/內含碳排）。
- **批次資料包 API**：對外唯讀端點以批號取「批次護照」JSON（產品識別/批次事實/T1–T4 供應鏈/原料溯源/文件狀態/審查結論），採 **X-Api-Key** 認證（金鑰存 `system_settings`，可換發），供 DPP 或其他出口合規系統對接。
- UI：生產批號 Drawer 新增「出口市場審查」區塊（設定市場、執行審查、檢視 findings）。

## Capabilities

### New Capabilities
- `batch-export-compliance`: 批次×市場出口合規審查（審查引擎、狀態機、findings 結構）與批次資料包對外 API（API Key 認證）。

### Modified Capabilities
<!-- 無既有 spec 需求變更：production-batch-management 僅新增區塊，market-compliance-rules 為讀取重用 -->

## Impact

- **esgchain-api**：migration（`batch_export_reviews`、`export_api_key` seed）；`BatchExportReviewService`（規則檢核屬業務流程，非計分，依慣例放 Laravel，先例 `MarketComplianceChecker`）；內部端點（JWT）＋對外端點（ApiKey middleware）。
- **esgchain-web**：`ProductionBatchesView` Drawer 新增出口市場審查區塊。
- **重用**：`market_definitions`、`market_compliance_rules`、`MarketComplianceChecker`、`RawMaterialOrigin`、批次 PCF。
