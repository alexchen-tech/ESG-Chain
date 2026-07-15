# Spec: SAQ Project Status Machine

## Overview

SaqProject.status 的合法狀態與流轉規則，防止非法操作。

## States（SaqProject）

```
draft ──(首次發送)──▶ active ──(手動結案)──▶ closed
```

| 狀態 | 說明 |
|------|------|
| `draft` | 已建立，尚未發送任何問卷 |
| `active` | 已發送至少一家供應商，進行中 |
| `closed` | 手動結案，不可再發送 |

## Transition Rules（SaqProject）

| 觸發操作 | 允許的來源狀態 | 結果狀態 |
|----------|--------------|---------|
| 首次 send | draft | active |
| 再次 send | active | active（不變） |
| close | active | closed |
| send | closed | ❌ 422 錯誤 |

---

## SAQ（個別問卷）Status Enum（統一後）

`saqs.status` ENUM：

| 值 | 意義 |
| -- | ---- |
| `sent` | 已發送，待供應商填寫（取代舊 `not_started`） |
| `in_progress` | 供應商填寫中 |
| `submitted` | 已繳交，待審核 |
| `under_review` | 審核中 |
| `review_returned` | 退回修改 |
| `completed` | 審核完成（通過） |
| `reviewed` | 複核確認 |
| `disputed` | 供應商發起申訴中 |
| `re_review` | 審核員重新審核中 |
| `finalized` | 最終確認（終態，不可逆） |

移除：`not_started`（由 `sent` 取代）

## SAQ State Transitions（QuestionnaireService::TRANSITIONS）

```
sent            --[start_fill]--> in_progress
in_progress     --[submit]------> submitted
sent            --[submit]------> submitted   (供應商直接送出，未觸及 in_progress)
submitted       --[start_review]--> under_review
under_review    --[complete_review]--> completed
under_review    --[return_review]---> review_returned
review_returned --[submit]------> submitted
completed       --[mark_reviewed]--> reviewed
completed       --[dispute]------> disputed   (供應商，7 天窗口期內)
disputed        --[re_review]----> re_review  (審核員)
re_review       --[finalize]-----> finalized  (審核員，終態)
```

### 申訴相關狀態說明

#### Scenario: 完整狀態列表（更新後）

WHEN 查詢或顯示 SAQ 狀態
THEN 系統 SHALL 支援以下所有狀態值：`sent`、`in_progress`、`submitted`、`under_review`、`review_returned`、`completed`、`reviewed`、`disputed`、`re_review`、`finalized`

#### Scenario: 申訴相關狀態轉換

WHEN 執行申訴相關操作
THEN 合法轉換 SHALL 為：

- `completed` --[dispute]--> `disputed`（供應商，7 天窗口期內）
- `disputed` --[re_review]--> `re_review`（審核員）
- `re_review` --[finalize]--> `finalized`（審核員，終態）

#### Scenario: finalized 為終態

WHEN SAQ 狀態為 `finalized`
THEN `QuestionnaireService::TRANSITIONS['finalized']` SHALL 為空陣列，任何狀態轉換操作 SHALL 回傳 422

## SAQ 起點

SAQ 建立時 status = `sent`（由 `SaqProjectController::send()` 寫入）。

## 約束

- `project_id NOT NULL`（DB 已強制）
- 審核動作需有 project 脈絡，前端從 SaqProjectDetailView 觸發

---

## Requirements

### Backend

- `SaqProject` Model 加入 `transitionStatus(string $new): void` 方法
  - 違反規則時拋出 `InvalidStatusTransitionException`（回 422）
- `SaqProjectController::close()` 新端點：`POST /api/v1/saq-projects/{id}/close`
  - 僅 admin / sustain / buyer 可操作
  - active → closed，記錄 `closed_at` timestamp
- `SaqProject` migration：新增 `closed_at TIMESTAMP NULL` 欄位
- `saqs.status` ENUM 遷移：移除 `not_started`，新增 `sent`

## Acceptance Criteria

- [ ] `closed` 狀態的 project 呼叫 send 端點回 422
- [ ] `draft` 狀態發送後自動轉為 `active`
- [ ] `close` 端點對 `draft` 狀態回 422（不允許跳過 active 直接關閉）
- [ ] `closed_at` 在結案時正確記錄
- [ ] SAQ 建立時 status = `sent`（不再使用 `not_started`）
- [ ] `sent` → `submitted` 直接跳轉合法（供應商未觸及 in_progress 直接送出）
- [ ] `completed` → `reviewed` 需 `mark_reviewed` 動作
