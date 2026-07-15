<template>
  <div class="heatmap-view">
    <!-- 頁首 -->
    <div class="page-header">
      <div>
        <h1>永續風險概覽</h1>
        <p class="page-desc">供應商 E1–E6 六維度風險評分，紅色代表低於警戒閾值</p>
      </div>
      <div class="header-actions">
        <template v-if="activeTab === 'heatmap'">
          <span class="summary-badge" v-if="summary">
            {{ summary.any_dim_critical }} / {{ summary.total }} 家需關注
          </span>

          <!-- 顯示模式（歷史對比） -->
          <div class="mode-group">
            <button
              v-for="m in diffModes"
              :key="m.value"
              class="mode-btn"
              :class="{ active: diffMode === m.value, loading: m.value !== 'latest' && diffLoading && diffMode === m.value }"
              @click="setDiffMode(m.value)"
            >{{ m.label }}</button>
          </div>

          <!-- 比較模式切換 -->
          <button
            class="btn-secondary"
            :class="{ 'btn-active': compareMode }"
            @click="toggleCompareMode"
          >比較模式</button>

          <button class="btn-secondary" @click="showCalcModal = true">計算方式說明</button>

          <button class="btn-secondary" @click="loadData" :disabled="loading">
            <span v-if="loading">載入中...</span>
            <span v-else>重新整理</span>
          </button>
        </template>

        <template v-else>
          <button class="btn-secondary" @click="showMatrixCalcModal = true">計算方式說明</button>
        </template>
      </div>
    </div>

    <!-- 計算方式說明 Modal -->
    <Teleport to="body">
      <div v-if="showCalcModal" class="modal-overlay" @click.self="showCalcModal = false">
        <div class="modal-box">
          <div class="modal-header">
            <span class="modal-title">六維熱圖計算說明</span>
            <button class="modal-close" @click="showCalcModal = false">✕</button>
          </div>
          <div class="modal-body">
            <div class="impact-ref__grid">
              <div class="impact-ref__col">
                <div class="impact-ref__dim-label">維度合規分（E1–E6）</div>
                <code class="impact-ref__formula">dim_score = Σ(raw × tag_weight) / Σ(tag_weight) × 100</code>
                <div class="impact-ref__note">raw：題目原始得分；tag_weight：問卷標籤維度權重</div>
                <div class="impact-ref__note">一題可跨多個維度（多對多標籤），分數 0–100，越高越安全</div>
              </div>
              <div class="impact-ref__col">
                <div class="impact-ref__dim-label">警戒閾值</div>
                <code class="impact-ref__formula">E1≤40 / E2≤45 / E3≤40 / E4≤35 / E5≤40 / E6≤50</code>
                <div class="impact-ref__note">低於閾值的格子以紅色標示，代表該維度需要關注</div>
                <div class="impact-ref__note">E6（法規準備）null 且有適用法規時標記為「資料缺口」</div>
              </div>
              <div class="impact-ref__col">
                <div class="impact-ref__dim-label">六維分布</div>
                <code class="impact-ref__formula">E1 環境管理　E2 氣候與碳排　E3 社會責任</code>
                <code class="impact-ref__formula">E4 地緣風險　E5 公司治理　E6 法規準備</code>
                <div class="impact-ref__note">可依任一維度排序，快速找出特定面向的高風險供應商</div>
              </div>
              <div class="impact-ref__col">
                <div class="impact-ref__dim-label">風險暴露率</div>
                <code class="impact-ref__formula">risk_score = ERP 採購金額加權風險指數</code>
                <div class="impact-ref__note">由 ERP 出口裝運資料計算，數值越低越安全</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- 頁面 Tab -->
    <div class="page-tabs">
      <button class="page-tab" :class="{ active: activeTab === 'heatmap' }" @click="activeTab = 'heatmap'">六維熱圖</button>
      <button class="page-tab" :class="{ active: activeTab === 'matrix' }" @click="activeTab = 'matrix'">5×5 風險矩陣</button>
    </div>

    <!-- 5×5 矩陣計算說明 Modal -->
    <Teleport to="body">
      <div v-if="showMatrixCalcModal" class="modal-overlay" @click.self="showMatrixCalcModal = false">
        <div class="modal-box">
          <div class="modal-header">
            <span class="modal-title">5×5 矩陣計算說明</span>
            <button class="modal-close" @click="showMatrixCalcModal = false">✕</button>
          </div>
          <div class="modal-body">
            <div class="impact-ref__grid">
              <div class="impact-ref__col">
                <div class="impact-ref__dim-label">Probability（P）</div>
                <code class="impact-ref__formula">P = max(1, ceil((100 − dim_score) / 20))</code>
                <div class="impact-ref__note">dim_score：E1–E5 各維度合規分（0–100，越高越安全）</div>
                <div class="impact-ref__note">分數越低 → P 越高（最高 5）；100分 → P=1</div>
              </div>
              <div class="impact-ref__col">
                <div class="impact-ref__dim-label">Impact（I）</div>
                <code class="impact-ref__formula">I = Tier 採購層級</code>
                <div class="impact-ref__note">Tier 1（直接供應商）→ 5　Tier 2 → 3　Tier 3+ → 1</div>
                <div class="impact-ref__note">Impact 反映採購暴露程度，與國家風險無關</div>
              </div>
              <div class="impact-ref__col">
                <div class="impact-ref__dim-label">Cell Score &amp; 風險等級</div>
                <code class="impact-ref__formula">cell_score = P × I（1–25）</code>
                <div class="impact-ref__note">Very Low 1–4　Low 5–9　Medium 10–14</div>
                <div class="impact-ref__note">High 15–19　Extreme 20–25</div>
              </div>
              <div class="impact-ref__col">
                <div class="impact-ref__dim-label">COMPOSITE 綜合最差</div>
                <code class="impact-ref__formula">dim_score = min(E1, E2, E3, E4, E5)</code>
                <div class="impact-ref__note">取五個維度中最低分，呈現最差情況的風險位置</div>
                <div class="impact-ref__note">E6（法規準備）另以四態標記呈現，不納入 P 計算</div>
              </div>
            </div>
            <div class="impact-ref__note" style="margin-top:12px;padding-top:10px;border-top:1px solid var(--border)">
              各維度警戒閾值：E1≤40 / E2≤45 / E3≤40 / E4≤35 / E5≤40 / E6≤50
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── 矩陣 Tab ── -->
    <RiskMatrix5x5 v-if="activeTab === 'matrix'" @open-compare="showCompareModal = true" />

    <!-- ── 熱圖 Tab ── -->
    <template v-if="activeTab === 'heatmap'">

    <!-- 篩選列 -->
    <div class="filter-bar">
      <span class="filter-label">篩選：</span>

      <!-- 國家多選 -->
      <div class="country-filter">
        <select class="filter-select" @change="addCountry($event.target.value)" :value="''">
          <option value="" disabled>+ 國家</option>
          <option v-for="c in availableCountries" :key="c" :value="c" :disabled="filterCountries.includes(c)">
            {{ c }}
          </option>
        </select>
        <span
          v-for="c in filterCountries"
          :key="c"
          class="filter-tag"
        >{{ c }} <button class="tag-remove" @click="removeCountry(c)">×</button></span>
      </div>

      <!-- 供應商分組 -->
      <div class="group-filter" v-if="availableGroups.length">
        <select class="filter-select" v-model="filterGroupId">
          <option value="">全部分組</option>
          <option v-for="g in availableGroups" :key="g.id" :value="g.id">{{ g.name }}</option>
        </select>
      </div>

      <!-- 風險等級 -->
      <div class="risk-filter-group">
        <button
          v-for="opt in riskFilterOpts"
          :key="opt.value"
          class="risk-filter-btn"
          :class="{ active: filterRisk === opt.value }"
          @click="filterRisk = opt.value"
        >{{ opt.label }}</button>
      </div>

      <!-- 計數 + 清除 -->
      <span class="filter-count">顯示 {{ filteredRows.length }} / {{ rows.length }} 家</span>
      <button
        v-if="hasFilter"
        class="clear-filter-btn"
        @click="clearFilter"
      >清除篩選</button>
    </div>

    <!-- 維度排序列 -->
    <div class="dim-sort-bar">
      <span class="sort-label">依維度排序：</span>
      <button
        v-for="dim in dims"
        :key="dim.key"
        class="dim-sort-btn"
        :class="{ active: sortDim === dim.key }"
        @click="toggleSort(dim.key)"
      >
        {{ dim.key }}
        <span class="dim-name">{{ dim.label }}</span>
        <span v-if="sortDim === dim.key">{{ sortAsc ? '↑' : '↓' }}</span>
      </button>
    </div>

    <div v-if="loading" class="loading-state">載入中...</div>
    <div v-else-if="!filteredRows.length && rows.length" class="empty-state">無符合條件的供應商</div>
    <div v-else-if="!rows.length" class="empty-state">尚無風險評估資料。待問卷評核完成後將自動產生。</div>
    <div v-else class="table-container">
      <table class="heatmap-table">
        <thead>
          <tr>
            <!-- 比較模式 checkbox 欄 -->
            <th v-show="compareMode" class="col-check"></th>
            <th class="col-supplier">供應商</th>
            <th class="col-country">國家</th>
            <th v-for="dim in dims" :key="dim.key" class="col-dim" :title="dim.label">
              {{ dim.key }}
            </th>
            <th class="col-score" title="風險暴露率（越低越安全）：由 ERP 計算的採購加權風險指數">風險暴露率</th>
            <th class="col-cap">CAP</th>
            <th class="col-source">來源</th>
            <th class="col-date">評估日</th>
            <th v-if="diffMode !== 'latest'" class="col-hist-note"></th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in sortedRows"
            :key="row.supplier_id"
            class="heatmap-row"
            :class="{ 'row-critical': row.is_critical, 'row-selected': compareIds.includes(row.supplier_id) }"
            @click="handleRowClick(row)"
          >
            <!-- 比較 checkbox -->
            <td v-show="compareMode" class="col-check" @click.stop>
              <input
                type="checkbox"
                :checked="compareIds.includes(row.supplier_id)"
                @change="toggleCompare(row)"
              />
            </td>
            <td class="col-supplier">{{ row.supplier_name }}</td>
            <td class="col-country">{{ row.country_code || '—' }}</td>
            <!-- 維度格子 + delta 徽章 -->
            <td
              v-for="dim in dims"
              :key="dim.key"
              class="col-dim font-mono"
              :class="dimCellClass(row[dim.field], dim.key)"
              :title="`${dim.label}：${row[dim.field] ?? '未評估'}`"
            >
              <div class="dim-cell-inner">
                <span>{{ row[dim.field] !== null && row[dim.field] !== undefined ? row[dim.field].toFixed(1) : '—' }}</span>
                <span
                  v-if="diffMode !== 'latest' && getDelta(row.supplier_id, dim.field) !== undefined"
                  class="delta-badge"
                  :class="deltaBadgeClass(getDelta(row.supplier_id, dim.field))"
                >{{ formatDelta(getDelta(row.supplier_id, dim.field)) }}</span>
              </div>
            </td>
            <td class="col-score font-mono" :title="row.risk_score !== null ? `風險暴露率：原始值 ${row.risk_score}` : ''">
              {{ row.risk_score !== null ? (row.risk_score * 100).toFixed(1) + '%' : '—' }}
            </td>
            <td class="col-cap">
              <span v-if="row.open_cap_count > 0" class="cap-badge">{{ row.open_cap_count }}</span>
              <span v-else class="no-cap">—</span>
            </td>
            <td class="col-source">
              <span class="source-badge" :class="`source-${row.source_type}`">
                {{ sourceLabel(row.source_type) }}
              </span>
            </td>
            <td class="col-date">{{ formatDate(row.assessed_at) }}</td>
            <td v-if="diffMode !== 'latest'" class="col-hist-note">
              <span v-if="!historyMap[row.supplier_id]" class="no-hist">無歷史</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 側欄詳情 -->
    <div v-if="selectedRow && !compareMode" class="detail-panel" @click.self="selectedRow = null">
      <div class="detail-content">
        <div class="detail-header">
          <h2>{{ selectedRow.supplier_name }}</h2>
          <button class="close-btn" @click="selectedRow = null">✕</button>
        </div>
        <div class="detail-meta">
          <span>{{ selectedRow.country_code }}</span>
          <span>{{ selectedRow.supplier_code }}</span>
          <span class="source-badge" :class="`source-${selectedRow.source_type}`">
            {{ sourceLabel(selectedRow.source_type) }}
          </span>
        </div>
        <div class="detail-dims">
          <div v-for="dim in dims" :key="dim.key" class="dim-row">
            <div class="dim-info">
              <span class="dim-key">{{ dim.key }}</span>
              <span class="dim-label">{{ dim.label }}</span>
            </div>
            <div class="dim-bar-wrap">
              <div
                class="dim-bar"
                :class="dimCellClass(selectedRow[dim.field], dim.key)"
                :style="{ width: (selectedRow[dim.field] ?? 0) + '%' }"
              />
            </div>
            <span class="dim-value font-mono">
              {{ selectedRow[dim.field] !== null && selectedRow[dim.field] !== undefined
                  ? selectedRow[dim.field].toFixed(1) : '—' }}
            </span>
          </div>
        </div>
        <div class="detail-stats">
          <div>風險暴露率 <strong class="font-mono">{{ selectedRow.risk_score != null ? (selectedRow.risk_score * 100).toFixed(1) + '%' : '—' }}</strong></div>
          <div>未結 CAP <strong>{{ selectedRow.open_cap_count }}</strong> 件</div>
          <div>最近評估 <strong>{{ formatDate(selectedRow.assessed_at) }}</strong></div>
        </div>
      </div>
    </div>

    <!-- 比較浮層 -->
    <div v-if="compareMode && compareIds.length > 0" class="compare-panel">
      <div class="compare-panel-header">
        <div class="cp-header-left">
          <span class="compare-title">比較中</span>
          <span class="cp-count-chip">{{ compareIds.length }}/5 家</span>
          <span v-if="compareIds.length >= 2" class="cp-gap-badge">
            差距最大：<strong>{{ maxGapDim.key }} {{ maxGapDim.label }}</strong>
            <span class="font-mono cp-gap-num">{{ maxGapDim.gap }} 分</span>
          </span>
        </div>
        <div class="compare-actions">
          <button class="cp-btn cp-btn-ghost" @click="compareIds = []">清除全部</button>
          <button class="cp-btn cp-btn-close" @click="toggleCompareMode">關閉比較</button>
        </div>
      </div>
      <div class="compare-body">
        <!-- 供應商名稱列（固定在最頂） -->
        <div class="cp-names-row">
          <div class="cp-dim-label"></div>
          <div class="cp-bars">
            <div
              v-for="sid in compareIds"
              :key="sid"
              class="cp-bar-item cp-name"
            >{{ compareRowMap[sid]?.supplier_name ?? '—' }}</div>
          </div>
        </div>
        <!-- 維度長條圖 -->
        <div v-for="dim in dims" :key="dim.key" class="cp-dim-row">
          <div class="cp-dim-label">
            <span class="cp-dim-key">{{ dim.key }}</span>
            <span class="cp-dim-name">{{ dim.label }}</span>
          </div>
          <div class="cp-bars">
            <div
              v-for="sid in compareIds"
              :key="sid"
              class="cp-bar-item"
            >
              <div class="cp-bar-track">
                <div
                  class="cp-bar"
                  :class="dimCellClass(compareRowMap[sid]?.[dim.field], dim.key)"
                  :style="{ width: (compareRowMap[sid]?.[dim.field] ?? 0) + '%' }"
                ></div>
              </div>
              <span class="cp-bar-val font-mono">{{ compareRowMap[sid]?.[dim.field]?.toFixed(1) ?? '—' }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Toast -->
    <div v-if="toast" class="toast">{{ toast }}</div>

    </template><!-- /activeTab === 'heatmap' -->

    <CompareModal v-if="showCompareModal" @close="showCompareModal = false" />
  </div>
</template>

<script>
import { riskApi } from '@/api/modules/risk'
import RiskMatrix5x5 from '@/components/risk/RiskMatrix5x5.vue'
import CompareModal from '@/components/CompareModal.vue'

const DIMS = [
  { key: 'E1', field: 'dim_e1', label: '環境管理' },
  { key: 'E2', field: 'dim_e2', label: '氣候與碳排' },
  { key: 'E3', field: 'dim_e3', label: '社會責任' },
  { key: 'E4', field: 'dim_e4', label: '地緣風險' },
  { key: 'E5', field: 'dim_e5', label: '公司治理' },
  { key: 'E6', field: 'dim_e6', label: '供應鏈透明度' },
]

export default {
  name: 'SixDimHeatmapView',
  components: { RiskMatrix5x5, CompareModal },
  data() {
    return {
      activeTab: 'heatmap',
      showCompareModal: false,
      showCalcModal: false,
      showMatrixCalcModal: false,
      rows: [],
      thresholds: {},
      summary: null,
      loading: false,
      sortDim: null,
      sortAsc: false,
      selectedRow: null,
      dims: DIMS,
      // 篩選
      filterCountries: [],
      filterRisk: 'all',
      filterGroupId: '',
      riskFilterOpts: [
        { value: 'all', label: '全部' },
        { value: 'critical', label: '需關注' },
        { value: 'extreme', label: '高風險' },
      ],
      // 比較
      compareMode: false,
      compareIds: [],
      // 歷史對比
      diffMode: 'latest',
      diffModes: [
        { value: 'latest', label: '最新' },
        { value: '30d', label: '▲▼ 30 天' },
        { value: '90d', label: '▲▼ 90 天' },
      ],
      historyCache: {},
      diffLoading: false,
      // toast
      toast: null,
      toastTimer: null,
    }
  },
  computed: {
    availableCountries() {
      const set = new Set(this.rows.map(r => r.country_code).filter(Boolean))
      return [...set].sort()
    },
    availableGroups() {
      const seen = new Map()
      for (const r of this.rows) {
        if (r.group_id && !seen.has(r.group_id)) seen.set(r.group_id, r.group_name ?? r.group_id)
      }
      return [...seen.entries()].map(([id, name]) => ({ id, name })).sort((a, b) => a.name.localeCompare(b.name))
    },
    hasFilter() {
      return this.filterCountries.length > 0 || this.filterRisk !== 'all' || this.filterGroupId !== ''
    },
    filteredRows() {
      return this.rows.filter(r => {
        if (this.filterCountries.length && !this.filterCountries.includes(r.country_code)) return false
        if (this.filterGroupId && r.group_id !== this.filterGroupId) return false
        if (this.filterRisk === 'critical' && !r.is_critical) return false
        if (this.filterRisk === 'extreme') {
          const dangerCount = DIMS.filter(d => {
            const score = r[d.field]
            return score !== null && score !== undefined && score < (this.thresholds[d.key] ?? 40)
          }).length
          if (dangerCount < 3) return false
        }
        return true
      })
    },
    sortedRows() {
      if (!this.sortDim) return this.filteredRows
      const field = DIMS.find(d => d.key === this.sortDim)?.field
      if (!field) return this.filteredRows
      return [...this.filteredRows].sort((a, b) => {
        const av = a[field] ?? -1
        const bv = b[field] ?? -1
        return this.sortAsc ? av - bv : bv - av
      })
    },
    compareRowMap() {
      const map = {}
      for (const sid of this.compareIds) {
        map[sid] = this.rows.find(r => r.supplier_id === sid)
      }
      return map
    },
    historyMap() {
      const days = this.diffMode === '30d' ? 30 : this.diffMode === '90d' ? 90 : null
      if (!days) return {}
      const hist = this.historyCache[days] ?? []
      const map = {}
      for (const h of hist) {
        map[h.supplier_id] = h
      }
      return map
    },
    maxGapDim() {
      let max = { key: '', label: '', gap: 0 }
      for (const dim of DIMS) {
        const vals = this.compareIds
          .map(sid => this.compareRowMap[sid]?.[dim.field])
          .filter(v => v !== null && v !== undefined)
        if (vals.length < 2) continue
        const gap = Math.max(...vals) - Math.min(...vals)
        if (gap > max.gap) max = { key: dim.key, label: dim.label, gap: Math.round(gap * 10) / 10 }
      }
      return max
    },
  },
  mounted() {
    this.loadData()
  },
  methods: {
    async loadData() {
      this.loading = true
      try {
        const res = await riskApi.sixDimHeatmap()
        const body = res.data
        this.rows = body.data
        this.thresholds = body.thresholds
        this.summary = body.summary
      } catch (e) {
        console.error(e)
      } finally {
        this.loading = false
      }
    },
    async setDiffMode(mode) {
      this.diffMode = mode
      if (mode === 'latest') return
      const days = mode === '30d' ? 30 : 90
      if (this.historyCache[days]) return
      this.diffLoading = true
      try {
        const res = await riskApi.sixDimHeatmap({ before_days: days })
        this.historyCache[days] = res.data.data
      } catch (e) {
        console.error(e)
      } finally {
        this.diffLoading = false
      }
    },
    getDelta(supplierId, field) {
      const hist = this.historyMap[supplierId]
      if (!hist) return undefined
      const current = this.rows.find(r => r.supplier_id === supplierId)?.[field]
      if (current === null || current === undefined || hist[field] === null || hist[field] === undefined) return undefined
      return Math.round((current - hist[field]) * 10) / 10
    },
    formatDelta(delta) {
      if (delta === undefined) return ''
      if (delta > 0) return `▲ +${delta}`
      if (delta < 0) return `▼ ${delta}`
      return '→'
    },
    deltaBadgeClass(delta) {
      if (delta === undefined) return ''
      if (delta > 0) return 'delta-up'
      if (delta < 0) return 'delta-down'
      return 'delta-flat'
    },
    toggleSort(dimKey) {
      if (this.sortDim === dimKey) {
        this.sortAsc = !this.sortAsc
      } else {
        this.sortDim = dimKey
        this.sortAsc = false
      }
    },
    dimCellClass(score, dimKey) {
      if (score === null || score === undefined) return 'cell-unknown'
      const threshold = this.thresholds[dimKey] ?? 40
      if (score < threshold) return 'cell-danger'
      if (score < 70) return 'cell-warn'
      return 'cell-ok'
    },
    sourceLabel(type) {
      return { saq: 'SAQ', geo_event: '地緣事件', regulation_change: '法規變動', manual_review: '手動' }[type] ?? type
    },
    formatDate(iso) {
      if (!iso) return '—'
      return iso.slice(0, 10)
    },
    handleRowClick(row) {
      if (this.compareMode) return
      this.selectedRow = row
    },
    // 篩選
    addCountry(code) {
      if (code && !this.filterCountries.includes(code)) {
        this.filterCountries.push(code)
      }
    },
    removeCountry(code) {
      this.filterCountries = this.filterCountries.filter(c => c !== code)
    },
    clearFilter() {
      this.filterCountries = []
      this.filterRisk = 'all'
      this.filterGroupId = ''
    },
    // 比較
    toggleCompareMode() {
      this.compareMode = !this.compareMode
      if (!this.compareMode) this.compareIds = []
      this.selectedRow = null
    },
    toggleCompare(row) {
      const sid = row.supplier_id
      const idx = this.compareIds.indexOf(sid)
      if (idx >= 0) {
        this.compareIds.splice(idx, 1)
      } else {
        if (this.compareIds.length >= 5) {
          const removed = this.rows.find(r => r.supplier_id === this.compareIds[0])
          this.compareIds.shift()
          this.showToast(`已替換 ${removed?.supplier_name ?? ''}`)
        }
        this.compareIds.push(sid)
      }
    },
    showToast(msg) {
      this.toast = msg
      clearTimeout(this.toastTimer)
      this.toastTimer = setTimeout(() => { this.toast = null }, 2500)
    },
    openDetail(row) {
      this.selectedRow = row
    },
  },
}
</script>

<style scoped>
.heatmap-view { padding: 1.5rem; position: relative; padding-bottom: 6rem; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; }
.page-desc { color: var(--text-muted, #888); font-size: 0.875rem; margin-top: 0.25rem; }
.header-actions { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; justify-content: flex-end; }
.summary-badge { background: var(--accent, #1a4d3e); color: #fff; padding: 0.3rem 0.75rem; border-radius: 1rem; font-size: 0.875rem; }

/* 顯示模式切換 */
.mode-group { display: flex; border: 1px solid var(--border, #ddd); border-radius: 4px; overflow: hidden; }
.mode-btn { padding: 0.3rem 0.7rem; font-size: 0.8rem; border: none; cursor: pointer; background: transparent; }
.mode-btn.active { background: var(--accent, #1a4d3e); color: #fff; }
.mode-btn.loading { opacity: 0.6; }

/* 頁面 Tab */
.page-tabs {
  display: flex;
  gap: 0;
  border-bottom: 2px solid var(--border);
  margin-bottom: 20px;
}
.page-tab {
  padding: 10px 24px;
  font-size: 14px;
  font-weight: 500;
  border: none;
  background: transparent;
  color: var(--text-secondary);
  cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  transition: color 0.15s, border-color 0.15s;
}
.page-tab:hover { color: var(--text-primary); }
.page-tab.active { color: var(--accent); border-bottom-color: var(--accent); font-weight: 600; }

/* 篩選列 */
.filter-bar { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; flex-wrap: wrap; padding: 0.5rem 0.75rem; background: var(--surface, #f8f8f8); border-radius: 6px; border: 1px solid var(--border, #eee); }
.filter-label { font-size: 0.8rem; color: var(--text-muted, #888); flex-shrink: 0; }
.country-filter { display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap; }
.filter-select { font-size: 0.8rem; padding: 0.2rem 0.4rem; border: 1px solid var(--border, #ddd); border-radius: 4px; background: transparent; cursor: pointer; }
.filter-tag { display: inline-flex; align-items: center; gap: 0.2rem; background: var(--accent, #1a4d3e); color: #fff; font-size: 0.75rem; padding: 0.15rem 0.4rem; border-radius: 3px; }
.tag-remove { background: none; border: none; color: inherit; cursor: pointer; padding: 0; font-size: 0.85rem; line-height: 1; }
.risk-filter-group { display: flex; border: 1px solid var(--border, #ddd); border-radius: 4px; overflow: hidden; }
.risk-filter-btn { padding: 0.2rem 0.55rem; font-size: 0.8rem; border: none; cursor: pointer; background: transparent; }
.risk-filter-btn.active { background: var(--accent, #1a4d3e); color: #fff; }
.filter-count { font-size: 0.8rem; color: var(--text-muted, #888); margin-left: auto; }
.clear-filter-btn { font-size: 0.8rem; padding: 0.2rem 0.6rem; border: 1px solid var(--border, #ddd); border-radius: 4px; cursor: pointer; background: transparent; }

/* 排序列 */
.dim-sort-bar { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap; }
.sort-label { font-size: 0.875rem; color: var(--text-muted, #888); }
.dim-sort-btn { padding: 0.25rem 0.6rem; border: 1px solid var(--border, #ddd); border-radius: 4px; cursor: pointer; font-size: 0.8rem; background: transparent; }
.dim-sort-btn.active { background: var(--accent, #1a4d3e); color: #fff; border-color: var(--accent, #1a4d3e); }
.dim-name { font-size: 0.7rem; opacity: 0.7; margin-left: 0.25rem; }

/* 表格 */
.table-container { overflow-x: auto; }
.heatmap-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.heatmap-table th { padding: 0.6rem 0.75rem; background: var(--surface, #f5f5f5); border-bottom: 2px solid var(--border, #ddd); text-align: center; white-space: nowrap; }
.heatmap-table td { padding: 0.5rem 0.75rem; border-bottom: 1px solid var(--border, #eee); text-align: center; }
.col-check { width: 32px; padding: 0.5rem 0.25rem; }
.col-supplier { text-align: left !important; font-weight: 500; cursor: pointer; }
.heatmap-row { cursor: pointer; transition: background 0.1s; }
.heatmap-row:hover { background: var(--surface, #f9f9f9); }
.row-critical td:first-child { border-left: 3px solid #e53e3e; }
.row-selected { background: rgba(26,77,62,0.06) !important; }

/* 維度格子 */
.dim-cell-inner { display: flex; flex-direction: column; align-items: center; gap: 1px; }
.cell-ok { background: rgba(72, 187, 120, 0.15); }
.cell-warn { background: rgba(237, 137, 54, 0.15); }
.cell-danger { background: rgba(229, 62, 62, 0.2); font-weight: 600; color: #c53030; }
.cell-unknown { color: #aaa; }

/* Delta 徽章 */
.delta-badge { font-size: 10px; font-weight: 600; padding: 0 2px; }
.delta-up { color: #276749; }
.delta-down { color: #c53030; }
.delta-flat { color: #888; }

/* 無歷史 */
.col-hist-note { width: 48px; font-size: 0.7rem; }
.no-hist { color: #aaa; font-size: 0.7rem; }

.cap-badge { background: #e53e3e; color: #fff; border-radius: 1rem; padding: 0.1rem 0.5rem; font-size: 0.8rem; }
.no-cap { color: #aaa; }
.source-badge { font-size: 0.75rem; padding: 0.15rem 0.4rem; border-radius: 3px; }
.source-saq { background: #bee3f8; color: #2b6cb0; }
.source-geo_event { background: #fefcbf; color: #744210; }
.source-manual_review { background: #e9d8fd; color: #553c9a; }
.loading-state, .empty-state { padding: 2rem; text-align: center; color: var(--text-muted, #888); }

/* 側欄 */
.detail-panel { position: fixed; inset: 0; background: rgba(0,0,0,0.3); z-index: 200; display: flex; justify-content: flex-end; }
.detail-content { background: var(--bg, #fff); width: 360px; max-width: 100%; padding: 1.5rem; overflow-y: auto; }
.detail-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
.close-btn { background: none; border: none; font-size: 1.25rem; cursor: pointer; color: var(--text-muted, #888); }
.detail-meta { display: flex; gap: 0.5rem; font-size: 0.8rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
.detail-dims { display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1.5rem; }
.dim-row { display: flex; align-items: center; gap: 0.5rem; }
.dim-info { width: 100px; flex-shrink: 0; }
.dim-key { font-weight: 700; font-size: 0.8rem; margin-right: 0.25rem; }
.dim-label { font-size: 0.75rem; color: var(--text-muted, #888); }
.dim-bar-wrap { flex: 1; height: 8px; background: var(--surface, #f0f0f0); border-radius: 4px; overflow: hidden; }
.dim-bar { height: 100%; border-radius: 4px; transition: width 0.3s; }
.dim-bar.cell-ok { background: #48bb78; }
.dim-bar.cell-warn { background: #ed8936; }
.dim-bar.cell-danger { background: #e53e3e; }
.dim-value { width: 40px; text-align: right; flex-shrink: 0; font-size: 0.875rem; }
.detail-stats { display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.875rem; }

/* 按鈕 */
.btn-secondary { padding: 0.4rem 1rem; border: 1px solid var(--border, #ddd); border-radius: 4px; cursor: pointer; background: transparent; font-size: 0.875rem; }
.btn-secondary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-active { background: var(--accent, #1a4d3e); color: #fff; border-color: var(--accent, #1a4d3e); }

/* 比較浮層 */
.compare-panel {
  position: fixed; bottom: 0; left: 0; right: 0; z-index: 100;
  background: var(--bg, #fff);
  border-top: 2px solid var(--accent, #1a4d3e);
  box-shadow: 0 -6px 24px rgba(0,0,0,0.10);
  padding: 10px 20px 20px;
}
.compare-panel-header {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 8px;
}
.cp-header-left { display: flex; align-items: center; gap: 10px; }
.compare-title { font-weight: 700; font-size: 13px; color: var(--text-primary); }
.cp-count-chip {
  font-size: 11px; font-weight: 600;
  background: var(--accent, #1a4d3e); color: #fff;
  padding: 1px 7px; border-radius: 10px;
}
.cp-gap-badge {
  font-size: 12px; color: var(--text-secondary);
  padding: 2px 10px 2px 8px;
  background: var(--surface, #f5f4f2);
  border-radius: 4px;
  display: flex; align-items: center; gap: 5px;
}
.cp-gap-badge strong { color: var(--text-primary); font-weight: 600; }
.cp-gap-num { color: #e53e3e; font-size: 12px; }
.compare-actions { display: flex; gap: 6px; }
.cp-btn {
  padding: 3px 10px; font-size: 12px;
  border-radius: 5px; cursor: pointer; border: 1px solid var(--border, #ddd);
  transition: all 0.15s;
}
.cp-btn-ghost { background: transparent; color: var(--text-secondary); }
.cp-btn-ghost:hover { background: var(--surface); color: var(--text-primary); }
.cp-btn-close { background: var(--surface); color: var(--text-primary); font-weight: 500; }
.cp-btn-close:hover { border-color: var(--accent); color: var(--accent); }

.compare-body { display: flex; flex-direction: column; gap: 5px; }

/* 供應商名稱列 */
.cp-names-row { display: flex; align-items: center; gap: 12px; margin-bottom: 4px; padding-bottom: 6px; border-bottom: 1px solid var(--border, #eee); }
.cp-name { font-size: 11.5px; color: var(--text-secondary); font-weight: 500; justify-content: center; text-align: center; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* 維度列 */
.cp-dim-row { display: flex; align-items: center; gap: 12px; min-height: 26px; }
.cp-dim-label { width: 110px; flex-shrink: 0; display: flex; align-items: center; gap: 5px; }
.cp-dim-key { font-size: 11px; font-weight: 700; color: var(--accent, #1a4d3e); background: rgba(26,77,62,0.08); padding: 1px 5px; border-radius: 3px; flex-shrink: 0; }
.cp-dim-name { font-size: 11.5px; color: var(--text-secondary); }

.cp-bars { display: flex; gap: 8px; flex: 1; }
.cp-bar-item { display: flex; align-items: center; gap: 6px; flex: 1; min-width: 0; }
.cp-bar-track { flex: 1; height: 18px; background: var(--surface, #f0f0f0); border-radius: 4px; overflow: hidden; }
.cp-bar { height: 100%; border-radius: 4px; min-width: 3px; transition: width 0.35s ease; }
.cp-bar.cell-ok { background: #48bb78; }
.cp-bar.cell-warn { background: #ed8936; }
.cp-bar.cell-danger { background: #e53e3e; }
.cp-bar.cell-unknown { background: #cbd5e0; }
.cp-bar-val { font-size: 11.5px; color: var(--text-primary); white-space: nowrap; min-width: 30px; text-align: right; }

/* Toast */
.toast {
  position: fixed; bottom: 5rem; left: 50%; transform: translateX(-50%);
  background: rgba(0,0,0,0.75); color: #fff;
  padding: 0.5rem 1.25rem; border-radius: 2rem; font-size: 0.875rem;
  z-index: 300; pointer-events: none;
}

@media (prefers-color-scheme: dark) {
  .cell-ok { background: rgba(72, 187, 120, 0.2); }
  .cell-warn { background: rgba(237, 137, 54, 0.2); }
  .cell-danger { background: rgba(229, 62, 62, 0.25); }
}

/* 計算方式說明 Modal */
.modal-overlay { position: fixed; inset: 0; z-index: 1000; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; }
.modal-box { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; width: min(720px, 92vw); max-height: 85vh; overflow-y: auto; box-shadow: 0 8px 32px rgba(0,0,0,0.18); }
.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid var(--border); }
.modal-title { font-size: 14px; font-weight: 700; color: var(--text-primary); }
.modal-close { background: none; border: none; cursor: pointer; font-size: 15px; color: var(--text-secondary); padding: 2px 6px; border-radius: 4px; }
.modal-close:hover { background: var(--surface-2); }
.modal-body { padding: 20px; }
.impact-ref__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px 24px; }
.impact-ref__col { display: flex; flex-direction: column; gap: 4px; }
.impact-ref__dim-label { font-size: 12px; font-weight: 600; color: var(--text-primary); margin-bottom: 2px; }
.impact-ref__formula { font-size: 11.5px; font-family: var(--font-mono); background: #f4f3f1; color: #1a4d3e; padding: 3px 8px; border-radius: 4px; display: block; white-space: nowrap; overflow-x: auto; }
@media (prefers-color-scheme: dark) { .impact-ref__formula { background: #2a2825; color: #7ecba0; } }
.impact-ref__note { font-size: 11px; color: var(--text-secondary); line-height: 1.5; }
</style>
