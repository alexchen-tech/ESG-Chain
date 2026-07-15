# Spec: saq-submission-required-validation

## 概述

SAQ 問卷提交前，系統須確認所有標記為 `is_required = true` 的題目皆已作答。前端即時驗證，後端狀態機入口強制檢核。

---

## Requirements

### Requirement: 前端必填題驗證

系統 SHALL 在供應商點擊「提交問卷」時，於確認 Modal 內顯示所有尚未作答的必填題清單，並停用「確認提交」按鈕。

#### Scenario: 存在未答必填題

WHEN 供應商開啟提交確認 Modal，且有 `is_required = true` 且未填值的題目
THEN Modal 顯示紅色警告區塊，列出所有未答必填題的序號與題目文字；「確認提交」按鈕保持 disabled

#### Scenario: 所有必填題已作答

WHEN 供應商開啟提交確認 Modal，且所有 `is_required = true` 題目均有非空值
THEN Modal 顯示正常確認訊息，「確認提交」按鈕可點擊

#### Scenario: 多選題必填驗證

WHEN 題目類型為 `multi_choice` 且 `is_required = true`
THEN 系統 SHALL 以 `multiAnswers[id]` 陣列長度 > 0 為「已作答」判斷條件

### Requirement: 後端必填題驗證

系統 SHALL 在 `POST /api/v1/questionnaires/{id}/submit` 執行狀態轉換前，驗證所有必填題已有有效回應。

#### Scenario: 必填題未答送出

WHEN `QuestionnaireService::submit()` 被呼叫，且存在 `project_questions.is_required = true` 對應的 `saq_responses` 無有效值（`answer = null/''` 且 `answer_options = null/[]`）
THEN 系統返回 HTTP 422，body 含 `error_code: REQUIRED_UNANSWERED`、`message`（含未答題數）、`unanswered_question_ids` 陣列

#### Scenario: 無 project_id 的問卷（模板直發）跳過驗證

WHEN `SAQ.project_id = null`（非 project 管理的問卷）
THEN `assertRequiredAnswered()` SHALL 直接通過，不執行必填驗證
