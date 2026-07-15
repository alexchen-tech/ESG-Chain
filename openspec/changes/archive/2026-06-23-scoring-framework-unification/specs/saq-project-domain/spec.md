## REMOVED Requirements

### Requirement: Project.domain 寫入停止

`SaqProjectController::store()` 建立 Project 時不再寫入 `domain` 欄位（欄位保留 nullable，不 DROP）。

#### Scenario: 建立 Project 後 domain 為 NULL
- **WHEN** POST `/api/v1/saq-projects` 建立新 Project
- **THEN** `saq_projects.domain` 為 NULL（不從 template.scoring_framework 複製）
- **AND** API 回傳的框架資訊讀自 `project.template.scoring_framework`

### Requirement: active 後不可改 domain 的鎖定邏輯廢棄

`SaqProjectController::update()` 移除 domain 欄位的驗證規則與 active 鎖定邏輯。框架不可變性由 `template_id` 在 Project 建立後不可修改保障。

### Requirement: VALID_DOMAINS 驗證從 Project 層移除

`SaqProjectController::VALID_DOMAINS` 常數廢棄，domain 欄位不再出現在 update 的驗證規則中。

## MODIFIED Requirements

### Requirement: 前端以 template.scoring_framework 顯示框架

所有讀取 `project.domain` 顯示框架標籤的 UI，改讀 `project.template?.scoring_framework`。

#### Scenario: Project 列表框架 badge
- **WHEN** SaqProjectDetailView 顯示 Project 的框架 badge
- **THEN** 讀 `project.template?.scoring_framework`（非 `project.domain`）
- **AND** template 物件已含於 API 回傳（確認 eager load）

#### Scenario: ReviewDetailView 計分框架顯示
- **WHEN** ReviewDetailView 讀取計分框架以決定顯示邏輯
- **THEN** 讀 `saq?.project?.template?.scoring_framework`（移除 `?? saq?.project?.domain` fallback）

## MODIFIED Requirements（AI service）

### Requirement: 移除 project_domain 死碼

AI service `scoring_service` 清除 `project_domain` 相關邏輯。

#### Scenario: 計分請求不含 project_domain
- **WHEN** AI service 收到計分請求（Celery 或同步）
- **THEN** `scoring_framework` 為唯一框架來源，不再有 `project_domain` fallback
- **AND** `_resolve_framework()` 函式簡化為直接回傳 `scoring_framework`

## ADDED Requirements

### Requirement: TagLibraryController 補全 ISO26000

`TagLibraryController::VALID_DOMAINS` 加入 `'ISO26000'`，與 `QuestionnaireTemplateController` 的驗證清單保持一致。

#### Scenario: 建立 ISO26000 l1_domain 的 tag
- **WHEN** POST `/api/v1/settings/tag-library`，`l1_domain = 'ISO26000'`
- **THEN** 系統 SHALL 允許建立（原本會因 VALID_DOMAINS 缺漏而被拒絕）
