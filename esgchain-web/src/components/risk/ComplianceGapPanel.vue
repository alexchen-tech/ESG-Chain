<template>
  <div class="gap-panel">
    <div class="panel-header">
      <div class="panel-title">義務缺口明細</div>
      <div class="panel-meta">{{ tradeGoodName || '—' }} → {{ market }}</div>
    </div>

    <div v-if="loading" class="panel-loading">載入中...</div>
    <div v-else-if="error" class="panel-error">{{ error }}</div>
    <template v-else>
      <!-- 法規義務清單 -->
      <div v-if="obligations.length === 0" class="panel-empty">
        此商品市場組合無義務缺口
      </div>
      <div v-else class="obligations-list">
        <div v-for="ob in obligations" :key="ob.id" class="obligation-row">
          <div class="ob-top">
            <span class="ob-name">{{ ob.regulation_name }}</span>
            <span class="ob-status-chip" :class="statusClass(ob.status)">{{ statusLabel(ob.status) }}</span>
          </div>
          <div class="ob-doc-type text-muted">{{ ob.doc_type_label ?? ob.doc_type }}</div>

          <!-- 責任供應商清單 -->
          <div class="suppliers-list" v-if="ob.responsible_suppliers?.length">
            <div v-for="sup in ob.responsible_suppliers" :key="sup.id" class="supplier-row">
              <span class="sup-name">{{ sup.name }}</span>
              <span class="axis-chip" :class="axisClass(sup.axis1_level)"
                :title="`ESG揭露：${sup.axis1_score ?? '—'}`">
                E: {{ sup.axis1_level ? LEVEL_LABEL[sup.axis1_level] ?? sup.axis1_level : '—' }}
              </span>
              <button
                v-if="ob.status !== 'valid'"
                class="btn-create-cap"
                :disabled="creatingCap === sup.id + ob.doc_type"
                @click="createCap(sup, ob)">
                {{ creatingCap === sup.id + ob.doc_type ? '建立中...' : '補文件' }}
              </button>
              <button
                class="btn-replace"
                @click="requestReplacement(sup)">
                換供應商
              </button>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import api from '@/api/http'

const STATUS_LABEL = { valid: '合規', expiring_soon: '即將到期', missing: '缺文件' }
const STATUS_CLASS  = { valid: 'status-valid', expiring_soon: 'status-expiring', missing: 'status-missing' }
const AXIS_CLASS    = {
  extreme: 'axis-extreme', high: 'axis-high', medium: 'axis-medium',
  low: 'axis-low', very_low: 'axis-very-low',
}
const LEVEL_LABEL   = { extreme: '極高', high: '高', medium: '中', low: '低', very_low: '極低' }

export default {
  name: 'ComplianceGapPanel',
  emits: ['create-cap', 'request-replacement'],

  props: {
    tradeGoodId: { type: String, required: true },
    market:      { type: String, required: true },
    tradeGoodName: { type: String, default: '' },
  },

  data() {
    return {
      loading: false,
      error: null,
      obligations: [],
      creatingCap: null,
      LEVEL_LABEL,
    }
  },

  watch: {
    tradeGoodId: 'loadData',
    market:      'loadData',
  },

  mounted() {
    this.loadData()
  },

  methods: {
    async loadData() {
      this.loading = true
      this.error = null
      try {
        const resp = await api.get(`/trade-goods/${this.tradeGoodId}/compliance-gap`, {
          params: { market: this.market },
        })
        this.obligations = resp.data.obligations ?? []
      } catch {
        this.error = '無法載入義務缺口資料'
      } finally {
        this.loading = false
      }
    },

    statusLabel(s) { return STATUS_LABEL[s] ?? s },
    statusClass(s) { return STATUS_CLASS[s] ?? '' },
    axisClass(level) { return AXIS_CLASS[level] ?? '' },

    async createCap(supplier, obligation) {
      const key = supplier.id + obligation.doc_type
      this.creatingCap = key
      try {
        await api.post('/cap', {
          supplier_id:  supplier.id,
          source_type:  'compliance_doc_gap',
          doc_type:     obligation.doc_type,
          market:       this.market,
          trade_good_id: this.tradeGoodId,
          regulation:   obligation.regulation_name,
        })
        this.$emit('create-cap', { supplierId: supplier.id, docType: obligation.doc_type })
        alert(`已為 ${supplier.name} 建立「${obligation.doc_type_label ?? obligation.doc_type}」補件 CAP`)
      } catch {
        alert('建立 CAP 失敗，請稍後再試')
      } finally {
        this.creatingCap = null
      }
    },

    requestReplacement(supplier) {
      this.$emit('request-replacement', { supplierId: supplier.id, supplierName: supplier.name })
    },
  },
}
</script>

<style scoped>
.gap-panel {
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 0.75rem;
  overflow: hidden;
}
.panel-header {
  background: var(--accent, #1a4d3e);
  color: white;
  padding: 0.75rem 1rem;
}
.panel-title { font-weight: 600; font-size: 0.9rem; }
.panel-meta  { font-size: 0.75rem; opacity: 0.8; margin-top: 0.125rem; }
.panel-loading, .panel-error, .panel-empty {
  padding: 1.5rem; text-align: center; color: #9ca3af; font-size: 0.85rem;
}
.panel-error { color: #ef4444; }

.obligations-list { padding: 0.75rem; display: flex; flex-direction: column; gap: 0.75rem; }

.obligation-row {
  border: 1px solid var(--color-border, #f3f4f6);
  border-radius: 0.5rem;
  padding: 0.75rem;
}
.ob-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.25rem; }
.ob-name { font-weight: 600; font-size: 0.85rem; }
.ob-doc-type { font-size: 0.75rem; margin-bottom: 0.5rem; }
.text-muted { color: #9ca3af; }

.ob-status-chip { font-size: 0.68rem; padding: 2px 8px; border-radius: 9999px; font-weight: 500; }
.status-valid     { background: #d1fae5; color: #065f46; }
.status-expiring  { background: #fef9c3; color: #a16207; }
.status-missing   { background: #fee2e2; color: #b91c1c; }

.suppliers-list { display: flex; flex-direction: column; gap: 0.375rem; }
.supplier-row {
  display: flex; align-items: center; gap: 0.5rem;
  padding: 0.375rem 0.5rem;
  background: #f9fafb; border-radius: 0.375rem;
}
.sup-name { flex: 1; font-size: 0.8rem; }

.axis-chip { font-size: 0.68rem; padding: 2px 8px; border-radius: 9999px; font-weight: 500; white-space: nowrap; }
.axis-extreme { background: #fee2e2; color: #b91c1c; }
.axis-high    { background: #ffedd5; color: #c2410c; }
.axis-medium  { background: #fef9c3; color: #a16207; }
.axis-low     { background: #dcfce7; color: #15803d; }
.axis-very-low{ background: #f0fdf4; color: #166534; }

.btn-create-cap {
  font-size: 0.75rem; padding: 3px 10px;
  background: var(--accent, #1a4d3e); color: white;
  border: none; border-radius: 0.375rem; cursor: pointer;
  white-space: nowrap;
}
.btn-create-cap:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-create-cap:hover:not(:disabled) { opacity: 0.85; }

.btn-replace {
  font-size: 0.72rem; padding: 3px 8px;
  background: white; color: var(--accent, #1a4d3e);
  border: 1px solid var(--accent, #1a4d3e); border-radius: 0.375rem; cursor: pointer;
  white-space: nowrap;
}
.btn-replace:hover { background: #f0fdf4; }
</style>
