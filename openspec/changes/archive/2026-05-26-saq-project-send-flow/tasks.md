# Tasks: saq-project-send-flow

## Backend

- [x] 1. Migration：`saq_projects` 表新增 `closed_at TIMESTAMP NULL` 欄位
- [x] 2. `SaqProject` Model：加入 `STATUS_TRANSITIONS` 常數與 `transitionStatus()` 方法，違規拋出 422 exception
- [x] 3. `SaqProjectController::send()`：改為接受 `supplier_ids[]`，批次建立 SAQ，跳過重複，首次發送觸發 draft→active 轉換
- [x] 4. `SaqProjectController::close()`：新增結案端點，active→closed，記錄 `closed_at`
- [x] 5. `routes/api.php`：新增 `POST saq-projects/{project}/close` 路由

## Frontend — API

- [x] 6. `api/modules/saq.ts`：新增 `saqProjectsApi`（list, get, create, send, close）

## Frontend — Views

- [x] 7. 新增 `views/questionnaires/SaqProjectsView.vue`：列表頁含狀態 Tab、建立 Modal
- [x] 8. 新增 `views/questionnaires/SaqProjectDetailView.vue`：詳情頁含進度卡片、SAQ 列表
- [x] 9. `SaqProjectDetailView.vue`：實作「發送給供應商」Modal（群組 Tab + 搜尋 Tab，已發送禁選）
- [x] 10. `SaqProjectDetailView.vue`：實作「結案」按鈕與確認對話框
- [x] 11. `router/index.ts`：新增 `/questionnaires/projects` 與 `/questionnaires/projects/:id` 路由
- [x] 12. Sidebar：在「問卷管理」下新增「問卷專案」子項目連結

## Verification

- [x] 13. 手動測試：建立專案 → 發送給群組 → 確認 status 從 draft 變 active
- [x] 14. 手動測試：重複發送同供應商時，response skipped 計數正確且 UI 有標記
- [x] 15. 手動測試：結案後「發送」按鈕 disabled，API 回 422
