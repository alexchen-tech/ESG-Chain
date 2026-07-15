# Design: project-question-snapshot

## DB Schema

### 新 Table：project_questions

```sql
CREATE TABLE project_questions (
  id                           CHAR(36) PRIMARY KEY,
  project_id                   CHAR(36) NOT NULL,
  order                        INT NOT NULL DEFAULT 0,
  question_text                TEXT NOT NULL,
  question_type                ENUM('single','multiple','text','scale','boolean') NOT NULL,
  options                      JSON NULL,
  weight                       DECIMAL(5,4) NULL,   -- 繼承自 series（Change 2），此 change 為 null
  is_required                  TINYINT(1) DEFAULT 1,
  sasb_topic_id                CHAR(36) NULL,
  sasb_metric_code             VARCHAR(50) NULL,
  tags                         JSON NULL,            -- tag 快照 [{id, name, l1_domain}]
  source_bank_question_id      CHAR(36) NULL,        -- 追溯到題庫原題
  source_template_question_id  CHAR(36) NULL,        -- 追溯到範本題
  created_at TIMESTAMP, updated_at TIMESTAMP,
  INDEX idx_project_id (project_id),
  INDEX idx_source_bank (source_bank_question_id)
);
```

### 新 Table：assessment_series（Change 2 使用，此 change 僅建表）

```sql
CREATE TABLE assessment_series (
  id          CHAR(36) PRIMARY KEY,
  code        VARCHAR(100) NOT NULL UNIQUE,
  name        VARCHAR(200) NOT NULL,
  domain      VARCHAR(50) NULL,
  description TEXT NULL,
  created_at TIMESTAMP, updated_at TIMESTAMP
);
```

### 新 Table：assessment_series_weights（Change 2 使用，此 change 僅建表）

```sql
CREATE TABLE assessment_series_weights (
  id                 CHAR(36) PRIMARY KEY,
  series_id          CHAR(36) NOT NULL,
  source_question_id CHAR(36) NOT NULL,
  weight             DECIMAL(5,4) NOT NULL,
  updated_at TIMESTAMP,
  UNIQUE KEY uq_series_question (series_id, source_question_id)
);
```

### 修改 Table：saq_templates

```sql
ALTER TABLE saq_templates
  ADD COLUMN status   ENUM('draft','published','archived') NOT NULL DEFAULT 'published' AFTER version,
  ADD COLUMN draft_of CHAR(36) NULL AFTER status,  -- 自參照：此 draft 從哪個 published 版衍生
  MODIFY COLUMN version INT NOT NULL DEFAULT 1;     -- 版本號改整數
```

資料遷移：現有記錄 status 設為：
- `archived_at IS NOT NULL` → `'archived'`
- 其餘 → `'published'`

### 修改 Table：saq_projects

```sql
ALTER TABLE saq_projects
  ADD COLUMN series_id             CHAR(36) NULL AFTER domain,
  ADD COLUMN template_ref_id       CHAR(36) NULL AFTER series_id,
  ADD COLUMN template_ref_version  INT NULL       AFTER template_ref_id;
```

### 修改 Table：saq_responses

```sql
ALTER TABLE saq_responses
  ADD COLUMN project_question_id CHAR(36) NULL AFTER question_id,
  ADD COLUMN raw_score           DECIMAL(8,4) NULL AFTER evidence_note,
  ADD INDEX idx_pq_id (project_question_id);
```

`question_id` 保留不刪（現有 SAQ = 0 筆，無歷史資料風險，但保持向後相容）。

---

## 快照流程設計

```
SaqProjectController::store()
  1. 建立 saq_projects 記錄（含 template_ref_id, template_ref_version）
  2. 讀取 template.questions（含 tagAssignments）
  3. 批次 INSERT project_questions：
     - 複製所有欄位（question_text, type, options, is_required, etc.）
     - source_bank_question_id = question.source_bank_question_id
     - source_template_question_id = question.id
     - tags = JSON snapshot（question.tags + tagAssignments 合併）
     - weight = null（Change 2 從 series 繼承）
  4. 回傳 project（含 project_questions_count）
```

---

## 範本 Draft/Publish 狀態機

```
published（可快照）
  │
  │ 任何 PUT/PATCH（題目新增/刪除/編輯/排序）
  ▼
draft（有 draft_of 指向 published 版）
  │
  │ POST /publish
  ▼
published（新版號 = 舊版號 + 1）+ 舊版 → archived
```

### API 行為

- `GET /settings/questionnaire-templates/{template}`
  - 若有對應 draft（`draft_of = template.id`）→ 回傳 `has_draft: true, draft_id: xxx`
  - 回傳的永遠是 published 版

- `PUT /settings/questionnaire-templates/{template}` 或任何題目操作
  - 若該範本已有 draft → 直接在 draft 上操作
  - 若無 draft → 先複製一份（version 同、status='draft'、draft_of=template.id），再操作
  - 永遠回傳 draft record 供前端即時更新

- `POST /settings/questionnaire-templates/{template}/publish`
  - draft → status='published', version = published版.version + 1
  - published版 → status='archived', archived_at=now()
  - 刪除 draft_of 自參照

---

## SAQ 讀取路徑切換

### 讀題目（已發送問卷）

```
舊：$saq->template->questions
新：$saq->project->projectQuestions
```

### SAQController::show()、QuestionnaireController::show()

```php
// 舊
$saq->load(['supplier', 'template.questions', 'responses', 'reviewHistories'])

// 新
$saq->load(['supplier', 'project.projectQuestions', 'responses', 'reviewHistories'])
```

### SAQResponse 寫入（供應商填寫）

```php
// 舊
$saq->responses()->updateOrCreate(
  ['question_id' => $questionId], [...]
)

// 新
$saq->responses()->updateOrCreate(
  ['project_question_id' => $projectQuestionId], [...]
)
```

---

## esgchain-ai 評分 Response 擴充

```json
// 現有（保持不變）
{
  "score": 72.5,
  "grade": "B",
  "job_id": "xxx"
}

// 新增欄位（向後相容）
{
  "score": 72.5,
  "grade": "B",
  "job_id": "xxx",
  "question_scores": [
    {
      "project_question_id": "uuid",
      "source_question_id": "uuid-or-null",
      "raw_score": 8.0,
      "max_score": 10.0
    }
  ]
}
```

`SAQController::scoreCallback()` 收到 `question_scores` 後批次更新 `saq_responses.raw_score`（by project_question_id）。

---

## Portal 題目讀取路徑

`SupplierSurveyView.vue` 目前讀取 `questionnaire.template.questions`。

切換後讀取 `questionnaire.project.project_questions`，API response 結構需調整：

```json
// 舊
{ "data": { "template": { "questions": [...] } } }

// 新
{ "data": { "project": { "project_questions": [...] } } }
```

前端對應調整：`questionnaire.template?.questions` → `questionnaire.project?.project_questions`
