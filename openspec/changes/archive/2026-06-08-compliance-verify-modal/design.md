## Context

`SupplierComplianceDoc` 已有 `notes`（text nullable）、`expires_at`（datetime nullable）、`verified_at`、`verified_by` 欄位。`file_path` 儲存在 Laravel local storage。`Storage` facade 已 import 於 controller。

現有 `POST /compliance-docs/:id/verify` 不接收任何 body，直接寫入 `verified_at = now()`。

前端有兩個審核入口：
1. `MaterialComplianceView`（待審核 Tab）— 上一 change 新增，目前 inline 按鈕
2. `SupplierComplianceDetailView`（供應商文件明細）— 既有「審核」按鈕

## Goals / Non-Goals

**Goals:**
- 後端新增文件下載端點，Stream 回傳 binary
- 後端 verify 端點接收 `expires_at`（missing_expiry 時 required）與 `notes`（optional）
- 前端統一 VerifyModal 元件，在兩個入口使用相同 Modal 邏輯
- Modal 顯示：供應商名稱 / 文件類型 / 檔案名稱 / 上傳日期 / 缺漏有效期警告 / 下載按鈕 / expires_at input / notes textarea / 確認送出

**Non-Goals:**
- 不實作 in-browser PDF 預覽（直接下載即可）
- 不修改 Portal 供應商上傳流程
- 不實作批次審核

## Decisions

**決策 1：VerifyModal 實作在 MaterialComplianceView，SupplierComplianceDetailView 傳入 prop 觸發**

兩個入口文件結構略有差異（PendingVerificationDoc vs SupplierComplianceDoc），Modal 統一接收 `{ id, supplier_name, doc_type, file_name, uploaded_at, missing_expiry, expires_at }` 共用 shape，由呼叫端映射。

**決策 2：download 使用瀏覽器 `window.open` 帶 JWT token 作為 query param**

`<a href>` 無法帶 Authorization header。選項有兩種：
- `window.open` 帶 `?token=...` query param（後端從 query 解析 JWT）
- axios `responseType: blob` + 前端建立 object URL

選擇後者（axios blob）：不需後端改動認證邏輯，安全性較高（token 不出現在 URL log）。

**決策 3：expires_at 在 verify body 中更新，而非額外 PATCH**

在 verify 時一次寫入 `expires_at`（如有提供）、`notes`、`verified_at`、`verified_by`，避免多次 API 呼叫。後端只在 `expires_at` 原本為 null 時才允許通過 verify body 更新（已有值時忽略 body 中的 expires_at，以避免覆蓋供應商填寫的正確日期）。

**決策 4：SupplierComplianceDetailView 不重構為子元件，直接複用 Modal data/methods**

兩個 View 都是 Options API，直接在各自 `data()` 加 verifyModal 狀態即可，不需要抽 mixin 或子元件。邏輯可複製，維護成本低。

## Risks / Trade-offs

- **下載授權**：使用 axios blob，token 在 Authorization header，Storage local disk 需確認 Laravel 可讀取。若 storage 路徑不正確，回傳 404 而非 500。
- **expires_at 覆蓋風險**：決策 3 限制只在原本為 null 時才更新，避免覆蓋已有值。
