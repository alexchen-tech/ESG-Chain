# Spec: saq-review-in-project

## 功能描述

在 `SaqProjectDetailView` 的 SAQ 列表中，依據每筆 SAQ 的 status 顯示對應的審核動作按鈕。

## 審核動作對照

| 當前 status | 可執行動作 | 按鈕文字 |
|------------|-----------|---------|
| `submitted` | `start_review` | 開始審核 |
| `under_review` | `complete_review` | 通過 |
| `under_review` | `return_review` | 退回 |
| `completed` | `mark_reviewed` | 複核確認 |

## API 端點

| 動作 | 方法 | 路由 |
|------|------|------|
| start_review | POST | `/api/v1/saqs/{saq}/start-review` |
| complete_review | POST | `/api/v1/saqs/{saq}/complete-review` |
| return_review | POST | `/api/v1/saqs/{saq}/return-review` |
| mark_reviewed | POST | `/api/v1/saqs/{saq}/mark-reviewed` |

退回時需提供 `comment`（必填）。

## 前端行為

- 按鈕點擊後立即 disabled + loading，防止重複送出
- 退回動作打開 comment 輸入 Modal
- 完成後刷新 SAQ 列表
- 非上述 status（如 `sent`、`in_progress`、`reviewed`）不顯示動作按鈕
