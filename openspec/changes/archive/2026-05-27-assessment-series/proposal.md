## Why

跨年度/跨期的供應商 ESG 表現難以比較：每個 SaqProject 各自獨立，無法追蹤同一供應商在不同期間的改善軌跡。Assessment Series 將相關專案組織成「系列」，讓品牌採購商可看到供應商在同一評核框架下的分數趨勢，驅動持續改善。

## What Changes

- 新增 `AssessmentSeries` 實體，用於將多個 SaqProject 歸屬至同一系列
- 新增 `AssessmentSeriesWeight` 實體，定義系列層級的 E/S/G 權重與各題 weight schema
- 建立 Series CRUD API（建立、列表、詳情、更新、刪除）
- 建立 Series 下的 Project 管理（加入/移除）
- SaqProject 建立時可指定 series_id，並繼承 series 的 weight schema
- 新 series 下第一個 project：使用者手動設定各題 weight
- 後續加入相同 series 的 project：自動繼承 series weight schema
- 跨專案分數比較 API（依 supplier_id + source_question_id 追蹤分數變化）
- 前端：Series 管理頁、Series 詳情頁（供應商分數趨勢圖）

## Capabilities

### New Capabilities

- `assessment-series-management`: Series 的 CRUD 操作、Series 下 Project 管理、weight schema 設定
- `cross-project-score-comparison`: 依 series + supplier_id 跨 project 比較 raw_score，呈現供應商趨勢

### Modified Capabilities

- `saq-project-domain`: SaqProject 建立時支援 series_id 欄位並繼承 weight schema（schema 欄位已存在，需補 API 行為規格）
- `project-question-snapshot`: project_questions.weight 原為 null，現需從 series weight schema 填入

## Impact

- **esgchain-api**：AssessmentSeries / AssessmentSeriesWeight 模型（migration 已建立，需補 Controller / Service / Routes）；SaqProjectController::store() 加入 series weight 繼承邏輯
- **esgchain-web**：新增 Series 管理頁面（`/questionnaires/series`）、Series 詳情含供應商趨勢圖
- **esgchain-ai**：計分時已接收 weight per question，無需修改；比較 API 由 Laravel 直接計算 raw_score 差異
- **Sidebar**：ESG 問卷群組加入「系列管理」入口
