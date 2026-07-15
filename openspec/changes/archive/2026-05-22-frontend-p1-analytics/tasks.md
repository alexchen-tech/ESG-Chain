# Tasks: Frontend P1 — 分析工具頁面

## API 模組

- [x] P1-0.1 建立 `src/api/modules/risk.ts`（matrix/matrixSuppliers/assessments）
- [x] P1-0.2 建立 `src/api/modules/reports.ts`（scope3/exportScope3）

## P1-1 風險矩陣視覺化

- [x] P1-1.1 建立 `src/views/risk/RiskMatrixView.vue`（E/S/G/GP Tab + 空殼）
- [x] P1-1.2 實作 5×5 CSS Grid 熱力圖（orderedCells computed，p=5 在頂部）
- [x] P1-1.3 格子顏色依 risk_level 套用（very_low/low/medium/high/extreme CSS class）
- [x] P1-1.4 建立右側 Drawer 元件（點擊格子滑出，顯示 GET /risk/matrix/suppliers 結果）
- [x] P1-1.5 Drawer 供應商列表點擊 → 導向 /suppliers/:id
- [x] P1-1.6 右上角 extreme/high 摘要數字
- [x] P1-1.7 Router 新增 /risk 路由，Sidebar 新增「風險矩陣」選單項

## P1-2 Scope 3 報告

- [x] P1-2.1 建立 `src/views/reports/ReportsView.vue`（年度選擇器 + 空殼）
- [x] P1-2.2 串接 GET /reports/scope3，顯示總排放量大字
- [x] P1-2.3 實作 15 類別水平長條圖（純 CSS，barWidth computed）
- [x] P1-2.4 匯出 xlsx 按鈕（blob 下載，觸發 a.click()）
- [x] P1-2.5 Router 新增 /reports 路由，Sidebar 新增「排放報告」選單項
