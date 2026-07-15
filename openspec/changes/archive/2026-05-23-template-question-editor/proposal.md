## Why

問卷範本是 ESG·Chain 計分引擎的核心輸入，但目前只能建立空範本、無法管理題目。系統設定頁的範本 Tab 缺少題目 CRUD，且無任何路由入口讓管理員「進入」一個範本。後端雖有 SAQQuestion model 與 show() eager load，卻無任何題目操作 API。採購商無法在不訪問資料庫的情況下建立有效的 ESG 問卷。

## What Changes

- 新增路由 `/settings/templates/:id` → `TemplateDetailView.vue`（獨立頁面）
- 後端補題目 CRUD API：`GET/POST/PUT/DELETE /api/v1/settings/questionnaire-templates/:id/questions`
- `TemplateDetailView.vue`：顯示範本資訊 + 題目列表（可新增/編輯/刪除/排序）
- 題型支援：`single_choice / multiple_choice / text / number / boolean`
- 每題含 `category`（E/S/G）、`weight`（浮點數）、`is_required`、`order`
- `SettingsView.vue` 範本列表加入「編輯題目」按鈕，跳轉至 `/settings/templates/:id`

## Capabilities

### New Capabilities
- `template-detail-page`: `/settings/templates/:id` 獨立頁面，顯示範本基本資訊與題目列表
- `question-crud`: 題目 CRUD UI（新增 Modal、行內編輯、刪除確認、上下移動排序）
- `question-api`: 後端 SAQQuestion CRUD API（index/store/update/destroy）

### Modified Capabilities
- `template-list-entry`: SettingsView 範本列表加「編輯題目」入口按鈕

## Impact

- **後端**：新 `SAQQuestionController`、新路由 5 條
- **前端**：新 `TemplateDetailView.vue`、更新路由、更新 `settings.ts` API 模組、小改 `SettingsView.vue`
- **無 DB migration**：SAQQuestion 表已存在
