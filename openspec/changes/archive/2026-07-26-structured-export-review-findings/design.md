## Context

`MarketComplianceChecker::check()`（`esgchain-api/app/Services/Compliance/MarketComplianceChecker.php:53-64`）已經算出 `results[]`，每筆包含 `doc_type`、`is_mandatory`、`status`（`missing`/`pending`/`expiring_soon`/`expired`/`valid`）、`expires_at`、`supplier_name`（缺 `supplier_id`，但來源 `SupplierComplianceDoc` 本身有這欄，補上不難）。

`BatchExportReviewService::checkMarketDocs()`（`esgchain-api/app/Services/ProductionBatch/BatchExportReviewService.php:132-153`）呼叫上述方法後，只取 `overall` 狀態跟一串用頓號串起來的 `doc_type` 名稱塞進單一 finding 的 `detail` 字串，逐筆的 `status`/`expires_at`/`supplier_id` 全部被丟棄。

`BatchExportReview::updateOrCreate()` 把 `findings`（`finding()` helper產生的 `{check,label,status,detail}` 陣列）整包存成 JSON 欄位，前端 `ProductionBatchDetailView.vue`（review tab）直接照陣列渲染。

## Goals / Non-Goals

**Goals:**
- `checkMarketDocs()` 改成每份文件各自一筆 finding，保留 `doc_type`/`status`/`expires_at`/`supplier_id`/`supplier_name`，不再壓成字串。
- 前端針對非齊備狀態（`missing`/`expiring_soon`/`expired`）的 finding，顯示可點擊連結導向 `/compliance/suppliers/{supplier_id}`（合規管理分頁，可用 query string 帶 `doc_type` 供頁面日後選配定位，本次不強制該頁要接住這個 query 參數，只確保連結本身正確可跳轉到對的供應商）。
- 三種問題狀態（缺失/即將到期/已過期）在畫面上視覺可區分，不再共用一顆 `fail` 紅點。

**Non-Goals:**
- 不做審查歷史留存/稽核軌跡，`BatchExportReview::updateOrCreate()` 覆蓋式儲存維持不變。
- 不做 DDS 草稿的 PDF/檔案產出，`ddsDraft()` 維持純 JSON 顯示。
- 不改 `MarketComplianceChecker`（資料源頭已足夠，只改下游怎麼接住/呈現）。
- 不對 `SupplierComplianceDetailView.vue` 做任何「依 query string 自動篩選/高亮特定 doc_type」的接收端開發——本次只確保連結能正確導到對的供應商頁面。

## Decisions

1. **每份文件一筆 finding，而非把 `results[]`整包塞進單一 finding 的 metadata。**
   理由：`findings` 是給前端直接逐筆渲染用的陣列（既有 `pass 通過`/`fail 未通過` 卡片就是逐筆畫），維持「一筆 finding = 一個畫面上的可獨立操作項目」的既有慣例，比另外塞一個巢狀結構更貼合現有 UI 渲染模式，前端改動也最小。
   - 考慮過的替代方案：維持一筆聚合 finding，但把 `detail` 從字串改成物件塞進去（如 `{missing:[...], expired:[...]}`）。捨棄原因：這樣「一筆 finding 對應一張卡片」的既有 UI 慣例會被打破，卡片內部還要再渲染一層列表，改動範圍反而更大，且未來要對單一文件做動作（如點擊補件）時仍要拆分。

2. **finding 的 `check` 欄位對非齊備文件用 `market_doc:{doc_type}` 當唯一鍵**（例如 `market_doc:ORIGIN_CERT`），文件狀態為 `valid` 且非強制或已通過的項目可以合併成一筆「必備文件齊備」摘要 finding（維持現有「全部通過就顯示一句話」的簡潔體驗），只有出問題的文件才逐筆展開。
   理由：全部文件都合規時逐筆列出 10 幾份「✓ 已備妥」反而增加閱讀負擔，維持現況「全過就一句話」但「有問題就逐筆列」是對使用者最有用的折衷。

3. **`supplier_id` 直接從 `SupplierComplianceDoc` 帶出**（該 model 本來就有這個欄位，`MarketComplianceChecker::check()` 目前組 `results[]` 時漏帶，這次一併補上），不透過 `supplier_name` 反查。

4. **前端連結格式**：`/compliance/suppliers/{supplier_id}?tab=compliance&doc_type={doc_type}`。`SupplierComplianceDetailView.vue` 本次不需要跟著解析這個 query（維持現有行為），純粹是把資訊帶過去、之後若要做「自動跳到合規管理分頁並高亮該文件」可以直接讀這個參數，不用回頭改連結產生端。若 finding 沒有 `supplier_id`（例如文件從未有任何供應商回報過、找不到對應對象），畫面上該筆不顯示連結，只顯示純文字。

## Risks / Trade-offs

- **[Risk] `batch_export_reviews.findings` 是既有 JSON 欄位，資料庫裡舊資料仍是壓平字串格式的 finding，新舊格式會混雜**，直到使用者對每個 batch×market 重新按過一次「執行審查」才會變成新格式。
  → Mitigation：前端渲染時用 duck-typing 判斷（新格式的 finding 帶 `doc_type` 欄位，舊格式沒有），沒有 `doc_type` 就照舊只顯示 `detail` 字串，不強制使用者立刻重跑全部審查；不做資料庫遷移腳本回填舊資料。
- **[Risk] 一個市場可能有 10+ 份必備文件，若大多數都不合規，逐筆展開會讓卡片變得很長。**
  → Mitigation：非本次範圍的視覺優化（如摺疊/分組），先以「資訊正確且可操作」為優先，畫面長度問題留待之後有實際資料回饋再處理。

## Migration Plan

- 純程式碼變更，不需要資料庫 migration（`findings` 本來就是 JSON 欄位，schema 內容變化不需要 DDL）。
- 部署順序：先部署後端（`BatchExportReviewService`），再部署前端（因為前端要能同時相容新舊格式，部署順序理論上互不阻塞，但建議先後端再前端，確保前端上線時至少有機會看到新格式資料）。
- 沒有回滾風險：新格式只在使用者重新執行審查時才產生，舊資料/舊前端仍可正常讀取（前端相容邏輯本身不依賴後端版本）。

## Open Questions

（無）
