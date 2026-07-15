## Context

ESG-Chain 的 SAQ 計分由 FastAPI（esgchain-ai）執行，Laravel（esgchain-api）透過 Celery Job 觸發，完成後 callback 回填 `saqs.score/grade`。現有架構已預留 `question_scores` callback 欄位但從未使用；`saq_responses.raw_score` 欄位存在但恆為 null。

本次設計目標：補完計分鏈中三個斷點（raw_score 回寫、未答補零、覆核機制），並新增兩個子系統（申訴流程、計分快照）。

## Goals / Non-Goals

**Goals:**
- `raw_score` 語意確立：`answer_score(0–100) × q.weight`，表示此題對總分的加權貢獻（值域 0 ~ q.weight）
- 計分模式 A 正式確立並文件化：E/S/G 三維加權，q.weight 用於類別內加權平均
- 未答題計 0（懲罰性），不縮減分母
- 審核員可對每道題打覆核分，覆核後重算 `final_score`
- 供應商可在 `completed` 後 7 天內發起申訴
- 計分事件留快照，含 `scoring_model_id`

**Non-Goals:**
- 改動 E/S/G 三維加權比例（由 `scoring_models` table 管理，非本次範疇）
- 供應商申訴後自動重新 AI 計分（re_review 由審核員手動判斷）
- 歷史已完成 SAQ 的快照補填（僅對新觸發計分的 SAQ 建立快照）

## Decisions

### D1：raw_score 語意 = answer_score × q.weight
`answer_score` 為 0–100 的單題原始分（「是」=100, 「否」=0 等），`raw_score = answer_score × q.weight`，值域為 `[0, q.weight]`。

趨勢矩陣顯示 `raw_score`，加總即等於 `total_score`（近似），對 ESG 稽核師提供題目貢獻直觀視角。

**淘汰方案**：顯示 `answer_score`（0–100）—更直觀，但無法直接看出題目對總分的影響力。

### D2：scoring_service 補填 question_scores
在 `calculate_saq_score()` 函式末尾，遍歷每個 response，計算 `raw_score = _score_single_response(r) × r.weight`，組成 `list[QuestionScoreItem]` 填入 `SAQScoringResultResponse.question_scores`。

Celery task 已將 `result.model_dump()` 整個傳給 callback，Laravel callback handler 已有 `question_scores` 接收路徑，無需改動 callback 協定。

### D3：未答題由 DispatchSaqScoringJob 補齊
Job 改為：取 project 所有 `project_questions`，對每道題 `LEFT JOIN saq_responses`，無 response 者帶入 `answer=null, answer_options=[]`。AI 側 `_score_single_response` 遇 null 回傳 0.0，行為已符合「未答計 0」語意，無需改 AI。

### D4：saqs 新增 final_score / final_grade / disputed_at
- `final_score / final_grade`：覆核後的最終分，null 表示使用 AI 原始分（`score / grade`）
- `disputed_at`：記錄申訴時間，用於 7 天窗口期計算
- UI 顯示「最終評分」時：`COALESCE(final_score, score)`

### D5：saq_response_reviews 設計
```
saq_response_reviews
  id                  uuid PK
  saq_id              uuid FK→saqs CASCADE
  project_question_id uuid FK→project_questions
  reviewer_id         uuid FK→users
  reviewer_score      decimal(5,2)  -- 0~100 原始分
  note                text null
  created_at / updated_at
  UNIQUE(saq_id, project_question_id)  -- 一題只有一筆最新覆核
```

覆核完成後，後端用覆核分重算 `final_score`：
```
對每道題：
  effective_score = reviewer_score（若有覆核）else AI raw_score / q.weight（還原 answer_score）
再走一次 Mode A 計算邏輯 → final_score / final_grade
→ 新增 saq_score_snapshots(trigger='reviewer_override')
```

### D6：saq_score_snapshots 設計
```
saq_score_snapshots
  id               uuid PK
  saq_id           uuid FK→saqs CASCADE
  score            decimal(5,2)
  grade            string(1)
  score_e          decimal(5,2) null
  score_s          decimal(5,2) null
  score_g          decimal(5,2) null
  scoring_model_id uuid null
  trigger          enum('submit','weight_updated','reviewer_override','re_review')
  triggered_by     uuid null  -- user_id，submit 時為 null
  scored_at        timestamp
```

Append-only：不可 UPDATE / DELETE。

### D7：申訴狀態機擴充
新增三個狀態至 `QuestionnaireService::TRANSITIONS`：

```
completed  --[dispute]-----> disputed      (供應商，7天內)
disputed   --[re_review]---> re_review     (審核員，開始重新審核)
re_review  --[finalize]----> finalized     (審核員，終態不可逆)
```

`finalized` 時：`final_score / final_grade` 鎖定，`saqs.score` 保留原 AI 值供稽核比對。

7 天窗口期：Laravel 在 `dispute` action 前檢查 `completed_at`（即 `reviewed_at`）至今是否超過 7 天，超過則 422。

### D8：Assessment Series 比較 API 加 scoring_model 資訊
`getComparison()` 對每個 project 加入：
```json
{ "id": "...", "name": "...", "scoring_model_id": "...", "created_at": "..." }
```
前端：若相鄰兩波次 `scoring_model_id` 不同，折線兩點之間改為虛線，並加 tooltip「此波次使用不同計分模型，分數不直接可比」。

## Risks / Trade-offs

- **raw_score 值域小（0~0.08）**：趨勢矩陣數值接近 0，閱讀需注意格式（建議顯示至小數點後 3 位）。若未來決定改顯示 answer_score，只需改前端 getter，raw_score 欄位不變。
- **UNIQUE(saq_id, project_question_id) 在 saq_response_reviews**：審核員只能有一筆覆核；若需要版本歷程，需改為不加 UNIQUE 並另記 `latest` 旗標。本次決策：保持 UNIQUE，以 `updated_at` 追蹤最後修改。
- **覆核重算邏輯在 Laravel 執行**：不依賴 AI 服務，讓審核員可離線操作。但覆核計算邏輯需與 AI scoring_service 保持一致（Mode A），需維護兩份平行邏輯。**Mitigation**：在 PHP 中直接實作 Mode A（E/S/G 三維加權），並在 spec 中明文化公式，確保兩端一致。
- **7 天窗口期以 reviewed_at 計算**：若審核員多次操作（complete → return → submit → complete），`reviewed_at` 以最後一次 `complete_review` 為準，窗口期從最後一次通過開始計算。
