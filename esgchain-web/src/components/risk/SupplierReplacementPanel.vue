<template>
  <div class="replacement-panel">
    <div class="panel-header">
      <div>
        <div class="panel-title">替換供應商推薦</div>
        <div class="panel-meta">替換：{{ supplierName }}</div>
      </div>
      <button class="close-btn" @click="$emit('close')">✕</button>
    </div>

    <div class="disclaimer">
      以下候選名單依 ESG 揭露風險改善幅度排序，僅供決策參考，實際採購請依採購流程執行。
    </div>

    <div v-if="loading" class="panel-loading">計算推薦候選中...</div>
    <div v-else-if="error" class="panel-error">{{ error }}</div>
    <div v-else-if="candidates.length === 0" class="panel-empty">未找到符合條件的替換候選</div>

    <div v-else class="candidates-list">
      <div v-for="c in candidates" :key="c.supplier_id"
        class="candidate-row"
        :class="{ 'already-in-chain': c.already_in_supply_chain }"
        @click="openDetail(c.supplier_id)">

        <div class="candidate-left">
          <div class="candidate-name">
            {{ c.supplier_name }}
            <span v-if="c.already_in_supply_chain" class="in-chain-badge">已在供應鏈</span>
          </div>
          <div class="candidate-meta">
            <span class="country-flag">{{ countryFlag(c.country_code) }}</span>
            <span class="country-code">{{ c.country_code }}</span>
          </div>
        </div>

        <div class="candidate-right">
          <span class="esg-chip" :class="axisClass(c.esg_level)">
            ESG: {{ LEVEL_LABEL[c.esg_level] ?? c.esg_level ?? '—' }}
          </span>
          <span v-if="c.improvement_pct != null" class="improve-pct">
            ↓{{ c.improvement_pct.toFixed(1) }}%
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import api from '@/api/http'

const LEVEL_LABEL = { extreme: '極高', high: '高', medium: '中', low: '低', very_low: '極低' }
const AXIS_CLASS  = {
  extreme: 'axis-extreme', high: 'axis-high', medium: 'axis-medium',
  low: 'axis-low', very_low: 'axis-very-low',
}

export default {
  name: 'SupplierReplacementPanel',
  emits: ['close'],

  props: {
    supplierId:   { type: String, required: true },
    supplierName: { type: String, default: '' },
    tradeGoodId:  { type: String, required: true },
  },

  data() {
    return {
      loading: false,
      error: null,
      candidates: [],
      LEVEL_LABEL,
    }
  },

  mounted() {
    this.loadCandidates()
  },

  methods: {
    async loadCandidates() {
      this.loading = true
      this.error = null
      try {
        const resp = await api.post('/supplier-replacement/candidates', {
          supplier_id:  this.supplierId,
          trade_good_id: this.tradeGoodId,
        })
        this.candidates = resp.data.candidates ?? []
      } catch {
        this.error = '無法載入替換推薦候選'
      } finally {
        this.loading = false
      }
    },

    axisClass(level) { return AXIS_CLASS[level] ?? '' },

    countryFlag(code) {
      const flags = {
        TW:'🇹🇼', CN:'🇨🇳', VN:'🇻🇳', TH:'🇹🇭', IN:'🇮🇳', ID:'🇮🇩',
        MY:'🇲🇾', KR:'🇰🇷', JP:'🇯🇵', BD:'🇧🇩', PK:'🇵🇰', US:'🇺🇸',
        DE:'🇩🇪', CH:'🇨🇭', HK:'🇭🇰', KH:'🇰🇭', ET:'🇪🇹', LK:'🇱🇰', MM:'🇲🇲',
      }
      return flags[code] ?? '🏳'
    },

    openDetail(supplierId) {
      this.$router.push({ name: 'supplier-detail', params: { id: supplierId } })
    },
  },
}
</script>

<style scoped>
.replacement-panel {
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 0.75rem;
  overflow: hidden;
}

.panel-header {
  display: flex; align-items: flex-start; justify-content: space-between;
  background: #1e3a5f; color: white; padding: 0.75rem 1rem;
}
.panel-title { font-weight: 600; font-size: 0.9rem; }
.panel-meta  { font-size: 0.75rem; opacity: 0.8; margin-top: 0.125rem; }
.close-btn {
  background: none; border: none; color: white; opacity: 0.7;
  cursor: pointer; font-size: 1rem; padding: 0; flex-shrink: 0;
}
.close-btn:hover { opacity: 1; }

.disclaimer {
  font-size: 0.72rem; color: #6b7280; background: #fffbeb;
  padding: 0.5rem 1rem; border-bottom: 1px solid #fef3c7;
}

.panel-loading, .panel-error, .panel-empty {
  padding: 1.5rem; text-align: center; color: #9ca3af; font-size: 0.85rem;
}
.panel-error { color: #ef4444; }

.candidates-list { padding: 0.75rem; display: flex; flex-direction: column; gap: 0.5rem; }

.candidate-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border, #f3f4f6);
  border-radius: 0.5rem;
  cursor: pointer;
  transition: background 0.1s;
}
.candidate-row:hover { background: #f9fafb; }
.already-in-chain { background: #f0fdf4; border-color: #bbf7d0; }

.candidate-left { flex: 1; }
.candidate-name { font-weight: 500; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem; }
.in-chain-badge {
  font-size: 0.65rem; background: #dcfce7; color: #15803d;
  padding: 1px 6px; border-radius: 9999px;
}
.candidate-meta { display: flex; align-items: center; gap: 0.25rem; font-size: 0.75rem; color: #6b7280; margin-top: 0.125rem; }

.candidate-right { display: flex; align-items: center; gap: 0.5rem; }

.esg-chip { font-size: 0.68rem; padding: 2px 8px; border-radius: 9999px; font-weight: 500; white-space: nowrap; }
.axis-extreme { background: #fee2e2; color: #b91c1c; }
.axis-high    { background: #ffedd5; color: #c2410c; }
.axis-medium  { background: #fef9c3; color: #a16207; }
.axis-low     { background: #dcfce7; color: #15803d; }
.axis-very-low{ background: #f0fdf4; color: #166534; }

.improve-pct {
  font-size: 0.8rem; font-weight: 700; color: #15803d;
  font-family: monospace; white-space: nowrap;
}
</style>
