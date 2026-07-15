## ADDED Requirements

### 編輯按鈕顯示規則
- 專案狀態為 `draft` 或 `active` 時，Header 右側顯示「✏ 編輯」按鈕
- 專案狀態為 `closed` 時，不顯示編輯按鈕

### 編輯 Modal 欄位規則

| 欄位        | draft | active | 說明                         |
|-------------|-------|--------|------------------------------|
| name        | 可編輯 | 可編輯 | 必填，最多 120 字             |
| due_date    | 可編輯 | 可編輯 | 日期選擇，可清空              |
| description | 可編輯 | 可編輯 | 選填，多行文字                |
| domain      | 可編輯 | disabled | active 後顯示說明「已發送後無法修改」 |

### 儲存行為
- 按「儲存」送出 `PUT /api/v1/saq-projects/{id}`
- 成功後用回傳資料就地更新 `project`，關閉 Modal
- 失敗時顯示後端 message，Modal 保持開啟

### 刪除專案規則
- 僅 `draft` 狀態顯示刪除入口（位於編輯 Modal 底部左側）
- 刪除前需二次確認
- 確認後送出 `DELETE /api/v1/saq-projects/{id}`
- 成功後跳轉至 `/questionnaires/projects`
- 若後端回傳 422（例如已發送問卷導致狀態變 active），顯示錯誤訊息，不跳轉
