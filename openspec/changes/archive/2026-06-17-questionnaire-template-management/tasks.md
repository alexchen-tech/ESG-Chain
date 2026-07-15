## 1. DB Migration

- [x] 1.1 建立 `add_archived_at_to_saq_templates` migration（archived_at nullable timestamp，index）
- [x] 1.2 docker cp + `php artisan migrate`

## 2. 後端 Controller & Routes

- [x] 2.1 更新 `SAQTemplate` Model：fillable 補 `archived_at`，casts 補 `archived_at => datetime`
- [x] 2.2 更新 `QuestionnaireTemplateController::index()`：預設過濾 archived_at IS NULL；`?is_archived=true` 時回傳封存清單
- [x] 2.3 新增 `QuestionnaireTemplateController::clone()`：複製範本（name+' (複製)'、version+'.copy'、is_active=false）+ 複製所有 SAQQuestions
- [x] 2.4 新增 `QuestionnaireTemplateController::archive()`：設 archived_at=now(), is_active=false
- [x] 2.5 新增 `QuestionnaireTemplateController::unarchive()`：設 archived_at=null
- [x] 2.6 在 `routes/api.php` 新增三條路由：`POST /{template}/clone`、`POST /{template}/archive`、`POST /{template}/unarchive`
- [x] 2.7 docker cp + `php artisan route:cache`

## 3. 前端 API 模組更新

- [x] 3.1 `settings.ts` 補 `QuestionnaireTemplate.archived_at: string | null` 欄位、`question_count?: number`
- [x] 3.2 `settings.ts` 的 `settingsApi.templates` 補：`list(params?)` 補 `is_archived` 參數、`clone(id)`、`archive(id)`、`unarchive(id)`

## 4. QuestionnaireTemplatesView.vue（列表頁）

- [x] 4.1 建立 `src/views/questionnaires/QuestionnaireTemplatesView.vue`（Options API）：
  - 頁頭（標題「問卷範本設計」 + 「+ 新增範本」按鈕）
  - 三 Tab（啟用/停用/封存）切換，各自呼叫 list API（is_active + is_archived 組合）
  - 列表 table（名稱/版本/題目數/SASB產業/建立時間/操作欄）
  - 空狀態引導
- [x] 4.2 操作欄按鈕邏輯：
  - 啟用/停用 Tab：「編輯題目」、「複製」、`is_active` toggle、「封存」
  - 封存 Tab：「取消封存」
  - 複製成功後切換至停用 Tab
- [x] 4.3 新增範本 Modal：名稱（必填）、版本（預設 1.0.0）、描述（選填），建立成功後 router.push 到新範本詳情頁
- [x] 4.4 封存確認 Modal：「確認封存後此範本將無法編輯，問卷發送時不會出現此範本。」
- [x] 4.5 路由 `/questionnaires/templates` 改指向此元件（`name: 'questionnaires-templates'`）

## 5. TemplateDetailView.vue 更新

- [x] 5.1 麵包屑修正：「系統設定 › 問卷範本」改為「ESG 問卷 › 問卷範本設計」，點擊跳轉 `/questionnaires/templates`
- [x] 5.2 頁頭補基本資訊 inline 編輯：點擊範本名稱旁的「編輯」小按鈕開啟 Modal（名稱/版本/描述），`PUT settingsApi.templates.update()` 儲存後 reload
- [x] 5.3 封存 Banner：`template.archived_at` 非 null 時在頁頭下方顯示黃色 banner，`isArchived` computed 控制所有操作按鈕 `disabled`

## 6. 驗證

- [x] 6.1 `npx vue-tsc --noEmit` 無錯誤
- [x] 6.2 進入 /questionnaires/templates 顯示範本列表，三 Tab 切換正常
- [x] 6.3 複製範本後停用 Tab 出現新記錄（name+' (複製)'）
- [x] 6.4 封存範本後移至封存 Tab，進入詳情顯示 banner 且操作 disabled
- [x] 6.5 取消封存後範本回到停用 Tab，操作恢復正常
