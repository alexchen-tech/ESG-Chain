# Tasks: assessment-series

## 1. Backend Models & Service

- [x] 1.1 確認 `AssessmentSeries` Model 完整（fillable 含 name/description/domain/status/created_by_id，scopeActive()，relation: projects() HasMany SaqProject）
- [x] 1.2 確認 `AssessmentSeriesWeight` Model 完整（fillable 含 series_id/source_template_question_id/weight）
- [x] 1.3 建立 `AssessmentSeriesService`：create()、update()、archive()、setWeights()、getWeights()、getProjects()、getComparison()

## 2. Backend Controllers & Routes

- [x] 2.1 建立 `AssessmentSeriesController`：index()、store()、show()、update()、archive()
- [x] 2.2 建立 `AssessmentSeriesWeightController`：index()、update()（PUT，批次 upsert）
- [x] 2.3 建立 `AssessmentSeriesComparisonController`：show()（GET comparison，接收 supplier_ids[]）
- [x] 2.4 routes/api.php 新增 series 相關路由：
      - GET/POST `/assessment-series`
      - GET/PUT/POST(archive) `/assessment-series/{series}`
      - GET/PUT `/assessment-series/{series}/weights`
      - GET `/assessment-series/{series}/projects`
      - GET `/assessment-series/{series}/comparison`
- [x] 2.5 docker cp controllers + routes → container，`php artisan route:clear`

## 3. SaqProject 繼承 Weight 邏輯

- [x] 3.1 `SaqProjectController::store()`：驗證 series_id（若帶入）對應 series 存在且 status = 'active'，否則 422
- [x] 3.2 `ProjectQuestionService::snapshot()`：快照後若 project 有 series_id，查詢 `assessment_series_weights`，依 `source_template_question_id` 更新 project_questions.weight
- [x] 3.3 docker cp SaqProjectController + ProjectQuestionService → container

## 4. Frontend API Module

- [x] 4.1 `esgchain-web/src/api/modules/saq.ts`：新增 `assessmentSeriesApi`（list、create、update、archive、getWeights、setWeights、getProjects、getComparison）
- [x] 4.2 新增 TypeScript 型別：`AssessmentSeries`、`AssessmentSeriesWeight`、`SeriesComparisonResponse`

## 5. Frontend Series 管理頁

- [x] 5.1 新增 `SeriesListView.vue`（`/questionnaires/series`）：系列列表表格（名稱/domain/project 數量/最近日期/status）、「建立系列」Modal（name/description/domain）
- [x] 5.2 新增 `SeriesDetailView.vue`（`/questionnaires/series/:id`）：含三個 Tab（概覽、Weight 設定、供應商比較）
- [x] 5.3 Series 詳情 - 概覽 Tab：顯示 series 基本資訊、projects 列表（點擊跳轉 SaqProjectDetailView）
- [x] 5.4 Series 詳情 - Weight 設定 Tab：列出題目（取自第一個 project 的 project_questions），逐題 weight 輸入框，儲存呼叫 setWeights API
- [x] 5.5 Series 詳情 - 供應商比較 Tab：供應商多選下拉（saqApi.getProjectSaqs 取得已完成的 supplier 清單）、橫軸 project 縱軸 total_score 折線圖（使用 canvas 或純 CSS 實作，不引入新圖表庫）、下方逐題分數對比表格

## 6. Router & Sidebar

- [x] 6.1 `router/index.ts`：新增 `/questionnaires/series`（SeriesListView）與 `/questionnaires/series/:id`（SeriesDetailView）路由，僅 sustain/comply/analyst/admin 可存取
- [x] 6.2 `AppSidebar.vue`：在「ESG 問卷」群組加入「系列管理」項目，icon 使用 `M` 或 link-circle svg

## 7. SaqProjectsView 整合

- [x] 7.1 `SaqProjectsView.vue`：建立 project Modal 加入「系列」選單（series 列表下拉，選填），選擇後顯示「將繼承此系列的 weight 設定」提示

## 8. 驗證

- [x] 8.1 建立 series、設定 weights、建立 project（指定 series）→ 確認 project_questions.weight 正確填入
- [x] 8.2 comparison API 回傳結構正確，不對齊題目以 null 填充
- [x] 8.3 封存 series 後無法建立新 project 加入該 series（422）
- [x] 8.4 前端 Weight Tab 儲存後，下次進入頁面 weight 正確顯示
- [x] 8.5 `php artisan route:list` 確認所有 assessment-series 路由存在
