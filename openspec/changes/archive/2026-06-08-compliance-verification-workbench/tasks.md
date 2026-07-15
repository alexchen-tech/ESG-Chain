## 1. 後端：Service 新增方法

- [x] 1.1 `SupplierComplianceStatusService::getPendingVerifications()` — 查詢 `verified_at = null` 的文件，with `supplier`，依 `expires_at ASC NULLS FIRST` 排序，組合回傳陣列（含 `missing_expiry`、`uploaded_at`、`supplier_name` 欄位）

## 2. 後端：Controller 與路由

- [x] 2.1 `ComplianceDashboardController::pendingVerifications()` — 呼叫 service，回傳 JSON
- [x] 2.2 `routes/api.php` 新增 `Route::get('compliance/pending-verifications', [ComplianceDashboardController::class, 'pendingVerifications'])`

## 3. 前端：API 型別與呼叫

- [x] 3.1 `src/api/modules/compliance.ts` 新增 `PendingVerificationDoc` interface（id, supplier_id, supplier_name, doc_type, file_name, uploaded_at, expires_at, missing_expiry, status）
- [x] 3.2 `complianceDashboardApi` 新增 `pendingVerifications: () => http.get(...)` 方法

## 4. 前端：MaterialComplianceView — 資料與方法

- [x] 4.1 `activeTab` 型別新增 `'pending'`
- [x] 4.2 `data()` 新增：`pendingDocs: PendingVerificationDoc[]`、`pendingLoading: boolean`、`pendingFilter: { search: string; docType: string; missingExpiry: boolean }`
- [x] 4.3 computed `filteredPendingDocs` — 套用 search（供應商名稱）、docType、missingExpiry 三個前端篩選條件
- [x] 4.4 method `switchToPending()` — 切換 Tab，首次呼叫時觸發 `loadPendingDocs()`
- [x] 4.5 method `loadPendingDocs()` — 呼叫 API，寫入 `pendingDocs`，設定 `pendingLoading`
- [x] 4.6 method `verifyPendingDoc(doc)` — 呼叫 `complianceDocApi.verify(doc.id)`，成功後 splice 移除，isSubmitting 防重複

## 5. 前端：MaterialComplianceView — Template

- [x] 5.1 Tab Bar 新增「待審核」按鈕（`@click="switchToPending"`），標籤後顯示 `({{ pendingDocs.length }})` badge（灰底數字）
- [x] 5.2 新增 `<template v-else-if="activeTab === 'pending'">` 區塊
- [x] 5.3 工作台篩選列：供應商搜尋 input + 文件類型 select + 缺漏有效期 checkbox
- [x] 5.4 清單 table：欄位為「供應商 / 文件類型 / 檔案名 / 上傳日 / 到期日 / 狀態 / 審核」
- [x] 5.5 到期日欄位：`missing_expiry: true` 時顯示紅色「⚠ 未填有效期」，否則顯示日期
- [x] 5.6 審核欄位：sustain/comply/admin 顯示「審核」按鈕（disabled 期間 loading），analyst 不顯示按鈕
- [x] 5.7 空狀態：`filteredPendingDocs.length === 0 && !pendingLoading` 時顯示「所有文件均已完成審核 ✓」

## 6. Docker 同步與驗證

- [x] 6.1 `docker cp esgchain-api/app/. esgchain-api:/app/app/ && docker restart esgchain-api`
- [x] 6.2 `docker cp esgchain-web/src/. esgchain-web:/app/src/`
- [x] 6.3 curl 驗證 `GET /api/v1/compliance/pending-verifications` 回傳含 `missing_expiry` 欄位
- [x] 6.4 瀏覽器確認：合規看板出現「待審核」Tab，清單正確顯示待審文件
- [x] 6.5 瀏覽器確認：點擊「審核」後該列消失、badge 數字減 1
