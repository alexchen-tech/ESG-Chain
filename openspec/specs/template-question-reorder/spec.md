# Spec: Template Question Reorder

## Overview

範本題目拖曳排序功能。使用者可在範本詳情頁直接拖曳題目列調整順序，儲存後更新 `sort_order`。

## Requirements

### Backend

- `PATCH /api/v1/settings/questionnaire-templates/{id}/questions/reorder`
  - Request body: `{ "question_ids": ["uuid1", "uuid2", ...] }` — 完整排序後的 ID 陣列
  - 驗證：所有 ID 必須屬於該 template，否則 422
  - 行為：依陣列索引位置更新各題目的 `sort_order`（從 1 開始）
  - Response: `{ success: true, data: [更新後題目陣列] }`
  - 權限：需登入，admin/buyer/sustain 可操作

### Frontend

- `TemplateDetailView.vue` 題目列表改用 `vue-draggable-next` 包裹
- 拖曳把手 (drag handle) 顯示在每列左側
- 拖曳結束後自動呼叫 reorder API，期間顯示 loading 狀態
- 若 API 失敗，恢復拖曳前順序並顯示錯誤提示
- 安裝 `vue-draggable-next`（版本 ^0.3.x）

## Acceptance Criteria

- [ ] 拖曳題目後，順序在前端立即更新（optimistic update）
- [ ] API 呼叫成功後，重新 fetch 確認後端順序一致
- [ ] API 失敗時，恢復原順序並顯示錯誤訊息
- [ ] 題目數量 ≥ 2 時才顯示拖曳把手
