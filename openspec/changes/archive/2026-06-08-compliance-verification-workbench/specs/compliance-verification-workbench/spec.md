## ADDED Requirements

### Requirement: Pending Verifications API

系統 SHALL 提供 `GET /api/v1/compliance/pending-verifications` 端點，回傳所有 `verified_at = null` 的合規文件，依 `expires_at ASC NULLS FIRST` 排序（缺漏有效期者優先，讓審核員最先看到需補填的文件）。

每筆回傳欄位：
- `id`：文件 UUID
- `supplier_id`、`supplier_name`：所屬供應商
- `doc_type`：文件類型
- `file_name`：上傳的檔名
- `uploaded_at`：`created_at`（上傳時間）
- `expires_at`：到期日（nullable）
- `missing_expiry`：boolean，`expires_at = null` 時為 `true`
- `status`：計算屬性（pending / valid / expiring_soon / expired）

#### Scenario: 無待審文件

- **WHEN** 所有文件均已審核（`verified_at IS NOT NULL`）
- **THEN** 回傳 `{ success: true, data: [] }`

#### Scenario: 有待審文件且缺漏有效期

- **WHEN** 文件 `verified_at = null` 且 `expires_at = null`
- **THEN** 該文件出現於清單，`missing_expiry: true`，排序在最前

#### Scenario: 有待審文件且有效期已填

- **WHEN** 文件 `verified_at = null` 且 `expires_at IS NOT NULL`
- **THEN** 該文件出現於清單，`missing_expiry: false`，依到期日排序

#### Scenario: RBAC 限制

- **WHEN** supplier / sup_esg 角色呼叫此端點
- **THEN** 系統 SHALL 回傳 403

### Requirement: Verification Workbench Tab

合規看板（`MaterialComplianceView`）SHALL 新增「待審核」Tab，顯示從 pending-verifications API 取得的文件清單。

#### Scenario: 首次切換至待審核 Tab

- **WHEN** 使用者點擊「待審核」Tab
- **THEN** 系統呼叫 pending-verifications API，顯示載入中 → 完成後呈現清單

#### Scenario: 待審數 KPI badge

- **WHEN** 待審核 Tab 資料載入完成
- **THEN** Tab 標籤旁顯示待審總數 badge（如：待審核 (12)）

#### Scenario: 缺漏有效期警告

- **WHEN** 文件 `missing_expiry: true`
- **THEN** 該列到期日欄位顯示紅色「⚠ 未填有效期」提示

#### Scenario: inline 審核

- **WHEN** sustain / comply / admin 角色點擊某文件的「審核」按鈕
- **THEN** 系統呼叫 POST `/api/v1/compliance-docs/:id/verify`，成功後從清單即時移除該列，待審總數 -1，按鈕期間 disabled

#### Scenario: analyst 角色唯讀

- **WHEN** analyst 角色查看工作台
- **THEN** 清單正常顯示，但「審核」按鈕不存在（隱藏）

#### Scenario: 空狀態

- **WHEN** 所有文件均已審核
- **THEN** 顯示「所有文件均已完成審核 ✓」空狀態訊息

### Requirement: 工作台篩選

工作台 SHALL 提供三種篩選：

1. **供應商搜尋**：文字輸入，即時過濾供應商名稱（前端 filter）
2. **文件類型下拉**：全部 / UFLPA_DECLARATION / EUDR_DDS / CMRT / SDS / CE_DOC / ORIGIN_CERT / OTHER
3. **缺漏有效期**：勾選框，勾選時只顯示 `missing_expiry: true` 的文件

#### Scenario: 組合篩選

- **WHEN** 使用者同時輸入供應商關鍵字並勾選「缺漏有效期」
- **THEN** 清單顯示同時符合兩個條件的文件（AND 關係）

#### Scenario: 清除篩選

- **WHEN** 使用者清空搜尋欄、重設下拉、取消勾選
- **THEN** 清單恢復顯示全部待審文件
