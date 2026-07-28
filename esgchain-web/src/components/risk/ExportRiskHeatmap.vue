<template>
  <div class="heatmap-container">
    <div v-if="loading" class="heatmap-loading">計算熱力圖中...</div>

    <div v-else-if="error" class="heatmap-error">{{ error }}</div>

    <div v-else class="heatmap-scroll">
      <!-- 圖例 + 評分說明 -->
      <div class="legend">
        <div class="legend-row">
          <span class="legend-label">風險等級</span>
          <span v-for="item in legendItems" :key="item.level" class="legend-item">
            <span class="legend-dot" :class="'dot-' + item.level"></span>{{ item.label }}
            <span class="legend-range">{{ item.range }}</span>
          </span>
          <span class="legend-divider"></span>
          <span class="legend-item legend-gap">
            <span class="legend-dot dot-gap"></span>資料缺口
          </span>
          <span class="legend-item legend-calculating">
            <span class="legend-dot dot-calculating"></span>計算中
          </span>
        </div>
        <div class="legend-hint">
          分數 = 合規義務缺口分數 × 缺口放大係數（1.0～2.0）；缺口分數依「必要/選用文件」加權缺件、到期、過期狀態，
          缺越多必要文件、分數越高，跟供應商 ESG 評分無關，純粹反映該產品在此市場的法規文件缺口風險。
        </div>
      </div>

      <!-- 列=商品，欄=市場 -->
      <table class="heatmap-table">
        <thead>
          <tr>
            <th class="corner-cell">商品 / 市場</th>
            <th v-for="market in markets" :key="market" class="header-cell">{{ market }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="product in products" :key="product.id">
            <td class="row-label">
              <div class="product-name">{{ product.name }}</div>
              <div class="product-code font-mono">{{ product.hs_code }}</div>
            </td>
            <td v-for="market in markets" :key="market"
              class="data-cell"
              :class="cellClass(product.id, market)"
              :title="cellTooltip(product, market)"
              @click="onCellClick(product, market)">
              <div class="cell-content">
                <span class="cell-score" v-if="cellData(product.id, market)">
                  {{ cellScoreLabel(cellData(product.id, market)) }}
                </span>
                <span v-else class="cell-empty">—</span>
                <span v-if="cellData(product.id, market)?.status === 'calculating'" class="cell-badge">計算中</span>
                <span v-if="cellData(product.id, market)?.has_data_gap" class="cell-gap-dot" title="資料缺口">●</span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import api from '@/api/http'

const MARKETS = ['EU', 'US', 'UK', 'JP', 'TW', 'CN']

// 分數區間對應 esgchain-api CalculatePathRiskJob::scoreToLevel() 的門檻，UI 顯示要跟後端算法保持一致
const LEGEND_ITEMS = [
  { level: 'extreme', label: '極高', range: '≥1.6' },
  { level: 'high',    label: '高',   range: '1.2–1.6' },
  { level: 'medium',  label: '中',   range: '0.8–1.2' },
  { level: 'low',     label: '低',   range: '0.4–0.8' },
  { level: 'very_low',label: '極低', range: '<0.4' },
]

export default {
  name: 'ExportRiskHeatmap',
  emits: ['cell-click'],

  props: {
    selectedCell: { type: Object, default: null },
  },

  data() {
    return {
      loading: false,
      error: null,
      products: [],
      matrixData: {},
      markets: MARKETS,
      legendItems: LEGEND_ITEMS,
      pollTimer: null,
    }
  },

  mounted() {
    this.loadMatrix()
  },

  beforeUnmount() {
    clearInterval(this.pollTimer)
  },

  methods: {
    async loadMatrix() {
      this.loading = true
      this.error = null
      try {
        const resp = await api.get('/api/v1/trade-goods/export-risk-matrix')
        this.products = resp.data.products ?? []
        this.matrixData = resp.data.matrix ?? {}

        // 若有 calculating 格子，輪詢更新
        const hasCalculating = Object.values(this.matrixData).some(row =>
          Object.values(row).some(c => c?.status === 'calculating')
        )
        if (hasCalculating && !this.pollTimer) {
          this.pollTimer = setInterval(() => this.refreshMatrix(), 5000)
        }
      } catch (e) {
        this.error = '無法載入出口風險矩陣，請稍後再試。'
      } finally {
        this.loading = false
      }
    },

    async refreshMatrix() {
      try {
        const resp = await api.get('/api/v1/trade-goods/export-risk-matrix')
        this.products = resp.data.products ?? []
        this.matrixData = resp.data.matrix ?? {}

        const hasCalculating = Object.values(this.matrixData).some(row =>
          Object.values(row).some(c => c?.status === 'calculating')
        )
        if (!hasCalculating) {
          clearInterval(this.pollTimer)
          this.pollTimer = null
        }
      } catch {}
    },

    cellData(productId, market) {
      return this.matrixData[productId]?.[market] ?? null
    },

    cellClass(productId, market) {
      const cell = this.cellData(productId, market)
      if (!cell) return 'cell-empty-state'
      if (cell.status === 'calculating') return 'cell-calculating'
      const level = cell.risk_level ?? 'very_low'
      const isSelected = this.selectedCell?.tradeGoodId === productId && this.selectedCell?.market === market
      return [`cell-${level}`, cell.has_data_gap ? 'cell-data-gap' : '', isSelected ? 'cell-selected' : ''].filter(Boolean).join(' ')
    },

    cellScoreLabel(cell) {
      if (!cell || cell.status === 'calculating') return ''
      const score = cell.path_risk_score
      if (score == null) return '—'
      return score.toFixed(2)
    },

    cellTooltip(product, market) {
      const cell = this.cellData(product.id, market)
      const lines = [`${product.name}（${product.hs_code}） → ${market}`]
      if (!cell) {
        lines.push('無資料')
      } else if (cell.status === 'calculating') {
        lines.push('風險計算中，請稍後重新整理')
      } else {
        const levelLabel = LEGEND_ITEMS.find(l => l.level === cell.risk_level)?.label ?? cell.risk_level ?? '—'
        lines.push(`路徑風險分數：${cell.path_risk_score?.toFixed(2) ?? '—'}（${levelLabel}）`)
        if (cell.has_data_gap) lines.push('存在資料缺口，點擊查看義務缺口明細')
      }
      return lines.join('\n')
    },

    onCellClick(product, market) {
      const cell = this.cellData(product.id, market)
      this.$emit('cell-click', {
        tradeGoodId: product.id,
        tradeGoodName: product.name,
        market,
        riskLevel: cell?.risk_level,
        pathRiskScore: cell?.path_risk_score,
      })
    },
  },
}
</script>

<style scoped>
.heatmap-container {
  min-height: 200px;
  background: var(--surface, #fff);
  border: 1px solid var(--border, #e2ddd6);
  border-radius: 12px;
  padding: 1.25rem 1.5rem 1.5rem;
  box-shadow: 0 1px 2px rgba(28,25,23,0.04);
}
.heatmap-loading, .heatmap-error { text-align: center; padding: 3rem; color: var(--text-secondary, #78716c); font-size: 0.85rem; }
.heatmap-scroll { overflow-x: auto; }

.heatmap-table {
  border-collapse: separate;
  border-spacing: 4px;
  min-width: 500px;
}

.corner-cell, .header-cell {
  padding: 0.4rem 0.75rem 0.6rem;
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--text-secondary, #78716c);
  text-align: center;
  white-space: nowrap;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
.corner-cell { text-transform: none; letter-spacing: 0; text-align: left; }

.row-label {
  padding: 0.55rem 0.85rem;
  font-size: 0.82rem;
  white-space: nowrap;
  min-width: 148px;
  border-radius: 8px;
  transition: background 0.12s;
}
.product-name { font-weight: 600; color: var(--text-primary, #1c1917); }
.product-code { font-size: 0.68rem; color: var(--text-secondary, #a8a29e); margin-top: 1px; }

.data-cell {
  width: 78px; height: 58px;
  border-radius: 8px;
  cursor: pointer;
  border: 1px solid transparent;
  transition: transform 0.12s ease, box-shadow 0.12s ease, filter 0.12s ease;
  position: relative;
}
.data-cell:hover { transform: translateY(-1px); filter: brightness(0.97); box-shadow: 0 4px 10px rgba(28,25,23,0.14); z-index: 1; }

/* 同一列 hover 時，整列淡淡標示，方便對齊商品/市場 */
tbody tr:hover .row-label { background: var(--surface-2, #f0ede6); }

.cell-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  gap: 2px;
}
.cell-score { font-size: 0.78rem; font-weight: 700; font-family: var(--font-mono, monospace); letter-spacing: -0.01em; }
.cell-empty { font-size: 0.78rem; color: var(--text-secondary, #c9c4bc); }

.cell-badge { font-size: 0.58rem; font-weight: 600; letter-spacing: 0.02em; color: var(--text-secondary, #78716c); }
.cell-gap-dot { position: absolute; top: 5px; right: 6px; color: #c2410c; font-size: 0.5rem; line-height: 1; }

/* 風險等級底色與文字（同色系深淺搭配，score/label 文字沿用同色調的深版本，維持可讀性且不需只靠顏色辨識） */
.cell-extreme  { background: #f6cbc9; }
.cell-extreme  .cell-score  { color: #9f2a26; }
.cell-high     { background: #f8dcc0; }
.cell-high     .cell-score  { color: #a15417; }
.cell-medium   { background: #f5e7b8; }
.cell-medium   .cell-score  { color: #8a6d10; }
.cell-low      { background: #cdeace; }
.cell-low      .cell-score  { color: #276b3a; }
.cell-very_low { background: #dcf1dc; }
.cell-very_low .cell-score  { color: #2f7a42; }
.cell-empty-state  { background: var(--surface-2, #f7f5f1); cursor: default; }
.cell-calculating  { background: var(--surface-2, #f0ede6); }

/* 資料缺口橘框 */
.cell-data-gap { border-color: #c2410c; }

/* 選中態 */
.cell-selected { border-color: var(--accent, #1a4d3e) !important; box-shadow: 0 0 0 2px rgba(26,77,62,0.18); }

/* 圖例（移到表格上方，含分數區間與評分邏輯說明） */
.legend {
  margin-bottom: 1.1rem;
  padding-bottom: 0.9rem; border-bottom: 1px solid var(--border, #e2ddd6);
  font-size: 0.76rem; color: var(--text-secondary, #78716c);
}
.legend-row {
  display: flex; align-items: center; gap: 1.1rem;
  flex-wrap: wrap;
}
.legend-label { font-weight: 600; color: var(--text-primary, #1c1917); }
.legend-item { display: inline-flex; align-items: center; gap: 0.35rem; }
.legend-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
.legend-range { font-family: var(--font-mono, monospace); color: var(--text-secondary, #78716c); font-size: 0.7rem; }
.dot-extreme  { background: #d0524d; }
.dot-high     { background: #d9863f; }
.dot-medium   { background: #c6a13a; }
.dot-low      { background: #4e9e63; }
.dot-very_low { background: #3f8a56; }
.dot-gap      { background: transparent; border: 2px solid #c2410c; }
.dot-calculating { background: var(--border, #d6d1c8); }
.legend-divider { width: 1px; height: 14px; background: var(--border, #e2ddd6); }
.legend-hint {
  margin-top: 0.6rem; line-height: 1.6; font-size: 0.74rem;
  color: var(--text-secondary, #78716c);
}
</style>
