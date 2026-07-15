# Delta Spec: cross-project-score-comparison

## MODIFIED Requirements

### Requirement: 可比性條件改為同範本 + 同 scoring_framework

**原規格**：同 assessment_series 內的所有 project 可跨專案比較分數。

**新規格**：跨專案比較的前提是「同一份範本（同 template_id）且同一個 scoring_framework」，確保計分基礎完全一致。

#### Scenario: 同系列、同範本、同 scoring_framework（可比）

WHEN 系列內所有 project 使用同一範本且範本 scoring_framework 相同
THEN 所有 project 的分數可直接比較，顯示趨勢圖與排名

#### Scenario: 跨 domain 但同範本同框架（可比）

WHEN 兩個 project 的 `domain` 不同（如一個 "ESG"、一個 "ISO20400"），但使用同一範本（scoring_framework = "ISO20400"）
THEN 分數仍可比較（domain 為 UI 標籤，不影響計分）

#### Scenario: 不同範本（不可比）

WHEN 比較的 project 使用不同範本
THEN 系統顯示警告「不同範本的分數基礎不同，請謹慎比較」；`total_score` 仍顯示但加上免責標注

## ADDED Requirements

### Requirement: category_scores 跨專案趨勢

**原規格**：比較 API 只回傳 `total_score` 與 `grade`。

**新規格**：比較 API 新增 `category_scores_by_project` 欄位，顯示每個 pillar 在各 project 的分數趨勢。

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
