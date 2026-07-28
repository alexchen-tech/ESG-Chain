## Why

針對「產品建立 → BOM/物料綁定 → 供應商合規文件 → 生產批號 → 原料溯源 → 出口市場審查 → DDS草稿/批次護照」全流程進行一次稽核，發現 10 項資料一致性缺口與可維護性問題（部分會導致審查結果基於過期資料），需逐項修正並記錄，避免未來重蹈覆轍。

## What Changes

- BOM 行新增/修改/刪除/匯入時，即時觸發 `SalesProduct::syncInferredRegulations()`，不再只靠合規儀表板頁面或排程指令才重算法規推論欄位
- `possibly_stale`（審查結果可能已過期）判斷邏輯統一收斂到 `ProductUpstreamResolver::hasNewerComplianceDocsSince()`，`gateCheck()`／`ddsDraft()`／批次護照（passport）三個彙總端點行為一致
- 批次護照（passport）輸出補上 `program`（本次審查跑的是完整審查還是特定法規範疇）與 `possibly_stale` 欄位
- 新增 `GET production-batches/{batchId}/gate-check` API，讓原本沒有路由曝露的出貨關卡查詢方法可被呼叫
- `MarketComplianceChecker::checkBatch()` 補上可選 `program` 參數，與單筆 `check()` 行為對稱
- DPP 六項檢查（有害物質揭露／微纖維釋放風險／包材資訊／製程級地點／循環經濟／運輸資訊）於程式碼加註標注「產品層級快照」或「批次層級」，避免誤用；部分 finding 文字微調以精確指出資料來源層級
- CBAM 範疇審查、`SupplierComplianceDoc.trade_good_id` 死分支、多市場審查清單摘要欄位語意，皆加註說明目前功能邊界與行為，不做功能擴充

## Capabilities

### Modified Capabilities
- `product-regulation-inference`：新增「BOM 異動即時觸發法規重算」需求，不再只依賴排程或儀表板頁面觸發
- `export-review-queue`：新增「出貨關卡查詢 API」需求；「批次護照」與既有審查結果彙總端點需一致回傳 `program`/`possibly_stale`

## Impact

- 後端：`ProductBomLineService`、`ProductBomLineController`、`BomLineImportService`、`ProductUpstreamResolver`（新增 `hasNewerComplianceDocsSince()`）、`BatchExportReviewService`、`BatchPassportService`、`MarketComplianceChecker`、`ExportReviewQueueService`、`BatchExportReviewController`、`routes/api.php`
- 前端：`esgchain-web/src/api/modules/productionBatch.ts`（型別註解澄清，無結構變更）
- 不影響：未指定 program 時的既有完整審查行為、既有 API 回傳格式（僅新增欄位/新增端點，不刪改既有欄位）
