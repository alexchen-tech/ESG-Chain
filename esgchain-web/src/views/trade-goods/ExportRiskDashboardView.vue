<template>
  <div class="page-container">
    <div class="page-header">
      <h1 class="page-title">出口合規風險看板</h1>
      <p class="page-subtitle">商品 × 市場路徑風險熱力圖，識別合規義務缺口並支援供應商替換決策</p>
    </div>

    <div class="main-layout">
      <!-- 熱力圖（商品 × 市場） -->
      <div class="heatmap-section">
        <ExportRiskHeatmap
          :selected-cell="selectedCell"
          @cell-click="onCellClick"
        />
      </div>

      <!-- 右側面板 -->
      <div v-if="selectedCell" class="detail-panel">
        <!-- 缺口明細 -->
        <ComplianceGapPanel
          :trade-good-id="selectedCell.tradeGoodId"
          :market="selectedCell.market"
          :trade-good-name="selectedCell.tradeGoodName"
          @create-cap="onCreateCap"
          @request-replacement="onRequestReplacement"
        />

        <!-- 替換供應商推薦（觸發後顯示） -->
        <SupplierReplacementPanel
          v-if="replacementContext"
          :supplier-id="replacementContext.supplierId"
          :supplier-name="replacementContext.supplierName"
          :trade-good-id="selectedCell.tradeGoodId"
          :market="selectedCell.market"
          @close="replacementContext = null"
        />
      </div>

      <div v-else class="detail-placeholder">
        <div class="placeholder-icon">⬡</div>
        <p>點擊熱力圖中的格子<br>查看合規義務缺口與供應商風險</p>
      </div>
    </div>
  </div>
</template>

<script>
import ExportRiskHeatmap from '@/components/risk/ExportRiskHeatmap.vue'
import ComplianceGapPanel from '@/components/risk/ComplianceGapPanel.vue'
import SupplierReplacementPanel from '@/components/risk/SupplierReplacementPanel.vue'

export default {
  name: 'ExportRiskDashboardView',
  components: { ExportRiskHeatmap, ComplianceGapPanel, SupplierReplacementPanel },

  data() {
    return {
      selectedCell: null,
      replacementContext: null,
    }
  },

  mounted() {
    const q = this.$route.query
    if (q.trade_good_id && q.market) {
      this.selectedCell = {
        tradeGoodId: q.trade_good_id,
        market: q.market,
        tradeGoodName: '',
      }
    }
  },

  methods: {
    onCellClick(cell) {
      this.selectedCell = cell
      this.replacementContext = null
    },

    onCreateCap({ capId, capTitle }) {
      const goNow = confirm(`已建立「${capTitle}」CAP。是否立即前往查看？`)
      if (goNow) {
        this.$router.push({ name: 'cap', query: { highlight: capId } })
      }
    },

    onRequestReplacement({ supplierId, supplierName }) {
      this.replacementContext = { supplierId, supplierName }
    },
  },
}
</script>

<style scoped>
/* 資料密集頁面靠左對齊，避免寬螢幕時 margin:0 auto 置中產生側邊欄旁的大片留白 */
.page-container { max-width: 1600px; margin: 0; padding: 1.75rem 2rem 2rem; }
.page-header { margin-bottom: 1.5rem; padding-bottom: 1.25rem; border-bottom: 1px solid var(--border, #e2ddd6); }
.page-title { font-size: 1.4rem; font-weight: 700; color: var(--text-primary, #1c1917); letter-spacing: 0.01em; }
.page-subtitle { color: var(--text-secondary, #78716c); font-size: 0.85rem; margin-top: 0.35rem; }

/* nowrap：兩欄在中間寬度時靠熱力圖自身的橫向捲動（overflow-x:auto）擠壓，
   而不是讓 flex-wrap 在任意寬度不受控地把面板擠到下一行，導致版面被切成上下兩段 */
.main-layout { display: flex; align-items: flex-start; gap: 1.5rem; flex-wrap: nowrap; }
@media (max-width: 1024px) { .main-layout { flex-direction: column; flex-wrap: wrap; } }

/* flex-shrink 讓表格在空間不足時自己觸發內部橫向捲動，不會撐爆整個版面把面板推到畫面外；
   空間充足時 flex-basis:auto 讓表格照內容寬度顯示，不會被拉伸出多餘留白 */
.heatmap-section { flex: 1 1 auto; min-width: 0; overflow-x: auto; }

.detail-panel { flex: 0 0 560px; max-width: 100%; display: flex; flex-direction: column; gap: 1rem; }

.detail-placeholder {
  flex: 0 0 560px; max-width: 100%;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  height: 320px; color: var(--text-secondary, #a8a29e); text-align: center; line-height: 1.7;
  background: var(--surface, #fff);
  border: 1px dashed var(--border, #d6d1c8); border-radius: 12px;
  font-size: 0.88rem;
}
.placeholder-icon { font-size: 2.2rem; margin-bottom: 0.85rem; opacity: 0.6; }
</style>
