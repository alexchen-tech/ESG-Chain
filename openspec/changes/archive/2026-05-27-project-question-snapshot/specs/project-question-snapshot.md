# Spec: project-question-snapshot

## 概述

SaqProject 建立時，將範本題目完整快照進 `project_questions` 獨立 table，之後 SAQ 讀取題目來源為 `project_questions`，與 `saq_templates` 完全解耦。

## 快照觸發條件

- 觸發點：`POST /api/v1/saq-projects`（建立專案）
- 觸發時機：建立 project record 後立即執行
- 範本必須為 `status = 'published'`（draft 不可被快照）

## project_questions 欄位

| 欄位 | 來源 | 說明 |
|------|------|------|
| project_id | 當前專案 | |
| order | template question.order | 快照排序 |
| question_text | template question.question_text | 快照 |
| question_type | template question.question_type | 快照 |
| options | template question.options | 快照 |
| weight | null | Change 2 從 series 繼承 |
| is_required | template question.is_required | 快照 |
| sasb_topic_id | template question.sasb_topic_id | 快照（參考用，非強制外鍵） |
| sasb_metric_code | template question.sasb_metric_code | 快照 |
| tags | JSON snapshot | tagAssignments 合併展平 |
| source_bank_question_id | template question.source_bank_question_id | 追溯題庫原題 |
| source_template_question_id | template question.id | 追溯範本題 |

## SAQ 讀取行為

- `GET /api/v1/questionnaires/{id}` → load `project.projectQuestions`
- `GET /api/v1/saqs/{id}` → load `project.projectQuestions`
- Portal `GET /api/v1/supplier/survey/{id}` → 同上

## SAQResponse 寫入行為

- 供應商填寫時以 `project_question_id` 為 key（取代舊 `question_id`）
- `question_id` 欄位保留但不再寫入新資料

## 驗收條件

- 建立專案後，`project_questions` 筆數 = 範本題目數
- `source_template_question_id` 正確對應範本題目 id
- 修改範本題目後，已建立的專案 `project_questions` 內容不變
- Portal 供應商填寫頁面正確顯示 project_questions 的題目
