## 1. DB Migration（esgchain-api）

- [x] 1.1 新增 migration：`assessment_series` 加欄位 `pillar_weights JSON NULL` 與 `grade_thresholds JSON NULL`
- [x] 1.2 執行 migration：`docker exec esgchain-api php artisan migrate`

## 2. Laravel API（esgchain-api）

- [x] 2.1 `AssessmentSeries` Model：`$fillable` 加入 `pillar_weights`、`grade_thresholds`；`$casts` 加 `'pillar_weights' => 'array'`、`'grade_thresholds' => 'array'`
- [x] 2.2 `AssessmentSeriesService`：新增 `getScoringConfig(string $id)` 方法，回傳 `{ pillar_weights, grade_thresholds, available_pillars }`；`available_pillars` 依 `series.template.scoring_framework` 查 `FRAMEWORK_PILLARS` 常數（定義在 Service 或 config）
- [x] 2.3 `AssessmentSeriesService`：新增 `updateScoringConfig(string $id, array $data)` 方法，驗證 pillar_weights 合計（0.99–1.01）及 grade_thresholds 遞減規則，通過後 update
- [x] 2.4 `AssessmentSeriesController`：新增 `scoringConfig()` (GET) 與 `updateScoringConfig()` (PUT) action，各自呼叫 Service method
- [x] 2.5 `routes/api.php`：新增路由 `GET /api/v1/assessment-series/{id}/scoring-config` 與 `PUT /api/v1/assessment-series/{id}/scoring-config`

## 3. DispatchSaqScoringJob 修改（esgchain-api）

- [x] 3.1 eager load 加入 `project.series`（現為 `project.template`，改為 `project.template, project.series`）
- [x] 3.2 payload 加入 `series_pillar_weights => $saq->project?->series?->pillar_weights ?? null` 與 `series_grade_thresholds => $saq->project?->series?->grade_thresholds ?? null`
- [x] 3.3 同步更新 `docker cp` 並 `docker restart esgchain-api` 驗證

## 4. AI Service 修改（esgchain-ai）

- [x] 4.1 `CelerySaqScoringRequest`（`scoring.py`）：新增欄位 `series_pillar_weights: dict | None = None`、`series_grade_thresholds: dict | None = None`
- [x] 4.2 `celery_trigger_saq_scoring`：將新欄位傳入 Celery task `async_score_task.delay(...)`
- [x] 4.3 `scoring_tasks.py`：`calculate_saq_score` task 接收 `series_pillar_weights`、`series_grade_thresholds`，傳入 `scoring_service.calculate_saq_score()`
- [x] 4.4 `scoring_service.py`：`calculate_saq_score()` 新增參數 `series_pillar_weights: dict | None = None`、`series_grade_thresholds: dict | None = None`
- [x] 4.5 `scoring_service.py`：pillar 加權邏輯 — `series_pillar_weights` 有值時，建立 `pillar_weight_by_name` 對照表（slug prefix + "." → SLUG_PREFIX_TO_PILLAR → pillar name），計算加權平均取代等權平均
- [x] 4.6 `scoring_service.py`：grade 閾值邏輯 — `series_grade_thresholds` 有值時，直接用此閾值判定等級，跳過 `_get_scoring_model_sync`（仍保留 scoring_model_id 可為 null）
- [x] 4.7 同步至容器並驗證：`docker cp` esgchain-ai 相關檔案後 restart

## 5. 前端：assessmentSeriesApi（esgchain-web）

- [x] 5.1 `src/api/modules/saq.ts`：`AssessmentSeries` interface 加入 `pillar_weights: Record<string, number> | null`、`grade_thresholds: Record<string, number> | null`
- [x] 5.2 `assessmentSeriesApi` 加入 `getScoringConfig(id)` 與 `updateScoringConfig(id, data)` 兩個 API 函式

## 6. 前端：Series 詳情頁計分設定 Tab（esgchain-web）

- [x] 6.1 `SeriesDetailView.vue`：`TABS` 加入 `{ key: 'scoring', label: '計分設定' }`
- [x] 6.2 `SeriesDetailView.vue`：定義 `FRAMEWORK_PILLARS` 常數，key = scoring_framework，value = `[{ slug, label }]` 陣列（含所有框架 pillar）
- [x] 6.3 `SeriesDetailView.vue`：`data()` 加入 `scoringConfig: { pillar_weights: null, grade_thresholds: {...} }`、`scoringForm`（編輯用）、`isSavingScoring: false`
- [x] 6.4 `SeriesDetailView.vue`：`onTabChange('scoring')` 呼叫 `loadScoringConfig()`
- [x] 6.5 `SeriesDetailView.vue`：實作 `loadScoringConfig()`、`saveScoringConfig()`、`resetToEqualWeight()` methods
- [x] 6.6 `SeriesDetailView.vue`：新增計分設定 Tab HTML（`v-if="activeTab === 'scoring'"`）：pillar 加權表格（input % + 進度條視覺）、合計驗證提示、重設按鈕、閾值輸入、儲存按鈕
- [x] 6.7 同步至容器並驗證 UI：`docker cp` + `docker exec esgchain-web touch`
