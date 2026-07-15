# Spec: SAQ Project Status Machine

## Overview

SaqProject.status 的合法狀態與流轉規則，防止非法操作。

## States

```
draft ──(首次發送)──▶ active ──(手動結案)──▶ closed
```

| 狀態 | 說明 |
|------|------|
| `draft` | 已建立，尚未發送任何問卷 |
| `active` | 已發送至少一家供應商，進行中 |
| `closed` | 手動結案，不可再發送 |

## Transition Rules

| 觸發操作 | 允許的來源狀態 | 結果狀態 |
|----------|--------------|---------|
| 首次 send | draft | active |
| 再次 send | active | active（不變） |
| close | active | closed |
| send | closed | ❌ 422 錯誤 |

## Requirements

### Backend

- `SaqProject` Model 加入 `transitionStatus(string $new): void` 方法
  - 違反規則時拋出 `InvalidStatusTransitionException`（回 422）
- `SaqProjectController::close()` 新端點：`POST /api/v1/saq-projects/{id}/close`
  - 僅 admin / sustain / buyer 可操作
  - active → closed，記錄 `closed_at` timestamp
- `SaqProject` migration：新增 `closed_at TIMESTAMP NULL` 欄位

## Acceptance Criteria

- [ ] `closed` 狀態的 project 呼叫 send 端點回 422
- [ ] `draft` 狀態發送後自動轉為 `active`
- [ ] `close` 端點對 `draft` 狀態回 422（不允許跳過 active 直接關閉）
- [ ] `closed_at` 在結案時正確記錄
