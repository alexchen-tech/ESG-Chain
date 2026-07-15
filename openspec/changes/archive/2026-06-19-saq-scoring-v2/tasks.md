## 1. DB Migrations

- [x] 1.1 Migration：`saqs` 新增 `final_score decimal(5,2) null`、`final_grade string(1) null`、`disputed_at timestamp null`
- [x] 1.2 Migration：建立 `saq_response_reviews` 表（含 UNIQUE saq_id+project_question_id）
- [x] 1.3 Migration：建立 `saq_score_snapshots` 表（含 trigger enum）

## 2. AI 計分補完（esgchain-ai）

- [x] 2.1 `scoring_service.py`：在 `calculate_saq_score()` 末尾填充 `question_scores`，`raw_score = _score_single_response(r) × r.weight`，組成 `list[QuestionScoreItem]`
- [x] 2.2 `scoring_tasks.py`：Celery callback payload 加入 `question_scores`（`result.model_dump()` 已含，確認 field 不被 exclude）

## 3. 未答題補零（esgchain-api Job）

- [x] 3.1 `DispatchSaqScoringJob`：改取 project 所有 `project_questions`，LEFT JOIN `saq_responses`，無 response 者插入 `answer=null, answer_options=[]`，確保所有題目都進入 AI payload

## 4. Models & Relationships

- [x] 4.1 新增 `SAQResponseReview` Model（HasUuids，fillable，belongsTo SAQ / ProjectQuestion / User）
- [x] 4.2 新增 `SaqScoreSnapshot` Model（HasUuids，fillable，無 delete/update route）
- [x] 4.3 `SAQ` Model：加入 `responseReviews()` hasMany、`scoreSnapshots()` hasMany、新 fillable 欄位

## 5. 計分快照 Service

- [x] 5.1 新增 `SaqScoreSnapshotService::create(SAQ, trigger, triggeredBy=null)`：建立 snapshot，從 SAQ 當前 score/grade/score_e/s/g/scoring_model_id 取值
- [x] 5.2 `SAQController::scoreCallback()`：callback 成功後呼叫 `SaqScoreSnapshotService::create(trigger='submit')`
- [x] 5.3 `SaqProjectController::updateWeights()`：觸發重算後，每份重算 SAQ 在新 callback 完成時建立 `trigger='weight_updated'` 快照

## 6. 題目層覆核 API

- [x] 6.1 新增 `SaqReviewerScoreService`：`submitReviews(SAQ, reviews[], reviewerId)` — upsert saq_response_reviews、重算 final_score（Mode A）、建立 snapshot（trigger='reviewer_override'）
- [x] 6.2 `SaqReviewerScoreService::recalculateFinalScore(SAQ)`：實作 Mode A 重算邏輯（覆核分優先，否則 raw_score/weight 還原 answer_score）
- [x] 6.3 新增路由 `POST /api/v1/saqs/{saq}/response-reviews`，Controller 方法 `submitResponseReviews()`
- [x] 6.4 新增路由 `GET /api/v1/saqs/{saq}/response-reviews`，回傳覆核分清單
- [x] 6.5 新增路由 `GET /api/v1/saqs/{saq}/score-snapshots`，回傳快照列表

## 7. 申訴狀態機擴充

- [x] 7.1 `QuestionnaireService::TRANSITIONS`：新增 `disputed`、`re_review`、`finalized` 及對應轉換
- [x] 7.2 新增 `QuestionnaireService::dispute(SAQ, userId, reason)`：7 天窗口期檢查、更新 disputed_at、建立 review history
- [x] 7.3 新增 `QuestionnaireService::startReReview(SAQ, userId)`
- [x] 7.4 新增 `QuestionnaireService::finalize(SAQ, userId, comment)`：鎖定 final_score、建立 snapshot（trigger='re_review'）
- [x] 7.5 新增路由：`POST /api/v1/questionnaires/{saq}/dispute`（供應商角色）
- [x] 7.6 新增路由：`POST /api/v1/saqs/{saq}/re-review`、`POST /api/v1/saqs/{saq}/finalize`（審核員角色）

## 8. Assessment Series 比較 API 擴充

- [x] 8.1 `AssessmentSeriesService::getComparison()`：查詢每個 project 最新 saq_score_snapshot 的 scoring_model_id，加入 projects 陣列
- [x] 8.2 回傳 `scoring_model_inconsistent: bool`（相鄰 project scoring_model_id 不同時為 true）

## 9. 前端：審核覆核 UI

- [x] 9.1 `saq.ts`：新增 `saqApi.getResponseReviews(id)`、`saqApi.submitResponseReviews(id, reviews[])`、`saqApi.getScoreSnapshots(id)`
- [x] 9.2 `SaqProjectDetailView.vue`：under_review SAQ 詳情加入「逐題覆核」面板（題目列表、AI score、覆核分輸入、理由輸入）
- [x] 9.3 覆核提交後顯示「最終評分」徽章；可展開查看「AI 評分」

## 10. 前端：申訴入口（Portal）

- [x] 10.1 `questionnaire.ts`：新增 `questionnaireApi.dispute(id, reason)`
- [x] 10.2 `SupplierSurveyView.vue`：`completed` 狀態且窗口期內顯示申訴按鈕（剩餘天數倒數）
- [x] 10.3 `disputed` / `re_review` 狀態顯示「申訴審核中」說明

## 11. 前端：折線圖 scoring_model 警示

- [x] 11.1 `SeriesDetailView.vue`：`buildPolylinePoints()` 分段處理，相鄰不同 scoring_model 的片段改用虛線（`stroke-dasharray`）
- [x] 11.2 虛線段中點加 ⚠ 文字節點（SVG `<text>`），hover 顯示 tooltip

## 12. 驗證

- [x] 12.1 AI 回寫後 `saq_responses.raw_score` 不再全為 null
- [x] 12.2 完全未答的供應商總分 = 0（未答補零生效）
- [x] 12.3 審核員提交覆核分 → `final_score` 更新，快照建立
- [x] 12.4 供應商在 7 天內申訴 → 狀態轉為 `disputed`；第 8 天申訴 → 422
- [x] 12.5 `finalized` 狀態的 SAQ 拒絕覆核分修改
- [x] 12.6 Series 比較頁不同 scoring_model 波次之間顯示虛線
