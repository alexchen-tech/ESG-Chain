## MODIFIED Requirements

### Requirement: 題目庫管理頁顯示 compliance_domains
題目庫管理頁（`/settings/question-bank`）SHALL 在題目列表與編輯 Modal 中顯示並支援編輯 `compliance_domains`。

#### Scenario: 題目列表顯示合規範疇 chip
- **WHEN** Admin 進入題目庫管理頁
- **THEN** 每道題的列表行 SHALL 顯示 `compliance_domains` 的 chip 標籤（如 `CMRT`、`EUDR`），無值時不顯示

#### Scenario: 題目列表可依合規範疇篩選
- **WHEN** Admin 在篩選列選擇「CMRT」
- **THEN** 列表 SHALL 只顯示 `compliance_domains` 含 `CMRT` 的題目

#### Scenario: 新增題目 Modal 包含 compliance_domains 欄位
- **WHEN** Admin 點擊「新增題目」
- **THEN** Modal SHALL 包含「合規範疇」chip 多選欄位（UFLPA / EUDR / CMRT / SDS / CE），預設全未選

#### Scenario: 編輯題目 Modal 顯示現有 compliance_domains
- **WHEN** Admin 編輯已有 compliance_domains 的題目
- **THEN** Modal SHALL 預填現有選中的 chip

### Requirement: BankImportModal 顯示 compliance_domains chip
在問卷範本編輯頁從題庫匯入題目的 Modal（BankImportModal）中，SHALL 顯示每道題的 `compliance_domains` chip，方便 Admin 識別合規相關題目。

#### Scenario: BankImportModal 顯示合規 chip
- **WHEN** Admin 在範本編輯頁開啟題庫匯入 Modal
- **THEN** 每道題旁 SHALL 顯示 `compliance_domains` chip（有值才顯示，無值不顯示空白佔位）
