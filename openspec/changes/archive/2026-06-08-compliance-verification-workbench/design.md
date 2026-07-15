## Context

`SupplierComplianceDoc` 已有 `verified_at`（datetime, nullable）與 `verified_by`（uuid, nullable）欄位。`status` 為計算屬性（由 `expires_at` 決定：null→pending / 過期→expired / 30天內→expiring_soon / 其他→valid），與 `verified_at` 完全獨立。後端已有 `POST /compliance-docs/:id/verify` 與 `DELETE /compliance-docs/:id/verify` 端點。

`MaterialComplianceView` 已有 supplier / product / matrix / dpp 四個 Tab，合規看板路由 `/compliance` 限 sustain / comply / analyst / admin 存取。

## Goals / Non-Goals

**Goals:**
- 新增後端端點回傳全部待審文件（`verified_at = null`），含供應商資訊與缺漏有效期警告
- 合規看板新增「待審核」Tab，顯示工作台清單
- 支援篩選：供應商搜尋、文件類型、缺漏有效期（`expires_at = null`）
- inline 審核按鈕：呼叫既有 verify 端點，審核後從清單即時移除
- 頂部 KPI：待審總數

**Non-Goals:**
- 不修改合規計算邏輯（verified_at 不影響 compliant 判斷）
- 不實作批次審核
- 不新增電子郵件通知
- analyst 角色可查看清單但無審核按鈕（唯讀）

## Decisions

**決策 1：後端在 Service 層聚合，不用 Eloquent scope**

`getPendingVerifications()` 直接 `SupplierComplianceDoc::whereNull('verified_at')->with('supplier')->orderBy('expires_at')->get()`，在 PHP 層組合回傳結構。理由：資料量可控（文件數通常 < 1000），不需要分頁複雜度；未來若需分頁可再加。

**決策 2：缺漏有效期（`expires_at = null`）作為獨立警告欄位回傳**

回傳 `missing_expiry: bool`，前端用紅色警告 icon 標示。業務語意：供應商上傳但未填有效期，審核員需要追蹤補填。

**決策 3：審核後前端 splice 移除，不重新載入整個清單**

`verifyDoc(doc)` 呼叫 verify API 成功後，直接從 `pendingDocs` array 中移除該筆，更新 KPI count。避免重載整頁造成審核員失去捲動位置。

**決策 4：Tab 切換時才載入資料（lazy load）**

與 matrix / dpp tab 一致，切換到「待審核」Tab 才觸發 API，初始 mount 不呼叫。

## Risks / Trade-offs

- **即時性**：若兩個審核員同時操作同一份文件，後者點「審核」時後端仍正常執行（冪等），前端清單可能顯示已被審核的項目。接受此 edge case，不加 lock 機制。
- **無分頁**：若待審文件數超過數百筆，初始載入稍慢。當前供應商規模下（< 50 家），風險低。
