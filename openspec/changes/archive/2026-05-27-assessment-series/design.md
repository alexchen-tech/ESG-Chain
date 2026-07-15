## Context

`project-question-snapshot` change 已建立 `assessment_series` 與 `assessment_series_weights` 兩張 table（migration 已執行），以及對應的 Eloquent Model，但尚無 Controller、Service、Routes 與前端。

目前 SaqProject 有 `series_id`、`template_ref_id`、`template_ref_version` 三個欄位，`project_questions.weight` 預設為 null，等待本 change 從 series weight schema 填入。

## Goals / Non-Goals

**Goals:**
- Series CRUD（建立/列表/詳情/更新/封存）
- Series 下 Project 管理（加入/移除/排序）
- Weight schema 設定：新 series 手動設定各題 weight；後續 project 繼承 series weight
- `project_questions.weight` 在快照時從 series weight schema 填入
- 跨 project 分數比較：依 `series_id + supplier_id`，以 `source_template_question_id` 對齊不同 project 的 raw_score
- 前端 Series 管理頁（列表/建立）、Series 詳情頁（含供應商趨勢圖）

**Non-Goals:**
- 不修改計分引擎（esgchain-ai 已接受 per-question weight）
- 不支援 series 間的跨系列比較
- 不做 weight 的歷史版本管理（weight 變更直接覆蓋）

## Decisions

### D1：Weight Schema 存放在 `assessment_series_weights` 而非 project_questions
Series 的 weight schema 以 `series_id + source_template_question_id + weight` 存在 `assessment_series_weights`。快照時 `ProjectQuestionService::snapshot()` 查詢此表填入 `project_questions.weight`。
**理由**：weight 是 series 層級的政策，與特定 project 無關；若存在 project_questions 則每次建立 project 都需手動設定。

### D2：新 series 第一個 project 手動設定 weight
建立 series 時不需要立即設定 weight；第一個 project 建立後，提示使用者至 Series Weight 設定頁填入各題 weight，才可快照並發送問卷。後續 project 加入同 series 時自動繼承。
**理由**：Weight 設定需要知道快照後的 project_questions，先建立 project 再設定 weight 符合操作流程。

### D3：比較 API 在 Laravel 層計算，不走 FastAPI
`GET /api/v1/assessment-series/{id}/comparison?supplier_ids[]=...` 直接在 Laravel 查詢 saq_responses.raw_score，按 source_template_question_id + supplier_id 彙整，回傳 per-question 趨勢。
**理由**：比較只涉及 raw_score 聚合，無需 AI 計算；FastAPI 負責計分，Laravel 負責業務資料整合。

### D4：Series 狀態簡化為 active / archived
不做複雜狀態機，series 只有 active（可加入新 project）與 archived（唯讀）。

## Risks / Trade-offs

- [Risk] weight 設定後新加入的 project 才會繼承，之前快照的 project_questions.weight 不會自動更新 → Mitigation：UI 提示「修改 weight 後需重新建立 project 才生效」，不提供回填功能
- [Risk] 同一 series 不同 project 可能使用不同範本，source_template_question_id 不同導致比較空白 → Mitigation：前端比較表格中，無對應題目的格子顯示「—」，不強制範本一致
- [Risk] 第一個 project 建立後 weight 尚未設定，快照的 project_questions.weight 為 null → Mitigation：發送問卷前驗證 weight 是否已設定，null weight 發出警告但不阻擋（計分引擎以 weight=1.0 為預設）

## Migration Plan

- `assessment_series` 與 `assessment_series_weights` table 已在 `2026_05_27_000002` migration 建立
- `saq_projects.series_id` 欄位已存在
- 本 change 無需新 migration，僅補充 Controller / Service / Routes / 前端
- 上線後既有 SaqProject（series_id = null）不受影響，繼續獨立運作

## Open Questions

- Series 列表頁是否需要顯示「各供應商最新 grade」摘要？（暫定：詳情頁才顯示）
- Weight 設定 UI 是否在 Series 詳情頁內的獨立 Tab，還是 Modal？（暫定：獨立 Tab）
