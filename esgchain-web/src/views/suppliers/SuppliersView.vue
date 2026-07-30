<template>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h1 class="page-title">供應商管理</h1>
        <p class="page-subtitle">共 {{ pagination.total }} 家供應商</p>
      </div>
      <div style="display:flex;gap:8px;">
        <button class="btn btn-secondary" @click="$router.push('/suppliers/import')">↑ 批次匯入</button>
      </div>
    </div>

    <!-- 風險矩陣篩選來源 chip -->
    <div v-if="riskFilter" class="risk-context-bar">
      <span class="risk-context-icon">⬡</span>
      <span class="risk-context-text">來自風險矩陣：<strong>{{ riskFilterLabel }}</strong></span>
      <button class="risk-context-clear" @click="clearRiskFilter" title="清除此篩選">×</button>
    </div>

    <!-- 篩選 -->
    <div class="filter-bar">
      <input v-model="filters.search" class="filter-input" placeholder="搜尋名稱或代碼..." @keyup.enter="resetAndLoad" style="width: 200px;" />
      <select v-model="filters.status" class="filter-select" @change="resetAndLoad">
        <option value="">所有狀態</option>
        <option value="active">啟用</option>
        <option value="suspended">暫停</option>
        <option value="terminated">終止</option>
      </select>
      <select v-model="filters.tier" class="filter-select" @change="resetAndLoad">
        <option value="">所有層級</option>
        <option value="1">Tier 1</option>
        <option value="2">Tier 2</option>
        <option value="3">Tier 3</option>
      </select>
      <select v-model="filters.country_code" class="filter-select" @change="resetAndLoad">
        <option value="">所有國家</option>
        <option v-for="c in countryOptions" :key="c.code" :value="c.code">{{ c.flag }} {{ c.label }}</option>
      </select>
      <select v-model="filters.supplier_group_id" class="filter-select" @change="resetAndLoad">
        <option value="">所有分組</option>
        <option v-for="g in supplierGroups" :key="g.id" :value="g.id">{{ g.name }}</option>
      </select>
      <button class="btn btn-secondary btn-sm" @click="resetAndLoad">搜尋</button>
      <button v-if="hasActiveFilters" class="btn btn-secondary btn-sm" @click="clearFilters">✕ 清除</button>
    </div>

    <!-- 表格 -->
    <div class="table-container">
      <div v-if="isLoading" class="loading-mask">載入中...</div>
      <div v-else-if="suppliers.length === 0" class="empty-state">
        <p>尚無符合條件的供應商</p>
      </div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th style="width:36px;padding:9px 8px;"></th>
            <th>供應商名稱</th>
            <th>代碼</th>
            <th>國家</th>
            <th>層級</th>
            <th>風險分數</th>
            <th>活躍狀態</th>
            <th style="width:80px;"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in suppliers" :key="s.id" class="clickable-row" @click="openDetail(s)">
            <td style="padding:11px 8px;" @click.stop>
              <input
                type="checkbox"
                class="compare-checkbox"
                :checked="selectedIds.includes(s.id)"
                :disabled="!canSelectMore(s)"
                :title="!canSelectMore(s) ? '最多比較 4 家' : ''"
                @change="toggleSelect(s)"
              />
            </td>
            <td>
              <div class="sup-name">{{ maskSupplierName(s.name) }}</div>
              <div class="sup-meta">{{ s.industry || '—' }}</div>
            </td>
            <td><span class="font-mono sup-code">{{ s.code || '—' }}</span></td>
            <td>
              <span class="country-cell">
                <span class="country-flag">{{ countryFlag(s.country_code) }}</span>
                <span class="country-code">{{ s.country_code || '—' }}</span>
              </span>
            </td>
            <td>
              <span class="tier-chip" :class="`tier-${s.tier}`">T{{ s.tier }}</span>
            </td>
            <td>
              <div class="risk-score-cell">
                <div class="risk-score-bar-wrap">
                  <div
                    class="risk-score-bar"
                    :class="riskBarClass(s.risk_score)"
                    :style="{ width: riskBarWidth(s.risk_score) }"
                  ></div>
                </div>
                <span class="risk-score-num">{{ riskScoreLabel(s.risk_score) }}</span>
              </div>
            </td>
            <td><span class="badge" :class="statusBadgeClass(s.onboarding_stage)">{{ statusLabel(s.onboarding_stage) }}</span></td>
            <td @click.stop>
              <div class="row-actions">
                <button class="row-action-btn" title="變更狀態" @click="openTransitionModal(s)">⇄</button>
                <button class="row-action-btn" title="詳情" @click="openDetail(s)">›</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 分頁 -->
    <div class="pagination">
      <span>第 {{ pagination.current_page }} / {{ pagination.last_page }} 頁</span>
      <button class="pg-btn" :disabled="pagination.current_page <= 1" @click="goPage(pagination.current_page - 1)">‹</button>
      <button class="pg-btn" :disabled="pagination.current_page >= pagination.last_page" @click="goPage(pagination.current_page + 1)">›</button>
    </div>

    <!-- 比較 Sticky Bar -->
    <div v-if="selectedIds.length >= 1" class="compare-sticky-bar">
      <span class="compare-sticky-label">已選 {{ selectedIds.length }} 家</span>
      <button class="btn btn-secondary btn-sm" @click="clearSelected">清除</button>
      <button
        class="btn btn-accent btn-sm"
        :disabled="selectedIds.length < 2"
        @click="openCompare"
      >開始比較 →</button>
    </div>

    <CompareModal v-if="showCompareModal" @close="showCompareModal = false" />

    <!-- 狀態流轉 Modal -->
    <div v-if="showTransitionModal" class="modal-overlay" @click.self="showTransitionModal = false">
      <div class="modal" style="min-width: 400px;">
        <div class="modal-header">
          <span class="modal-title">變更供應商狀態</span>
          <button class="modal-close" @click="showTransitionModal = false">×</button>
        </div>
        <p style="font-size: 14px; color: var(--text-secondary); margin-bottom: 16px;">
          {{ maskSupplierName(selectedSupplier?.name) }} 當前狀態：
          <span class="badge" :class="statusBadgeClass(selectedSupplier?.onboarding_stage || '')">
            {{ statusLabel(selectedSupplier?.onboarding_stage || '') }}
          </span>
        </p>
        <div class="form-group">
          <label class="form-label">新狀態</label>
          <select v-model="transitionForm.status" class="form-select" :disabled="!allowedNextStagesList.length">
            <option v-for="s in allowedNextStagesList" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
          <p v-if="!allowedNextStagesList.length" style="font-size:12px;color:#ef4444;margin-top:4px;">此狀態無可轉換的下一步</p>
        </div>
        <div class="form-group">
          <label class="form-label">原因（稽核日誌）</label>
          <textarea v-model="transitionForm.reason" class="form-textarea" placeholder="說明狀態變更原因..."></textarea>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showTransitionModal = false">取消</button>
          <button class="btn btn-primary" :disabled="isSubmitting" @click="doTransition">
            {{ isSubmitting ? '更新中...' : '確認更新' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { suppliersApi, supplierGroupsApi, type Supplier, type SupplierGroup } from '@/api/modules/suppliers'
import { useCompareStore } from '@/stores/compareStore'
import CompareModal from '@/components/CompareModal.vue'

const STATUS_LABELS: Record<string, string> = {
  active: '啟用', suspended: '暫停', terminated: '終止',
}

const ONBOARDING_TRANSITIONS: Record<string, string[]> = {
  active:     ['suspended'],
  suspended:  ['active', 'terminated'],
  terminated: [],
}

export default defineComponent({
  name: 'SuppliersView',
  components: { CompareModal },
  setup() { return { router: useRouter(), route: useRoute(), compareStore: useCompareStore() } },

  data() {
    return {
      isLoading: false,
      isSubmitting: false,
      suppliers: [] as Supplier[],
      supplierGroups: [] as SupplierGroup[],
      pagination: { current_page: 1, per_page: 20, total: 0, last_page: 1 },
      filters: { search: '', status: '', tier: '', country_code: '', supplier_group_id: '' },
      riskFilter: null as { dim: string; probability: string; impact: string } | null,
      selectedIds: [] as string[],
      showCompareModal: false,
      showTransitionModal: false,
      selectedSupplier: null as Supplier | null,
      AXIS_TITLES: { axis1: 'ESG揭露', axis2: '治理成熟度', axis3: '地緣產業' } as Record<string, string>,
      transitionForm: { status: 'suspended', reason: '' },
    }
  },

  computed: {
    allowedNextStagesList(): Array<{ value: string; label: string }> {
      const current = this.selectedSupplier?.onboarding_stage ?? ''
      return (ONBOARDING_TRANSITIONS[current] ?? []).map(s => ({ value: s, label: STATUS_LABELS[s] ?? s }))
    },
    hasActiveFilters(): boolean {
      const f = this.filters
      return !!(f.search || f.status || f.tier || f.country_code || f.supplier_group_id || this.riskFilter)
    },
    riskFilterLabel(): string {
      if (!this.riskFilter) return ''
      const DIM_LABELS: Record<string, string> = { E: 'E 環境', S: 'S 社會', G: 'G 治理', GP: 'GP 地緣政治' }
      return `${DIM_LABELS[this.riskFilter.dim] ?? this.riskFilter.dim} · P=${this.riskFilter.probability} × I=${this.riskFilter.impact}`
    },
    countryOptions(): { code: string; label: string; flag: string }[] {
      const countryMap: Record<string, { label: string; flag: string }> = {
        TW: { label: '台灣', flag: '🇹🇼' }, CN: { label: '中國', flag: '🇨🇳' }, JP: { label: '日本', flag: '🇯🇵' },
        KR: { label: '韓國', flag: '🇰🇷' }, VN: { label: '越南', flag: '🇻🇳' }, TH: { label: '泰國', flag: '🇹🇭' },
        MY: { label: '馬來西亞', flag: '🇲🇾' }, SG: { label: '新加坡', flag: '🇸🇬' }, PH: { label: '菲律賓', flag: '🇵🇭' },
        ID: { label: '印尼', flag: '🇮🇩' }, IN: { label: '印度', flag: '🇮🇳' }, AU: { label: '澳洲', flag: '🇦🇺' },
        US: { label: '美國', flag: '🇺🇸' }, DE: { label: '德國', flag: '🇩🇪' }, GB: { label: '英國', flag: '🇬🇧' },
        FR: { label: '法國', flag: '🇫🇷' }, IT: { label: '義大利', flag: '🇮🇹' }, NL: { label: '荷蘭', flag: '🇳🇱' },
        PL: { label: '波蘭', flag: '🇵🇱' }, CZ: { label: '捷克', flag: '🇨🇿' }, CH: { label: '瑞士', flag: '🇨🇭' },
        RU: { label: '俄羅斯', flag: '🇷🇺' }, TR: { label: '土耳其', flag: '🇹🇷' }, CA: { label: '加拿大', flag: '🇨🇦' },
        MX: { label: '墨西哥', flag: '🇲🇽' }, BR: { label: '巴西', flag: '🇧🇷' }, CL: { label: '智利', flag: '🇨🇱' },
        HK: { label: '香港', flag: '🇭🇰' }, ZA: { label: '南非', flag: '🇿🇦' }, NG: { label: '奈及利亞', flag: '🇳🇬' },
        EG: { label: '埃及', flag: '🇪🇬' }, MA: { label: '摩洛哥', flag: '🇲🇦' }, LK: { label: '斯里蘭卡', flag: '🇱🇰' },
      }
      const codes = [...new Set(this.suppliers.map(s => s.country_code).filter(Boolean) as string[])]
        .concat(Object.keys(countryMap))
      const unique = [...new Set(codes)]
      return unique
        .filter(c => countryMap[c])
        .map(c => ({ code: c, label: countryMap[c]!.label, flag: countryMap[c]!.flag }))
        .sort((a, b) => a.label.localeCompare(b.label, 'zh-TW'))
    },
  },

  mounted() {
    const q = this.route.query
    if (q.risk_dim && q.risk_probability && q.risk_impact) {
      this.riskFilter = {
        dim: String(q.risk_dim),
        probability: String(q.risk_probability),
        impact: String(q.risk_impact),
      }
    }
    // 與 compareStore 同步 selectedIds
    this.selectedIds = this.compareStore.suppliers.map(s => s.id)
    this.loadData()
    this.loadGroups()
  },

  methods: {
    async loadGroups() {
      try {
        const { data } = await supplierGroupsApi.list()
        this.supplierGroups = data.data
      } catch { /**/ }
    },

    async loadData() {
      this.isLoading = true
      try {
        const { data } = await suppliersApi.list({
          page: this.pagination.current_page,
          per_page: this.pagination.per_page,
          q: this.filters.search || undefined,
          onboarding_stage: this.filters.status || undefined,
          tier: this.filters.tier ? Number(this.filters.tier) : undefined,
          country_code: this.filters.country_code || undefined,
          supplier_group_id: this.filters.supplier_group_id || undefined,
          risk_dim: this.riskFilter?.dim || undefined,
          risk_probability: this.riskFilter?.probability || undefined,
          risk_impact: this.riskFilter?.impact || undefined,
        })
        this.suppliers = data.data
        this.pagination = data.pagination
      } finally { this.isLoading = false }
    },

    resetAndLoad() { this.pagination.current_page = 1; this.loadData() },

    clearFilters() {
      this.filters = { search: '', status: '', tier: '', country_code: '', supplier_group_id: '' }
      this.riskFilter = null
      this.router.replace({ query: {} })
      this.resetAndLoad()
    },
    clearRiskFilter() {
      this.riskFilter = null
      this.router.replace({ query: {} })
      this.resetAndLoad()
    },

    goPage(page: number) { this.pagination.current_page = page; this.loadData() },
    openTransitionModal(s: Supplier) {
      this.selectedSupplier = s
      const next = (ONBOARDING_TRANSITIONS[s.onboarding_stage ?? ''] ?? [])[0] ?? ''
      this.transitionForm = { status: next, reason: '' }
      this.showTransitionModal = true
    },
    openDetail(s: Supplier) { this.router.push(`/suppliers/${s.id}`) },
    statusLabel(status: string) { return STATUS_LABELS[status] ?? status },
    statusBadgeClass(status: string) {
      const map: Record<string, string> = {
        active: 'badge-green', suspended: 'badge-yellow', terminated: 'badge-red',
      }
      return map[status] ?? 'badge-gray'
    },
    axisChipClass(level: string | null): string {
      const map: Record<string, string> = {
        extreme: 'axis-chip-extreme', high: 'axis-chip-high', medium: 'axis-chip-medium',
        low: 'axis-chip-low', very_low: 'axis-chip-very-low',
      }
      return level ? (map[level] ?? '') : 'axis-chip-empty'
    },
    riskBarClass(score: string | number | null): string {
      const v = parseFloat(String(score ?? 0))
      if (v >= 0.8) return 'rs-extreme'   // ≥20/25
      if (v >= 0.6) return 'rs-high'      // ≥15/25
      if (v >= 0.4) return 'rs-medium'    // ≥10/25
      return 'rs-low'
    },
    riskBarWidth(score: string | number | null): string {
      const v = parseFloat(String(score ?? 0))
      return Math.round(v * 100) + '%'
    },
    riskScoreLabel(score: string | number | null): string {
      if (score == null || score === '') return '—'
      return Math.round(parseFloat(String(score)) * 100) + '%'
    },
    countryFlag(code: string | null): string {
      if (!code) return ''
      const flags: Record<string, string> = {
        TW:'🇹🇼', CN:'🇨🇳', VN:'🇻🇳', TH:'🇹🇭', IN:'🇮🇳', ID:'🇮🇩',
        MY:'🇲🇾', KR:'🇰🇷', JP:'🇯🇵', BD:'🇧🇩', PK:'🇵🇰', US:'🇺🇸',
        DE:'🇩🇪', CH:'🇨🇭', HK:'🇭🇰', KH:'🇰🇭', ET:'🇪🇹', LK:'🇱🇰', MM:'🇲🇲',
      }
      return flags[code] ?? '🏳'
    },
    async doTransition() {
      if (!this.selectedSupplier) return
      this.isSubmitting = true
      try {
        await suppliersApi.transitionOnboarding(this.selectedSupplier.id, this.transitionForm.status, this.transitionForm.reason)
        this.showTransitionModal = false
        this.loadData()
      } finally { this.isSubmitting = false }
    },

    // ── 比較功能 ──────────────────────────────────────────────
    toggleSelect(s: Supplier) {
      if (this.selectedIds.includes(s.id)) {
        this.selectedIds = this.selectedIds.filter(id => id !== s.id)
        this.compareStore.remove(s.id)
      } else {
        if (!this.compareStore.canAdd) return
        this.selectedIds.push(s.id)
        this.compareStore.add(s)
      }
    },
    canSelectMore(s: Supplier): boolean {
      return this.selectedIds.includes(s.id) || this.compareStore.canAdd
    },
    openCompare() {
      // 確保 compareStore 與 selectedIds 同步
      this.suppliers
        .filter(s => this.selectedIds.includes(s.id))
        .forEach(s => this.compareStore.add(s))
      this.showCompareModal = true
    },
    clearSelected() {
      this.selectedIds = []
      this.compareStore.clear()
    },
  },
})
</script>

<style scoped>
/* 表格儲存格 */
.sup-name { font-weight: 600; font-size: 13.5px; line-height: 1.3; }
.sup-meta { font-size: 12px; color: var(--text-secondary); margin-top: 1px; }
.sup-code { font-size: 12.5px; color: var(--text-secondary); }

.country-cell { display: inline-flex; align-items: center; gap: 5px; }
.country-flag { font-size: 15px; line-height: 1; }
.country-code { font-size: 12.5px; font-weight: 600; color: var(--text-secondary); }

.tier-chip {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 5px;
  font-size: 11.5px;
  font-weight: 700;
  letter-spacing: 0.03em;
}
.tier-1 { background: #e0f2fe; color: #0369a1; }
.tier-2 { background: #f0fdf4; color: #166534; }
.tier-3 { background: #f5f3ee; color: #78716c; border: 1px solid var(--border); }

.row-actions { display: flex; gap: 4px; }
.row-action-btn {
  width: 28px; height: 28px;
  display: flex; align-items: center; justify-content: center;
  background: none;
  border: 1px solid var(--border);
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  color: var(--text-secondary);
  transition: background 0.1s, color 0.1s;
}
.row-action-btn:hover { background: var(--surface-2); color: var(--text-primary); }

.compare-checkbox { width: 15px; height: 15px; cursor: pointer; accent-color: var(--accent); }
.compare-checkbox:disabled { cursor: not-allowed; opacity: .4; }

.compare-sticky-bar {
  position: fixed;
  bottom: 24px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 11px 20px;
  background: #1a1714;
  color: #fff;
  border-radius: 999px;
  box-shadow: 0 8px 30px rgba(0,0,0,.25);
  z-index: 200;
  white-space: nowrap;
}
.compare-sticky-label { font-size: 14px; font-weight: 600; }

.risk-context-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  margin-bottom: 12px;
  background: #eff4ff;
  border: 1px solid #bfdbfe;
  border-radius: 7px;
  font-size: 13px;
  color: #1e40af;
}
.risk-context-icon { font-size: 15px; flex-shrink: 0; }
.risk-context-text { flex: 1; }
.risk-context-clear {
  background: none;
  border: none;
  font-size: 16px;
  cursor: pointer;
  color: #6b7280;
  line-height: 1;
  padding: 0 2px;
  flex-shrink: 0;
}
.risk-context-clear:hover { color: #1e40af; }

.axis-chips-row { display: flex; gap: 3px; }
.axis-mini-chip {
  width: 22px; height: 22px; border-radius: 4px; display: flex; align-items: center;
  justify-content: center; font-size: 0.65rem; font-weight: 600; cursor: default;
}
.axis-chip-extreme { background: #fee2e2; color: #b91c1c; }
.axis-chip-high    { background: #ffedd5; color: #c2410c; }
.axis-chip-medium  { background: #fef9c3; color: #a16207; }
.axis-chip-low     { background: #dcfce7; color: #15803d; }
.axis-chip-very-low{ background: #f0fdf4; color: #166534; }
.axis-chip-empty   { background: #f3f4f6; color: #9ca3af; }
</style>
