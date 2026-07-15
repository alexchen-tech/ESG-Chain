# Tasks: saq-scoring-quality

## 1. DB Migration（esgchain-api）

- [x] 1.1 建立 migration：`template_questions` 加入 `scoring_direction` enum（positive/negative，預設 positive）、`scoring_type` varchar(20) nullable、`option_scores` JSON nullable
- [x] 1.2 建立 migration：`project_questions` 加入相同三個欄位（scoring_direction / scoring_type / option_scores）
- [x] 1.3 建立 migration：`saq_responses` 加入 `llm_score` decimal(5,2) nullable、`llm_score_reason` text nullable、`score_confidence` enum('high','medium','low') nullable

## 2. 後端 Model 更新（esgchain-api）

- [x] 2.1 `TemplateQuestion` Model：加入新欄位至 `$fillable`，加入 `$casts`（option_scores → array）
- [x] 2.2 `ProjectQuestion` Model：同上
- [x] 2.3 `SAQResponse` Model：加入 `llm_score`、`llm_score_reason`、`score_confidence` 至 `$fillable` 與 `$casts`

## 3. AI 計分邏輯擴充（esgchain-ai）

- [x] 3.1 `SAQResponseItemRequest` schema 加入 `scoring_direction`、`scoring_type`、`option_scores` 欄位
- [x] 3.2 `_score_single_response()` 擴充：依優先順序實作 evidence_only / llm(暫定0.5) / custom / ordered_asc / ordered_desc / boolean+direction / fallback
- [x] 3.3 `_score_single_response()` 回傳值改為 `tuple[float | None, str]`（score, confidence），evidence_only 回傳 `(None, None)`
- [x] 3.4 `calculate_saq_score()` 更新：evidence_only 題排除分子分母；從 response 取得 confidence 並回傳至 callback
- [x] 3.5 `SAQScoringResultResponse` 加入 `question_scores` 中的 `confidence` 欄位

## 4. AI 計分 Callback 更新（esgchain-api）

- [x] 4.1 `DispatchSaqScoringJob` 傳遞 `scoring_direction`、`scoring_type`、`option_scores` 至 AI payload
- [x] 4.2 `SAQController::scoreCallback()` 接收並寫入每題 `score_confidence` 至 `saq_responses`

## 5. LLM 文字評分 Task（esgchain-ai）

- [x] 5.1 建立 `app/prompts/llm_text_scoring_v1.txt`：system prompt rubric（角色、評分標準表、JSON 回傳格式）
- [x] 5.2 建立 `app/tasks/llm_scoring_tasks.py`：`llm_text_scoring_task` Celery task，呼叫 LLM、解析 JSON、最多重試 3 次
- [x] 5.3 `scoring_tasks.py` callback 完成後，對 `scoring_type = llm` 的 responses 批次派發 `llm_text_scoring_task`
- [x] 5.4 建立 Laravel route + controller action：`POST /api/v1/saqs/{saq}/llm-score-callback`，接收 LLM 結果寫入 `saq_responses`，更新 `score_confidence = medium`，觸發重算

## 6. 前端：ReviewDetailView 信心度顯示

- [x] 6.1 `saq.ts` API interface 的 response 型別加入 `score_confidence`、`llm_score_reason`
- [x] 6.2 ReviewDetailView 分數列加入信心度徽章（● / ◐ / ⚠），依 `score_confidence` 條件渲染
- [x] 6.3 `score_confidence = medium` 時，分數列下方加入可展開的 LLM 理由區塊（`llm_score_reason`）
- [x] 6.4 `scoring_type = evidence_only` 題目：分數列改為「此題為佐證說明，不計入評分」灰色提示，隱藏覆核輸入面板
- [x] 6.5 題目 header：`evidence_only` 顯示「📎 佐證」chip（與現有 type badge 同排）

## 7. 前端：模板編輯器計分設定面板

- [x] 7.1 `TemplateDetailView` 題目 Modal 加入「計分設定」折疊面板
- [x] 7.2 `scoring_direction` 切換（正向/反向），預設正向
- [x] 7.3 `scoring_type` 選擇器（自動升序 / 自動降序 / 自訂分值 / 佐證說明 / LLM評分 / 預設）
- [x] 7.4 選擇「自訂分值」時，各選項旁顯示 0–100 分值輸入欄
- [x] 7.5 選擇「佐證說明」時，隱藏分值相關欄位，顯示說明文字
- [x] 7.6 模板儲存時一併儲存 `scoring_direction`、`scoring_type`、`option_scores`

## 8. Seed 資料更新

- [x] 8.1 執行 DB 更新腳本：boolean 題中含否定詞者標記 scoring_direction = negative
- [x] 8.2 執行 DB 更新腳本：18 題 single_choice 更新為 scoring_type = ordered_asc
- [x] 8.3 `SaqDemoSeeder`：seed 資料的 `saq_responses` 補充 `score_confidence`（boolean/choice=high, text=low）
