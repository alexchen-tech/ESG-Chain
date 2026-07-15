# Spec: saq-project-status-machine

## Status Enum

`saqs.status` ENUM（統一後）：

| 值 | 意義 |
|----|------|
| `sent` | 已發送，待供應商填寫（取代舊 `not_started`） |
| `in_progress` | 供應商填寫中 |
| `submitted` | 已繳交，待審核 |
| `under_review` | 審核中 |
| `review_returned` | 退回修改 |
| `completed` | 審核完成（通過） |
| `reviewed` | 複核確認 |

移除：`not_started`（由 `sent` 取代）

## State Transitions（QuestionnaireService::TRANSITIONS）

```
sent            --[start_fill]--> in_progress
in_progress     --[submit]------> submitted
sent            --[submit]------> submitted   (供應商直接送出，未觸及 in_progress)
submitted       --[start_review]--> under_review
under_review    --[complete_review]--> completed
under_review    --[return_review]---> review_returned
review_returned --[submit]------> submitted
completed       --[mark_reviewed]--> reviewed
```

## 起點

SAQ 建立時 status = `sent`（由 `SaqProjectController::send()` 寫入）。

## 約束

- `project_id NOT NULL`（DB 已強制）
- 審核動作需有 project 脈絡，前端從 SaqProjectDetailView 觸發
