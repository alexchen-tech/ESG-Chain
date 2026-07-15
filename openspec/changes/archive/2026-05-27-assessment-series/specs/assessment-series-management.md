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
| domain | enum | ESG/ISO20400/Geo-Risk/Product-Compliance/null |
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

## ADDED Requirements

### Requirement: Series CRUD

系統 SHALL 提供 Assessment Series 的建立、列表、詳情、更新、封存操作。

#### Scenario: 建立 Series
WHEN 使用者 POST `/api/v1/assessment-series`，帶有 name、domain（選填）、description（選填）
THEN 系統建立 series，status = 'active'，回傳 201 與 series 資料

#### Scenario: 列表 Series
WHEN 使用者 GET `/api/v1/assessment-series`
THEN 系統回傳所有 series，含 projects_count、latest_project_date

#### Scenario: 封存 Series
WHEN 使用者 POST `/api/v1/assessment-series/{id}/archive`
THEN 系統將 status 改為 'archived'，封存後不可加入新 project

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

### Requirement: 前端 Series 管理頁

系統 SHALL 提供 Series 列表頁（`/questionnaires/series`）與 Series 詳情頁（`/questionnaires/series/:id`）。

#### Scenario: Series 列表頁
WHEN 使用者造訪 `/questionnaires/series`
THEN 頁面顯示所有 series，含名稱、domain、project 數量、最近 project 日期、status；提供「建立系列」按鈕

#### Scenario: Series 詳情頁 - Weight Tab
WHEN 使用者在 Series 詳情頁切換至「Weight 設定」Tab
THEN 頁面顯示 series 下所有已快照的題目（以第一個 project 的 project_questions 為基準），可逐題填入 weight；儲存後 upsert assessment_series_weights
