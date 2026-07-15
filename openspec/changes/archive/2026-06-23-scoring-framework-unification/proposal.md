## Why

系統目前有兩個描述「評核框架」的欄位，語義高度重疊但存在於不同實體：

- `SAQTemplate.scoring_framework`（強型別）：驅動計分引擎、DB Trigger 約束、pillar 加權查詢
- `SaqProject.domain` / `AssessmentSeries.domain`（弱型別）：顯示用，建立時從 `template.scoring_framework` 複製一次，之後不再更新

**實際資料顯示：**
- 全部 7 筆 SaqProject：`domain == template.scoring_framework`（100% 一致）
- 全部 4 筆 AssessmentSeries：`domain == template.scoring_framework`（100% 一致）
- 無通用型範本（`scoring_framework IS NULL` = 0 筆）

`domain` 欄位從來就不是獨立資料——它是 `scoring_framework` 建立當下的冗餘快照，且 Template 為 soft-delete（`archived_at`），JOIN 永遠有效。AI service 的 `project_domain` 參數在計分路徑上亦永遠為 `None`（已是死碼）。

## What Changes

### 移除
- `AssessmentSeries.domain` 欄位的寫入與讀取邏輯（欄位保留 nullable，不立即 DROP）
- `SaqProject.domain` 欄位的寫入與讀取邏輯（欄位保留 nullable，不立即 DROP）
- `SaqProjectController` 的 `VALID_DOMAINS` 驗證（框架約束已由 Template Trigger 執行）
- `SaqProjectController` 的「active 後不可改 domain」鎖定邏輯（由 template_id 不可變性隱性保障）
- AI `scoring_service._resolve_framework()` 的 `project_domain` fallback（簡化為直接使用 `scoring_framework`）
- AI 計分 payload 的 `project_domain` 欄位（`DispatchSaqScoringJob` 本就未傳）

### 修改
- 所有讀 `series.domain` 或 `project.domain` 的前端頁面，改讀 `template.scoring_framework`（確保 template 已 eager load）
- `TagLibraryController::VALID_DOMAINS` 補上 `ISO26000`（現有遺漏 bug）

### 不變
- `SAQTemplate.scoring_framework`：唯一來源，完全不動
- DB Trigger 框架約束
- `framework_default_weights` 計分設定查詢邏輯
- `assessment_series.domain` 和 `saq_projects.domain` 欄位的 DB 結構（保留，不 DROP）

## Capabilities

### Modified Capabilities
- `saq-project-domain`：移除 Project 層的 domain 欄位管理，改從 Template 讀取框架資訊
- `assessment-series-management`：移除 Series 層的 domain 欄位，框架由 `series.template.scoring_framework` 唯一決定

## Impact

- **esgchain-api**：移除 `AssessmentSeriesService::create()` 的 domain 寫入；移除 `SaqProjectController` 的 domain 驗證與鎖定；移除 AI payload 的 project_domain（無實際影響，已不傳）
- **esgchain-web**：前端各頁面的 `?? s.domain` fallback 改為 `series.template?.scoring_framework`；確保所有 API 呼叫的 template 已 eager load
- **esgchain-ai**：`_resolve_framework()` 簡化，移除 `project_domain` 參數；schema 同步移除欄位

統一後語義：
```
Template.scoring_framework（唯一來源，不可變）
  ├── 約束題目 TAG（DB Trigger）
  ├── 計分 pillar 分組（SLUG_PREFIX_TO_PILLAR）
  ├── pillar 預設加權（framework_default_weights）
  ├── 顯示框架 badge（Series 列表、Project 列表、Series 詳情）
  └── 自動命名推導（「Q3 ESG 評核」）
```
