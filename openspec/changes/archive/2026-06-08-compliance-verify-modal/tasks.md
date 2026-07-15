## 1. 後端：新增下載端點

- [x] 1.1 `SupplierComplianceDocController::download()` — 從 `Storage::disk('local')->path($doc->file_path)` 取得完整路徑，`Storage::disk('local')->exists()` 檢查存在，存在則 `response()->download()`，否則 404 JSON
- [x] 1.2 `routes/api.php` 新增 `Route::get('compliance-docs/{complianceDoc}/download', [SupplierComplianceDocController::class, 'download'])`

## 2. 後端：擴充 verify 端點

- [x] 2.1 `SupplierComplianceDocController::verify()` 接收 `expires_at`（若 doc `expires_at=null` 則 required，最長 date）與 `notes`（nullable, max:500）
- [x] 2.2 驗證規則：`required_if:expires_at_missing,true`（依 doc 目前值動態判斷），`notes` nullable string max:500
- [x] 2.3 更新邏輯：`expires_at` 只在 doc 原值為 null 時寫入；`notes` 覆蓋寫入

## 3. 前端：API 擴充

- [x] 3.1 `compliance.ts` 的 `complianceDocApi.verify()` 改為接收第二個參數 `body?: { expires_at?: string; notes?: string }`，傳至 POST body
- [x] 3.2 `compliance.ts` 新增 `complianceDocApi.download(docId)` — `http.get(url, { responseType: 'blob' })`，caller 負責建立 object URL 觸發下載

## 4. 前端：MaterialComplianceView — VerifyModal 資料與方法

- [x] 4.1 `data()` 新增 `verifyModal: { open: boolean; doc: PendingVerificationDoc | null; expiresAt: string; notes: string; downloading: boolean; submitting: boolean }`
- [x] 4.2 method `openVerifyModal(doc)` — 設定 `verifyModal.doc`、重置 `expiresAt`/`notes`、`verifyModal.open = true`
- [x] 4.3 method `downloadModalDoc()` — axios blob 下載，建立 `<a>` object URL 觸發，期間 `downloading = true`
- [x] 4.4 method `submitVerify()` — 呼叫 `complianceDocApi.verify(id, body)`，成功後 splice pendingDocs、關閉 Modal；`missing_expiry && !expiresAt` 時前端 guard 攔截

## 5. 前端：MaterialComplianceView — Template 修改

- [x] 5.1 待審核 Table：將「審核」按鈕改為「審核…」，`@click="openVerifyModal(doc)"`
- [x] 5.2 新增 VerifyModal template block（`v-if="verifyModal.open"`，根層級 modal-overlay + modal）
- [x] 5.3 Modal 摘要區：供應商名稱 / 文件類型 / 檔案名稱（font-mono）/ 上傳日期（font-mono）
- [x] 5.4 Modal 下載按鈕：`@click="downloadModalDoc"`，loading 狀態顯示「下載中…」
- [x] 5.5 Modal 有效期 input：`missing_expiry` 時顯示紅色 label「有效期至 *（必填）」，非 missing 時顯示「有效期至（選填，如需修正）」並隱藏（`missing_expiry` 才顯示）
- [x] 5.6 Modal 備註 textarea：placeholder「審核意見（選填）」，max 500 字
- [x] 5.7 Modal 底部：「取消」關閉 Modal；「確認審核」`@click="submitVerify"`，`missing_expiry && !verifyModal.expiresAt` 時 disabled，submitting 期間 loading

## 6. 前端：SupplierComplianceDetailView — 同步套用

- [x] 6.1 import 擴充後的 `complianceDocApi`（已有）
- [x] 6.2 `data()` 新增相同 `verifyModal` 結構
- [x] 6.3 methods 新增 `openVerifyModal(doc)`、`downloadModalDoc()`、`submitVerify()`（邏輯與 MaterialComplianceView 一致，成功後呼叫 `this.loadDocs()`）
- [x] 6.4 template：「審核」按鈕改為「審核…」並 `@click="openVerifyModal(doc)"`
- [x] 6.5 template：新增相同 VerifyModal block（`v-if="verifyModal.open"`）

## 7. Docker 同步與驗證

- [x] 7.1 `docker cp esgchain-api/app/. esgchain-api:/app/app/ && docker cp esgchain-api/routes/api.php esgchain-api:/app/routes/api.php && docker restart esgchain-api`
- [x] 7.2 `docker cp esgchain-web/src/. esgchain-web:/app/src/`
- [x] 7.3 curl 驗證 `GET /api/v1/compliance-docs/:id/download` 回傳 binary（Content-Disposition 含 filename）
- [x] 7.4 curl 驗證 `POST /api/v1/compliance-docs/:id/verify`（帶 `expires_at`）422 情境與正常情境
- [x] 7.5 瀏覽器確認：待審核 Tab 點「審核…」開啟 Modal，下載按鈕可用，missing_expiry 欄位必填驗證，確認後列消失
- [x] 7.6 瀏覽器確認：供應商文件明細頁「審核…」同樣觸發 Modal
