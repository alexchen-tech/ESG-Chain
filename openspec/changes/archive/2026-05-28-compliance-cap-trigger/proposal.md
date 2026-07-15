## Why

合規文件（CMRT/EUDR/UFLPA/SDS/CE）到期時，系統沒有自動通知或後續行動機制，採購商只能依賴人工定期查看「合規看板」才能發現問題，容易造成合規缺口。本次變更讓文件到期事件直接觸發 CAP，確保每一個過期風險都有可追蹤的矯正行動。

## What Changes

- **CAP 資料表加入來源欄位** `source_type`（`saq` / `compliance_doc` / `manual`）與 `source_id`（指向 `SupplierComplianceDoc.id`），讓 CAP 可追溯觸發原因
- **新增 Scheduled Job** `ComplianceDocExpiryJob`：每日掃描 `expires_at` 在 30 天內的文件，對尚無 open/in_progress CAP 的文件自動建立 CAP + CAPFinding
- **CAP priority 依到期距離自動設定**：< 7 天 → `critical`，< 30 天 → `high`
- **重複觸發保護**：同一文件若已有 open/in_progress CAP，不重複建立；CAP 關閉後文件仍未更新則下次掃描重新觸發
- **CAP 列表頁顯示來源**：標示「合規文件到期」並可跳轉至對應文件記錄

## Capabilities

### New Capabilities

- `compliance-cap-trigger`: 合規文件到期自動觸發 CAP 的排程邏輯、CAP 來源追蹤、重複保護規則

### Modified Capabilities

- `compliance-document-hub`: CAP 觸發規則作為新的文件生命週期行為，需補充到規格中
- `supplier-compliance-status`: 現有合規狀態規格需說明 `expiring_soon` 狀態如何與 CAP 連動

## Impact

- `esgchain-api`：`caps` 資料表 migration（加 2 欄）、`CAP` model、新增 `ComplianceDocExpiryJob`、`AppServiceProvider` 排程註冊
- `esgchain-web`：CAP 列表頁顯示 source_type badge（合規文件來源）
- 無 breaking change，`saq_id` 保持 nullable，現有 CAP 資料不受影響
