# Spec: assessment-series-management

## 概述

Assessment Series 將多個 SaqProject 組織為「系列」，支援跨期 ESG 評核追蹤。Series 定義共用的 weight schema，新加入的 project 自動繼承。

## 資料模型

### assessment_series
| 欄位 | 型別 | 說明 |
|------|------|------|
| id | UUID | 主鍵 |
| name | string | 系列名稱（必填） |
| description | text\|null | 說明 |
| template_id | UUID | FK → saq_templates（必填，建立後不可更換） |
| template_version_at_creation | string | 建立時快照的範本版本號 |
| domain | enum | 廢棄欄位（保留供歷史查閱），值從 template.scoring_framework 讀取 |
| status | enum | active / archived |
| created_by_id | UUID | 建立者 |
| created_at / updated_at | timestamp | |

### assessment_series_weights
| 欄位 | 型別 | 說明 |
|------|------|------|
| id | UUID | 主鍵 |
| series_id | UUID | FK → assessment_series |
| source_template_question_id | UUID | 對應範本題目 id（source of truth） |
| weight | decimal(8,4) | 此題在系列中的權重 |

---

## Requirements

### Requirement: Series CRUD

系統 SHALL 提供 Assessment Series 的建立、列表、詳情、更新、封存操作。建立系列時 SHALL 要求指定 `template_id`，以範本作為系列的評核標準；`domain` 欄位廢棄（保留欄位但不再寫入，框架來源改由 `series.template.scoring_framework` 讀取）。

#### Scenario: 建立 Series（需指定 template_id）
- **WHEN** 使用者 POST `/api/v1/assessment-series`，帶有 name、template_id（必填）、description（選填）
- **THEN** 系統 SHALL 建立 series，status = 'active'，template_version_at_creation 記錄範本當前版本，回傳 201 與 series 資料

#### Scenario: 建立 Series 未帶 template_id
- **WHEN** 使用者 POST `/api/v1/assessment-series`，未帶 template_id
- **THEN** 系統 SHALL 回傳 422，message: 'template_id 為必填欄位'

#### Scenario: 建立 Series 後 domain 為 NULL
- **WHEN** POST `/api/v1/assessment-series` 建立新 Series
- **THEN** `assessment_series.domain` 為 NULL（不從 template.scoring_framework 複製）
- **AND** API 回傳的框架資訊讀自 `series.template.scoring_framework`

#### Scenario: 列表 Series
- **WHEN** 使用者 GET `/api/v1/assessment-series`
- **THEN** 系統 SHALL 回傳所有 series，含 projects_count、latest_project_date、template.name、template.scoring_framework

#### Scenario: 封存 Series
- **WHEN** 使用者 POST `/api/v1/assessment-series/{id}/archive`
- **THEN** 系統 SHALL 將 status 改為 'archived'，封存後不可加入新 project

### Requirement: Series Weight Schema 管理

系統 SHALL 支援對 series 設定各題 weight，權重以 source_template_question_id 為 key。

#### Scenario: 設定 Weight Schema
WHEN 使用者 PUT `/api/v1/assessment-series/{id}/weights`，帶有 weights 陣列（source_template_question_id + weight）
THEN 系統 upsert assessment_series_weights，覆蓋既有設定

#### Scenario: 讀取 Weight Schema
WHEN 使用者 GET `/api/v1/assessment-series/{id}/weights`
THEN 系統回傳此 series 的 weights 陣列

### Requirement: Series 下 Project 管理

系統 SHALL 支援查看 series 下所有 project 及其狀態摘要。

#### Scenario: 查看 Series 下的 Projects
WHEN 使用者 GET `/api/v1/assessment-series/{id}/projects`
THEN 系統回傳此 series 下所有 SaqProject，含 SAQ 數量、平均分數、進度摘要

### Requirement: Project 建立時繼承 Series Weight

系統 SHALL 在 SaqProject 指定 series_id 時，快照完成後自動從 series weight schema 填入 project_questions.weight。

#### Scenario: 繼承 Weight
WHEN 建立 SaqProject 並指定 series_id，且 assessment_series_weights 有資料
THEN 快照後，project_questions.weight 依 source_template_question_id 對應填入 weight

#### Scenario: Series 無 Weight Schema 時的快照
WHEN 建立 SaqProject 並指定 series_id，但 assessment_series_weights 為空
THEN 快照正常執行，project_questions.weight 保持 null，系統記錄警告但不阻擋

#### Scenario: 不指定 Series 的 Project
WHEN 建立 SaqProject 不帶 series_id
THEN project_questions.weight 維持 null（獨立 project 行為不變）

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

### Requirement: 前端 Series 管理頁

系統 SHALL 提供 Series 列表頁（`/questionnaires/series`）與 Series 詳情頁（`/questionnaires/series/:id`）。

#### Scenario: Series 列表頁
WHEN 使用者造訪 `/questionnaires/series`
THEN 頁面顯示所有 series，含名稱、domain、project 數量、最近 project 日期、status；提供「建立系列」按鈕

#### Scenario: Series 詳情頁 - Weight Tab

WHEN 使用者在 Series 詳情頁切換至「Weight 設定」Tab
THEN 頁面顯示 series 下所有已快照的題目（以第一個 project 的 project_questions 為基準），可逐題填入 weight；儲存後 upsert assessment_series_weights

### Requirement: 供應商比較頁折線圖渲染

系統 SHALL 以有效 SVG 座標渲染總分趨勢折線圖。

#### Scenario: 多波次多供應商折線

WHEN 系列含 2 個以上 project，且供應商在各 project 均有分數
THEN 折線圖 SHALL 以連續 polyline 連接各波次分數點，各供應商使用不同顏色；僅連接有分數的波次，null 點不斷線

#### Scenario: SVG 座標系

WHEN 渲染折線圖
THEN SVG SHALL 使用 `viewBox="0 0 1000 200"` 絕對座標，X 軸範圍 50–950、Y 軸範圍 10–190，不使用百分比字串作為 points 屬性值

#### Scenario: 單一波次

WHEN 系列僅有 1 個 project
THEN 折線圖區塊 SHALL 不顯示（`v-if="comparisonData.projects.length > 1"`）

### Requirement: 逐題趨勢矩陣可用性

系統 SHALL 提供可讀性良好的逐題趨勢矩陣。

#### Scenario: 題目欄固定

WHEN 矩陣欄位超出可視區域需橫向捲動
THEN 「題目」欄 SHALL sticky 固定於左側，不隨橫向捲動移動

#### Scenario: 波次標籤縮短

WHEN 渲染矩陣欄標題的波次子標籤
THEN 系統 SHALL 優先提取波次名稱中的 `Q1/Q2/Q3/Q4`、`H1/H2`、或 4 位年份作為標籤，取代直接截斷字串
