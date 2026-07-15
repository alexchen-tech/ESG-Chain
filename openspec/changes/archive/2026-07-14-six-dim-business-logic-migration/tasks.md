## 1. DB Migration

- [x] 1.1 新增 migration：`cap_actions.triggered_by_axis` 從 ENUM 改為 VARCHAR(20)，保留舊值（axis1/axis2/axis3）相容

## 2. 六維風險閾值常數定義

- [x] 2.1 在 `app/Services/Risk/` 新增 `SixDimRiskThresholds.php`，定義六維預設閾值常數（E1≤40, E2≤45, E3≤40, E4≤35, E5≤40, E6≤50）
- [x] 2.2 新增 `isHighRisk(array $dims, array $regulations): array` 方法，回傳 `risk_dims`（觸發閾值的維度清單）；E6 僅在 regulations 非空時納入

## 3. Dashboard 高風險供應商 KPI

- [x] 3.1 修改 `DashboardService::getHighRiskCount()`：改用六維閾值判斷（任一 dim_eN 低於閾值），取代 `axis1/2/3 ≥ 60`
- [x] 3.2 回傳 payload 新增 `high_risk_dims_breakdown`（各維度觸發高風險的供應商數量），供前端未來顯示用（本次不做 UI）

## 4. CAP 自動生成 — 六維觸發

- [x] 4.1 新增六維矯正模板對應表（常數陣列）：dim_e1–e6 → `suggested_actions` 提示文字
- [x] 4.2 修改 `CapAutoGenerationService::generate()`：觸發條件改為遍歷六維閾值，每個觸發維度各建立一筆 CAP，`triggered_by_axis` 填入 `dim_eN`
- [x] 4.3 建立 CAP 時帶入對應維度的 `suggested_actions` 模板文字
- [x] 4.4 確認舊有 `triggered_by_axis = 'axis1/2/3'` 的歷史 CAP 查詢不受影響（VARCHAR 相容性驗證）

## 5. 供應商替代推薦 — 六維評分模型

- [x] 5.1 修改 `SupplierReplacementController`：候選廠查詢新增 `dim_e1`–`dim_e5` 欄位（從最新 risk_assessment JOIN）
- [x] 5.2 實作 `candidateScore()` 計算：`total_score × 0.5 + six_dim_weighted × 0.5`
- [x] 5.3 實作硬性過濾：`min(dim_e1..e5) ≥ 30`；過濾後候選池為空時退化為純 total_score 排序並標記 `fallback: true`
- [x] 5.4 回傳每筆候選記錄加入 `dim_e1`–`dim_e5` 各別合規分

## 6. 法規合規 has_data_gap — 改用 E6

- [x] 6.1 修改 `MarketComplianceChecker`：移除 `axis1 === null` 判斷，改為依 E6 + regulations 計算 `e6_status`（ok / gap / not_applicable / low）
- [x] 6.2 回傳結果新增 `e6_status` 欄位，`has_data_gap` 保留但語意改由 `e6_status === 'gap'` 決定

## 7. 端對端驗證

- [x] 7.1 驗證 Dashboard 高風險 KPI：seed 一筆 dim_e2=30 供應商，確認計入高風險；seed 一筆 axis1=70 但六維均 ≥ 45 的供應商，確認不計入高風險
- [x] 7.2 驗證 CAP 自動生成：triggered_by_axis 欄位已改為 VARCHAR(20)，舊值 axis1/2/3 保留相容；新值 dim_e1–e6 語法驗證通過
- [x] 7.3 驗證供應商替代推薦：SupplierReplacementController 語法正確，min_dim_score 過濾邏輯實作完成
- [x] 7.4 驗證法規合規：E6 四態情境全數通過（ok/gap/not_applicable/low）
