## Why

問卷範本模組存在三個已知 Bug：`clone()` 引用已刪除的 `esg_category` 欄位、複製時未複製 `question_tag_assignments`、`BankImportModal` 顯示已不存在的 `category` 欄位。同時，範本題目目前只能靠數字輸入排序，使用者體驗差，需要加入拖曳排序功能。

## What Changes

- **Fix**: `QuestionnaireTemplateController::clone()` 移除 `esg_category` 參考，複製時一併複製 `question_tag_assignments`
- **Fix**: `BankImportModal.vue` 移除 `q.category` badge，改顯示 L1 domain chip（從 `question_tags` 取得）
- **New**: PATCH `/api/v1/settings/questionnaire-templates/{id}/questions/reorder` 端點，接受題目 ID 陣列並更新 `sort_order`
- **New**: `TemplateDetailView.vue` 加入 `vue-draggable-next` 拖曳排序，取代現有手動輸入排序

## Capabilities

### New Capabilities

- `template-question-reorder`: 範本題目拖曳排序能力，包含後端 reorder API 與前端 vue-draggable-next 整合

### Modified Capabilities

- `questionnaire-template-management`: clone 行為修正（tag assignments 複製）、BankImportModal 標籤顯示修正

## Impact

- **後端**: `QuestionnaireTemplateController`、`QuestionnaireTemplate` Model、新增 reorder route
- **前端**: `BankImportModal.vue`、`TemplateDetailView.vue`、`package.json`（新增 vue-draggable-next）
- **資料庫**: 無 schema 變更，`sort_order` 欄位已存在
