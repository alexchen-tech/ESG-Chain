## 1. 資料庫 Migration

- [x] 1.1 Migration M1：`risk_assessments` 四軸欄位改 NULLABLE（e_probability、e_impact、s_probability、s_impact、g_probability、g_impact、gp_probability、gp_impact），加入 `source_type` ENUM('saq','geo_event','regulation_change','manual_review') DEFAULT 'saq'，加入 `source_id` UUID NULLABLE；`assessment_version` default 改 'v3'；回填 `source_id = source_saq_id WHERE source_saq_id IS NOT NULL`
- [x] 1.2 Migration M2：建立 `geo_events` 表（id UUID PK、name、event_type、affected_scope JSON、severity ENUM('low','medium','high','critical')、status ENUM('active','archived') DEFAULT 'active'、occurred_at、created_by_id、timestamps）
- [x] 1.3 Migration M3：建立 `geo_event_supplier_reviews` 表（id UUID PK、geo_event_id FK、supplier_id FK、status ENUM('pending','recalculating','done','failed') DEFAULT 'pending'、pre_e4_score DECIMAL NULLABLE、post_e4_score DECIMAL NULLABLE、risk_assessment_id UUID NULLABLE、recalculation_started_at NULLABLE、error_message TEXT NULLABLE、timestamps）
- [x] 1.4 Seeder：truncate `risk_assessments`（DEMO 環境清除 legacy 資料）；插入 `system_settings` key=`cap_thresholds` 值為 `{"E1":40,"E2":40,"E3":35,"E4":35,"E5":40,"E6":40}`

## 2. 後端核心模型與 Observer 重寫

- [x] 2.1 `RiskAssessment` Model：移除 `buildDimension()` 方法；更新 `toRiskSummary()` 使 six_dims 為唯一輸出（移除四軸分支）；加入 `source_type`、`source_id` fillable
- [x] 2.2 `RiskAssessmentObserver`：重寫 `syncSupplierRiskScore()`，改用 E1–E6 加權公式（dim_weight_defaults from system_settings，E6 null 時分攤 weight）；重寫 `checkAndCreateCap()`，改為讀取 cap_thresholds 比對各維度閾值，legacy/v1 RA 跳過
- [x] 2.3 `RiskAutoDerivationService`：移除 `deriveFromSaqV2()` 的 D6 投影公式；新主路徑直接讀 saq.dim_e1–e6 建立 v3 RA（source_type='saq'，四軸全 null）；加 upsert 邏輯防重複

## 3. 地緣事件後端

- [x] 3.1 建立 `GeoEvent` Model（HasUuids、fillable）與 `GeoEventSupplierReview` Model
- [x] 3.2 建立 `GeoEventService`：`create()`（計算受影響供應商、建立 review 記錄、pre_e4_score 取最新 RA）、`dispatchRecalculation()`（更新 status=recalculating、POST /ai/v1/geo-event/recalculate-e4）、`handleReviewCallback()`（建立新 RA、更新 review 記錄）
- [x] 3.3 建立 `GeoEventController`：CRUD + `POST /{id}/recalculate`（回 202）+ `POST /{id}/review-callback`（AI 回調）
- [x] 3.4 Laravel 路由：`/api/v1/risk/geo-events` 路由組（admin/sustain 中介層）；callback 路由不過 JWT（用 internal secret 或 IP 白名單）
- [x] 3.5 `CheckRecalculatingTimeout` Job：查找 recalculation_started_at > 10 分鐘且 status=recalculating 的 reviews，標記 status='failed'；加入 Scheduler 每 5 分鐘執行

## 4. FastAPI / Celery E4 批次重算

- [x] 4.1 新增 FastAPI 路由 `POST /ai/v1/geo-event/recalculate-e4`（接收 supplier_ids + country_defense_score_overrides）；立即 enqueue Celery task，回 202
- [x] 4.2 建立 Celery task `recalculate_e4_batch`：for each supplier 取最新 SAQ dim_e4_raw、以新 country_defense_score 重算 dim_e4、重算 saqs.score（依 series.dim_weights）；計算完成後回調 Laravel `POST /api/v1/risk/geo-events/{id}/review-callback`
- [x] 4.3 更新 `AiRiskSuggestionService` payload：加入 dim_e1–dim_e6、各維度 label；移除四軸欄位

## 5. 六維熱圖 API

- [x] 5.1 建立或改寫 `RiskHeatmapController`：`GET /api/v1/risk/six-dim-heatmap` — 每家供應商取最新 RA（MAX assessed_at）、join supplier、計算 any_dim_critical；讀 cap_thresholds from system_settings
- [x] 5.2 路由：將 `/api/v1/risk/matrix` 路由別名指向新 heatmap controller（舊 URL 兼容），或直接廢棄

## 6. 供應商時間線後端

- [x] 6.1 `SupplierTimelineService`：更新 `buildRiskEvent()`，統一走 dim_e1–e6 路徑；依 source_type 產生不同事件 title 與附加欄位（saq_id 或 geo_event_name）；移除 `buildDimension()` 相關程式碼

## 7. 前端更新

- [x] 7.1 新增 `SixDimHeatmapView.vue`：表格顯示供應商 × E1–E6，色票邏輯（green/yellow/red/gray），維度點擊排序，供應商側欄詳情
- [x] 7.2 更新路由 `router/index.ts`：`/risk` 指向 `SixDimHeatmapView`；舊 `/risk/matrix` 保留但側欄不顯示
- [x] 7.3 `SustainabilityRiskView.vue`：移除 axis1/axis2/axis3 欄，改為 E1–E6 六欄（font-mono + 色票）
- [x] 7.4 `SupplierDetailView.vue` 時間線區塊：移除四軸顯示邏輯；依 source_type 渲染不同卡片（saq → 六維分數列表；geo_event → E4 前後對比 + 地球儀 badge）
- [x] 7.5 新增 `GeoEventView.vue`（列表頁）與 `GeoEventDetailView.vue`（詳情 + 受影響供應商清單 + 批次重算按鈕 + 5 秒輪詢）；加入路由 `/risk/geo-events`
- [x] 7.6 更新 `risk.ts` API module：加入 heatmap、geo-events CRUD、recalculate 相關型別與 composables；移除舊四軸相關 type 定義

## 8. DEMO 資料補全

- [x] 8.1 建立 `RiskAssessmentV3DemoSeeder`：為 DEMO 供應商（已有 submitted SAQ 的）直接插入 v3 RA（dim_e1–e6 填入合理值，source_type='saq'），避免清除 legacy RA 後風險頁面全空
- [x] 8.2 建立 2–3 筆 DEMO 地緣事件（如「2025 美中關稅調升」、「越南制裁更新」），各含數筆受影響供應商 review 記錄（status='done'，pre/post_e4 有值）
