<template>
  <div class="heatmap-container">
    <div v-if="loading" class="heatmap-loading">計算熱力圖中...</div>

    <div v-else-if="error" class="heatmap-error">{{ error }}</div>

    <div v-else class="heatmap-scroll">
      <!-- 商品優先：列=商品，欄=市場 -->
      <table v-if="pivotBy === 'commodity'" class="heatmap-table">
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

      <!-- 市場優先：列=市場，欄=商品 -->
      <table v-else class="heatmap-table">
        <thead>
          <tr>
            <th class="corner-cell">市場 / 商品</th>
            <th v-for="product in products" :key="product.id" class="header-cell">
              <div>{{ product.name }}</div>
              <div class="font-mono" style="font-size:0.65rem;color:#9ca3af;">{{ product.hs_code }}</div>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="market in markets" :key="market">
            <td class="row-label">{{ market }}</td>
            <td v-for="product in products" :key="product.id"
              class="data-cell"
              :class="cellClass(product.id, market)"
              @click="onCellClick(product, market)">
              <div class="cell-content">
                <span class="cell-score" v-if="cellData(product.id, market)">
                  {{ cellScoreLabel(cellData(product.id, market)) }}
                </span>
                <span v-else class="cell-empty">—</span>
                <span v-if="cellData(product.id, market)?.has_data_gap" class="cell-gap-dot" title="資料缺口">●</span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- 圖例 -->
      <div class="legend">
        <span class="legend-label">風險等級：</span>
        <span v-for="item in legendItems" :key="item.level" class="legend-item" :class="'cell-' + item.level">
          {{ item.label }}
        </span>
        <span class="legend-gap">
          <span class="cell-gap-dot">●</span> 資料缺口
        </span>
        <span class="legend-calculating">計算中</span>
      </div>
    </div>
  </div>
</template>

<script>
import api from '@/api/http'

const MARKETS = ['EU', 'US', 'UK', 'JP', 'TW', 'CN']

const LEGEND_ITEMS = [
  { level: 'extreme', label: '極高' },
  { level: 'high',    label: '高' },
  { level: 'medium',  label: '中' },
  { level: 'low',     label: '低' },
  { level: 'very_low',label: '極低' },
]

export default {
  name: 'ExportRiskHeatmap',
  emits: ['cell-click'],

  props: {
    pivotBy: { type: String, default: 'commodity' },
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
        const resp = await api.get('/trade-goods/export-risk-matrix')
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
        const resp = await api.get('/trade-goods/export-risk-matrix')
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
.heatmap-container { min-height: 200px; }
.heatmap-loading, .heatmap-error { text-align: center; padding: 3rem; color: #9ca3af; }
.heatmap-scroll { overflow-x: auto; }

.heatmap-table {
  border-collapse: separate;
  border-spacing: 3px;
  min-width: 500px;
}

.corner-cell, .header-cell {
  padding: 0.5rem 0.75rem;
  font-size: 0.8rem;
  font-weight: 600;
  color: #374151;
  text-align: center;
  white-space: nowrap;
}

.row-label {
  padding: 0.5rem 0.75rem;
  font-size: 0.8rem;
  white-space: nowrap;
  min-width: 140px;
}
.product-name { font-weight: 500; }
.product-code { font-size: 0.7rem; color: #9ca3af; }

.data-cell {
  width: 72px; height: 52px;
  border-radius: 6px;
  cursor: pointer;
  transition: opacity 0.15s, transform 0.1s;
  position: relative;
}
.data-cell:hover { transform: scale(1.05); opacity: 0.9; }

.cell-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  gap: 2px;
}
.cell-score { font-size: 0.75rem; font-weight: 700; font-family: monospace; }
.cell-empty { font-size: 0.75rem; color: #d1d5db; }

.cell-badge { font-size: 0.6rem; color: #6b7280; }
.cell-gap-dot { color: #f97316; font-size: 0.6rem; }

/* 風險等級底色 */
.cell-extreme  { background: #fca5a5; }
.cell-high     { background: #fdba74; }
.cell-medium   { background: #fde68a; }
.cell-low      { background: #bbf7d0; }
.cell-very_low { background: #d1fae5; }
.cell-empty-state  { background: #f9fafb; cursor: default; }
.cell-calculating  { background: #f3f4f6; }

/* 資料缺口橘框 */
.cell-data-gap { outline: 2px solid #f97316; outline-offset: -2px; }

/* 選中態 */
.cell-selected { outline: 2px solid var(--accent, #1a4d3e) !important; outline-offset: -2px; }

/* 圖例 */
.legend { display: flex; align-items: center; gap: 0.75rem; margin-top: 0.75rem; flex-wrap: wrap; font-size: 0.75rem; }
.legend-label { color: #6b7280; }
.legend-item { padding: 2px 10px; border-radius: 4px; font-weight: 500; }
.legend-item.cell-extreme  { background: #fca5a5; }
.legend-item.cell-high     { background: #fdba74; }
.legend-item.cell-medium   { background: #fde68a; }
.legend-item.cell-low      { background: #bbf7d0; }
.legend-item.cell-very_low { background: #d1fae5; }
.legend-gap { color: #f97316; }
.legend-calculating { color: #9ca3af; }
</style>
