## Why

SAQ 計分引擎目前有三個結構性缺陷：
1. `saq_responses.raw_score` 從未被 AI 回寫（callback payload 缺少 `question_scores`），導致逐題趨勢矩陣永遠顯示空值
2. 未答題目不納入計分（AI 僅處理有 response 的題目），造成分母縮減、分數虛高
3. 審核員在 `under_review` 階段無法對個別題目的得分進行覆核，只能整體通過或退回

此外，供應商在審核完成後缺乏申訴管道，計分也沒有版本快照，無法追溯「分數因 weight 調整而重算」的變動歷程。

## What Changes

- **AI 補回 question_scores**：`scoring_service.py` 補填 `question_scores` 清單（`raw_score = answer_score × q.weight`），callback 已有接收路徑
- **未答題補 0 分**：`DispatchSaqScoringJob` 改為取 project 所有題目，未有 response 者帶入 `answer=null`（AI 側計為 0）
- **計分模式確立為 Mode A（E/S/G 三維加權）**：`category_avg(E)×w_E + category_avg(S)×w_S + category_avg(G)×w_G`，題目 weight 用於類別內加權平均（現況行為即 Mode A，此次明文化並補文件）
- **新增題目層審核覆核**：新表 `saq_response_reviews`，審核員在 `under_review` 階段可對每道題打覆核分，完成後重算 `saqs.final_score / final_grade`
- **新增分數版本快照**：新表 `saq_score_snapshots`，每次計分事件（submit / weight_updated / reviewer_override / re_review）新增一筆快照，含 `scoring_model_id`
- **新增供應商申訴流程**：`completed` 後 7 天內供應商可發起 dispute，進入 `disputed → re_review → finalized` 狀態鏈；`finalized` 為不可逆終態，鎖定 `final_score`
- **計分模式一致性警示**：Assessment Series 比較 API 回傳各波次 `scoring_model_id`，前端在不同 model 的波次之間標示虛線警示

## Capabilities

### New Capabilities
- `saq-reviewer-score-override`：題目層審核覆核，含覆核分重算 final_score 邏輯
- `saq-dispute-flow`：供應商申訴狀態機（disputed → re_review → finalized）與 7 天窗口期
- `saq-score-snapshot`：計分版本快照，append-only，記錄每次計分觸發原因與 scoring_model_id

### Modified Capabilities
- `saq-project-status-machine`：SAQ 狀態機新增 `disputed`、`re_review`、`finalized` 三個狀態
- `cross-project-score-comparison`：比較 API 新增 `scoring_model_id` 欄位與一致性警示旗標

## Impact

- `esgchain-ai/app/services/scoring_service.py`（補填 question_scores）
- `esgchain-ai/app/schemas/scoring.py`（SAQScoringResultResponse.question_scores 已存在，確認填充）
- `esgchain-api/app/Jobs/DispatchSaqScoringJob.php`（補全未答題）
- `esgchain-api/database/migrations/`（新增 saq_response_reviews、saq_score_snapshots；saqs 加 final_score/final_grade/disputed_at）
- `esgchain-api/app/Models/SAQ.php`、`SAQResponse.php`（新關聯）
- `esgchain-api/app/Services/Questionnaire/QuestionnaireService.php`（狀態機擴充、dispute 邏輯）
- `esgchain-api/app/Http/Controllers/Api/SAQ/SAQController.php`（新 API endpoint）
- `esgchain-web/src/views/questionnaires/SaqProjectDetailView.vue`（審核覆核 UI）
- `esgchain-web/src/views/portal/SupplierSurveyView.vue`（申訴入口）
- `esgchain-web/src/views/questionnaires/SeriesDetailView.vue`（不同 scoring_model 警示）
