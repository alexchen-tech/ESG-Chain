## 1. DB Migrations（esgchain-api）

- [x] 1.1 建立 migration：`framework_default_weights`（`scoring_framework`, `pillar_slug`, `weight DECIMAL(5,4)`, `sort_order INT`，unique `(scoring_framework, pillar_slug)`）
- [x] 1.2 建立 migration：`sasb_required_topics`（`sasb_industry_code`, `tag_slug`, `rationale TEXT NULL`，unique `(sasb_industry_code, tag_slug)`）
- [x] 1.3 建立 migration：`project_questions` 加欄位 `is_sasb_required BOOLEAN DEFAULT false`

## 2. Laravel Models & Services

- [x] 2.1 建立 `FrameworkDefaultWeight` Model（HasUuids，fillable：`scoring_framework`, `pillar_slug`, `weight`, `sort_order`）
- [x] 2.2 建立 `SasbRequiredTopic` Model（HasUuids，fillable：`sasb_industry_code`, `tag_slug`, `rationale`）
- [x] 2.3 建立 `FrameworkDefaultWeightService`：`getAll()` 回傳按框架分組、`updateFramework(string $framework, array $weights)` 含加總驗證（±0.01）
- [x] 2.4 建立 `SasbRequiredTopicService`：`getAll()` 按 code 分組並 join `question_tags` 取 `label_zh`、`create(array $data)`、`delete(string $id)`

## 3. Laravel Controllers & Routes

- [x] 3.1 建立 `FrameworkDefaultWeightController`（`index`、`update`），路由：`GET/PUT /api/v1/settings/framework-default-weights/{framework}`
- [x] 3.2 建立 `SasbRequiredTopicController`（`index`、`store`、`destroy`），路由：`GET/POST /api/v1/settings/sasb-required-topics`、`DELETE /api/v1/settings/sasb-required-topics/{id}`
- [x] 3.3 在 `api.php` 將兩組路由加入 `admin` middleware group

## 4. Seed 資料

- [x] 4.1 建立 `FrameworkDefaultWeightSeeder`：ESG（env 40%、soc 35%、gov 25%）、ISO20400（6 pillars 等權）、ISO26000（7 pillars 等權）、Geo-Risk（4 pillars 等權）
- [x] 4.2 建立 `SasbRequiredTopicSeeder`：EM-IS / TC-ES / EM-MM / TR-MT 等主要 SASB 代碼，每個代碼 3–6 個 ESG L3 tag_slug（確認現有代碼格式）
- [x] 4.3 執行 seed 並驗證資料

## 5. 修改 AssessmentSeriesService（getScoringConfig 預設值來源）

- [x] 5.1 `getScoringConfig()` 當 `series.pillar_weights IS NULL` 時，查 `FrameworkDefaultWeight` by `scoring_framework`，有資料則填入 `pillar_weights`（標記 `is_default: true`），無資料則維持 `null`

## 6. SASB 必調標記注入（SaqProjectSendService）

- [x] 6.1 SAQ show 時動態注入：依 `saq.supplier.sasb_industry_code` 查 `SasbRequiredTopic`，對 `project_questions.tags` 做交集，回傳 `is_sasb_required`（快照語意：在 SAQController::show() 計算）
- [x] 6.2 `ProjectQuestion` Model 加 `is_sasb_required` 到 `$fillable` 與 `$casts`

## 7. 前端：ScoringModelView.vue 完全重寫

- [x] 7.1 頁面副標題改為「管理各框架預設 Pillar 加權與 SASB 必調題目設定」
- [x] 7.2 區塊 1：框架預設加權（Tab，每框架顯示 pillar 加權列表、進度條、合計驗證、儲存按鈕）
- [x] 7.3 區塊 2：SASB 必調設定（可展開列表，顯示 SASB code、必調數，展開查看 topic 清單，支援新增 / 刪除）
- [x] 7.4 新增 API 呼叫：`saq.ts` 加入 `frameworkDefaultWeightsApi`（getAll、update）和 `sasbRequiredTopicsApi`（getAll、create、delete）

## 8. 前端：Portal SAQ 填答頁顯示 SASB 必調標記

- [x] 8.1 Portal 填答頁（SupplierSurveyView）讀取 `is_sasb_required`（由 SAQController::show() 動態注入），顯示「SASB 必調」標籤（amber 色系）

## 9. 驗證

- [x] 9.1 `docker restart esgchain-api` 並驗證兩支新 API 回傳正確格式
- [x] 9.2 驗證 `getScoringConfig()` 在 series 無自訂加權時回傳 `is_default: true` 的框架預設值（已確認：ESG 回傳 env 0.4 / soc 0.35 / gov 0.25）
- [x] 9.3 SASB 必調注入邏輯實作於 SAQController::show()，動態注入 `is_sasb_required`
- [x] 9.4 SupplierSurveyView 已加入「SASB 必調」amber 標籤
