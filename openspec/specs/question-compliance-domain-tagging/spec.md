## ADDED Requirements

### Requirement: 題目合規範疇欄位
`SAQQuestion` SHALL 包含 `compliance_domains` 欄位（JSON array），值域限定為 `['UFLPA', 'EUDR', 'CMRT', 'SDS', 'CE']`，預設為空陣列 `[]`。此欄位標記該題目與哪些物料合規框架相關。

#### Scenario: 題庫題目建立時設定 compliance_domains
- **WHEN** Admin 建立題庫題目並選擇 compliance_domains（如 `["CMRT"]`）
- **THEN** 系統 SHALL 儲存該值，後續查詢可依此欄位篩選

#### Scenario: compliance_domains 超出值域時拒絕
- **WHEN** 請求包含 `compliance_domains: ["UNKNOWN"]`
- **THEN** 系統 SHALL 回傳 422，說明僅允許 UFLPA / EUDR / CMRT / SDS / CE

#### Scenario: 預設為空陣列
- **WHEN** 建立題目時未傳入 compliance_domains
- **THEN** 欄位 SHALL 預設為 `[]`，題目正常建立

### Requirement: 依 compliance_domain 篩選題庫
系統 SHALL 支援以 `?compliance_domain=CMRT` 查詢參數篩選題庫題目，回傳 `compliance_domains` 中包含該值的題目。

#### Scenario: 篩選 CMRT 相關題目
- **WHEN** 採購商請求 `GET /api/v1/question-bank?compliance_domain=CMRT`
- **THEN** 系統 SHALL 只回傳 `compliance_domains` 包含 `"CMRT"` 的題目

#### Scenario: 無篩選時回傳全部
- **WHEN** 請求未帶 `compliance_domain` 參數
- **THEN** 系統 SHALL 回傳全部題庫題目（維持現有行為）

### Requirement: 題庫管理 UI 顯示與編輯 compliance_domains
Admin 在題目庫管理頁（`/settings/question-bank`）的題目建立與編輯 Modal 中 SHALL 能透過 chip 多選器設定 `compliance_domains`。

#### Scenario: Chip 多選器顯示現有值
- **WHEN** Admin 編輯一道已有 `compliance_domains: ["CMRT"]` 的題目
- **THEN** Modal 中 CMRT chip SHALL 呈現選中狀態

#### Scenario: 選擇多個合規範疇
- **WHEN** Admin 點選 UFLPA 與 EUDR chip
- **THEN** 儲存後 `compliance_domains` SHALL 為 `["UFLPA", "EUDR"]`

### Requirement: compliance_domains 複製進範本時一併帶入
當題庫題目被引用至問卷範本（範本編輯頁加入題目）時，系統 SHALL 將 `compliance_domains` 一併複製至範本題目記錄。

#### Scenario: 從題庫匯入題目至範本
- **WHEN** Admin 在範本編輯頁從題庫選入一道 `compliance_domains: ["EUDR"]` 的題目
- **THEN** 該題目在範本內的記錄 SHALL 保留 `compliance_domains: ["EUDR"]`
