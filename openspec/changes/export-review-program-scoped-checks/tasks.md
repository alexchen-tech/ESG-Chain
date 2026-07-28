## 1. 資料庫與模型

- [x] 1.1 Migration：`market_compliance_rules` 新增 `program` enum，依 `doc_type` 回填既有資料
- [x] 1.2 Migration：`batch_export_reviews` 新增 `program`（nullable）
- [x] 1.3 `MarketComplianceRule::PROGRAMS` 常數新增；`$fillable` 新增 `program`
- [x] 1.4 `BatchExportReview::$fillable` 新增 `program`
- [x] 1.5 兩個 `MarketComplianceRuleRequest` 驗證規則新增 `program`

## 2. 審查邏輯分流

- [x] 2.1 `MarketComplianceChecker::check()` 新增可選 `$program` 參數，篩選規則
- [x] 2.2 `BatchExportReviewService::checkMarketDocs()` 新增 `$program` 參數並轉呼叫
- [x] 2.3 `BatchExportReviewService::review()` 依範疇分流：`checkMarketDocs` 一律呼叫（帶入 program）；EUDR/UFLPA/DPP/電池 DPP 檢查僅範疇相符或完整審查時才呼叫；PCF 一律執行
- [x] 2.4 `gateCheck()` 回傳新增 `program`
- [x] 2.5 `BatchExportReviewController::store()` 接受可選 `program` 並傳入 `review()`

## 3. 前端

- [x] 3.1 `ExportReviewsView.vue` 新增法規範疇下拉（選項對應 `MarketComplianceRule::PROGRAMS`）
- [x] 3.2 `runExportReview()` API 呼叫新增 `program` 參數
- [x] 3.3 審查卡片、DDS 草稿顯示範疇標籤（完整審查 / DPP / CBAM / EUDR / UFLPA / 一般文件）
- [x] 3.4 `MarketComplianceRulesView.vue` 新增「法規範疇」欄位（表格顯示＋表單選擇）
- [x] 3.5 `vue-tsc` 全專案型別檢查通過

## 4. 部署與驗證

- [x] 4.1 Laravel 檔案與 migration 同步至 esgchain-api 與 esgchain-queue-worker，restart + migrate + config:cache
- [x] 4.2 Vue 檔案同步至 esgchain-web，觸發 HMR
- [x] 4.3 以真實資料驗證：既有規則依 doc_type 正確回填範疇（EUDR_DDS→eudr、CBAM_REPORT→cbam、UFLPA_DECLARATION→uflpa、DPP_DECLARATION→dpp，其餘→general）；EU+dpp 範疇審查只產生 market_docs/batch_pcf/dpp_* findings；EU+cbam 範疇審查只產生 market_docs/batch_pcf findings
