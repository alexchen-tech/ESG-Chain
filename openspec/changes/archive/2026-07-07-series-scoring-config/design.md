## Context

ESG-Chain 的計分引擎以 `ScoringModel`（PostgreSQL，AI service 管理）作為全域設定，以 `sasb_industry_code` 為索引查詢 E/S/G 加權與等級閾值。非 ESG 框架的 pillar 加權目前固定為等權平均，無法客製化。

品牌客戶的評核系列（AssessmentSeries）已綁定框架（透過 template），是最自然的計分設定掛載點：同一系列的所有專案共用同一組計分設定，跨系列時可獨立調整。

## Goals / Non-Goals

**Goals:**
- 讓品牌客戶在 AssessmentSeries 層自訂 pillar 加權與等級閾值
- 支援所有框架（ESG、ISO20400、ISO26000、Geo-Risk）
- 計分引擎優先使用 series 設定，無設定時 fallback 全域 ScoringModel
- 計分設定 Tab 整合在 Series 詳情頁（第四個 Tab）

**Non-Goals:**
- 不修改全域 ScoringModel 的 CRUD 功能
- 不對 ScoringModel 新增 `scoring_framework` 欄位（本次不涉及）
- 不支援 per-Project 的計分設定（只到 Series 層）
- 不重算已完成 SAQ 的歷史分數

## Decisions

**D1：pillar_weights 用 slug prefix 作為 key（不含結尾點）**
- 例：`{"iso20400.policy": 0.50, "iso20400.perf": 0.30, "iso20400.risk": 0.20}`
- 原因：直接對應 `SLUG_PREFIX_TO_PILLAR` 的 key 結構（去尾點），不依賴中文翻譯字串，未來改 UI 文字不影響計分邏輯
- AI service 轉換：`slug_prefix + "."` 查 `SLUG_PREFIX_TO_PILLAR` 得到 pillar name，再對應 pillar_buckets

**D2：grade_thresholds 用 JSON 取代現行 A/B/C/D 四欄位語意**
- 格式：`{"A": 85, "B": 65, "C": 45, "D": 25}`（E 級隱含為 < D）
- 儲存在 `assessment_series.grade_thresholds` (JSON nullable)

**D3：傳遞方式為 Job 時注入（stateless AI service）**
- `DispatchSaqScoringJob` eager load `project.series`，讀取 `pillar_weights` 與 `grade_thresholds`，加入 payload 傳給 AI
- AI service 不主動查 MySQL，保持無狀態

**D4：AI service 的優先序**
```
pillar 加權：
  1. 傳入的 pillar_weights（series 自訂）
  2. 等權平均（現行 fallback）

grade 閾值：
  1. 傳入的 grade_thresholds（series 自訂）
  2. _get_scoring_model_sync(sasb_industry_code)（ScoringModel）
  3. DEFAULT_THRESHOLDS {A:80, B:60, C:40, D:20}
```

**D5：前端 pillar 名稱對照表維護在前端**
- `SeriesDetailView.vue` 維護 `FRAMEWORK_PILLARS` 常數，依 `series.template.scoring_framework` 顯示可設定的 pillar 清單與中文名
- 後端不負責翻譯，只儲存與傳遞 slug prefix

**D6：API 設計**
- `GET  /api/v1/assessment-series/{id}/scoring-config` — 取得設定（回傳 pillar_weights、grade_thresholds、可用 pillar 清單）
- `PUT  /api/v1/assessment-series/{id}/scoring-config` — 儲存設定
- 驗證：pillar_weights 合計須在 0.99–1.01 之間（浮點容差）；grade_thresholds A > B > C > D > 0

## Risks / Trade-offs

- **pillar slug 更名**：若 AI service 的 `SLUG_PREFIX_TO_PILLAR` key 修改，已儲存的 `pillar_weights` JSON 會失效。緩解：pillar slug 視為穩定合約，修改需同步 migration。
- **歷史 SAQ 不重算**：修改 series 計分設定後，已完成的 SAQ 分數不變。操作者需知悉此行為（UI 加提示）。
- **等權平均 fallback 語意**：若 series 未設定 `pillar_weights`，仍用等權平均，非 ESG 框架無等級差異。這是刻意設計（不強制設定）。
