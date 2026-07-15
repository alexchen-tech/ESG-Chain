## Context

本文件整合三個並行開發的規格變更：

| 原 Change | 狀態 | 核心內容 |
|---|---|---|
| `risk-matrix-intelligence` | 19/20 tasks | buildMatrix bug fix、SAQ→RA 自動推導、extreme CAP 觸發 |
| `supplier-timeline` | 40/46 tasks | source_saq_id FK、timeline API、事件流 UI、比較籃 |
| （本 session UI 優化，未歸 change） | 完成 | SupplierDetailView/RiskMatrixView/SuppliersView/QuestionnaireView 介面重構 |

合併後視為單一功能集，以下統一描述技術決策與實作細節。

---

## Goals / Non-Goals

**Goals:**
- 每個 supplier_id 在矩陣中只對應其最新評估（修正重複格子 bug）
- SAQ 計分完成後自動建立 RiskAssessment（E/S/G），精確追蹤來源
- Extreme 維度自動開立 CAP，防止人工遺漏
- 供應商詳情頁提供可視化因果鏈（SAQ ↔ RA ↔ CAP 時間軸），採雙欄佈局
- 介面視覺層次達到專業水準（字型、間距、色彩、操作按鈕）

**Non-Goals:**
- GP 自動推導（無對應 SAQ 題組）
- 比較籃 localStorage 持久化
- CompareModal 匯出 PDF/CSV
- 回填歷史 RA 的 `source_saq_id`
- esgchain-ai PostgreSQL RiskAssessment 整合

---

## 後端設計

### D1：buildMatrix() 修正

**問題根因**：`GROUP BY supplier_id, p, i` + `MAX(assessed_at)` 組合，在同一 supplier 有多筆不同 p/i 的評估時，各組合各自保留，導致同一供應商出現在多個格子。

**修正方案**：
```sql
WITH latest AS (
  SELECT supplier_id, MAX(assessed_at) as max_at
  FROM risk_assessments
  GROUP BY supplier_id
)
SELECT ra.supplier_id, ra.{dim}_probability, ra.{dim}_impact
FROM risk_assessments ra
JOIN latest ON ra.supplier_id = latest.supplier_id
          AND ra.assessed_at = latest.max_at
```

Laravel 用 `joinSub()`，每個維度 tab 獨立一次查詢。

---

### D2：SAQ → RiskAssessment 自動推導

**換算規則**：
```
probability = max(1, ceil((100 - score_dim) / 20))
impact      = 3（固定基準）

score_e → e_probability, e_impact = 3
score_s → s_probability, s_impact = 3
score_g → g_probability, g_impact = 3
gp_*    = null（保留上次手動值或預設 3，不自動）
```

score_dim 為 null 時跳過，不建立 RiskAssessment。

**觸發點**：`SAQController::scoreCallback()` 完成計分後，呼叫 `RiskAutoDerivationService::deriveFromSaq($saq)`。

**assessed_by**：null（系統建立），`notes = '自動從 SAQ #{saq_id} 推導'`，`is_auto = true`。

---

### D3：source_saq_id FK

**Migration**：
```sql
ALTER TABLE risk_assessments
  ADD COLUMN source_saq_id CHAR(36) NULL,
  ADD CONSTRAINT fk_ra_source_saq
    FOREIGN KEY (source_saq_id) REFERENCES saqs(id) ON DELETE SET NULL;
```

**填入時機**：`RiskAutoDerivationService::deriveFromSaq()` 建立 RA 時直接設定；手動建立的 RA `source_saq_id = null`。

**歷史資料**：舊 RA 保持 null，不回填。前端以 `is_auto` 欄位判斷是否顯示「自動」標籤，無 `source_saq_id` 則不顯示「查看問卷 →」連結。

---

### D4：Extreme CAP 自動觸發

**Observer**：`RiskAssessmentObserver::created()` — 在 `RiskAutoDerivationService` 建立 RA 後觸發。

**偵測邏輯**：
```php
$extremeDims = [];
foreach (['e', 's', 'g', 'gp'] as $dim) {
    $p = $assessment->{"{$dim}_probability"} ?? 0;
    $i = $assessment->{"{$dim}_impact"} ?? 0;
    if ($p * $i >= 20) $extremeDims[] = strtoupper($dim);
}
```

**防重複**：查 `caps` 表，`source_type='risk_assessment' AND source_id=$assessment->id`，已存在則跳過。

**建立的 CAP**：
```php
[
  'supplier_id'  => $assessment->supplier_id,
  'source_type'  => 'risk_assessment',
  'source_id'    => $assessment->id,
  'priority'     => 'high',
  'title'        => "風險評估 Extreme 警示：{$supplier->name}",
  'due_date'     => now()->addDays(30),
  'status'       => 'open',
]
```

每個 extreme 維度建一筆 CAPFinding：
```php
[
  'category' => $dim,   // 'E' / 'S' / 'G' / 'GP'
  'finding'  => "{$dim} 維度 cell_score={$score}，已達 extreme 等級",
]
```

