## 1. DB Migration

- [x] 1.1 建立 migration `add_iso_subject_to_saq_questions`：新增 `iso_subject` nullable string 欄位（length 20），加 index
- [x] 1.2 在同一 migration 的 `up()` 加資料遷移：tags 中 `ISO-組織治理` → `iso_subject='組織治理'`（移除前綴），以此類推七大主題；移除 tags 中的 E/S/G 值
- [x] 1.3 docker cp migration + `php artisan migrate`

## 2. 後端 Model & Controller

- [x] 2.1 `SAQQuestion` model：`$fillable` 補 `iso_subject`；`$casts` 加 `iso_subject => 'string'`（nullable）
- [x] 2.2 `QuestionBankController::index()`：加 `?iso_subject=` 查詢參數過濾
- [x] 2.3 `QuestionBankController::store()` 與 `update()`：加 `iso_subject` validate（`nullable|in:組織治理,人權,勞工,環境,公平營運,消費者,社區`）
- [x] 2.4 更新 `DEFAULT_TAGS` 常數：移除 E/S/G 與 ISO-xxx，只保留 `地域風險`
- [x] 2.5 docker cp 後端檔案 + `php artisan route:cache`

## 3. 前端常數與 API 型別

- [x] 3.1 `src/constants/questionBank.ts`：
  - 新增 `ISO_SUBJECTS` 常數（七大主題字串陣列）
  - 更新 `QUESTION_BANK_TAXONOMY`：移除 ESG 群組；ISO 26000 群組的 children 改為 `param: { iso_subject: '勞工' }` 格式
  - 更新 `BankFilterParam` 型別加入 `iso_subject` 可能
  - 更新 `applyBankFilter`：支援 `iso_subject` client-side filter（`item.iso_subject === iso_subject`）
- [x] 3.2 `src/api/modules/settings.ts`：`QuestionBankItem` 介面補 `iso_subject: string | null`；`questionBankApi.list` params 補 `iso_subject?: string`

## 4. QuestionBankFilter 更新

- [x] 4.1 `QuestionBankFilter.vue`：
  - `emitChange()` 支援 `iso_subject` payload key（當 L1=ISO 26000 選了細項時）
  - 移除 ESG 群組（L1 選項移除「ESG」，只保留「ISO 26000」、「地緣政治」）
  - 細項 value 對應 `iso_subject` 或 `tag`，由 TAXONOMY 的 `param` 決定

## 5. QuestionBankView Modal 更新

- [x] 5.1 移除 Modal 中的 tag checkboxes 群組（`<div class="tag-groups">`）
- [x] 5.2 新增 `iso_subject` radio 選擇區（Options API `qForm.iso_subject`），七個選項 + 清除按鈕
- [x] 5.3 `qForm` 移除 `tags` 欄位，改為 `iso_subject: string | null`
- [x] 5.4 `saveQuestion` payload 改用 `iso_subject`，不再帶 `tags`
- [x] 5.5 `blankForm()` 更新（移除 tags，加 iso_subject: null）

## 6. QuestionBankView 列表更新

- [x] 6.1 table 標籤欄改為顯示 `q.iso_subject`（若有則顯示 badge，無則顯示「—」）
- [x] 6.2 移除 `displayTags()` 方法、`tagClass()` 方法（或改為只處理 iso_subject badge 樣式）

## 7. 驗證

- [x] 7.1 `npx vue-tsc --noEmit` 無錯誤
- [ ] 7.2 新增題目：選 ISO 26000「勞工」→ 儲存後列表顯示「勞工」badge
- [ ] 7.3 篩選：QuestionBankFilter 選「ISO 26000 › 勞工」→ 只顯示 iso_subject=勞工 的題目
- [ ] 7.4 清除 iso_subject → 列表顯示「—」，API 回傳 iso_subject=null
- [ ] 7.5 確認既有題目的 iso_subject 已從 tags 正確遷移
