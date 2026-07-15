# Spec: saq-score-snapshot

## 概述

每次計分事件觸發後，系統新增一筆 `saq_score_snapshots` 快照，記錄當下的分數、等級、E/S/G 三維分數、使用的 scoring_model，以及觸發原因。快照為 append-only，不可更新或刪除。

---

## 資料模型

### saq_score_snapshots
| 欄位 | 型別 | 說明 |
|------|------|------|
| id | UUID PK | |
| saq_id | UUID FK→saqs CASCADE | |
| score | decimal(5,2) | 此次計算的總分 |
| grade | string(1) | A/B/C/D/E |
| score_e | decimal(5,2) null | E 類得分 |
| score_s | decimal(5,2) null | S 類得分 |
| score_g | decimal(5,2) null | G 類得分 |
| scoring_model_id | UUID null | 使用的 scoring_model；null 表示預設模型 |
| trigger | enum | 觸發原因（見下） |
| triggered_by | UUID null | 操作者 user_id；AI 自動計分時為 null |
| scored_at | timestamp | 計分完成時間 |

**trigger enum 值：**
| 值 | 觸發時機 |
|----|---------|
| `submit` | 供應商提交問卷，AI 計分完成後 callback |
| `weight_updated` | 審核員調整 project_questions.weight 後觸發重算 |
| `reviewer_override` | 審核員提交題目層覆核分，重算 final_score |
| `re_review` | 申訴後 finalize，確認最終分數 |

---

## Requirements

### Requirement: Append-only 快照建立

系統 SHALL 在每次計分事件完成後建立新的 `saq_score_snapshots` 記錄，不得更新或刪除既有快照。

#### Scenario: AI 計分 callback 建立快照
WHEN `scoreCallback` 成功更新 `saqs.score / grade`
THEN 系統 SHALL 同步新增一筆快照（trigger='submit'，triggered_by=null），含 score_e/s/g 與 scoring_model_id

#### Scenario: weight 調整觸發重算建立快照
WHEN `SaqProjectController::updateWeights()` 對 submitted/under_review/review_returned 的 SAQ 觸發重算，AI callback 完成後
THEN 系統 SHALL 建立快照（trigger='weight_updated'，triggered_by=調整權重的 user_id）

#### Scenario: 覆核分重算建立快照
WHEN `POST /saqs/{saq}/response-reviews` 完成 final_score 計算
THEN 系統 SHALL 建立快照（trigger='reviewer_override'，triggered_by=reviewer_id，score 填 final_score）

#### Scenario: 申訴 finalize 建立快照
WHEN `POST /saqs/{saq}/finalize` 執行
THEN 系統 SHALL 建立快照（trigger='re_review'，triggered_by=finalize 操作者）

### Requirement: 快照不可變

系統 SHALL 拒絕對 `saq_score_snapshots` 的任何 UPDATE 或 DELETE 操作。

#### Scenario: 嘗試刪除快照
WHEN 任何程式嘗試 DELETE FROM saq_score_snapshots
THEN 系統 SHALL 透過應用層防護（無 delete route，Model 不提供 delete 方法）阻止操作

### Requirement: 快照查詢 API

系統 SHALL 提供 `GET /api/v1/saqs/{saq}/score-snapshots` 端點供審核員查閱計分歷程。

#### Scenario: 查詢快照列表
WHEN 審核員 GET 快照列表
THEN 系統 SHALL 回傳依 scored_at 降冪排列的快照陣列，含所有欄位
