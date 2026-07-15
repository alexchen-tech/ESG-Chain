# Tasks — saq-project-draft-edit

## Frontend

- [x] `SaqProjectDetailView.vue`：在 Header 按鈕區加入「✏ 編輯」按鈕，draft/active 顯示、closed 隱藏
- [x] `SaqProjectDetailView.vue`：新增編輯 Modal（data: `showEditModal`, `editForm`, `isSaving`, `isDeleting`）
- [x] 編輯 Modal 欄位：name（input）、due_date（date input）、description（textarea）、domain（select，active 時 disabled）
- [x] 編輯 Modal 底部左側：「刪除專案」按鈕，僅 draft 顯示，含二次確認
- [x] `openEditModal()` method：將 project 現有值填入 editForm
- [x] `doSave()` method：呼叫 `saqProjectApi.update()`，成功後更新 `this.project` 並關閉 Modal
- [x] `doDelete()` method：呼叫 `saqProjectApi.remove()`，成功後 `router.push('/questionnaires/projects')`
- [x] `esgchain-web/src/api/modules/saq.ts`：確認 `saqProjectApi.remove(id)` 方法存在（已確認，命名為 remove）

## 同步

- [x] `docker cp` + Vite HMR 觸發，驗證草稿專案明細頁編輯 Modal 正常開關
- [x] 驗證 draft 可改 domain、active 的 domain 欄位為 disabled
- [x] 驗證儲存後頁面標題與 badge 即時更新（API PUT 回傳最新 project 物件，直接替換）
- [x] 驗證刪除草稿後跳回列表頁（API 測試通過）
