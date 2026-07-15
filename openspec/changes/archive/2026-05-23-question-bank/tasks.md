## 1. DB Migration

- [x] 1.1 建立 `alter_saq_questions_for_question_bank` migration：`template_id` 改 nullable、新增 `tags` JSON nullable、新增 `source_bank_question_id` nullable UUID（無 FK，自參考）
- [x] 1.2 同一 migration 的 `up()` 執行資料遷移 script：①現有 5 道題 template_id → NULL；②為原範本建 5 道快照副本（template_id=原值，source_bank_question_id=步驟①的 id，保留 order/weight 等所有欄位）
- [x] 1.3 docker cp + `php artisan migrate`，驗證 saq_questions 共 10 筆（5 庫 + 5 副本）

## 2. Laravel Model & Controller

- [x] 2.1 更新 `SAQQuestion` Model：`template_id` 從 fillable 移出（改在 controller 設定），補 `tags`、`source_bank_question_id` fillable；新增 `scopeBank()` local scope（where template_id IS NULL）；新增 `usageCount()` 計算方法
- [x] 2.2 建立 `QuestionBankController`（放 `app/Http/Controllers/Api/Settings/`）：`index`（支援 category/tag/keyword 過濾，回傳含 usage_count）、`store`、`update`、`destroy`（usage_count>0 時回 422 警告但仍刪）、`tags`（回傳預設 11 個 tag 清單）
- [x] 2.3 建立 `ImportFromBankController`（放 Questionnaire 資料夾）：接受 `question_ids[]`，驗證是題庫題目（template_id IS NULL），複製欄位建立新 saq_questions（template_id=路由範本，source_bank_question_id=來源 id，order 遞增）
- [x] 2.4 在 `routes/api.php` 新增：`GET/POST settings/question-bank`、`GET settings/question-bank/tags`、`PUT/DELETE settings/question-bank/:question`、`POST settings/questionnaire-templates/:template/import-from-bank`
- [x] 2.5 docker cp 新增檔案 + 修改 routes/api.php + `php artisan route:cache`

## 3. 前端型別與 API 模組

- [x] 3.1 `settings.ts` 補 `QuestionBankItem` interface（含 usage_count、tags）+ `questionBankApi`（list/create/update/remove/tags）
- [x] 3.2 `settings.ts` 補 `importFromBankApi`（templateId, questionIds）

## 4. 題目庫管理頁

- [x] 4.1 建立 `src/views/settings/QuestionBankView.vue`（Options API）：頁頭、搜尋欄位、E/S/G 過濾、Tag 多選 checkboxes、列表（分類 badge/題文/題型/SASB Topic/Tags chips/usage_count/操作）
- [x] 4.2 「+ 新增題目」Modal：與 TemplateDetailView 相同欄位 + Tag 多選區（11 個 checkboxes），儲存後重整列表
- [x] 4.3 「編輯題目」Modal：預填現有值（含 tags），題型 disabled
- [x] 4.4 刪除確認 Modal：usage_count > 0 時顯示「已被 N 個範本引用（副本），刪除不影響範本」警告
- [x] 4.5 路由加 `/settings/question-bank`（admin only）
- [x] 4.6 SettingsView 加「題目庫 ↗」Tab（點擊跳轉，與「計分模型」同樣處理方式）

## 5. TemplateDetailView 加「從題庫選題」

- [x] 5.1 在題目列表標題旁加「從題庫選題」按鈕，點擊開啟 BankImportModal
- [x] 5.2 建立 `BankImportModal.vue`（獨立 component，放 `components/common/`）：載入題庫列表、搜尋/E-S-G/Tag 過濾、checkbox 多選、「已選 N 道」計數、「加入範本」按鈕
- [x] 5.3 「加入範本」觸發 `importFromBankApi`，成功後 emit `imported` 事件，父層重整題目列表
- [x] 5.4 題庫為空時顯示「題庫目前沒有題目」+ 「前往題目庫」連結

## 6. 樣式

- [x] 6.1 Tag chip 樣式（小圓角 badge，不同 tag 顏色區分 E/S/G vs 地域風險 vs ISO 系列）
- [x] 6.2 usage_count badge 樣式（數字顯示，0 時灰色，>0 時強調色）

## 7. 驗證

- [x] 7.1 `npx vue-tsc --noEmit` 無錯誤
- [x] 7.2 題庫 CRUD：新增/編輯/刪除題目、usage_count 正確顯示
- [x] 7.3 TemplateDetailView：從題庫選題，題目出現在列表，source_bank_question_id 正確寫入
- [x] 7.4 刪除有引用的題庫題目，對應範本的副本不受影響
