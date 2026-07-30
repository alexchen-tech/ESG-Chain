<template>
  <div class="drawer-overlay" @click="$emit('close')"></div>
  <div class="drawer">
    <div class="drawer-header">
      <div>
        <span class="drawer-title">替代供應商</span>
        <div class="drawer-subtitle">換供應商對象：{{ maskSupplierName(supplierName) }}</div>
      </div>
      <button class="drawer-close" @click="$emit('close')">×</button>
    </div>

    <div class="disclaimer">
      推薦僅基於 ESG 風險評估，不含交期、價格、產能等商業因素。實際換源需透過 ERP 採購流程執行。
    </div>

    <div v-if="loading" class="drawer-body">
      <div class="skeleton-row" v-for="i in 3" :key="i"></div>
    </div>
    <div v-else-if="error" class="drawer-empty">{{ error }}</div>
    <div v-else-if="candidates.length === 0" class="drawer-empty">
      系統內無符合條件的替換候選。可透過 ERP 引入新供應商後，完成 SAQ 評估即可出現於此清單。
    </div>

    <div v-else class="drawer-body">
      <div v-for="c in candidates" :key="c.supplier_id"
        class="candidate-row"
        :class="{ 'already-in-chain': c.already_in_supply_chain }"
        @click="openDetail(c.supplier_id)">

        <div class="candidate-info">
          <div class="candidate-name">
            {{ maskSupplierName(c.name) }}
            <span class="country-badge">{{ countryFlag(c.country_code) }} {{ c.country_code }}</span>
            <span v-if="c.already_in_supply_chain" class="in-chain-badge">已在供應鏈</span>
          </div>
        </div>

        <div class="candidate-right">
          <span class="esg-chip" :class="axisClass(scoreToLevel(c.axis1_score))">
            ESG: {{ LEVEL_LABEL[scoreToLevel(c.axis1_score)] ?? '—' }}
          </span>
          <div v-if="c.improvement_pct != null" class="improve-pct">
            碳排 ↓{{ Number(c.improvement_pct).toFixed(1) }}%
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import api from '@/api/http'
import { maskSupplierName } from '@/utils/maskName'

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
    market:       { type: String, required: true },
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
        const resp = await api.post('/api/v1/supplier-replacement/candidates', {
          trade_good_id:       this.tradeGoodId,
          market:              this.market,
          replace_supplier_id: this.supplierId,
        })
        this.candidates = resp.data.candidates ?? []
        if (resp.data.message && this.candidates.length === 0) this.error = resp.data.message
      } catch {
        this.error = '無法載入替換推薦候選'
      } finally {
        this.loading = false
      }
    },

    maskSupplierName,
    axisClass(level) { return AXIS_CLASS[level] ?? '' },

    // axis1_score（ESG 揭露風險分數，越高越好）轉五級，與義務缺口面板一致
    scoreToLevel(score) {
      if (score == null) return null
      if (score >= 80) return 'very_low'
      if (score >= 60) return 'low'
      if (score >= 40) return 'medium'
      if (score >= 20) return 'high'
      return 'extreme'
    },

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
/* 與 MaterialComplianceView 的下鑽側欄一致的抽屜樣式 */
.drawer-overlay {
  position: fixed; inset: 0; background: rgba(28,25,23,0.32); z-index: 100;
}
.drawer {
  position: fixed; top: 0; right: 0; width: 420px; max-width: 100vw; height: 100vh;
  background: var(--surface, #fff); border-left: 1px solid var(--border, #e2ddd6);
  z-index: 101; display: flex; flex-direction: column; box-shadow: -8px 0 32px rgba(28,25,23,0.14);
}
.drawer-header {
  display: flex; align-items: flex-start; justify-content: space-between;
  padding: 1.1rem 1.35rem;
  background: var(--accent, #1a4d3e); color: white;
}
.drawer-title { font-size: 1.05rem; font-weight: 700; letter-spacing: 0.01em; }
.drawer-subtitle { font-size: 0.82rem; opacity: 0.75; margin-top: 0.25rem; }
.drawer-close {
  background: none; border: none; font-size: 1.15rem; cursor: pointer;
  color: white; opacity: 0.75; line-height: 1; padding: 0 4px; transition: opacity 0.12s;
}
.drawer-close:hover { opacity: 1; }

.disclaimer {
  font-size: 0.78rem; color: var(--text-secondary, #78716c); background: var(--surface-2, #f7f5f1);
  padding: 0.65rem 1.35rem; border-bottom: 1px solid var(--border, #e2ddd6); line-height: 1.5;
}

.drawer-body { flex: 1; overflow-y: auto; padding: 0.6rem 0; }
.drawer-empty {
  padding: 2.5rem 1.35rem; text-align: center; color: var(--text-secondary, #a8a29e);
  font-size: 0.88rem; line-height: 1.6;
}

.skeleton-row {
  height: 52px; margin: 8px 20px; border-radius: 8px;
  background: linear-gradient(90deg, var(--surface-2, #f0ede6) 25%, var(--border, #e2ddd6) 37%, var(--surface-2, #f0ede6) 63%);
  background-size: 400% 100%;
  animation: skeleton-shimmer 1.4s ease infinite;
}
@keyframes skeleton-shimmer {
  0% { background-position: 100% 50%; }
  100% { background-position: 0 50%; }
}

.candidate-row {
  display: flex; align-items: flex-start; justify-content: space-between;
  margin: 0.4rem 0.85rem; padding: 0.7rem 0.85rem;
  border: 1px solid var(--border, #efece5); border-radius: 8px; gap: 10px;
  cursor: pointer; transition: border-color 0.12s, background 0.12s;
}
.candidate-row:hover { background: var(--surface-2, #faf9f6); border-color: var(--border, #ddd7cc); }
.already-in-chain { background: #f2f8f3; border-color: #cdeace; }

.candidate-info { flex: 1; min-width: 0; }
.candidate-name { font-size: 0.88rem; font-weight: 700; color: var(--text-primary, #1c1917); display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.country-badge {
  font-size: 0.7rem; font-weight: 500; color: var(--text-secondary, #78716c);
  background: var(--surface-2, #f0ede6); border: 1px solid var(--border, #e2ddd6);
  padding: 1px 6px; border-radius: 4px; white-space: nowrap;
}
.in-chain-badge {
  font-size: 0.65rem; background: #dcf1dc; color: #276b3a;
  padding: 1px 7px; border-radius: 10px; font-weight: 600;
}

.candidate-right { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex-shrink: 0; }

.esg-chip { font-size: 0.72rem; padding: 3px 10px; border-radius: 99px; font-weight: 600; white-space: nowrap; }
.axis-extreme { background: #f6cbc9; color: #9f2a26; }
.axis-high    { background: #f8dcc0; color: #a15417; }
.axis-medium  { background: #f5e7b8; color: #8a6d10; }
.axis-low     { background: #cdeace; color: #276b3a; }
.axis-very-low{ background: #dcf1dc; color: #2f7a42; }

.improve-pct {
  font-size: 0.76rem; font-weight: 700; color: #2f7a42;
  font-family: var(--font-mono, 'Fira Code', monospace); white-space: nowrap;
}
</style>
