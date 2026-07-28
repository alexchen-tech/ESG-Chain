## Context

這次不是新功能開發，是對既有「產品到出口審查」全流程的一次稽核（畫流程圖比對程式碼邏輯），發現 10 項問題（編號 C1–C10），依風險與影響面分三級處理：

- **高**：C1 — BOM 異動不會即時重算 `inferred_regulations`，直接影響出口審查判斷的正確性
- **中**：C3（`possibly_stale` 三處重寫）、C4（passport 遺漏 `program`）、C6（`gateCheck()` 孤兒方法）、C8（DPP 六項檢查產品級/批次級混用未標注）
- **低**：C2（`checkBatch()` 缺 `program` 參數）、C5（cbam 範疇無深度檢查）、C7（`trade_good_id` 死分支）、C10（多市場審查清單摘要欄位語意）

## Goals / Non-Goals

**Goals：**
- 修正會導致審查結果基於過期資料的缺口（C1）
- 統一三個審查結果彙總端點（`gateCheck`/`ddsDraft`/`passport`）的欄位與過期判斷邏輯，避免同一份資料在不同端點顯示不一致的過期狀態
- 補齊沒有路由曝露的既有方法（C6），讓功能邊界清楚可查
- 用註解標注資料粒度混用之處（C8），降低未來誤用風險

**Non-Goals：**
- 不新增 CBAM 深度欄位完整度檢查（C5）——業務規則未明確，這次只標注功能邊界
- 不刪除 `trade_good_id` 查詢分支（C7）——保留但加註明「實務上幾乎未使用」
- 不新增多市場審查歷史版本控制或 `reviewed_markets` 欄位（C10）——評估後判斷現有前端展開面板已可看到批次所有市場審查記錄，非必要

## Decisions

**1. BOM 異動觸發法規重算放在 Service 層，不放 Controller/Observer**

`ProductBomLineService::create/update/delete()` 統一在異動完成後呼叫 `$product->syncInferredRegulations()`；`BomLineImportService::importFromArray()` 匯入流程也在既有 dispatch PCF/化學合規掃描 job 之前同步呼叫。不用 Model Observer 的原因：BOM 行的建立/更新/刪除已經都走 Service 層方法（`ProductBomLineController` 已改為呼叫 Service，不直接操作 model），加在 Service 層職責清楚、也避免 Observer 在批次匯入時對同一產品重複觸發 N 次重算（Service 層可以視情況只在最後統一呼叫一次，Observer 較難做到）。

**2. `possibly_stale` 判斷邏輯收斂到 `ProductUpstreamResolver`，不是留在 `BatchExportReviewService`**

`ProductUpstreamResolver` 已經有 `batchSupplierIds()`（推導一個批次涉及的供應商），新增 `hasNewerComplianceDocsSince(ProductionBatch $batch, SalesProduct $product, ?\DateTimeInterface $since): bool` 是同一類「上游供應商 × 合規文件時間戳」的通用邏輯，放在這裡比綁在審查 Service 裡更合理，`BatchExportReviewService::isPossiblyStale()` 改為委派呼叫此方法。`ExportReviewQueueService::format()` 的批次查詢（`MAX(updated_at)` 一次查完所有批次的供應商最新文件時間，避免 N+1）維持不變——這是清單頁的效能優化，語意與共用方法一致（`SupplierComplianceDoc.updated_at > reviewed_at`），只是換了實作方式，不強制改寫成逐筆呼叫共用方法犧牲效能。

**3. `gateCheck()` 曝露成獨立唯讀 API，不整合進既有 `dds-draft`/`passport` 回應**

`gateCheck()` 的語意是「出貨關卡判斷」（單一市場、是否 blocked），跟 `ddsDraft()`/`passport()` 的「彙總呈現」用途不同，維持獨立端點 `GET production-batches/{batchId}/gate-check?market=EU`，市場為必填 query 參數。

**4. DPP 檢查產品級/批次級的標注只做註解，不重構檢查方法本身**

這是可讀性/可維護性問題，不是邏輯錯誤——目前哪些欄位該是產品級、哪些該是批次級，本身是合理的業務設計（大部分 DPP 揭露欄位本來就是產品固有屬性，只有運輸資訊天生是批次級）。這次只在程式碼加註解讓維護者一眼看懂，不重構資料模型或檢查方法歸屬。

## Risks / Trade-offs

- [取捨] C1 的即時重算若未來 BOM 大量批次匯入（數百行）會對同一產品重複呼叫 `syncInferredRegulations()`，目前 `importFromArray()` 是在有異動時才呼叫一次（非逐行呼叫），風險可控；若未來匯入規模擴大，可考慮改為匯入結束後統一去重呼叫
- [風險] `gateCheck()` 新增路由後，若未來有模組（如 ERP 對接）真的開始呼叫它做出貨判斷，需要額外評估是否要加角色權限限制（目前比照 `dds-draft`/`passport` 沿用同一個 `auth:api` 群組，無額外角色判斷）

## Migration Plan

1. `ProductBomLineService`/`ProductBomLineController`/`BomLineImportService` 補上即時重算觸發（C1）
2. `ProductUpstreamResolver` 新增 `hasNewerComplianceDocsSince()`；`BatchExportReviewService`/`BatchPassportService` 改用共用方法（C3、C4）
3. `BatchExportReviewController` 新增 `gateCheck()` action；`routes/api.php` 新增路由並重建 route cache（C6）
4. `MarketComplianceChecker::checkBatch()` 補上可選 `program` 參數（C2）
5. `BatchExportReviewService`（DPP 相關方法）、`BatchPassportService::buildProcessLocations()` 加註解標注資料粒度（C8）
6. `BatchExportReviewService::review()`（cbam 分支）、`MarketComplianceChecker`／`BatchPassportService`（`trade_good_id` 查詢處）、`ExportReviewQueueService::format()`／前端型別 加註解說明功能邊界與欄位語意（C5、C7、C10）
7. 部署後以真實資料驗證：BOM 新增/刪除後 `inferred_regulations` 即時變化；`passport`/`gate-check` 回傳含 `program`/`possibly_stale`；既有 `checkBatch()` 呼叫端不受影響

## Open Questions

（無）
