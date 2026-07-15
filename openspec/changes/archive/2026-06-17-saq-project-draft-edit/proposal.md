## Why

`SaqProjectDetailView` 明細頁目前只能「讀」，沒有任何編輯入口。
草稿（draft）專案的 `name`、`due_date`、`description`、`domain` 均可透過後端
`PUT /api/v1/saq-projects/{id}` 修改，但前端完全沒有暴露這個能力。

使用者若需修改草稿專案只能刪除重建，摩擦成本高。
進行中（active）專案的截止日也可能因計畫變動需要調整，同樣無法在 UI 操作。

後端 API 已完備，缺的只是前端入口。

## What Changes

在 `SaqProjectDetailView` Header 區新增「✏ 編輯」按鈕，點擊後彈出 Modal：

- **草稿（draft）**：可修改 name、due_date、description、domain（四欄全開）
- **進行中（active）**：只開放 name、due_date、description（domain 已鎖定，欄位 disabled + tooltip）
- **已結案（closed）**：不顯示編輯按鈕

同時在草稿專案的 Header 加入「刪除專案」按鈕（active/closed 不顯示），
對應後端已有的 `DELETE /api/v1/saq-projects/{id}`。

## Capabilities

### New Capabilities
- `saq-project-edit`: 問卷專案基本資訊編輯（Modal），含草稿/進行中的欄位差異控制與刪除草稿

### Modified Capabilities
<!-- none -->

## Impact

- **前端**：`SaqProjectDetailView.vue`（新增 Modal + 按鈕）、`saq.ts` API module（確認 update/delete 方法）
- **後端**：無需修改，API 已完備
- **路由**：無需修改
