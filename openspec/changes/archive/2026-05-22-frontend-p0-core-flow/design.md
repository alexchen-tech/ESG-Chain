# Design: Frontend P0 — 核心流程頁面

## 元件架構

```
views/
  dashboard/DashboardView.vue      ← 補全真實 API
  suppliers/
    SuppliersView.vue              ← 列表行加「詳情」按鈕
    SupplierDetailView.vue         ← 新建
  questionnaires/
    QuestionnaireView.vue          ← 重構 SAQView（改名）
    QuestionnaireSendModal.vue     ← 發送問卷 Modal（含供應商多選 + 範本選擇）
  portal/
    PortalView.vue                 ← 供應商問卷列表（既有，補充）
    SupplierSurveyView.vue         ← 新建（問卷填寫）

api/modules/
  questionnaire.ts                 ← 新建（取代 saq.ts）
  risk.ts                          ← 新建
  cap.ts                           ← 既有，補 overdue 篩選
```

---

## P0-1 Dashboard 資料流

```
mounted() {
  Promise.all([
    suppliersApi.list({ per_page: 100 })        → totalSuppliers, certifiedSuppliers
    questionnaireApi.counts()                    → just_submitted_count
    capApi.list({ status: 'overdue' })           → overdueCAP
    riskApi.matrix({ dimension: 'E' })           → extreme_count（summary.extreme_count）
  ])
}
```

快速行動卡片設計：
```
┌──────────────────────────────────────────────────────┐
│  📋 待審問卷  24 份     → [前往審核]                  │
│  ⚠️  逾期 CAP  3 件     → [查看 CAP]                  │
└──────────────────────────────────────────────────────┘
```

---

## P0-2 SupplierDetailView 版面

```
/suppliers/:id
══════════════════════════════════════════════════════

[← 返回列表]

┌─── 基本資料 ─────────────────────────────────────────┐
│ 興豐紡織股份有限公司  [active ▾]                      │
│ SUP-00123  ·  TW  ·  Tier 1  ·  紡織業              │
│ 分組：T1 關鍵供應商                                   │
└──────────────────────────────────────────────────────┘

┌─── 風險評估 ──────────────────────────────────────────┐
│  E 環境      score: 12  ████░░  medium               │
│  S 社會      score: 6   ██░░░░  low                  │
│  G 治理      score: 4   █░░░░░  low                  │
│  GP 地緣政治 score: 25  ██████  extreme ⚠️            │
│                        [建立新評估]                   │
└──────────────────────────────────────────────────────┘

┌─── 問卷記錄 ──────────────────────────────────────────┐
│  2025 Q1 ESG 評估  submitted   2025-03-14  [審核]    │
│  2024 ESG 評估     completed   2024-06-30  [查看]    │
└──────────────────────────────────────────────────────┘

┌─── 聯絡人 ─────────────────────────────────────────────┐
│  陳志明  ESG 負責人  supplier1@tpsteel.com.tw  [主]   │
└──────────────────────────────────────────────────────┘
```

---

## P0-3 問卷管理 KPI + 七狀態

```
頁面頂部 KPI 列
┌───────────────┬───────────────────────────────────┐
│ 管線（待審）   │  累計提交                          │
│      24        │      187                           │
└───────────────┴───────────────────────────────────┘

狀態 Badge 顏色映射
  not_started   → gray
  in_progress   → yellow
  submitted     → blue（+「開始審核」按鈕）
  under_review  → purple（+「通過」「退回」按鈕）
  review_returned → orange
  completed     → green（+「標記複核」按鈕）
  reviewed      → dark green（終態）

操作按鈕依狀態顯示（不是全部都顯示）
  submitted     → [開始審核]
  under_review  → [審核通過] [退回修改]
  completed     → [標記複核]
```

---

## P0-4 問卷填寫頁題型渲染

```javascript
// 依 question_type 渲染不同元件
renderQuestion(question) {
  switch (question.question_type) {
    case 'single_choice':
      // v-for options → Radio group
    case 'multiple_choice':
      // v-for options → Checkbox group
    case 'boolean':
      // Yes / No toggle button
    case 'number':
      // <input type="number">
    case 'text':
    default:
      // <textarea>
  }
}
```

自動儲存邏輯：
```javascript
// debounce 1500ms
const debouncedSave = debounce(async () => {
  await questionnaireApi.update(this.saqId, { answers: this.answers })
  this.lastSaved = new Date()
}, 1500)

// watch: answers deep → debouncedSave()
```

is_editable 鎖：
```
is_editable = false → 整個表單 pointer-events: none + 頂部 Banner：
「問卷目前處於審核中，暫時無法編輯」
```

---

## Router 新增路由

```typescript
{ path: '/suppliers/:id', name: 'supplier-detail',
  component: () => import('@/views/suppliers/SupplierDetailView.vue'),
  meta: { requiresAuth: true, roles: ['admin','buyer','sustain','comply','analyst'] } },

{ path: '/questionnaires', name: 'questionnaires',
  component: () => import('@/views/questionnaires/QuestionnaireView.vue'),
  meta: { requiresAuth: true, roles: ['admin','sustain','comply','analyst','buyer'] } },

{ path: '/supplier/survey/:id', name: 'supplier-survey',
  component: () => import('@/views/portal/SupplierSurveyView.vue'),
  meta: { requiresAuth: true, roles: ['supplier','sup_esg'] } },
```

## Sidebar 更新

```typescript
{ name: 'questionnaires', path: '/questionnaires', icon: '◻',
  label: '問卷管理', roles: ['admin','sustain','comply','analyst','buyer'] },
```
