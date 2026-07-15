## Context

系統目前用兩個欄位描述同一概念「評核框架」：
- `SAQTemplate.scoring_framework`：驅動型，建立時設定，永不修改
- `SaqProject.domain` / `AssessmentSeries.domain`：顯示型，從 template 複製一次後靜置

實際資料 100% 一致，無通用型範本（`scoring_framework IS NULL`=0），AI `project_domain` 參數永遠為 `None`（死碼）。

## Goals / Non-Goals

**Goals:**
- 移除 `Project.domain` 和 `Series.domain` 的寫入邏輯
- 前端改從 `template.scoring_framework` 讀取，移除所有 `?? domain` fallback
- 移除 AI service 的 `project_domain` 參數（死碼清除）
- 順手修正 `TagLibraryController::VALID_DOMAINS` 遺漏 ISO26000 的 bug

**Non-Goals:**
- 不執行 `ALTER TABLE DROP COLUMN`（欄位保留 nullable，觀察期後再移除）
- 不修改 `SAQTemplate.scoring_framework`（唯一來源，不動）
- 不修改 DB Trigger 框架約束邏輯
- 不修改計分引擎 pillar 分組邏輯（`SLUG_PREFIX_TO_PILLAR`）

## Decisions

**D1：欄位保留、寫入停止、讀取改 JOIN**

`assessment_series.domain` 和 `saq_projects.domain` 欄位維持 nullable 但停止寫入。所有需要「框架」資訊的地方改讀 `template.scoring_framework`（API 回傳資料已含 template 物件）。未來穩定後另起 change 執行 DROP COLUMN。

**D2：前端以 `template.scoring_framework` 取代所有 `?? domain` fallback**

受影響的 Vue 元件共 5 個：
- `SeriesListView.vue`：`s.template?.scoring_framework ?? s.domain` → `s.template?.scoring_framework`
- `SeriesDetailView.vue`：`series.domain` → `series.template?.scoring_framework`（含自動命名產生）
- `SaqProjectsView.vue`：已讀 `template.scoring_framework`，移除備援
- `SaqProjectDetailView.vue`：`project.domain` badge → `project.template?.scoring_framework`
- `ReviewDetailView.vue`：`?? saq?.project?.domain` → 移除 fallback

確認各 API 呼叫的 response 均已 eager load template，若有缺漏同步補上。

**D3：移除 SaqProjectController 的 domain 驗證與鎖定**

`store()` 移除 `domain = template.scoring_framework` 寫入；`update()` 移除 domain 驗證規則與「active 後不可改」鎖定（框架不可變性由 `template_id` 的不可修改性保障）。

**D4：移除 AssessmentSeriesService::create() 的 domain 寫入**

建立 Series 時不再寫 `domain` 欄位，該欄位自然為 `NULL`（DB nullable）。

**D5：AI service 清理 `project_domain`**

- `CelerySaqScoringRequest` 和 `SaqScoringRequest` schema 移除 `project_domain` 欄位
- `calculate_saq_score()` 函式移除 `project_domain` 參數
- `_resolve_framework()` 簡化為 `return scoring_framework`（移除 fallback 邏輯）
- Celery task `async_score_task.delay()` 移除傳遞 `project_domain`

**D6：TagLibraryController::VALID_DOMAINS 補 ISO26000**

現有清單 `['ESG', 'ISO20400', 'Geo-Risk', 'Product-Compliance']` 補上 `'ISO26000'`，與 `QuestionnaireTemplateController` 的驗證清單一致。

## Risks / Trade-offs

- **欄位殘留**：`domain` 欄位不 DROP 但停止寫入，新建的 Series / Project 其 domain 為 NULL，若有外部腳本或報表直接讀 domain 欄位會拿到 NULL。風險可接受，因已知沒有此類依賴。
- **Template eager load**：前端去掉 `?? domain` fallback 後，若 API 回傳的 template 為 null（例如 template 被硬刪除），框架顯示會變空字串而非舊值。Template 為 soft-delete，此情境不會發生於正常流程。
- **AI schema 變更**：移除 `project_domain` 屬於非破壞性移除（欄位有預設 `None`），即使 API 仍傳舊欄位也不影響運作；但 API 端（`DispatchSaqScoringJob`）本就不傳 `project_domain`，故無相容問題。
