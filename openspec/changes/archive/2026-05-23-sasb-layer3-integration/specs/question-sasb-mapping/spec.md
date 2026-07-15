## ADDED Requirements

### Requirement: SAQQuestion 補 SASB 對應欄位
`saq_questions` 表 SHALL 新增 `sasb_topic_id`（nullable UUID FK → sasb_disclosure_topics）和 `sasb_metric_code`（nullable string，如 "EM-IS-110a.1"），允許題目對應到特定 SASB Disclosure Topic 與 Accounting Metric。

#### Scenario: 建立有 SASB 對應的題目
- **WHEN** 管理員在題目 Modal 選擇 SASB Topic 並填入 Metric code
- **THEN** 題目儲存 sasb_topic_id 和 sasb_metric_code，API 回傳包含這兩個欄位

#### Scenario: 建立無 SASB 對應的題目
- **WHEN** 管理員不選 SASB Topic
- **THEN** sasb_topic_id = null，sasb_metric_code = null，題目正常建立

### Requirement: 題目 Modal 補 SASB 欄位
`TemplateDetailView.vue` 的題目新增/編輯 Modal SHALL 在「分類」欄位下方，新增「SASB 揭露主題」下拉（依當前 template 關聯的 Industry 過濾 Topics；無 Industry 時顯示所有 Topics）和「SASB Metric 代碼」text input（選填，placeholder 如 "EM-IS-110a.1"）。

#### Scenario: 範本有關聯 Industry 時下拉篩選
- **WHEN** 範本已關聯 Industry "Iron & Steel Producers"
- **THEN** SASB Topic 下拉只顯示 EM-IS 的 Topics

#### Scenario: 選擇 Topic 後 Metric code 自動填入前綴
- **WHEN** 使用者選擇 Topic "GHG Emissions"（topic_code = "EM-IS-110a"）
- **THEN** Metric code input 自動填入 "EM-IS-110a." 作為前綴提示

### Requirement: 題目列表顯示 SASB 對應
`TemplateDetailView.vue` 的題目列表 SHALL 在每行新增「SASB Topic」欄位，顯示 topic_name（若有），無對應時顯示 "—"。

#### Scenario: 顯示有 SASB 對應的題目
- **WHEN** 題目有 sasb_topic_id
- **THEN** 列表顯示 topic_name（如 "GHG Emissions"）

### Requirement: saq_template_industries 多對多
系統 SHALL 建立 `saq_template_industries` pivot 表，一個範本可關聯多個 SASB Industry，並在 `SettingsView` 的範本新增/列表中體現多 Industry 選擇。

#### Scenario: 範本關聯多個 Industry
- **WHEN** 管理員建立範本並選擇多個 Industries
- **THEN** `saq_template_industries` 有對應筆數