---

### D5：SupplierTimelineService + API

**Endpoint**：`GET /api/v1/suppliers/{id}/risk-timeline`

**回傳結構**：
```json
{
  "events": [
    {
      "type": "risk_assessment",
      "date": "2026-01-15T08:00:00Z",
      "risk": {
        "id": "uuid",
        "e":  { "probability": 4, "impact": 3, "score": 12, "level": "medium" },
        "s":  { "probability": 3, "impact": 3, "score": 9,  "level": "low" },
        "g":  { "probability": 2, "impact": 2, "score": 4,  "level": "very_low" },
        "gp": { "probability": 5, "impact": 4, "score": 20, "level": "extreme" },
        "is_auto": true,
        "source_saq_id": "uuid|null",
        "notes": "自動從 SAQ #xxx 推導"
      },
      "linked_saq": {
        "id": "uuid", "score": 65, "grade": "C",
        "score_e": 68, "score_s": 55, "score_g": 71,
        "submitted_at": "2026-01-13T10:00:00Z"
      },
      "caps": [{ "id": "uuid", "status": "open", "findings_count": 2 }]
    },
    {
      "type": "saq_scored",
      "date": "2025-12-03T14:30:00Z",
      "saq": {
        "id": "uuid", "score": 75, "grade": "B",
        "score_e": 75, "score_s": 83, "score_g": 67,
        "submitted_at": "2025-12-27T09:00:00Z",
        "status": "completed"
      }
    }
  ],
  "pending_saq": {
    "id": "uuid",
    "status": "submitted",
    "submitted_at": "2026-06-20T11:00:00Z"
  }
}
```

`pending_saq`：最新 SAQ 若 `score IS NULL` 且 status 在 `['submitted','under_review']`，額外回傳供前端置頂顯示。

**實作**：`app/Services/Suppliers/SupplierTimelineService::getTimeline($supplierId)`，以 `assessed_at` / `submitted_at` 降冪 UNION 後排序，eager-load linked SAQ 與 CAP。

---

## 前端設計

### D6：供應商詳情頁 Tab 結構

| Tab key | 標題 | 內容 |
|---|---|---|
| `overview` | 概況 | 識別資訊 grid（改良版）|
| `risk` | 風險歷史 | 雙欄時間軸（SAQ 左 / RA 右）|
| `sustain` | 永續績效 | 風險評估 bar + 問卷記錄 + 揭露資料 |
| `comply` | 合規管理 | 供應材料清單 + 合規文件 |
| `facility` | 聯絡資訊 | 聯絡人 + 地址 |

### D7：雙欄時間軸佈局

```
┌─ SAQ 計分 ─────────────┐  ┌─ 風險評估 ──────────────┐
│ [Pending 卡]           │  │                        │
│ ┌──────────────────┐   │  │ ┌──────────────────┐   │
│ │ 2025/12/3 SAQ 計分│   │  │ │ 2026/1/7  ⚡ 自動 │   │
│ │ B  75.0分         │   │  │ │ E ████░░  4  VL  │   │
│ │ E:75 S:83 G:67   │   │  │ │ S ██████░ 6  L   │   │
│ │ 提交於 12/27      │   │  │ │ G ████░░  4  VL  │   │
│ └──────────────────┘   │  │ │ GP ███░░  3  VL  │   │
│                        │  │ │ 來源 SAQ: B 75.0分│   │
│ ┌──────────────────┐   │  │ └──────────────────┘   │
│ │ 2025/7/3  SAQ 計分│   │  │                        │
│ │ C  68.0分         │   │  │ ┌──────────────────┐   │
│ │ E:71 S:61 G:75   │   │  │ │ 2025/7/3  ⚡ 自動 │   │
│ └──────────────────┘   │  │ │ E ████████ 8  L   │   │
└────────────────────────┘  │ │ S ████████████12 M│   │
                             │ └──────────────────┘   │
                             └────────────────────────┘
```

CSS：`display: grid; grid-template-columns: 1fr 1fr; gap: 16px; align-items: start`

### D8：事件卡片視覺規則

| 類型 | 左邊框 | 背景 |
|---|---|---|
| `risk_assessment`（自動） | 3px `#f97316`（橙）| `#fffcf9` |
| `risk_assessment`（手動） | 3px `#94a3b8`（灰）| `var(--surface)` |
| `saq_scored` | 3px `#3b82f6`（藍）| `#f8faff` |
| `pending_saq` | 虛線 `#fbbf24`（黃）頂框 | `#fffdf0` |

### D9：識別資訊 Grid 樣式

- `detail-item`：`padding: 13px 16px 13px 0; border-bottom: 1px solid #f0ede6`
- 奇數欄（左半）：`border-right: 1px solid #f0ede6`，右 padding 32px
- 偶數欄（右半）：左 padding 32px
- 最後兩個 item 無底線
- `detail-label`：11.5px weight 500，`detail-value`：14px weight 600

### D10：Section 標題樣式

