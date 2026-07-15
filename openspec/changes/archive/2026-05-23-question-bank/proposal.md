## Why

目前 SAQ 題目強綁特定問卷範本（`template_id NOT NULL`），同類題目在多個範本中重複存在，修一道題要逐範本手動維護。管理員無法集中管理常用 ESG 題目，也無法追蹤哪些題目被多少範本使用。引入「問卷題目庫」讓題目成為可複用的資產，由題庫統一管理，各範本透過快照引用，確保問卷進行中不受題庫修改影響。

## What Changes

- `saq_questions.template_id` 改為 nullable（DB migration），template_id IS NULL 的題目即為題庫題目
- 新增 `saq_question_tags` 欄位（JSON，預設 10 個 tag）
- 新增 `saq_template_questions` pivot 表（範本 ↔ 題庫題目，含 order / weight_override）
- 現有 5 道題目遷移至題庫（template_id 設為 NULL，pivot 補建關聯）
- 後端 API：題庫 CRUD + 從題庫複製進範本（快照）
- 前端：新增題庫管理頁 `/settings/question-bank`；TemplateDetailView 新增「從題庫選題」Modal

## Capabilities

### New Capabilities
- `question-bank-management`: 題庫 CRUD（新增/編輯/刪除/tag 篩選/SASB 篩選）+ `usage_count` 顯示
- `question-bank-page`: `/settings/question-bank` 頁面，含搜尋/Tag/E-S-G/SASB Topic 過濾
- `template-import-from-bank`: 從題庫選題複製進範本（快照，獨立副本）

### Modified Capabilities
- `question-crud`: TemplateDetailView 加「從題庫選題」按鈕，補 tags 欄位顯示
- `template-list-entry`: SettingsView 加「題目庫」Tab 入口

## Impact

- **DB**：1 個 migration（nullable + saq_template_questions + tags 欄位 + migration script）
- **後端**：1 個新 Controller（QuestionBankController）、更新 SAQQuestionController、新路由
- **前端**：1 個新頁面（QuestionBankView.vue）、更新 TemplateDetailView.vue、更新 settings.ts
- **無破壞性**：現有範本專屬題目保持不變（template_id 仍有值），只是欄位改 nullable
