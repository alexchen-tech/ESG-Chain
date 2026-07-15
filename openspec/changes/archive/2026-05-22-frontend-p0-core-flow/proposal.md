# Change Proposal: Frontend P0 — 核心流程頁面

## 動機

後端 API（Wave A/B/C）已完整對齊 spec v2.1.0，但前台頁面仍停留在骨架狀態。P0 實作系統不可缺少的核心互動流程，讓採購商、永續長、供應商三個主要角色都能完整操作。

## 範圍

### P0-1 Dashboard 完整化

串接真實 API，補全所有統計指標：
- `GET /questionnaires/counts` → `just_submitted_count`（待審問卷管線）
- `GET /caps?status=overdue` → 逾期 CAP 數量
- `GET /risk/matrix?dimension=E` → extreme 供應商數量
- 快速行動入口：「待審問卷 X 份」→ 點擊導向 /questionnaires

### P0-2 供應商詳情頁 `/suppliers/:id`

新建 `SupplierDetailView.vue`：
- 基本資料（可編輯 inline）
- Risk Summary 四維度卡片（E/S/G/GP，含 score 和 level）
- 該供應商的問卷記錄列表（GET /questionnaires?supplier_id=）
- 聯絡人列表
- 狀態流轉操作按鈕（active/inactive/suspended）
- SuppliersView 列表行點擊「詳情」導向此頁

### P0-3 問卷管理 SAQView 全面重構

將 SAQView 重構為採購商 / 永續長視角：
- 列表改用 `GET /questionnaires`（全供應商視角，非 myList）
- 七狀態 Badge 顏色區分
- `GET /questionnaires/counts` → 頁面頂部 KPI 雙計數顯示
- 新增操作按鈕：start-review / complete-review / return-review（依狀態顯示）
- 發送問卷按鈕 → `POST /questionnaires/send`（選擇供應商 + 範本）

### P0-4 供應商問卷填寫頁

新建路由 `/supplier/survey/:id` 和 `SupplierSurveyView.vue`：
- 從 `GET /questionnaires/:id` 取得題目（template.questions）
- 題型渲染：single_choice / multiple_choice / text / number / boolean
- 題目按 E/S/G 分類展示（category 欄位）
- 答案 debounce 1.5s 自動儲存（`PUT /questionnaires/:id`）
- `is_editable = false` 時全表單 disabled + 顯示提示 Banner
- 提交按鈕（`POST /questionnaires/:id/submit`）
- PortalView 列表 → 點擊進入問卷填寫

## 新增 API 模組

- `esgchain-web/src/api/modules/questionnaire.ts`（取代/重構 saq.ts，符合新路徑）
- `esgchain-web/src/api/modules/risk.ts`（matrix/assessments）
- 更新 `suppliers.ts`（補 riskSummary 方法）

## 不在範圍

- 風險矩陣視覺化（P1）
- Scope 3 報告（P1）
- Settings 頁面（P2）

## 成功條件

- [ ] Dashboard 四個統計指標全部來自真實 API
- [ ] `/suppliers/:id` 可看到 risk summary（E/S/G/GP 四維度）
- [ ] 採購商登入可看到所有供應商問卷列表，可執行 start-review
- [ ] 供應商登入後可在 Portal 填寫問卷，自動儲存正常，提交後 disabled
