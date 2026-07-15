## ADDED Requirements

### Requirement: 範本列表頁路由
`/questionnaires/templates` SHALL 渲染 `QuestionnaireTemplatesView.vue`，為問卷範本設計的主入口頁面。

#### Scenario: 點擊 sidebar 問卷範本設計
- **WHEN** 使用者點擊 ESG 問卷 > 問卷範本設計
- **THEN** 導覽至 /questionnaires/templates，顯示範本列表

### Requirement: 三 Tab 狀態分類
範本列表 SHALL 分為「啟用」「停用」「封存」三個 Tab，分別顯示對應狀態的範本。切換 Tab 時重新載入對應清單。

#### Scenario: 啟用 Tab
- **WHEN** 選中「啟用」Tab
- **THEN** 顯示 is_active=true 且 archived_at IS NULL 的範本

#### Scenario: 封存 Tab
- **WHEN** 選中「封存」Tab
- **THEN** 顯示 archived_at IS NOT NULL 的範本，每列操作只有「取消封存」

### Requirement: 範本列表欄位與操作
每個範本列 SHALL 顯示：名稱、版本（font-mono）、題目數量、SASB 產業（若有）、建立時間，及操作按鈕（進入詳情 / 啟停用 / 複製 / 封存）。

#### Scenario: 點擊進入詳情
- **WHEN** admin 點擊「編輯題目」
- **THEN** router.push(`/questionnaires/templates/{id}`)

#### Scenario: 空列表
- **WHEN** 某 Tab 無範本
- **THEN** 顯示引導文字及「+ 新增範本」按鈕

### Requirement: 新增範本 Modal
列表頁 SHALL 提供「+ 新增範本」按鈕，開啟 Modal，欄位：名稱（必填）、版本（預設 1.0.0）、描述（選填）；建立後跳轉至新範本的 TemplateDetailView。

#### Scenario: 建立後跳轉
- **WHEN** 新增範本成功
- **THEN** router.push(`/questionnaires/templates/{newId}`)
