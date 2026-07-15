## MODIFIED Requirements

### Requirement: SaqProject 建立支援 series_id

SaqProject 建立 API SHALL 接受必填的 `series_id` 欄位（此前為選填）。Project 的評核框架（domain）由關聯 series 的範本 `scoring_framework` 決定，不再允許 Project 層自行選擇範本或 domain。

#### Scenario: 建立 Project 並加入 Series

- **WHEN** POST `/api/v1/saq-projects`，帶有有效 series_id
- **THEN** project 建立，`template_id` 從 series 繼承，`template_version` 記錄範本當前版本，`is_comparable` 預設 true（若 series 下已有不同版本則設 false），快照後 project_questions.weight 依 series weight schema 填入

#### Scenario: 指定已封存 Series

- **WHEN** POST `/api/v1/saq-projects`，series_id 指向 status = 'archived' 的 series
- **THEN** 系統 SHALL 回傳 422，message: 'Series 已封存，無法加入新專案'

#### Scenario: 升版範本的新 Project 自動標記不可比

- **WHEN** 系列已有 template_version = 'v1' 的 Project，範本升版至 v2 後建立新 Project
- **THEN** 新 Project `is_comparable = false`，`template_version = 'v2'`

## REMOVED Requirements

### Requirement: Project 建立時自選 domain

**Reason**: domain 語意改由 series → template → scoring_framework 鏈決定，Project 層不再擁有獨立的 domain 選擇。

**Migration**: 現有 `saq_projects.domain` 欄位保留（供歷史資料查閱），但建立新 Project 時不再接受 `domain` 參數；顯示 domain 時讀取 `project.series.template.scoring_framework`。