```css
.section-title {
  font-size: 15px;
  font-weight: 700;
  border-left: 3px solid var(--accent);  /* #1a4d3e */
  padding-left: 10px;
  margin-bottom: 20px;
}
```

**所有 `section-title-row` 內的 span** 移除 `style="border:none"` 內聯覆蓋，統一套用此樣式。

### D11：頁頭 Meta Chips

```html
<div class="supplier-meta-chips">
  <span class="meta-chip meta-chip-mono">{{ supplier.code }}</span>
  <span class="meta-chip">{{ supplier.country_code }}</span>
  <span class="meta-chip">Tier {{ supplier.tier }}</span>
</div>
```

```css
.meta-chip {
  font-size: 11.5px; font-weight: 500;
  background: #f0ede6; border: 1px solid #e4e0d9;
  border-radius: 5px; padding: 2px 8px;
}
```

### D12：RiskMatrixView Legend 橫式色條

```html
<div class="legend-band">
  <span v-for="l in LEVELS" :key="l.value" class="legend-band-item" :class="l.value">
    {{ l.label }}
  </span>
</div>
```

```css
.legend-band { display: flex; border-radius: 6px; overflow: hidden; height: 28px; }
.legend-band-item { flex: 1; display: flex; align-items: center; justify-content: center; font-size: 11.5px; font-weight: 600; }
.legend-band-item.very_low { background: var(--risk-very-low-bg); color: var(--risk-very-low-color); }
/* ... low / medium / high / extreme 同理 */
```

### D13：Pinia compareStore

```typescript
// src/stores/compareStore.ts
export const useCompareStore = defineStore('compare', {
  state: () => ({ suppliers: [] as Supplier[] }),
  getters: {
    canAdd: (s) => s.suppliers.length < 4,
    isAdded: (s) => (id: string) => s.suppliers.some(x => x.id === id),
  },
  actions: {
    add(supplier: Supplier) { if (this.canAdd) this.suppliers.push(supplier) },
    remove(id: string) { this.suppliers = this.suppliers.filter(x => x.id !== id) },
    clear() { this.suppliers = [] },
  }
})
```

---

## 資料欄位說明

| 欄位 | 所屬表 | 擁有者 | Sync 行為 |
|---|---|---|---|
| `source_saq_id` | `risk_assessments` | ESG-Chain | 永不被 ERP sync 覆蓋 |
| `is_auto` | `risk_assessments` | ESG-Chain | 永不被 ERP sync 覆蓋 |
| `onboarding_stage` | `suppliers` | ESG-Chain | 永不被 ERP sync 覆蓋 |

---

## Migration Plan

1. **DB migration**：`ALTER TABLE risk_assessments ADD COLUMN source_saq_id CHAR(36) NULL` + FK
2. **後端部署**：`RiskAutoDerivationService`、`RiskAssessmentObserver`（AppServiceProvider 註冊）、`SupplierTimelineService`、修改 `scoreCallback()` + `buildMatrix()`、新增路由
3. **前端部署**：`compareStore.ts`、修改 `SupplierDetailView.vue`（tab 結構 + 雙欄 timeline + meta chips + detail grid）、`RiskMatrixView.vue`（廠商卡 + legend）、`SuppliersView.vue`（表格欄位）、`QuestionnaireView.vue`（列寬）、`AppSidebar.vue`（icon + footer）、`components.css`、`index.html`（Fonts）
4. **Demo 資料**：執行 `DemoEnhancedSeeder`，填充 20 個供應商、72 筆 SAQ、65 筆 RA（48 筆含 source_saq_id）、29 筆 CAP

**Rollback**：前端重新部署舊 bundle；後端新增 endpoint 不影響現有路由；`DROP COLUMN source_saq_id` 回滾 migration。

---

## Risks / Trade-offs

| 風險 | 說明 | 緩解 |
|---|---|---|
| 歷史 RA 無 source_saq_id | 舊資料 null，無法點擊「查看問卷 →」 | 前端依 source_saq_id 是否存在決定是否顯示連結；無需回填 |
| GP 自動評估缺失 | 自動建立的 RA 無 gp_*，矩陣 GP tab 不更新 | 接受；GP 維持手動，notes 標示「GP 待補填」 |
| Extreme CAP 重複 | 同供應商多次評估可能觸發多個 CAP | Observer 以 source_id 去重，per-assessment 唯一 |
| 比較籃不持久化 | 頁面重整後清空 | 接受；非核心決策工具，避免過期資料問題 |
| 雙欄時間軸窄螢幕 | < 768px 可能擠壓 | `@media (max-width: 768px)` 改單欄 |

---

## Open Questions

- CompareModal（並排 4 家，SAQ + 風險四維度）尚未實作：`supplier-timeline` tasks 8.x 剩餘 6 個 subtask
- 比較籃是否需要從供應商清單 checkbox 觸發（目前只有 RiskMatrixView panel）
- supplier-timeline tasks 8.8（timeline CSS）已完成；8.9–8.11（CompareModal）待實作
