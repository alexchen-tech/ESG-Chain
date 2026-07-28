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
      <!-- 直列式：所有義務依序列出，各自一個區段，不需點擊切換 -->
      <div v-else class="obligations-list">
        <div v-for="ob in obligations" :key="ob.id" class="obligation-section">
          <div class="ob-top">
            <span class="ob-name">
              <span class="ob-tab-dot" :class="statusClass(ob.status)"></span>
              {{ ob.regulation_name }}
              <span v-if="ob.is_mandatory" class="ob-mandatory" title="強制文件">必要</span>
            </span>
            <span class="ob-status-chip" :class="statusClass(ob.status)">{{ statusLabel(ob.status) }}</span>
          </div>
          <div class="ob-doc-type text-muted font-mono">{{ ob.doc_type }}</div>

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
                :disabled="!sup.has_replacement_candidates"
                :title="sup.has_replacement_candidates ? '' : '同物料群組內查無其他核准供應商'"
                @click="requestReplacement(sup)">
                替代供應商
              </button>
            </div>
          </div>
          <div v-else class="ob-no-suppliers text-muted">此為產品層級義務，無特定負責供應商</div>
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
        const resp = await api.get(`/api/v1/trade-goods/${this.tradeGoodId}/compliance-gap`, {
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
      const docLabel = obligation.doc_type_label ?? obligation.doc_type
      try {
        const { data } = await api.post('/api/v1/caps', {
          supplier_id: supplier.id,
          source_type: 'compliance_doc',
          title: `補件：${docLabel}（${this.market} 市場）`,
          description: `${this.tradeGoodName || '—'} 出口至 ${this.market} 市場，缺少「${docLabel}」（${obligation.doc_type}）。供應商：${supplier.name}。`,
          priority: obligation.is_mandatory ? 'high' : 'medium',
        })
        this.$emit('create-cap', { supplierId: supplier.id, docType: obligation.doc_type, capId: data.data.id, capTitle: data.data.title })
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
  border: 1px solid var(--border, #e2ddd6);
  border-radius: 12px;
  overflow: hidden;
  background: var(--surface, #fff);
  box-shadow: 0 1px 2px rgba(28,25,23,0.04);
}
.panel-header {
  background: var(--accent, #1a4d3e);
  color: white;
  padding: 1.1rem 1.35rem;
}
.panel-title { font-weight: 700; font-size: 1.05rem; letter-spacing: 0.01em; }
.panel-meta  { font-size: 0.82rem; opacity: 0.75; margin-top: 0.25rem; }
.panel-loading, .panel-error, .panel-empty {
  padding: 2.5rem 1.5rem; text-align: center; color: var(--text-secondary, #a8a29e); font-size: 0.9rem; line-height: 1.6;
}
.panel-error { color: #c0392b; }

.obligations-list { display: flex; flex-direction: column; }

.obligation-section {
  padding: 1.15rem 1.35rem;
  border-bottom: 1px solid var(--border, #f0ede6);
  transition: background 0.12s;
}
.obligation-section:last-child { border-bottom: none; }
.obligation-section:hover { background: var(--surface-2, #faf9f6); }

.ob-tab-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.ob-tab-dot.status-valid     { background: #3f8a56; }
.ob-tab-dot.status-expiring  { background: #c6a13a; }
.ob-tab-dot.status-missing   { background: #d0524d; }

.ob-no-suppliers { font-size: 0.85rem; padding: 0.4rem 0 0; }
.ob-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.35rem; gap: 0.75rem; }
.ob-name { font-weight: 700; font-size: 0.98rem; color: var(--text-primary, #1c1917); display: flex; align-items: center; gap: 0.5rem; }
.ob-mandatory {
  font-size: 0.65rem; font-weight: 600; color: #9f2a26; background: #f6cbc9;
  padding: 2px 7px; border-radius: 9999px; letter-spacing: 0.02em;
}
.ob-doc-type { font-size: 0.74rem; margin-bottom: 0.85rem; letter-spacing: 0.02em; }
.text-muted { color: var(--text-secondary, #a8a29e); }

.ob-status-chip { font-size: 0.74rem; padding: 3px 11px; border-radius: 9999px; font-weight: 600; flex-shrink: 0; }
.status-valid     { background: #dcf1dc; color: #276b3a; }
.status-expiring  { background: #f5e7b8; color: #8a6d10; }
.status-missing   { background: #f6cbc9; color: #9f2a26; }

.suppliers-list { display: flex; flex-direction: column; gap: 0.5rem; }
.supplier-row {
  display: flex; align-items: center; gap: 0.65rem;
  padding: 0.6rem 0.8rem;
  background: var(--surface-2, #f7f5f1);
  border: 1px solid var(--border, #efece5);
  border-radius: 8px;
  transition: border-color 0.12s;
}
.supplier-row:hover { border-color: var(--border, #ddd7cc); }
.sup-name { flex: 1; font-size: 0.87rem; color: var(--text-primary, #1c1917); }

.axis-chip { font-size: 0.72rem; padding: 3px 10px; border-radius: 9999px; font-weight: 600; white-space: nowrap; }
.axis-extreme { background: #f6cbc9; color: #9f2a26; }
.axis-high    { background: #f8dcc0; color: #a15417; }
.axis-medium  { background: #f5e7b8; color: #8a6d10; }
.axis-low     { background: #cdeace; color: #276b3a; }
.axis-very-low{ background: #dcf1dc; color: #2f7a42; }

.btn-create-cap {
  font-size: 0.78rem; font-weight: 600; padding: 6px 13px;
  background: var(--accent, #1a4d3e); color: white;
  border: none; border-radius: 6px; cursor: pointer;
  white-space: nowrap; transition: background 0.12s, box-shadow 0.12s;
}
.btn-create-cap:disabled { opacity: 0.55; cursor: not-allowed; }
.btn-create-cap:hover:not(:disabled) { background: var(--accent-hover, #154033); box-shadow: 0 2px 6px rgba(26,77,62,0.25); }

.btn-replace {
  font-size: 0.78rem; font-weight: 600; padding: 6px 11px;
  background: var(--surface, white); color: var(--accent, #1a4d3e);
  border: 1px solid var(--accent, #1a4d3e); border-radius: 6px; cursor: pointer;
  white-space: nowrap; transition: background 0.12s;
}
.btn-replace:hover:not(:disabled) { background: rgba(26,77,62,0.07); }
.btn-replace:disabled {
  color: var(--text-secondary, #a8a29e); border-color: var(--border, #ddd7cc);
  cursor: not-allowed; opacity: 0.6;
}
</style>
