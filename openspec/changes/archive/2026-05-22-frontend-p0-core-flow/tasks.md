# Tasks: Frontend P0 — 核心流程頁面

## API 模組

- [x] P0-0.1 建立 `src/api/modules/questionnaire.ts`（符合 /questionnaires/* 路徑，含 counts/send/submit/startReview/completeReview/returnReview/markReviewed）
- [x] P0-0.2 建立 `src/api/modules/risk.ts`（matrix/assessments/riskSummary）
- [x] P0-0.3 更新 `src/api/modules/suppliers.ts`（補 riskSummary 方法、detail 取得聯絡人）

## P0-1 Dashboard 完整化

- [x] P0-1.1 串接 GET /questionnaires/counts → 顯示 just_submitted_count
- [x] P0-1.2 串接 GET /caps（filter status=overdue）→ 逾期 CAP 數量
- [x] P0-1.3 串接 GET /risk/matrix?dimension=E → extreme_count
- [x] P0-1.4 新增快速行動卡片（待審問卷 → /questionnaires，逾期 CAP → /cap）

## P0-2 供應商詳情頁

- [x] P0-2.1 建立 `src/views/suppliers/SupplierDetailView.vue`（基本資料區塊）
- [x] P0-2.2 加入風險評估區塊（E/S/G/GP 四維度，score + level + 進度條）
- [x] P0-2.3 加入問卷記錄列表（GET /questionnaires?supplier_id=）
- [x] P0-2.4 加入聯絡人列表區塊
- [x] P0-2.5 Router 新增 /suppliers/:id 路由
- [x] P0-2.6 SuppliersView 列表行加「詳情」按鈕

## P0-3 問卷管理重構

- [x] P0-3.1 建立 `src/views/questionnaires/QuestionnaireView.vue`（採購商視角，GET /questionnaires）
- [x] P0-3.2 頁面頂部 KPI 雙計數（just_submitted_count + submitted_count）
- [x] P0-3.3 依狀態顯示對應操作按鈕（start-review / complete-review / return-review / mark-reviewed）
- [x] P0-3.4 建立 QuestionnaireSendModal（選供應商 + 範本 + 截止日，POST /questionnaires/send）
- [x] P0-3.5 Router 新增 /questionnaires 路由，Sidebar 補「問卷管理」選單項
- [x] P0-3.6 舊 SAQView 導向 /questionnaires（或移除）

## P0-4 供應商問卷填寫頁

- [x] P0-4.1 建立 `src/views/portal/SupplierSurveyView.vue`（題目載入、E/S/G 分類展示）
- [x] P0-4.2 實作題型渲染（single_choice/multiple_choice/text/number/boolean）
- [x] P0-4.3 答案 debounce 1.5s 自動儲存（PUT /questionnaires/:id）
- [x] P0-4.4 is_editable=false 時全表單 disabled + 提示 Banner
- [x] P0-4.5 提交按鈕（POST /questionnaires/:id/submit）+ 確認 Dialog
- [x] P0-4.6 Router 新增 /supplier/survey/:id，PortalView 列表加「填寫」按鈕
