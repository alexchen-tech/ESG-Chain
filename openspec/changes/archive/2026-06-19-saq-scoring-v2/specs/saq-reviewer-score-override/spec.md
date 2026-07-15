# Spec: saq-reviewer-score-override

## 概述

審核員在 `under_review` 階段可對每道題目的 AI 評分進行覆核，輸入覆核分（0–100 原始分）與覆核理由。覆核完成後系統重算 `final_score / final_grade`，此後 UI 顯示「最終評分」。

---

## 資料模型

### saq_response_reviews
| 欄位 | 型別 | 說明 |
|------|------|------|
| id | UUID PK | |
| saq_id | UUID FK→saqs | CASCADE DELETE |
| project_question_id | UUID FK→project_questions | |
| reviewer_id | UUID FK→users | |
| reviewer_score | decimal(5,2) | 0–100 原始分 |
| note | text null | 覆核理由 |
| created_at / updated_at | timestamp | |

**UNIQUE(saq_id, project_question_id)**：一題一筆最新覆核，`updated_at` 追蹤修改時間。

### saqs（新增欄位）
| 欄位 | 型別 | 說明 |
|------|------|------|
| final_score | decimal(5,2) null | 覆核後最終分；null 表示沿用 AI score |
| final_grade | string(1) null | 對應 A/B/C/D/E；null 表示沿用 AI grade |

---

## Requirements

### Requirement: 題目層覆核 API

系統 SHALL 提供 `POST /api/v1/saqs/{saq}/response-reviews` 端點，供審核員在 `under_review` 狀態提交覆核分。

#### Scenario: 提交覆核分
WHEN 審核員 POST `{ "reviews": [{ "project_question_id": "uuid", "reviewer_score": 50, "note": "憑證已過期" }] }`，且 SAQ 狀態為 `under_review`
THEN 系統 SHALL upsert `saq_response_reviews`，重算 `final_score / final_grade`，新增 `saq_score_snapshots`（trigger='reviewer_override'），回傳 200 含更新後的 `final_score / final_grade`

#### Scenario: 非 under_review 狀態禁止覆核
WHEN SAQ 狀態不為 `under_review`
THEN 系統 SHALL 回傳 422，message 說明當前狀態不允許覆核

#### Scenario: 覆核分值域驗證
WHEN `reviewer_score` < 0 或 > 100
THEN 系統 SHALL 回傳 422 驗證錯誤

### Requirement: final_score 重算邏輯

系統 SHALL 以 Mode A（E/S/G 三維加權）重算 `final_score`，使用覆核分優先、AI 原始分次之的策略。

#### Scenario: 覆核分優先策略
WHEN 計算 `final_score` 時
THEN 對每道題：若 `saq_response_reviews` 存在覆核分，使用 `reviewer_score`；否則從 `saq_responses.raw_score` 還原 `answer_score = raw_score / q.weight`（q.weight > 0 時），帶入 Mode A 計算

#### Scenario: final_score 計算公式
WHEN 所有題目的 effective_score 確定後
THEN `category_avg(X) = Σ(effective_score × q.weight) / Σ(q.weight)` [X 類題]
     `final_score = category_avg(E)×w_E + category_avg(S)×w_S + category_avg(G)×w_G`
     `final_grade` 依 scoring_model 閾值判定

### Requirement: 前端審核覆核 UI

系統 SHALL 在 `SaqProjectDetailView` 的審核詳情中提供題目覆核介面。

#### Scenario: 審核員查看覆核介面
WHEN 審核員開啟 `under_review` 狀態的 SAQ 詳情
THEN 頁面 SHALL 顯示每道題的：題目文字、供應商原始答案、AI raw_score、覆核分輸入欄（預填空白）、覆核理由輸入欄

#### Scenario: 提交覆核
WHEN 審核員填入至少一道題的覆核分並點擊「提交覆核」
THEN 前端 SHALL 批次送出所有已填覆核分，顯示更新後的「最終評分」與等級

#### Scenario: 最終評分顯示
WHEN SAQ 有 `final_score`（覆核後）
THEN 列表與詳情頁 SHALL 顯示「最終評分：XX.X (Y)」；同時可展開查看「AI 評分：XX.X (Z)」以供稽核比對
