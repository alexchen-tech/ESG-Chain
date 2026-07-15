# Spec: saq-project-domain

## 定義

SaqProject 具備 `domain` 屬性，代表此問卷調查專案所採用的評核框架的 **UI 分類標籤**。

`domain` 僅作為 UI 分類與搜尋標籤，不影響計分邏輯。計分框架改由 `saq_templates.scoring_framework` 決定。

## Domain 枚舉值

| 值 | 說明 | Slug prefix 過濾 |
| --- | --- | --- |
| `ESG` | ESG 年度評核 | `esg.*` |
| `ISO20400` | ISO 20400 永續採購稽核 | `iso20400.*` |
| `Geo-Risk` | 地緣政治風險評估 | `geo_risk.*` |
| `Product-Compliance` | 產品合規查核 | `product_compliance.*` |
| `NULL` | 通用型（不過濾） | 全部 slug 參與計分 |

## 行為規則

### 建立專案

- `domain` 在建立時選填，管理員/採購商可選擇 UI 分類標籤
- 未選擇時預設 NULL（未指定 UI 分類），計分框架由 `template.scoring_framework` 決定

### 修改 domain

- 專案狀態為 `draft` 或 `pending` 時可修改 domain
- 專案狀態為 `active`（問卷已發出）或之後，禁止修改 domain（回應 422，說明已有計分紀錄）

### 計分請求

- Laravel 組裝計分 payload 時，讀取 `project.template.scoring_framework` 填入 `scoring_framework`
- `project_domain` 欄位已從計分 payload 完全移除（`SaqScoringRequest`、`CelerySaqScoringRequest`、`calculate_saq_score()`）
- `_resolve_framework()` 簡化為直接回傳 `scoring_framework`，不再有 `project_domain` fallback
- esgchain-ai 收到 `scoring_framework = NULL` 時，不過濾 slug prefix，全部標籤參與計分

## UI 驗收條件

- [x] 框架 badge 讀取 `project.template?.scoring_framework`（非 `project.domain`）
- [x] `SaqProjectDetailView` 移除 domain 下拉選單，框架由範本決定不可在 Project 層修改
- [x] `ReviewDetailView` 讀取 `saq?.project?.template?.scoring_framework`，移除 `?? saq?.project?.domain` fallback
- [x] `TagLibraryController::VALID_DOMAINS` 補上 `'ISO26000'`，與範本驗證清單一致

## Requirements

### Requirement: domain 職責降階為 UI 分類標籤

`saq_projects.domain` 僅作為 UI 分類與搜尋標籤，不影響計分邏輯。計分框架由 `saq_templates.scoring_framework` 決定。

#### Scenario: domain 設定不影響計分結果

WHEN 兩個系列使用同一範本（scoring_framework = "ISO20400"），domain 分別設為 "ESG" 和 "ISO20400"
THEN 兩個系列的供應商分數完全一致（計分邏輯相同）

#### Scenario: domain 仍用於 UI 分類

WHEN 管理員在系列列表篩選 domain = "ESG"
THEN 顯示所有 domain 設為 ESG 的系列（與計分框架無關）

### REMOVED Requirement: domain 驅動 slug prefix 過濾（廢棄）

esgchain-api 組裝計分 payload 時改讀 `project.template.scoring_framework`，不再讀取 `saq_project.domain` 填入 `project_domain`。`project_domain` 欄位在 AI 端的計分 payload 廢棄（過渡期 fallback 保留至下一主版本）。

### REMOVED Requirement: domain = NULL 語意變更

`domain = NULL` 原語意為「全 slug 參與計分（不過濾）」，新語意為「未指定 UI 分類」。計分框架由 `template.scoring_framework` 決定（若範本也是 NULL，則全 slug 參與）。

---

## SaqProject 建立支援 series_id

SaqProject 建立 API SHALL 接受必填的 `series_id` 欄位（此前為選填）。Project 的評核框架（domain）由關聯 series 的範本 `scoring_framework` 決定，不再允許 Project 層自行選擇範本或 domain。

**更新行為：**

- `series_id` 為**必填**；不帶此欄位時回傳 422
- 指定 `series_id` 時，系統 SHALL 驗證 series 存在且 status = 'active'
- `template_id` 從 series 繼承，`template_version` 記錄範本當前版本
- `is_comparable` 預設 true；若 series 下已有不同版本則設 false
- 快照完成後由 `ProjectQuestionService` 從 `assessment_series_weights` 填入 `project_questions.weight`

### Scenario: 建立 Project 並加入 Series

- **WHEN** POST `/api/v1/saq-projects`，帶有有效 series_id
- **THEN** project 建立，`template_id` 從 series 繼承，`template_version` 記錄範本當前版本，`is_comparable` 預設 true（若 series 下已有不同版本則設 false），快照後 project_questions.weight 依 series weight schema 填入

### Scenario: 指定已封存 Series

- **WHEN** POST `/api/v1/saq-projects`，series_id 指向 status = 'archived' 的 series
- **THEN** 系統 SHALL 回傳 422，message: 'Series 已封存，無法加入新專案'

### Scenario: 升版範本的新 Project 自動標記不可比

- **WHEN** 系列已有 template_version = 'v1' 的 Project，範本升版至 v2 後建立新 Project
- **THEN** 新 Project `is_comparable = false`，`template_version = 'v2'`

## REMOVED: Project 建立時自選 domain

**Reason**: domain 語意改由 series → template → scoring_framework 鏈決定，Project 層不再擁有獨立的 domain 選擇。

**Migration**: 現有 `saq_projects.domain` 欄位保留（供歷史資料查閱），但建立新 Project 時不再接受 `domain` 參數；顯示 domain 時讀取 `project.series.template.scoring_framework`。

## 與範本的關係

SaqProject 與 SAQTemplate 是多對一關係（多個專案可使用同一份範本）。`domain` 屬於 series → template 層級，不屬於 project 層級，因此：

- 範本透過 `scoring_framework` 決定計分框架
- 同一份範本可被多個系列使用，不同系列的計分框架由範本決定
- 題目打標時可疊加跨域標籤；slug prefix 過濾在計分時由 `scoring_framework` 決定，與 `domain` 無關
