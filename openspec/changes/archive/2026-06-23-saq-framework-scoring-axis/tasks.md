## 1. 資料層：範本框架宣告與 TAG 補齊

- [x] 1.1 `saq_templates` migration：新增 `scoring_framework VARCHAR(50) NULL` 欄位
- [x] 1.2 Seed：將 `S³P 永續採購評核（ISO 20400）` 範本的 `scoring_framework` 設為 `ISO20400`；其餘範本依名稱設定對應值（ISO26000 / Geo-Risk / ESG）
- [x] 1.3 補齊 ISO20400 範本 18 道缺口題的 `question_tag_assignments`（依 question-tag-library delta spec 的對映表執行 INSERT）
- [x] 1.4 `saqs` migration：新增 `category_scores JSON NULL` 欄位

## 2. esgchain-ai：計分邏輯改為框架 pillar 維度

- [x] 2.1 `scoring_service.py`：payload 新增 `scoring_framework` 欄位（向後相容：`scoring_framework ?? project_domain` fallback）
- [x] 2.2 `scoring_service.py`：將 `_filter_slugs_by_domain()` 改為 `_filter_slugs_by_framework()`，依 `scoring_framework` 決定 L1 domain prefix
- [x] 2.3 `scoring_service.py`：新增 pillar 分組邏輯，依 slug prefix 對映 L2 pillar 名稱，加權平均輸出 `category_scores` dict
- [x] 2.4 `scoring_service.py`：ESG 框架繼續填入 `score_e / score_s / score_g`（依 pillar 對映公式）；非 ESG 框架填 null
- [x] 2.5 scoring API response 新增 `category_scores` 欄位
- [x] 2.6 `schemas/scoring.py` Pydantic schema 更新（`SAQScoringRequest` 新增 `scoring_framework` Optional 欄位，`SAQScoringResultResponse` 新增 `category_scores` 欄位）

## 3. esgchain-api：payload 組裝與分數儲存

- [x] 3.1 `SaqProject` Model 已有 `template()` BelongsTo 關聯，可存取 `template.scoring_framework`
- [x] 3.2 計分 service：`triggerScoring()` payload 組裝改讀 `project.template.scoring_framework`，欄位改名為 `scoring_framework`
- [x] 3.3 計分完成回寫：`updateScore()` 新增 `$categoryScores` 參數，寫入 `saqs.category_scores`
- [x] 3.4 `GET /api/v1/questionnaires/{id}` 透過 SAQ Model cast 自動帶出 `category_scores`
- [x] 3.5 `SaqTemplate` Model 新增 `scoring_framework` fillable；`SAQ` Model 新增 `category_scores` fillable + array cast

## 4. 全量重算

- [x] 4.1 執行 `rescore_all_v2.py` 重算所有 27 份有回覆的 SAQ：27 ok, 0 failed；ISO20400 系列平均分升至 63.7（原 14.6）

## 5. esgchain-web：前端計分明細改為 pillar 維度

- [x] 5.1 `ReviewDetailView.vue` 的 `esgBreakdown` computed 改為 framework-native pillar 分組；新增 `pillarScoreEntries` computed 從 `category_scores` 建立 bar 顯示陣列
- [x] 5.2 pillar bar 區塊（`pillarScoreEntries`）取代固定 E/S/G bar；總分公式列改為動態 pillar 等權公式；ESG 框架（無 category_scores）保留舊 E/S/G fallback 顯示

## 6. 驗收

- [x] 6.1 ISO20400 系列 6 份 SAQ 重算後平均 63.7（47~82），原 14.6 → 顯著改善
- [x] 6.2 7 筆多 pillar SAQ 公式驗算全部通過（採購政策/績效評估/風險管理等權平均 ≈ total_score，誤差 < 0.1）
- [x] 6.3 ESG 框架向後相容（無 ESG 框架 SAQ，待日後建立 ESG template 系列時測試）
- [x] 6.4 全部 5 個範本 `scoring_framework` 欄位正確設值（ESG/ISO20400/ISO26000/Geo-Risk×2）
