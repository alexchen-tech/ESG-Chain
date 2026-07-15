## ADDED Requirements

### Requirement: 合規文件到期自動觸發 CAP
系統 SHALL 每日掃描 `expires_at` 在今日起 30 天內（含已過期）的 `SupplierComplianceDoc` 記錄，對每一筆符合條件且尚無有效 CAP 的文件自動建立一個 CAP 與對應的 CAPFinding。

#### Scenario: expiring_soon 文件觸發 CAP
- **WHEN** 排程 Job 執行，發現某份文件 `expires_at` 距今 ≤ 30 天且 > 0 天
- **AND** 該文件不存在 `status IN ('open', 'in_progress')` 的 CAP（以 source_id 比對）
- **THEN** 系統 SHALL 建立 CAP，`source_type = 'compliance_doc'`，`source_id = doc.id`，`priority = 'high'`

#### Scenario: 距到期少於 7 天時 priority 升級為 critical
- **WHEN** 排程 Job 執行，發現文件 `expires_at` 距今 ≤ 7 天
- **THEN** 建立的 CAP `priority` SHALL 為 `'critical'`

#### Scenario: expired 文件觸發 CAP
- **WHEN** 排程 Job 執行，發現文件 `expires_at` 已過今日
- **AND** 該文件不存在 open/in_progress CAP
- **THEN** 系統 SHALL 建立 CAP，`priority = 'critical'`，title 說明文件已過期

#### Scenario: pending 文件不觸發 CAP
- **WHEN** 排程 Job 執行，發現文件 `expires_at = null`（pending 狀態）
- **THEN** 系統 SHALL NOT 為該文件建立 CAP

### Requirement: 重複觸發保護
系統 SHALL 確保同一份合規文件在同一時間只有一個有效 CAP 存在。

#### Scenario: 已有 open CAP 時不重複建立
- **WHEN** 排程 Job 執行，某份文件已存在 `status = 'open'` 的 CAP（`source_id` 相符）
- **THEN** 系統 SHALL NOT 建立新 CAP

#### Scenario: CAP 關閉後文件仍未更新則重新觸發
- **WHEN** 排程 Job 執行，某份文件的所有對應 CAP 均已 `status = 'closed'`
- **AND** 文件仍為 `expiring_soon` 或 `expired`
- **THEN** 系統 SHALL 建立新的 CAP

### Requirement: CAP 來源追蹤
CAP 資料 SHALL 包含 `source_type`（`saq` / `compliance_doc` / `manual`）與 `source_id`（nullable UUID），標記 CAP 的觸發來源。

#### Scenario: 查詢合規文件觸發的 CAP
- **WHEN** 採購商查詢某供應商的 CAP 列表
- **THEN** 由合規文件觸發的 CAP SHALL 呈現 `source_type = 'compliance_doc'`，並可取得對應文件資訊

#### Scenario: 現有 SAQ 觸發 CAP 不受影響
- **WHEN** 透過問卷評核建立 CAP（既有流程）
- **THEN** `source_type` SHALL 為 `'saq'`，`saq_id` 欄位照常填入，`source_id` 同值

#### Scenario: 人工建立 CAP
- **WHEN** 採購商手動建立 CAP（未指定來源）
- **THEN** `source_type` SHALL 預設為 `'manual'`，`source_id` 為 null

### Requirement: Artisan 指令手動觸發
系統 SHALL 提供 Artisan 指令 `compliance:check-expiry` 允許手動觸發或驗證到期掃描邏輯。

#### Scenario: Dry-run 模式預覽
- **WHEN** 執行 `php artisan compliance:check-expiry --dry-run`
- **THEN** 系統 SHALL 列出將被觸發的文件清單與對應動作，但不實際建立 CAP

#### Scenario: 正式執行
- **WHEN** 執行 `php artisan compliance:check-expiry`
- **THEN** 系統 SHALL 建立符合條件的 CAP，並輸出建立筆數摘要
