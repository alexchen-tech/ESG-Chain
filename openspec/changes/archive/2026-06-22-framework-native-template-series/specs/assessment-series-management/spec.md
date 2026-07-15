## MODIFIED Requirements

### Requirement: Series CRUD

系統 SHALL 提供 Assessment Series 的建立、列表、詳情、更新、封存操作。建立系列時 SHALL 要求指定 `template_id`，以範本作為系列的評核標準；`domain` 欄位廢棄（保留欄位但忽略寫入，值自動從關聯 template 的 `scoring_framework` 讀取）。

#### Scenario: 建立 Series（需指定 template_id）
- **WHEN** 使用者 POST `/api/v1/assessment-series`，帶有 name、template_id（必填）、description（選填）
- **THEN** 系統 SHALL 建立 series，status = 'active'，template_version_at_creation 記錄範本當前版本，回傳 201 與 series 資料

#### Scenario: 建立 Series 未帶 template_id
- **WHEN** 使用者 POST `/api/v1/assessment-series`，未帶 template_id
- **THEN** 系統 SHALL 回傳 422，message: 'template_id 為必填欄位'

#### Scenario: 列表 Series
- **WHEN** 使用者 GET `/api/v1/assessment-series`
- **THEN** 系統 SHALL 回傳所有 series，含 projects_count、latest_project_date、template.name、template.scoring_framework

#### Scenario: 封存 Series
- **WHEN** 使用者 POST `/api/v1/assessment-series/{id}/archive`
- **THEN** 系統 SHALL 將 status 改為 'archived'，封存後不可加入新 project

## ADDED Requirements

### Requirement: Series 升版策略

系統 SHALL 支援系列在範本升版後繼續使用，並透過 `is_comparable` 旗標標記跨版本的可比性。

#### Scenario: 同版本 Project 可比
- **WHEN** 系列下所有 Project 的 template_version 相同
- **THEN** GET `/api/v1/assessment-series/{id}/projects` SHALL 在每個 project 回傳 `is_comparable: true`

#### Scenario: 升版後新 Project 標記不可比
- **WHEN** 系列綁定的範本發布新版本 v2，並在此系列建立新 Project（template_version = 'v2'）
- **THEN** 新 Project SHALL 記錄 `is_comparable: false`，舊 Project 不受影響

#### Scenario: API 回傳系列可比性摘要
- **WHEN** GET `/api/v1/assessment-series/{id}`
- **THEN** response SHALL 含 `comparable_versions_count`（唯一 template_version 數量）和 `has_mixed_versions: true/false`
