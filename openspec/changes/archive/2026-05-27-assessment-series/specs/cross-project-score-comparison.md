# Spec: cross-project-score-comparison

## 概述

依 assessment_series + supplier_id，以 source_template_question_id 為對齊鍵，跨多個 SaqProject 比較供應商的 raw_score 變化趨勢。

---

## ADDED Requirements

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
      ]
    }
  ]
}
```

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
