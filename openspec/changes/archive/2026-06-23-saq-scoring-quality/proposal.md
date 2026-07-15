## Why

現有 SAQ 計分引擎對所有題型使用同一套粗糙規則：boolean「是」固定 100 分（未考慮反向題語意）、有序單選全部 fallback 至 50 分（喪失鑑別力）、文字題只要有填就給 50 分（無法區分答案品質）。這導致最終分數無法真實反映供應商的 ESG 實踐水準，審核員必須靠人工判斷彌補，與 20 分鐘審核時限產生衝突。

## What Changes

- **新增題目計分 metadata**：在 `template_questions` / `project_questions` 加入 `scoring_direction`（正向/反向）、`scoring_type`（ordered_asc / ordered_desc / custom / evidence_only / llm）、`option_scores`（custom 分值字典）
- **修正反向題評分**：「是否有違規記錄？」等反向題，「否」應得 100 分，透過 `scoring_direction: negative` 自動翻轉
- **有序單選自動線性化**：標記 `ordered_asc`/`ordered_desc` 的題目，依選項數量自動分配等差分值（5 選項 → 0/25/50/75/100），無需人工逐項設定
- **文字題分類**：`evidence_only` 標記的文字題不計入分數，UI 顯示「佐證說明」標籤；`llm` 類型的文字題由 AI 進行品質評分並附理由
- **計分信心度**：每題計算 `confidence`（high / medium / low），低信心題在 ReviewDetailView 標示 ⚠ 提示審核員優先覆核
- **LLM 文字評分**：`scoring_type = llm` 的開放式文字題，Celery 呼叫 LLM 依題目語境評分（0–100）並回傳評分理由，存入 `saq_responses.llm_score_reason`

## Capabilities

### New Capabilities

- `saq-question-scoring-metadata`: 題目層計分 metadata 的資料模型與編輯介面——`scoring_direction`、`scoring_type`、`option_scores` 欄位定義、模板編輯器中的設定 UI、及自動線性化算法
- `saq-llm-text-scoring`: LLM 評估開放式文字題品質的非同步流程——Celery task、prompt rubric、`llm_score_reason` 欄位、信心度回傳

### Modified Capabilities

- `saq-scoring-engine`: `_score_single_response()` 擴充以支援新 metadata，`confidence` 欄位計算邏輯加入 scoring spec
- `saq-reviewer-score-override`: 低信心題 ⚠ 標示邏輯加入 ReviewDetailView spec

## Impact

- **esgchain-ai**：`scoring_service.py` 擴充 `_score_single_response()`；新增 `llm_scoring_task.py` Celery task
- **esgchain-api**：`template_questions` / `project_questions` migration 新增 3 個欄位；`saq_responses` 新增 `llm_score_reason`；`DispatchSaqScoringJob` 傳入新 metadata
- **esgchain-web**：模板編輯器新增計分設定面板；ReviewDetailView 新增信心度徽章與 LLM 理由顯示
- **不影響**：現有分數快照、申訴流程、Shipment snapshot 鎖定機制皆不受影響
