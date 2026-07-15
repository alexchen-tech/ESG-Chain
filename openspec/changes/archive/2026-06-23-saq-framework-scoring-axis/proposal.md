## Why

`saq_projects.domain` 目前同時承擔「計分框架過濾器」與「UI 分類標籤」兩個職責，導致同一份問卷範本在不同 domain 的系列下產生完全不可比的分數（ESG domain 平均 57–62 分 vs ISO20400 domain 平均 14.6 分），根本原因是 ISO20400 範本中 18/30 道題缺乏 ISO20400 TAG，domain filter 縮小了有效計分題母數。此外，E/S/G 三個固定欄位無法表達 ISO26000 的 7 個支柱或 Geo-Risk 的 2 個維度，計分維度與框架語意脫鉤。

## What Changes

- **範本宣告計分框架**：`saq_templates` 新增 `scoring_framework` 欄位，計分邏輯改由範本決定而非系列 domain
- **範本 TAG 覆蓋率強制 100%**：凡範本宣告 scoring_framework，該框架對應的所有 L2 pillar TAG 必須覆蓋到每一道題（為現有 ISO20400 範本補 18 道題的 TAG）
- **計分輸出改為動態 pillar 維度**：`saqs` 新增 `category_scores JSON` 欄位取代固定的 `score_e / score_s / score_g`（舊欄位保留以維持向後相容，但僅在 ESG 框架時有意義）
- **`saq_projects.domain` 職責單純化**：domain 改為純 UI 分類標籤，不再驅動計分 slug 過濾；計分過濾改由範本 `scoring_framework` 決定
- **前端計分明細改用 pillar 維度顯示**：ReviewDetailView、SAQ 分數 badge 等改為讀取 `category_scores`

## Capabilities

### New Capabilities

- `saq-template-framework`: 範本宣告 scoring_framework 與各 L2 pillar 權重設定

### Modified Capabilities

- `saq-scoring-engine`: 計分輸出從固定 E/S/G 三維改為依框架動態 pillar 維度，新增 `category_scores` 欄位
- `saq-project-domain`: domain 職責從「計分過濾器」降階為「UI 分類標籤」，計分改依範本框架
- `question-tag-library`: 新增「範本框架 TAG 覆蓋率」規則；補充 ISO20400 範本 18 道題的框架 TAG assignments
- `cross-project-score-comparison`: 可比性條件從「同系列」調整為「同範本 + 同 scoring_framework」

## Impact

- **esgchain-ai** `scoring_service.py`：`_filter_slugs_by_domain()` 改為由 payload 帶入 `scoring_framework`，`category_scores` dict 動態建立
- **esgchain-api** `saqs` 表：新增 `category_scores JSON` 欄位（migration）；scoring payload 改帶 `scoring_framework` 而非 `project_domain`
- **esgchain-web** `ReviewDetailView.vue`：分類明細改讀 `category_scores`；總分公式顯示改為動態 pillar 權重
- **資料層**：`question_tag_assignments` 補 18 筆 ISO20400 TAG（一次性 seed）
- 舊的 `score_e / score_s / score_g` 在 ESG 框架下繼續填入，非 ESG 框架填 null（向後相容）
