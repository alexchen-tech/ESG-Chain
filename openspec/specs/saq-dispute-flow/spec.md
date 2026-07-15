# Spec: saq-dispute-flow

## 概述

供應商在問卷審核通過（`completed`）後 7 天內可發起申訴（dispute），進入 `disputed → re_review → finalized` 流程。`finalized` 為不可逆終態，鎖定 `final_score`。

---

## 狀態擴充

```
completed ──[dispute, 7天內]──▶ disputed
disputed  ──[re_review]───────▶ re_review
re_review ──[finalize]────────▶ finalized  (終態，不可逆)
```

### saqs（新增欄位）

| 欄位 | 型別 | 說明 |
|------|------|------|
| disputed_at | timestamp null | 發起申訴時間；7 天窗口期計算依據 |

---

## Requirements

### Requirement: 供應商申訴 API

系統 SHALL 提供 `POST /api/v1/questionnaires/{saq}/dispute` 端點，供供應商在 7 天窗口期內發起申訴。

#### Scenario: 7 天窗口期內申訴

WHEN 供應商 POST `/questionnaires/{saq}/dispute`，帶有 `reason`（必填），且 `saqs.reviewed_at` 距今 ≤ 7 天，且 SAQ 狀態為 `completed`
THEN 系統 SHALL 更新 `status = 'disputed'`，記錄 `disputed_at = now()`，新增 `saq_review_histories`（action='dispute'），回傳 200

#### Scenario: 超過 7 天申訴被拒

WHEN `saqs.reviewed_at` 距今 > 7 天
THEN 系統 SHALL 回傳 422，message 含「申訴期限已過（審核完成後 7 天內可申訴）」

#### Scenario: 非 completed 狀態禁止申訴

WHEN SAQ 狀態不為 `completed`
THEN 系統 SHALL 回傳 422

### Requirement: 審核員處理申訴 API

系統 SHALL 提供 `POST /api/v1/saqs/{saq}/re-review` 與 `POST /api/v1/saqs/{saq}/finalize` 端點。

#### Scenario: 開始重新審核

WHEN 審核員 POST `/saqs/{saq}/re-review`，SAQ 狀態為 `disputed`
THEN 系統 SHALL 更新 `status = 're_review'`，新增 review history（action='re_review'），此時審核員可再次提交題目層覆核分

#### Scenario: 最終確認（finalize）

WHEN 審核員 POST `/saqs/{saq}/finalize`，SAQ 狀態為 `re_review`，帶有選填 `comment`
THEN 系統 SHALL 更新 `status = 'finalized'`，新增 review history（action='finalize'），新增 saq_score_snapshots（trigger='re_review'）；`final_score / final_grade` 從此鎖定不可再改

#### Scenario: finalized 後禁止任何分數修改

WHEN SAQ 狀態為 `finalized`，任何修改 `final_score` 或 `saq_response_reviews` 的請求
THEN 系統 SHALL 回傳 422，message 說明問卷已終結

### Requirement: 前端供應商申訴入口

系統 SHALL 在供應商 Portal 的問卷詳情頁提供申訴入口。

#### Scenario: 申訴按鈕可見條件

WHEN 供應商查看狀態為 `completed` 的問卷，且 `reviewed_at` 距今 ≤ 7 天
THEN Portal SHALL 顯示「申訴」按鈕，並標示剩餘天數（例：「尚可申訴 5 天」）

#### Scenario: 超過窗口期隱藏按鈕

WHEN `reviewed_at` 距今 > 7 天
THEN Portal SHALL 不顯示申訴按鈕，顯示「審核已完成」狀態徽章

#### Scenario: 申訴後狀態顯示

WHEN 問卷狀態為 `disputed` 或 `re_review`
THEN Portal SHALL 顯示對應狀態說明（「申訴審核中」），不可再次申訴
