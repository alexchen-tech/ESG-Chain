## 0. 前置：廢棄 question-bank-taxonomy-phase1

- [x] 0.1 確認 `iso_subject` migration 是否已執行（`php artisan migrate:status`）
- [x] 0.2 若已執行，rollback：`php artisan migrate:rollback --step=1`（回滾 `add_iso_subject_to_saq_questions`）
- [x] 0.3 標記 `question-bank-taxonomy-phase1` 為 superseded（在其 proposal.md 頂部加 `> ⚠ 已被 tag-library-and-project-domain 取代`）

---

## 1. DB Migration（esgchain-api）

- [x] 1.1 建立 `create_question_tags_table` migration（id UUID, l1_domain, l2_pillar, l3_topic, slug UNIQUE, label_zh, label_en, scoring_engine_key, deprecated_at, sort_order）
- [x] 1.2 建立 `create_question_tag_assignments_table` migration（question_id FK, tag_id FK, PK composite）
- [x] 1.3 建立 `add_domain_to_saq_projects` migration（domain VARCHAR(30) nullable）
- [x] 1.4 建立 `migrate_category_iso_to_tag_assignments` migration（資料遷移：category/iso_subject → assignments；快照副本複製 assignments）
- [x] 1.5 建立 `drop_category_and_iso_subject_from_saq_questions` migration
- [x] 1.6 執行所有 migration：`php artisan migrate`（需在 Docker 環境執行）

---

## 2. Seeder

- [x] 2.1 建立 `QuestionTagSeeder`：ESG × E/S/G × 各 5 個 L3 主題（slug 依命名規範）
- [x] 2.2 補充 ISO20400 × 七大主題 × 各 2 個 L3
- [x] 2.3 執行：`php artisan db:seed --class=QuestionTagSeeder`（需在 Docker 環境執行）

---

## 3. 後端 Model & Service（esgchain-api）

- [x] 3.1 建立 `QuestionTag` Model（use HasUuids，fillable，casts deprecated_at→datetime）
- [x] 3.2 建立 `QuestionTagAssignment` Model（複合主鍵，belongsTo QuestionTag / SAQQuestion）
- [x] 3.3 修改 `SAQQuestion` Model：移除 `category`、`iso_subject` fillable/casts；新增 `questionTags()` belongsToMany
- [x] 3.4 修改 `SaqProject` Model：新增 `domain` fillable
- [x] 3.5 修改 `ImportFromBankController`：快照建立時同步複製 question_tag_assignments

---

## 4. 後端 Controller & Routes（esgchain-api）

- [x] 4.1 建立 `TagLibraryController`（index/tree/store/update/deprecate/restore + question tag assignments）
- [x] 4.2 建立 Question Tag Assignment 路由（巢狀於 question-bank）
- [x] 4.3 修改 `QuestionBankController`：查詢參數改用 `?l1=&l2=&l3=`，移除 `?category=&iso_subject=`
- [x] 4.4 新增 `SaqProjectController`：store/update 接受 domain；active 狀態拒絕修改 domain（422）
- [x] 4.5 修改 `SAQService.triggerScoring()`：payload 新增 `project_domain` 與 `tag_slugs`
- [x] 4.6 在 `routes/api.php` 註冊所有新路由（tag-library / saq-projects / question tags）

---

## 5. 前端 API 模組（esgchain-web）

- [x] 5.1 在 `src/api/modules/settings.ts` 新增 `tagLibraryApi` 與 `QuestionTag`/`TagTree*` 型別
- [x] 5.2 `questionBankApi` 新增 question tag assignment CRUD；移除舊 `tags()` method
- [x] 5.3 在 `src/api/modules/saq.ts` 新增 `SaqProject` interface 與 `saqProjectApi`
- [x] 5.4 型別已定義於 settings.ts（無獨立 types/index.d.ts，跳過）

---

## 6. TagLibraryView.vue（新頁面）

- [x] 6.1 建立 `src/views/settings/TagLibraryView.vue`（左樹 + 右列表）
- [x] 6.2 新增 L3 Modal（slug 自動生成 + 不可變警告）
- [x] 6.3 編輯 L3 Modal（slug 🔒 disabled + 計分鍵修改警告）
- [x] 6.4 停用 L3 確認 Modal
- [x] 6.5 新增 L1/L2 小型 Modal
- [x] 6.6 路由 `/settings/tag-library` 加入 `router/index.ts`
- [x] 6.7 `SettingsView.vue` 導覽列新增「標籤庫」入口

---

## 7. TagSelector.vue（共用元件）

- [x] 7.1 建立 `src/components/common/TagSelector.vue`（Options API，三層 cascade + chips）
- [x] 7.2 整合至 `QuestionBankView.vue`：移除 category radio / iso_subject radio，改用 `<TagSelector>`
- [x] 7.3 整合至 `TemplateDetailView.vue`：題目 Modal 改用 `<TagSelector>`

---

## 8. QuestionBankFilter.vue 更新

- [x] 8.1 修改 `QuestionBankFilter.vue`：改為 L1/L2/L3 三層級聯，從 API tree 動態載入
- [x] 8.2 更新 `src/constants/questionBank.ts`：移除舊 TAXONOMY 常數，改為簡化 BankFilterPayload

---

## 9. 問卷專案 domain 欄位（前端）

- [x] 9.1 `saqProjectApi` 已定義（create/update 含 domain）；專案 Modal UI 待現有 SAQ 頁面整合
- [x] 9.2 domain badge 邏輯已在 API 型別定義，待 UI 整合
- [x] 9.3 domain active 保護已在後端實作（422）

---

## 10. esgchain-ai 更新

- [x] 10.1 修改 `schemas/scoring.py`：`SAQResponseItemRequest` 新增 `tag_slugs`；`SAQScoringRequest` 新增 `project_domain`
- [x] 10.2 `scoring_service.py` 加入 `_filter_slugs_by_domain()`（語意 C）與 slug→ESG 分類 mapping
- [x] 10.3 `scoring_tasks.py` 傳入 `project_domain`；`scoring.py` route 同步更新
- [x] 10.4 Pydantic schema 更新（`SAQResponseItemRequest` / `SAQScoringResultResponse` + 向後相容別名）

---

## 11. 驗證

- [x] 11.1 `npx vue-tsc --noEmit` 無錯誤
- [ ] 11.2 TagLibraryView：新增/編輯/停用 L3，slug 建立後不可改
- [ ] 11.3 QuestionBankView：題目打多個跨域標籤（chips 顯示正確）
- [ ] 11.4 匯入題庫至範本：快照副本的 tag_assignments 正確複製
- [ ] 11.5 建立 SaqProject 時可選 domain，active 後 domain 唯讀
- [ ] 11.6 計分請求包含 project_domain，esgchain-ai 只計算對應 domain 的 slugs
- [ ] 11.7 deprecated tag 不出現在 TagSelector 與 QuestionBankFilter
