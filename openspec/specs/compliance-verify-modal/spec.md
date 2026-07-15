## ADDED Requirements

### Requirement: 合規文件下載端點

系統 SHALL 提供 `GET /api/v1/compliance-docs/:id/download` 端點，以 Stream 方式回傳文件 binary，Content-Disposition 為 attachment。

#### Scenario: 正常下載

- **WHEN** 已登入的 sustain / comply / admin 呼叫下載端點
- **THEN** 回傳文件 binary，Content-Type 依副檔名推斷，Content-Disposition: attachment; filename="<file_name>"

#### Scenario: 文件不存在於 storage

- **WHEN** `file_path` 指向的檔案已從 storage 刪除
- **THEN** 回傳 404 JSON `{ success: false, message: '文件不存在' }`

#### Scenario: RBAC 限制

- **WHEN** supplier / sup_esg 呼叫下載端點
- **THEN** 回傳 403

### Requirement: 驗證 Modal（Verify Modal）

所有「審核」按鈕 SHALL 改為開啟驗證 Modal，Modal 內容包含：文件摘要資訊、文件下載按鈕、有效期輸入、備註輸入、確認送出按鈕。

#### Scenario: 開啟 Modal

- **WHEN** 點擊「審核…」按鈕
- **THEN** 顯示 Modal，呈現供應商名稱、文件類型、檔案名稱、上傳日期

#### Scenario: 文件下載

- **WHEN** 點擊 Modal 中「下載文件」按鈕
- **THEN** 呼叫下載 API（axios blob），下載期間按鈕顯示 loading，完成後瀏覽器自動下載

#### Scenario: missing_expiry 必填驗證

- **WHEN** 文件 `missing_expiry: true` 且有效期欄位未填
- **THEN** 「確認審核」按鈕 disabled，欄位顯示「有效期必填」提示

#### Scenario: 正常審核送出

- **WHEN** 填入有效期（若必填）並點擊「確認審核」
- **THEN** 呼叫 POST `/compliance-docs/:id/verify`，body 帶 `expires_at`、`notes`，成功後關閉 Modal、從清單移除該列

#### Scenario: 審核送出防重複

- **WHEN** 確認審核送出期間
- **THEN** 「確認審核」按鈕 disabled + loading 文字，防止重複送出

#### Scenario: analyst 唯讀

- **WHEN** analyst 角色查看清單
- **THEN** 「審核…」按鈕不顯示（同上一 change 規則）

### Requirement: 擴充 verify 端點接收 body

`POST /compliance-docs/:id/verify` SHALL 接收可選 body：

```json
{
  "expires_at": "2026-12-31",
  "notes": "文件內容符合要求"
}
```

- `expires_at`：若文件 `expires_at` 原本為 null，則為 required；若已有值則 body 中的 `expires_at` 被忽略
- `notes`：選填，最多 500 字，寫入 `notes` 欄位

#### Scenario: missing_expiry 未帶 expires_at

- **WHEN** 文件 `expires_at = null` 且 body 未帶 `expires_at`
- **THEN** 回傳 422 `{ message: '有效期為必填欄位' }`

#### Scenario: 帶 notes 審核

- **WHEN** body 帶 `notes: "..."` 呼叫 verify
- **THEN** `notes` 寫入資料庫，回傳 doc 含 notes 欄位
