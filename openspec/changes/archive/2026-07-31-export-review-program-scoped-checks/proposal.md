## Why

「執行審查」選定市場後，一律跑完整套檢查（市場文件規則、EUDR 溯源、UFLPA 溯源、批次 PCF、DPP 六大類別、電池 DPP），使用者無法只針對某個法規範疇（如只想確認 DPP 就緒度，不用連 EUDR 溯源都跑一次）快速檢查，也讓「批號合規」概念上混雜了好幾個彼此獨立的法規義務。

## What Changes

- `market_compliance_rules` 新增「法規範疇」（program：general/dpp/cbam/eudr/uflpa）欄位，既有資料依 `doc_type` 語意回填（EUDR_DDS→eudr、CBAM_REPORT→cbam、UFLPA_DECLARATION→uflpa、DPP_DECLARATION→dpp，其餘→general）
- 「執行審查」新增第二階「法規範疇」下拉選單（選定市場後才有意義），選定後只執行該範疇對應的檢查方法；留空則維持既有「完整審查」行為
- `batch_export_reviews` 新增 `program` 欄位記錄本次審查跑的是完整審查還是特定範疇，審查卡片與 DDS 草稿顯示對應標籤
- 「市場合規規則」設定頁新增「法規範疇」欄位，供管理員為每條規則標記範疇

## Capabilities

### Modified Capabilities
- `export-review-queue`：「執行審查」新增法規範疇篩選能力

## Impact

- 資料庫：`market_compliance_rules` 新增 `program` 欄位（含既有資料回填）；`batch_export_reviews` 新增 `program` 欄位
- 後端：`App\Models\MarketComplianceRule`（新增 `PROGRAMS` 常數）、`App\Models\BatchExportReview`、`App\Services\Compliance\MarketComplianceChecker::check()`（新增可選 `$program` 篩選參數）、`App\Services\ProductionBatch\BatchExportReviewService::review()`（依範疇分流執行對應檢查方法）、`BatchExportReviewController::store()`、兩個 `MarketComplianceRuleRequest`
- 前端：`ExportReviewsView.vue`（法規範疇下拉、審查卡片/DDS 草稿範疇標籤）、`MarketComplianceRulesView.vue`（法規範疇欄位與表單）
- 不影響：未指定範疇時的既有「完整審查」行為完全不變，向下相容
