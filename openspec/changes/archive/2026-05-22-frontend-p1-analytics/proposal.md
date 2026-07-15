# Change Proposal: Frontend P1 — 分析工具頁面

## 前置條件

Frontend P0 完成後執行。

## 動機

P0 完成核心操作流程後，P1 提供分析視角：視覺化風險矩陣讓採購商快速掌握供應商風險分布；Scope 3 報告滿足 GHG Protocol 揭露需求並支援匯出。

## 範圍

### P1-1 風險矩陣視覺化 `/risk`

新建 `RiskMatrixView.vue`：

**維度切換**
- 頁面頂部 E / S / G / GP 四個 Tab
- 切換時呼叫 GET /risk/matrix?dimension=X

**5×5 CSS Grid 熱力圖**
- 橫軸 Impact（1–5），縱軸 Probability（1–5）
- 格子顏色依 score（= probability × impact）：
  - very_low（1–4）：`#dcfce7`（淡綠）
  - low（5–9）：`#bbf7d0`（綠）
  - medium（10–14）：`#fef9c3`（黃）
  - high（15–19）：`#fed7aa`（橙）
  - extreme（20–25）：`#fecaca`（紅）
- 格子內顯示 `supplier_count`（0 時顯示為灰色空格）
- 右上角摘要：extreme X 家 / high Y 家

**格子點擊下鑽**
- 點擊含供應商的格子 → 右側 Drawer 滑出
- GET /risk/matrix/suppliers?dimension=X&probability=P&impact=I
- 顯示供應商列表，點擊 → 導向 /suppliers/:id

### P1-2 Scope 3 報告 `/reports`

新建 `ReportsView.vue`：

**年度選擇器**
- 下拉選單（2020–current year）
- 切換時呼叫 GET /reports/scope3?year=

**排放總量**
- 大字顯示 total_co2e（font-mono，附 tCO2e 換算）

**15 類別水平長條圖（純 CSS）**
- 每列：Category 編號 + 名稱 + 長條 + 數值
- 長條寬度 = co2e / max(co2e) × 100%
- 顏色：accent 綠色系

**匯出**
- [匯出 Excel] → GET /reports/scope3/export?year=X&format=xlsx
- 觸發瀏覽器下載

### P1-3 Sidebar + Router 補全

- 新增「風險矩陣」選單項（icon: ◫，roles: admin/buyer/sustain/comply/analyst）
- 新增「排放報告」選單項（icon: ≡，roles: admin/sustain/analyst）
- 新增 api/modules/reports.ts

## 不在範圍

- PCF 計算頁面（/pcf，可在此 change 後加）
- Settings 頁面（P2）

## 成功條件

- [ ] `/risk?dimension=E` 顯示正確 5×5 熱力圖，顏色符合 level
- [ ] 點擊含供應商的格子顯示供應商清單
- [ ] `/reports` 顯示 Scope 3 15 類別（從 FastAPI 讀取真實數據）
- [ ] 匯出 xlsx 可成功下載
