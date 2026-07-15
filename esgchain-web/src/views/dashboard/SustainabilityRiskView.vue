<template>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h1 class="page-title">永續風險概覽</h1>
        <p class="page-subtitle">供應商六維度（E1–E6）風險評分一覽，紅色代表低於警戒閾值。風險暴露率由 ERP 採購加權計算，數值越低代表風險越低。</p>
      </div>
    </div>

    <!-- 篩選列 -->
    <div class="filter-bar">
      <input
        v-model="search"
        type="text"
        placeholder="搜尋名稱或代碼..."
        class="filter-input"
        style="width:200px;"
        @keyup.enter="resetPage"
      />
      <select v-model="filterCountry" class="filter-select" @change="resetPage">
        <option value="">所有國家</option>
        <option v-for="c in countryOptions" :key="c" :value="c">{{ c }}</option>
      </select>
      <select v-model="filterGroup" class="filter-select" @change="resetPage">
        <option value="">所有分組</option>
        <option v-for="g in groupOptions" :key="g.id" :value="g.id">{{ g.name }}</option>
      </select>
      <select v-model="filterRisk" class="filter-select" @change="resetPage">
        <option value="all">所有風險</option>
        <option value="critical">需關注（任一維度低於閾值）</option>
        <option value="high">高風險（暴露率 ≥ 40%）</option>
      </select>
      <button class="btn btn-secondary btn-sm" @click="resetPage">搜尋</button>
      <span v-if="hasFilter" class="filter-result-hint">共 {{ filteredRows.length }} 筆</span>
      <button v-if="hasFilter" class="btn btn-secondary btn-sm" @click="clearFilter">清除篩選</button>
    </div>

    <div class="card table-wrap">
      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>載入中…
      </div>
      <div v-else-if="filteredRows.length === 0" class="empty-state">
        <div class="empty-state-icon">📊</div>
        <p>沒有符合條件的供應商</p>
      </div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th style="width:40px;">#</th>
            <th>供應商</th>
            <th style="text-align:center;">國家</th>
            <th
              v-for="dim in dims" :key="dim.key"
              class="th-sortable th-center"
              :title="dim.label"
              @click="setSort(dim.key)"
            >
              {{ dim.key }} <span class="sort-icon">{{ sortIcon(dim.key) }}</span>
            </th>
            <th class="th-sortable th-center" @click="setSort('risk_score')" title="風險暴露率（越低越安全）">
              風險暴露率 <span class="sort-icon">{{ sortIcon('risk_score') }}</span>
            </th>
            <th style="text-align:center;">CAP</th>
            <th style="text-align:center;">評估日</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(row, i) in paginatedRows"
            :key="row.supplier_id"
            class="clickable-row"
            @click="goToDetail(row.supplier_id)"
          >
            <td class="num">{{ (page - 1) * perPage + i + 1 }}</td>
            <td>
              <span class="sup-name">{{ row.supplier_name }}</span>
              <span class="sup-code font-mono">{{ row.supplier_code }}</span>
            </td>
            <td style="text-align:center;font-size:13px;color:var(--text-secondary);">{{ row.country_code || '—' }}</td>
            <td
              v-for="dim in dims" :key="dim.key"
              class="font-mono td-center"
              :class="dimClass(row[dim.field], dim.key)"
            >
              {{ row[dim.field] != null ? row[dim.field].toFixed(1) : '—' }}
            </td>
            <td class="font-mono td-center">
              {{ row.risk_score != null ? (row.risk_score * 100).toFixed(1) + '%' : '—' }}
            </td>
            <td style="text-align:center;">
              <span v-if="row.open_cap_count > 0" class="badge badge-red cap-badge">{{ row.open_cap_count }}</span>
              <span v-else style="color:var(--text-muted,#c4bfb8);">—</span>
            </td>
            <td style="text-align:center;font-size:12px;color:var(--text-secondary);">{{ row.assessed_at ? row.assessed_at.slice(0,10) : '—' }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="filteredRows.length > perPage" class="pagination">
      <span style="font-size:12.5px;color:var(--text-secondary);margin-right:4px;">
        第 {{ page }} / {{ Math.ceil(filteredRows.length / perPage) }} 頁，共 {{ filteredRows.length }} 筆
      </span>
      <button :disabled="page <= 1" class="pg-btn" @click="page--">上一頁</button>
      <button :disabled="page >= Math.ceil(filteredRows.length / perPage)" class="pg-btn" @click="page++">下一頁</button>
    </div>
  </div>
</template>

<script>
import { riskApi } from '@/api/modules/risk'

const DIMS = [
  { key: 'E1', field: 'dim_e1', label: 'ESG整體' },
  { key: 'E2', field: 'dim_e2', label: '永續採購' },
  { key: 'E3', field: 'dim_e3', label: '社會責任' },
  { key: 'E4', field: 'dim_e4', label: '地緣政治' },
  { key: 'E5', field: 'dim_e5', label: '供應鏈安全' },
  { key: 'E6', field: 'dim_e6', label: '產品合規' },
]

export default {
  name: 'SustainabilityRiskView',
  data() {
    return {
      rows: [],
      thresholds: {},
      loading: false,
      search: '',
      filterCountry: '',
      filterGroup: '',
      filterRisk: 'all',
      sortBy: 'risk_score',
      sortDir: 'desc',
      page: 1,
      perPage: 20,
      dims: DIMS,
    }
  },
  computed: {
    countryOptions() {
      return [...new Set(this.rows.map(r => r.country_code).filter(Boolean))].sort()
    },
    groupOptions() {
      const map = new Map()
      this.rows.forEach(r => {
        if (r.group_id && r.group_name) map.set(r.group_id, { id: r.group_id, name: r.group_name })
      })
      return [...map.values()].sort((a, b) => a.name.localeCompare(b.name))
    },
    hasFilter() {
      return !!(this.search || this.filterCountry || this.filterGroup || this.filterRisk !== 'all')
    },
    filteredRows() {
      let list = this.rows.filter(r => {
        if (this.search && !r.supplier_name?.includes(this.search) && !r.supplier_code?.includes(this.search)) return false
        if (this.filterCountry && r.country_code !== this.filterCountry) return false
        if (this.filterGroup && r.group_id !== this.filterGroup) return false
        if (this.filterRisk === 'critical' && !r.is_critical) return false
        if (this.filterRisk === 'high' && (r.risk_score == null || r.risk_score < 0.4)) return false
        return true
      })
      list = list.slice().sort((a, b) => {
        const dir = this.sortDir === 'asc' ? 1 : -1
        const dimEntry = DIMS.find(d => d.key === this.sortBy)
        const field = dimEntry ? dimEntry.field : this.sortBy
        const av = a[field] ?? -1
        const bv = b[field] ?? -1
        return (bv - av) * dir
      })
      return list
    },
    paginatedRows() {
      const start = (this.page - 1) * this.perPage
      return this.filteredRows.slice(start, start + this.perPage)
    },
  },
  mounted() { this.loadData() },
  methods: {
    async loadData() {
      this.loading = true
      try {
        const res = await riskApi.sixDimHeatmap()
        this.rows = res.data.data
        this.thresholds = res.data.thresholds
      } catch (e) {
        console.error(e)
        this.rows = []
      } finally {
        this.loading = false
      }
    },
    dimClass(score, dimKey) {
      if (score == null) return 'dim-unknown'
      const threshold = this.thresholds[dimKey] ?? 40
      if (score < threshold) return 'dim-danger'
      if (score < 70) return 'dim-warn'
      return 'dim-ok'
    },
    setSort(col) {
      if (this.sortBy === col) this.sortDir = this.sortDir === 'desc' ? 'asc' : 'desc'
      else { this.sortBy = col; this.sortDir = 'desc' }
    },
    sortIcon(col) {
      if (this.sortBy !== col) return '↕'
      return this.sortDir === 'desc' ? '↓' : '↑'
    },
    resetPage() { this.page = 1 },
    clearFilter() {
      this.search = ''; this.filterCountry = ''; this.filterGroup = ''; this.filterRisk = 'all'; this.page = 1
    },
    goToDetail(id) {
      this.$router.push({ name: 'supplier-detail', params: { id } })
    },
  },
}
</script>

<style scoped>
.table-wrap { overflow: hidden; }

.th-sortable { cursor: pointer; user-select: none; }
.th-sortable:hover { color: var(--accent); }
.th-center { text-align: center; min-width: 68px; }
.td-center { text-align: center; }
.sort-icon { font-size: 10px; opacity: 0.55; }

.sup-name { font-weight: 600; font-size: 13.5px; display: block; line-height: 1.3; }
.sup-code { font-size: 11px; color: var(--text-secondary); display: block; }

.dim-ok      { color: #15803d; }
.dim-warn    { color: #a16207; }
.dim-danger  { color: #b91c1c; font-weight: 700; background: rgba(229,62,62,0.07); }
.dim-unknown { color: var(--text-muted, #c4bfb8); }

.cap-badge { font-size: 11px; padding: 1px 7px; }

.filter-result-hint {
  font-size: 12.5px; color: var(--text-secondary);
  margin-left: 4px;
}

.loading-state {
  display: flex; align-items: center; justify-content: center;
  gap: 10px; padding: 48px;
  color: var(--text-secondary); font-size: 14px;
}
.spinner {
  width: 18px; height: 18px;
  border: 2px solid var(--border);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
  flex-shrink: 0;
}
@keyframes spin { to { transform: rotate(360deg); } }

.pagination {
  display: flex; align-items: center; justify-content: flex-end;
  gap: 8px; margin-top: 14px;
}
.pg-btn {
  padding: 4px 12px;
  border: 1px solid var(--border);
  border-radius: 5px;
  cursor: pointer;
  background: var(--surface);
  color: var(--text-primary);
  font-size: 13px;
  transition: background 0.1s;
}
.pg-btn:hover { background: var(--surface-2); }
.pg-btn:disabled { opacity: 0.35; cursor: not-allowed; }
</style>
