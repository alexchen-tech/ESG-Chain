## 1. esgchain-api：移除寫入邏輯

- [x] 1.1 `AssessmentSeriesService::create()` 移除 `'domain' => $template->scoring_framework` 寫入
- [x] 1.2 `SaqProjectController::store()` 移除 `'domain' => $template->scoring_framework` 寫入
- [x] 1.3 `SaqProjectController::update()` 移除 `domain` 驗證規則（`in:` 清單）與「active 後不可改」鎖定邏輯
- [x] 1.4 `SaqProjectController::VALID_DOMAINS` 常數廢棄（移除或留空）
- [x] 1.5 `TagLibraryController::VALID_DOMAINS` 補上 `'ISO26000'`

## 2. esgchain-ai：移除 project_domain 死碼

- [x] 2.1 `CelerySaqScoringRequest`（scoring.py）移除 `project_domain` 欄位
- [x] 2.2 `SaqScoringRequest`（scoring.py）移除 `project_domain` 欄位
- [x] 2.3 `celery_trigger_saq_scoring()` 移除傳遞 `project_domain` 至 Celery task
- [x] 2.4 `async_score_task.delay()` 呼叫移除 `project_domain` 參數
- [x] 2.5 `calculate_saq_score()` 函式簽章移除 `project_domain` 參數
- [x] 2.6 `_resolve_framework()` 簡化：移除 `project_domain` 參數，直接 `return scoring_framework`

## 3. esgchain-web：前端讀取路徑修正

- [x] 3.1 `SeriesListView.vue`：`s.template?.scoring_framework ?? s.domain` → `s.template?.scoring_framework`（色條 + badge + domainBadgeStyle 兩處）
- [x] 3.2 `SeriesDetailView.vue`：`series.domain` → `series.template?.scoring_framework`（框架 chip、自動命名、`getScoringConfig` 框架取得）
- [x] 3.3 `SaqProjectDetailView.vue`：`project.domain` badge → `project.template?.scoring_framework`
- [x] 3.4 `ReviewDetailView.vue`：`?? saq?.project?.domain` fallback 移除
- [x] 3.5 確認各 API 呼叫的 response 已 eager load template（檢查 `project.template`、`series.template`）

## 4. 部署與驗證

- [x] 4.1 `docker cp` + `docker restart esgchain-api` 同步後端
- [x] 4.2 `docker cp` + `docker exec touch` 觸發前端 HMR
- [x] 4.3 驗證 Series 列表頁框架 badge 正確顯示
- [x] 4.4 驗證 SeriesDetailView 框架 chip 正確顯示
- [x] 4.5 驗證新建 Series / Project 的 domain 欄位確為 NULL（DB 查詢確認）
- [x] 4.6 驗證 TagLibrary 可建立 ISO26000 l1_domain 的 tag
