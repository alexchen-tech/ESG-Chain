# Design: Frontend P1 — 分析工具頁面

## P1-1 風險矩陣 CSS Grid 實作

### HTML 結構

```html
<div class="risk-matrix">
  <!-- 軸標籤 -->
  <div class="matrix-y-label">Probability ↑</div>
  <div class="matrix-grid">
    <!-- 5×5 = 25 個格子，從 p=5 i=1 開始 -->
    <div
      v-for="cell in orderedCells"
      :key="`${cell.probability}-${cell.impact}`"
      class="matrix-cell"
      :class="cell.risk_level"
      :style="{ cursor: cell.supplier_count > 0 ? 'pointer' : 'default' }"
      @click="cell.supplier_count > 0 && openDrillDown(cell)"
    >
      <span class="cell-count">{{ cell.supplier_count || '' }}</span>
    </div>
  </div>
  <div class="matrix-x-label">Impact →</div>
</div>
```

### CSS

```css
.matrix-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  grid-template-rows: repeat(5, 1fr);
  gap: 4px;
  width: 400px;
  height: 400px;
}

.matrix-cell {
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
  font-family: var(--font-mono);
  font-size: 18px;
  font-weight: 700;
  transition: transform 0.1s;
}
.matrix-cell:hover { transform: scale(1.05); }

.matrix-cell.very_low { background: #dcfce7; color: #166534; }
.matrix-cell.low      { background: #bbf7d0; color: #15803d; }
.matrix-cell.medium   { background: #fef9c3; color: #854d0e; }
.matrix-cell.high     { background: #fed7aa; color: #9a3412; }
.matrix-cell.extreme  { background: #fecaca; color: #991b1b; }
.matrix-cell.empty    { background: var(--surface-2); color: var(--text-secondary); }
```

### 資料排序（p=5 在頂部）

```javascript
computed: {
  orderedCells() {
    // API 回傳 p1i1...p5i5，重新排序為視覺化順序
    // 頂部 = p=5，左 = i=1
    const result = []
    for (let p = 5; p >= 1; p--) {
      for (let i = 1; i <= 5; i++) {
        const cell = this.matrix.find(c => c.probability === p && c.impact === i)
        result.push(cell || { probability: p, impact: i, supplier_count: 0, risk_level: 'empty', cell_score: p * i })
      }
    }
    return result
  }
}
```

### Drawer 下鑽

```
右側 Drawer（position: fixed, right: 0, width: 360px）
  標題：E維度 - Probability 5 × Impact 5 (Extreme)
  列表：供應商名稱 + 國家 + 狀態 → 點擊導向詳情頁
```

---

## P1-2 Scope 3 長條圖（純 CSS）

```html
<div v-for="cat in categories" :key="cat.category_number" class="bar-row">
  <div class="bar-label">
    <span class="bar-num">{{ cat.category_number }}</span>
    <span class="bar-name">{{ cat.category_name }}</span>
  </div>
  <div class="bar-track">
    <div
      class="bar-fill"
      :style="{ width: barWidth(cat.co2e) + '%' }"
    ></div>
  </div>
  <div class="bar-value font-mono">{{ formatCo2e(cat.co2e) }}</div>
</div>
```

```javascript
methods: {
  barWidth(co2e) {
    const max = Math.max(...this.categories.map(c => c.co2e), 1)
    return (co2e / max) * 100
  },
  formatCo2e(val) {
    if (val >= 1000) return (val / 1000).toFixed(1) + ' tCO2e'
    return val.toFixed(1) + ' kgCO2e'
  }
}
```

---

## api/modules/reports.ts

```typescript
export const reportsApi = {
  scope3: (year: number) =>
    http.get('/api/v1/reports/scope3', { params: { year } }),
  exportScope3: (year: number, format: 'xlsx' | 'pdf') =>
    http.get('/api/v1/reports/scope3/export',
      { params: { year, format }, responseType: 'blob' }),
}
```

匯出下載處理：
```typescript
const response = await reportsApi.exportScope3(year, 'xlsx')
const url = URL.createObjectURL(new Blob([response.data]))
const a = document.createElement('a')
a.href = url
a.download = `scope3_${year}.csv`
a.click()
URL.revokeObjectURL(url)
```
