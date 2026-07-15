## Why

六維度評核（E1–E6）建立後，風險稽核模組仍以舊四軸（E/S/G/GP probability × impact）為主要資料模型，造成語意落差：D6 投影公式是人工近似值，無法精確反映 E1–E6 的獨立語意，且 CAP 觸發邏輯（score ≥ 20）與六維分數脫節。同時，地緣政治事件（關稅調整、制裁）或國家風險評等更新後，目前系統沒有任何機制能批次複查受影響供應商的 E4 風險——所有風險更新都只能依賴 SAQ 週期觸發。

## What Changes

- **BREAKING** 廢棄 `risk_assessments` 的四軸欄位（`e_probability`、`e_impact`、`s_probability`、`s_impact`、`g_probability`、`g_impact`、`gp_probability`、`gp_impact`）作為主要風險語意，改為 nullable 唯讀欄位（舊 legacy 資料清除，DEMO 環境 truncate）
- **BREAKING** 廢棄 `axis1_score`、`axis2_score`、`axis3_score` 及對應 source 欄位
- `risk_assessments` 加入 `source_type`（enum: `saq` / `geo_event` / `regulation_change` / `manual_review`）與 `source_id` 多型識別欄位
- 新增 `geo_events` 表及 `geo_event_supplier_reviews` 複查佇列表
- CAP 觸發邏輯改為 E1–E6 維度閾值規則（閾值存 `system_settings.cap_thresholds`，可設定）
- `suppliers.risk_score` 同步公式改為以系統預設 dim_weights 加權的 E1–E6 反向合成
- 風險矩陣 UI（5×5 probability×impact）改為「六維熱圖」（供應商 × E1–E6 色票）
- 永續風險概覽移除 axis1/axis2/axis3 欄，改為 E1–E6 六欄直接顯示
- 新增「地緣事件複查」頁面：建立地緣事件 → 計算受影響供應商 → Celery 批次重算 E4 → 自動觸發 CAP

## Capabilities

### New Capabilities

- `geo-event-portfolio-review`：地緣事件管理與跨供應商批次 E4 風險複查，含事件建立、受影響供應商計算、Celery 排程重算、複查佇列狀態追蹤
- `six-dim-risk-heatmap`：六維度熱圖視圖，取代舊風險矩陣，以供應商為列、E1–E6 為欄，色票標示風險等級，支援維度篩選與供應商詳情側欄

### Modified Capabilities

- `saq-to-risk-auto-derivation`：移除 D6 投影公式（E/S/G/GP 推導），改為直接儲存 dim_e1–e6；新增 `source_type` 多型識別；`assessment_version` 預設升為 `v3`
- `risk-extreme-cap-trigger`：CAP 觸發條件從 `probability × impact ≥ 20` 改為 E1–E6 各維度閾值比對，閾值從 `system_settings.cap_thresholds` 讀取
- `supplier-risk-history`：時間線事件改用 dim_e1–e6 欄位；移除 `buildDimension()` 輸出；新增 `source_type` 標記（SAQ 驅動 vs. 地緣事件驅動）
- `supplier-risk-timeline`：前端時間線卡片移除四軸顯示，改為六維分數欄，v2 專屬分支合併為唯一路徑

## Impact

**後端**
- `risk_assessments` 資料表 migration（舊四軸欄位 nullable、新增 source_type/source_id）
- 新增 `geo_events`、`geo_event_supplier_reviews` 資料表
- `RiskAssessment` Model、`RiskAutoDerivationService`、`RiskAssessmentObserver` 大幅重寫
- `SupplierTimelineService`、`AiRiskSuggestionService` 更新 payload
- 新增 `GeoEventService`、`GeoEventController`、Celery task `recalculate_e4_batch`
- `system_settings` 新增 `cap_thresholds` key
- `RiskMatrixController` 改寫為六維熱圖 API
- DEMO 資料：truncate `risk_assessments`（8 筆 legacy 資料清除）

**前端**
- `RiskMatrixView.vue` → `SixDimHeatmapView.vue`（重寫）
- `SustainabilityRiskView.vue`（axis 欄改 E1–E6）
- `SupplierDetailView.vue`（移除 legacy 分支）
- `AiRiskSuggestionPanel.vue`（E1–E6 顯示）
- 新增 `GeoEventView.vue`
- `risk.ts` API module 型別更新

**API**
- `GET /api/v1/risk/matrix` → `GET /api/v1/risk/six-dim-heatmap`
- 新增 `POST /api/v1/risk/geo-events`、`GET /api/v1/risk/geo-events`、`GET /api/v1/risk/geo-events/{id}/reviews`、`POST /api/v1/risk/geo-events/{id}/recalculate`
