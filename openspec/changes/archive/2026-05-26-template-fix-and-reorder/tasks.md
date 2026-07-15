# Tasks: template-fix-and-reorder

## Backend

- [x] 1. 修正 `QuestionnaireTemplateController::clone()` — 移除 `esg_category` 欄位引用
- [x] 2. 修正 `QuestionnaireTemplateController::clone()` — 複製時同步複製 `question_tag_assignments`
- [x] 3. 新增 `PATCH /api/v1/settings/questionnaire-templates/{template}/questions/reorder` 路由與 Controller 方法
- [x] 4. 實作 reorder Controller：驗證 question_ids 皆屬於該 template，批次更新 `sort_order`

## Frontend

- [x] 5. 安裝 `vue-draggable-next`（`npm install vue-draggable-next`）
- [x] 6. `BankImportModal.vue`：移除 `q.category` badge，改顯示 L1 domain chip（從 `question_tags` 取 level=1）
- [x] 7. `TemplateDetailView.vue`：引入 `vue-draggable-next`，題目列表改為 draggable 元件，加入拖曳把手
- [x] 8. `TemplateDetailView.vue`：實作 `onReorder()` — optimistic update + 呼叫 reorder API + 失敗 rollback
- [x] 9. `api/modules/questionnaire-templates.ts`（或現有 api 檔）：新增 `reorder(templateId, questionIds)` API 呼叫
