# Spec: cross-project-score-comparison

## 概述

依 assessment_series + supplier_id，以 source_template_question_id 為對齊鍵，跨多個 SaqProject 比較供應商的 raw_score 變化趨勢。

---

## Requirements

### Requirement: 跨 Project 分數比較 API

系統 SHALL 提供 `GET /api/v1/assessment-series/{id}/comparison` API，回傳指定供應商在系列內各 project 的逐題分數。

#### Scenario: 取得比較資料
WHEN 使用者 GET `/api/v1/assessment-series/{id}/comparison?supplier_ids[]=<uuid>&supplier_ids[]=<uuid>`
THEN 系統回傳結構：
```json
{
  "series_id": "...",
  "projects": [
    { "id": "...", "name": "...", "created_at": "..." }
  ],
  "suppliers": [
    {
      "supplier_id": "...",
      "supplier_name": "...",
      "scores_by_project": {
        "<project_id>": { "total_score": 82.5, "grade": "B" }
      },
      "question_trends": [
        {
          "source_template_question_id": "...",
          "question_text": "...",
          "scores": { "<project_id>": 0.85, "<project_id_2>": 0.92 }
        }
      ],
      "category_trends": {
        "<l2_pillar_name>": { "<project_id_1>": 72.3, "<project_id_2>": 78.1 }
      }
    }
  ]
}
```

#### Scenario: 同系列、同範本、同 scoring_framework（可比）

WHEN 系列內所有 project 使用同一範本且範本 scoring_framework 相同
THEN 所有 project 的分數可直接比較，顯示趨勢圖與排名

#### Scenario: 跨 domain 但同範本同框架（可比）

WHEN 兩個 project 的 `domain` 不同（如一個 "ESG"、一個 "ISO20400"），但使用同一範本（scoring_framework = "ISO20400"）
THEN 分數仍可比較（domain 為 UI 標籤，不影響計分）

#### Scenario: 不同範本（不可比）

WHEN 比較的 project 使用不同範本
THEN 系統顯示警告「不同範本的分數基礎不同，請謹慎比較」；`total_score` 仍顯示但加上免責標注

#### Scenario: 不同 Project 使用不同範本導致題目不對齊
WHEN 比較的 project 包含不同範本快照，source_template_question_id 不同
THEN 對齊不到的格子 scores 中對應 project_id 的值為 null；不強制範本一致

#### Scenario: 供應商在某 Project 無 SAQ
WHEN 指定 supplier_id 在某 project 下無對應 SAQ 或 SAQ 未完成計分
THEN 該 project 的 total_score 與 grade 為 null；question_trends 對應分數為 null

### Requirement: 前端 Series 詳情頁 - 比較 Tab

系統 SHALL 在 Series 詳情頁提供「供應商比較」Tab，以表格/折線圖呈現跨 project 的分數趨勢。

#### Scenario: 比較 Tab 顯示
WHEN 使用者在 Series 詳情頁切換至「供應商比較」Tab
THEN 頁面顯示：
  - 供應商多選篩選器（預設選取最近有填寫的前 5 家）
  - 橫軸為 project（依建立時間排序），縱軸為 total_score 的折線圖
  - 下方表格逐題列出 raw_score，無資料格子顯示「—」

#### Scenario: 匯出比較資料
WHEN 使用者點擊「匯出 CSV」
THEN 下載包含所有供應商跨 project 逐題 raw_score 的 CSV 檔案（此為 v2 延伸功能，v1 可跳過）

### Requirement: category_scores 跨專案趨勢

比較 API 新增 `category_trends` 欄位，顯示每個 pillar 在各 project 的分數趨勢。可比性條件為「同一份範本（同 template_id）且同一個 scoring_framework」。

#### Scenario: pillar 維度趨勢

WHEN GET `/api/v1/assessment-series/{id}/comparison?supplier_ids[]=...`
THEN response 中每個 supplier 新增：

```json
"category_trends": {
  "採購政策": { "<project_id_1>": 72.3, "<project_id_2>": 78.1 },
  "績效評估": { "<project_id_1>": 55.2, "<project_id_2>": 61.0 },
  "風險管理": { "<project_id_1>": 62.0, "<project_id_2>": 68.5 }
}
```

### Requirement: 跨 Project 分數比較 API — source_template_question_id 回傳值

系統 SHALL 在 `GET /api/v1/assessment-series/{id}/comparison` 回傳的 `question_trends[].source_template_question_id` 欄位中，使用唯一穩定鍵值：

- 若 `project_questions.source_template_question_id` 不為 null：使用原始 UUID
- 若為 null（seeded data 或舊資料）：使用 fallback 字串 `"order:<order>"`（例如 `"order:1"`）

#### Scenario: source_template_question_id 為 null 的題目

WHEN `project_questions.source_template_question_id = null`
THEN 回傳的 `source_template_question_id` SHALL 為 `"order:<order>"` 字串，前端可用此值作為唯一 Map key，矩陣 SHALL 顯示所有題目列

#### Scenario: source_template_question_id 有值的題目

WHEN `project_questions.source_template_question_id` 為有效 UUID
THEN 回傳的 `source_template_question_id` 維持原 UUID 不變

### Requirement: 比較 API 回傳 scoring_model 資訊

系統 SHALL 在 `GET /api/v1/assessment-series/{id}/comparison` 的 `projects` 陣列中，為每個 project 加入最近一次計分使用的 `scoring_model_id`。

#### Scenario: 回傳 scoring_model_id

WHEN 取得系列比較資料
THEN `projects` 陣列每筆 SHALL 包含 `scoring_model_id: string | null`（取該 project 最新 saq_score_snapshot 的 scoring_model_id）

#### Scenario: 不同 scoring_model 的一致性旗標

WHEN `projects` 陣列中存在相鄰兩個 project 的 `scoring_model_id` 不同（且皆不為 null）
THEN API SHALL 在 response root 加入 `scoring_model_inconsistent: true`

### Requirement: 前端不同 scoring_model 警示

系統 SHALL 在折線圖中以視覺方式標示 scoring_model 不一致的波次間隔。

#### Scenario: 不同 model 波次之間顯示虛線

WHEN 相鄰兩波次的 `scoring_model_id` 不同
THEN 折線圖中兩點之間的連線 SHALL 改為虛線（SVG `stroke-dasharray`），並在中點附近顯示警示圖示（⚠）

#### Scenario: Tooltip 說明

WHEN 使用者滑鼠移至虛線段或警示圖示
THEN Tooltip SHALL 顯示「此段波次使用不同計分模型，分數不直接可比」
