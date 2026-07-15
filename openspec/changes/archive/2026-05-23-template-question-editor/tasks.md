## 1. 後端 API

- [x] 1.1 建立 `SAQQuestionController`（放 `app/Http/Controllers/Api/Settings/`）：`index`、`store`、`update`、`destroy`，路由參數 `template` + `question`，`destroy` 驗證 question.template_id === template.id
- [x] 1.2 在 `routes/api.php` 補 4 條路由（巢狀在 questionnaire-templates 下）：`GET/POST /{template}/questions`、`PUT/DELETE /{template}/questions/{question}`
- [x] 1.3 docker cp 後端新增檔案 + 修改的 routes/api.php 進容器，執行 `php artisan route:cache`

## 2. 前端型別與 API 模組

- [x] 2.1 `settings.ts` 新增 `SAQQuestion` interface（id / template_id / category / question_text / question_type / options / weight / order / is_required）
- [x] 2.2 `settings.ts` 新增 `questionsApi`（`list(templateId)` / `create(templateId, data)` / `update(templateId, questionId, data)` / `remove(templateId, questionId)`）

## 3. 路由更新

- [x] 3.1 `router/index.ts` 新增路由 `{ path: '/settings/templates/:id', name: 'template-detail', component: () => import('@/views/settings/TemplateDetailView.vue') }`

## 4. TemplateDetailView.vue

- [x] 4.1 建立 `src/views/settings/TemplateDetailView.vue`（Options API）：頁頭麵包屑、範本基本資訊區塊（name/version/is_active badge）、題目列表區塊骨架
- [x] 4.2 題目列表 table：序號、分類 badge（E=綠/S=藍/G=紫）、題文（最多 60 字截斷）、題型 badge、權重 font-mono、必填標記、操作欄（編輯/↑/↓/刪除）
- [x] 4.3 「+ 新增題目」Modal：題文 input、分類 select（E/S/G）、題型 select（5 種）、選項動態輸入（single_choice/multiple_choice 時顯示，最少 2 最多 10）、權重 number input（0–1 step=0.01）、必填 checkbox
- [x] 4.4 「編輯題目」Modal：預填現有值，題型欄 disabled，其餘同新增 Modal
- [x] 4.5 刪除確認 Modal：顯示題文前 30 字，確認後呼叫 remove API + 本地移除
- [x] 4.6 ↑/↓ 排序：交換陣列中兩題的 order（重新賦值 1..n），呼叫兩次 update API，第一題 ↑ disabled，最後題 ↓ disabled
- [x] 4.7 `loadTemplate()`：呼叫 `settingsApi.templates.list()` + `questionsApi.list(id)` 同時載入

## 5. SettingsView.vue 入口

- [x] 5.1 問卷範本列表操作欄加「編輯題目」按鈕（`btn btn-secondary btn-sm`），點擊執行 `router.push('/settings/templates/' + t.id)`；需 import useRouter 並在 setup() 回傳

## 6. 樣式

- [x] 6.1 分類 badge：`cat-E`（綠）/ `cat-S`（藍）/ `cat-G`（紫），題型 badge 灰底
- [x] 6.2 題目列表行：hover 底色，操作按鈕 gap:4px

## 7. 驗證

- [x] 7.1 執行 `npx vue-tsc --noEmit` 無錯誤
- [x] 7.2 API 測試：新增題目 → 顯示於列表 → 編輯 → 排序 → 刪除 全流程正常
