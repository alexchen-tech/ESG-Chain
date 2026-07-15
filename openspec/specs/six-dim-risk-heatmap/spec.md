## MODIFIED Requirements

### Requirement: 六維熱圖 API
系統 SHALL 提供 `GET /api/v1/risk/six-dim-heatmap` 端點，支援可選的 `before_days` 查詢參數，用於歷史對比模式下取得指定天數前的 RA 快照。

#### Scenario: 正常回傳熱圖資料（無 before_days）
- **WHEN** 已認證用戶發送 `GET /api/v1/risk/six-dim-heatmap`（不帶 before_days）
- **THEN** 回傳每家供應商的最新 RA（以 `assessed_at` 取最新，無日期限制）
- **AND** 回應包含：supplier_id、supplier_name、supplier_code、country_code、assessed_at、source_type、dim_e1–dim_e6、open_cap_count、risk_score
- **AND** 回應包含 `thresholds` 物件（從 system_settings.cap_thresholds 讀取）
- **AND** 回應包含 `summary.total` 與 `summary.any_dim_critical`

#### Scenario: 帶 before_days 回傳歷史快照
- **WHEN** 已認證用戶發送 `GET /api/v1/risk/six-dim-heatmap?before_days=30`
- **THEN** 僅考慮 `assessed_at <= NOW() - 30 days` 的 RA 記錄
- **AND** 每家供應商取符合條件中最新的一筆 RA
- **AND** 無符合條件 RA 的供應商不出現於回應 data 陣列中
- **AND** 回應結構與無 before_days 時相同（含 thresholds、summary）

#### Scenario: before_days 參數驗證
- **WHEN** `before_days` 不為正整數或超過 365
- **THEN** 回傳 422 Unprocessable Entity，錯誤訊息說明合法範圍（1–365）

#### Scenario: 無供應商有 RA 時回傳空陣列
- **WHEN** 資料庫中無符合條件的 risk_assessments 記錄
- **THEN** 回傳 `{"data": [], "thresholds": {...}, "summary": {"total": 0, "any_dim_critical": 0}}`

#### Scenario: dim_e6 為 null 時不計入 critical 判斷
- **WHEN** 供應商的 dim_e6 為 null，其餘維度皆達標
- **THEN** 該供應商 SHALL NOT 被計入 `any_dim_critical`

---

### Requirement: 六維熱圖 UI（取代舊風險矩陣）
系統 SHALL 提供 `SixDimHeatmapView.vue` 頁面，以表格形式顯示供應商 × E1–E6 分數，色票標示風險等級，取代舊 `RiskMatrixView.vue`。

#### Scenario: 熱圖色票規則
- **WHEN** 顯示供應商的某維度分數
- **THEN** 若分數 ≥ 70：顯示綠色底色
- **AND** 若分數 40–69：顯示黃色底色（警示）
- **AND** 若分數 < 閾值（低於 cap_thresholds 的對應維度）：顯示紅色底色（危險）
- **AND** 若分數為 null：顯示灰色底色（未評估）

#### Scenario: 熱圖支援維度篩選
- **WHEN** 使用者點擊維度標籤（E1–E6）
- **THEN** 可依該維度分數高低重新排序供應商列

#### Scenario: 點擊供應商開啟側欄
- **WHEN** 使用者點擊供應商名稱
- **THEN** 在右側開啟供應商詳情側欄，顯示各維度分數、open CAP 數量、最近評估日期、source_type 標籤

#### Scenario: 路由切換
- **WHEN** 使用者進入風險稽核模組
- **THEN** 側欄路由 `/risk` 對應 `SixDimHeatmapView.vue`
- **AND** 舊 `/risk/matrix`（`RiskMatrixView.vue`）路由保留但從側欄導航移除

---

### Requirement: 永續風險概覽更新（SustainabilityRiskView）
永續風險概覽 SHALL 移除 axis1/axis2/axis3 欄位，改為直接顯示 E1–E6 六個維度分數欄。

#### Scenario: 欄位更新
- **WHEN** 使用者進入永續風險概覽頁
- **THEN** 表格欄位為：供應商、E1、E2、E3、E4、E5、E6、風險分數、開放 CAP 數、最近評估日
- **AND** 移除 axis1_score、axis2_score、axis3_score 欄位
- **AND** E1–E6 欄使用 `font-mono` 數字格式，分數值加色票背景（同熱圖規則）
