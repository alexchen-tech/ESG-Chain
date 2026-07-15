# Spec: saq-project-ui（更新）

## QuestionnaireView — 問卷審核 mode

- 加入 `project_id` 篩選下拉（選項來自 `GET /api/v1/saq-projects?status=active`）
- 每筆 SAQ 列表顯示所屬專案名稱欄位
- 移除發送 Modal（sendForm、matchMode、recommendations 相關 code）
- 發送入口改為提示文字 + 連結至 `/questionnaires/projects`

## AppSidebar — 「問卷發送」項目

- 將 `{ name: 'questionnaires-send', path: '/questionnaires/send', label: '問卷發送' }` 移除
- 「問卷專案」已是第一個子項目，不需另外入口

## StatusLabel 映射更新

所有顯示 SAQ status 的地方（前端 + Portal）：

| status | 顯示文字 |
|--------|---------|
| `sent` | 待填寫 |
| `in_progress` | 填寫中 |
| `submitted` | 待審核 |
| `under_review` | 審核中 |
| `review_returned` | 已退回 |
| `completed` | 審核完成 |
| `reviewed` | 已複核 |

移除 `not_started` mapping。
