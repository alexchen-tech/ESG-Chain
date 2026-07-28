## Context

`BatchExportReviewService::review()` 目前把市場文件規則檢查、EUDR/UFLPA 溯源檢查、批次 PCF、DPP 六大類別、電池 DPP 全部綁在一起執行，這些檢查實際上對應不同的法規義務（一般市場文件、DPP 揭露、CBAM、EUDR 禁伐林、UFLPA 強迫勞動），彼此獨立、審查頻率/急迫性也可能不同。`market_compliance_rules` 目前只有 `market`/`doc_type`/`scope`，沒有法規範疇分類，`MarketComplianceChecker::check()` 撈規則時無法依範疇篩選。

## Goals / Non-Goals

**Goals:**
- 選定市場後可進一步選定法規範疇，只執行該範疇對應的檢查
- 既有「不選範疇＝完整審查」行為完全不變
- 規則的範疇分類可由管理員在既有「市場合規規則」設定頁維護，不需要另開頁面

**Non-Goals:**
- 不做「範疇」與「市場」的多對多複雜規則引擎，範疇是單一列舉欄位，不是規則運算式
- 不改變 `batch_export_reviews` 的 unique key（`production_batch_id`+`market`）——同一批次同一市場只保留最後一次審查結果，不論該次是完整審查或範疇審查；範疇審查會覆蓋掉完整審查的結果（反之亦然），這是既有「重跑覆蓋」行為的延伸，不新增版本控制
- 不修改批次列表頁（`ExportReviewQueueService`）的批次篩選邏輯，範疇篩選只發生在「執行審查」這個動作上，不是清單查詢的篩選維度

## Decisions

**1. `program` 是列舉欄位，不是規則運算式**

`MarketComplianceRule::PROGRAMS = ['general', 'dpp', 'cbam', 'eudr', 'uflpa']`，每條規則歸屬一個範疇。理由：目前的檢查邏輯（`checkEudrOrigins`/`checkUflpaOrigins`/`checkDppFields`/`checkBatteryDppFields`）本來就是寫死的獨立方法對應獨立法規，範疇分類只是把這個既有的心智模型顯性化成一個欄位，不需要更複雜的規則引擎。

**2. `review()` 依範疇分流呼叫既有檢查方法，不重寫檢查邏輯本身**

`$program === null`（完整審查）時所有檢查方法都跑，維持原行為。指定範疇時：
- `checkMarketDocs()` 一律呼叫（把 `$program` 傳給 `MarketComplianceChecker::check()` 篩選規則），因為市場文件規則本身就分散在各個範疇（如 EUDR_DDS 規則歸在 eudr、DPP_DECLARATION 歸在 dpp）
- `checkEudrOrigins()`／`checkUflpaOrigins()`／`checkDppFields()`／`checkBatteryDppFields()` 僅在範疇相符時才呼叫
- `checkBatchPcf()` 一律執行——批次 PCF 是基礎資料完整度檢查，不屬於特定法規範疇

**3. `batch_export_reviews.program` 只記錄「最後一次跑的是什麼」，不做多筆並存**

沿用既有 unique key（`production_batch_id`+`market`），範疇審查與完整審查共用同一筆紀錄、互相覆蓋。理由：如果要讓「範疇審查」與「完整審查」分別保留歷史，需要調整 unique key 或改用一對多模型，屬於更大的架構改動（審查歷史紀錄），這次範圍內不做，先讓範疇篩選的核心價值（「我只想跑 DPP 檢查，不想等 EUDR/UFLPA 都跑一次」）成立即可。已有「覆蓋確認」提示（`export-review-batch-detail-link-and-safeguards` 那次加的）已涵蓋這個風險的告知。

**4. `MarketComplianceChecker::check()` 新增可選參數，不新增方法**

比照 `$supplierIdsOverride` 的既有模式，`$program = null` 時行為不變，向下相容 `TradeGoodMarketComplianceController`、`CalculatePathRiskJob` 等既有呼叫端。

## Risks / Trade-offs

- [風險] 範疇審查覆蓋掉完整審查結果後，`gateCheck()`（供 DDS 草稿讀取）會只反映該範疇的檢查結果，若使用者誤以為這仍是「完整審查」結果會誤判合規狀態 → 緩解：`gateCheck()`/DDS 草稿/審查卡片都新增顯示 `program` 標籤（「完整審查」或範疇名稱），不隱藏這個資訊
- [取捨] 既有 `market_compliance_rules` 資料的範疇分類是依 `doc_type` 名稱猜測回填（如 `ORIGIN_CERT`/`SDS`/`CPSIA_CERT`/`MSA_STATEMENT` 等歸類為 general），管理員若認為分類不準確，需自行到設定頁調整

## Migration Plan

1. Migration：`market_compliance_rules` 新增 `program`，依 `doc_type` 回填既有資料
2. Migration：`batch_export_reviews` 新增 `program`（nullable，記錄最後一次審查範疇）
3. `MarketComplianceRule::PROGRAMS` 常數新增；兩個 `MarketComplianceRuleRequest` 驗證規則新增 `program`
4. `MarketComplianceChecker::check()` 新增可選 `$program` 參數
5. `BatchExportReviewService::review()`／`checkMarketDocs()` 依範疇分流；`gateCheck()` 回傳新增 `program`
6. `BatchExportReviewController::store()` 接受可選 `program`
7. 前端：`ExportReviewsView.vue` 新增法規範疇下拉、審查卡片/DDS 草稿範疇標籤；`MarketComplianceRulesView.vue` 新增法規範疇欄位
8. 部署後以真實資料驗證：EU+dpp 範疇審查只產生 market_docs/batch_pcf/dpp_* 系列 finding，不含 eudr_origins；EU+cbam 範疇審查只產生 market_docs/batch_pcf

## Open Questions

（無）
