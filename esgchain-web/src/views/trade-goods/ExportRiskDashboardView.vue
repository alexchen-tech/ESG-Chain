<template>
  <div class="page-container">
    <div class="page-header">
      <h1 class="page-title">出口合規風險看板</h1>
      <p class="page-subtitle">商品 × 市場路徑風險熱力圖，識別合規義務缺口並支援供應商替換決策</p>
    </div>

    <!-- 入口切換 -->
    <div class="view-toggle">
      <button :class="['toggle-btn', { active: pivotBy === 'commodity' }]" @click="pivotBy = 'commodity'">
        商品優先
      </button>
      <button :class="['toggle-btn', { active: pivotBy === 'market' }]" @click="pivotBy = 'market'">
        市場優先
      </button>
    </div>

    <div class="main-layout">
      <!-- 熱力圖 -->
      <div class="heatmap-section">
        <ExportRiskHeatmap
          :pivot-by="pivotBy"
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
      pivotBy: 'commodity',
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

    onCreateCap({ supplierId, docType }) {
      this.$router.push({ name: 'cap', query: { supplier_id: supplierId, doc_type: docType, source_type: 'compliance_doc_gap' } })
    },

    onRequestReplacement({ supplierId, supplierName }) {
      this.replacementContext = { supplierId, supplierName }
    },
  },
}
</script>

<style scoped>
.page-container { max-width: 1400px; margin: 0 auto; padding: 1.5rem; }
.page-header { margin-bottom: 1rem; }
.page-title { font-size: 1.5rem; font-weight: 700; color: var(--accent, #1a4d3e); }
.page-subtitle { color: #6b7280; font-size: 0.875rem; margin-top: 0.25rem; }

.view-toggle { display: flex; gap: 0; margin-bottom: 1rem; }
.toggle-btn {
  padding: 0.4rem 1rem; border: 1px solid var(--color-border, #e5e7eb);
  cursor: pointer; background: white; font-size: 0.875rem;
}
.toggle-btn:first-child { border-radius: 0.5rem 0 0 0.5rem; }
.toggle-btn:last-child  { border-radius: 0 0.5rem 0.5rem 0; }
.toggle-btn.active { background: var(--accent, #1a4d3e); color: white; border-color: var(--accent, #1a4d3e); }

.main-layout { display: grid; grid-template-columns: 1fr 380px; gap: 1.5rem; align-items: start; }
@media (max-width: 1024px) { .main-layout { grid-template-columns: 1fr; } }

.heatmap-section { overflow-x: auto; }

.detail-panel { display: flex; flex-direction: column; gap: 1rem; }

.detail-placeholder {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  height: 300px; color: #9ca3af; text-align: center; line-height: 1.6;
  border: 1px dashed var(--color-border, #e5e7eb); border-radius: 0.75rem;
}
.placeholder-icon { font-size: 2rem; margin-bottom: 0.75rem; }
</style>
