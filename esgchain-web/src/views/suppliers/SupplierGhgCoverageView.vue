<template>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h1 class="page-title">供應商碳盤查覆蓋度</h1>
        <p class="page-subtitle">依供應商列出範疇一/二/三溫室氣體填報狀況，稽核盤查成熟度</p>
      </div>
    </div>

    <!-- 篩選 -->
    <div class="filter-bar">
      <select v-model="periodYear" class="filter-select" @change="onFilterChange">
        <option :value="null">全部期間（各 scope 取最近一次填報）</option>
        <option v-for="y in periodYearOptions" :key="y" :value="y">{{ y }} 年度</option>
      </select>
      <button class="btn btn-secondary btn-sm" @click="load">重新整理</button>
    </div>

    <!-- 圖表：分群組長條圖 + 年度覆蓋率成長曲線 -->
    <div class="charts-grid">
      <GhgCoverageGroupBarChart
        :data="byGroupData"
        :period-year="periodYear"
        @update:period-year="onGroupChartYearChange"
      />
      <GhgCoverageTrendLineChart :data="trendData" />
    </div>

    <!-- 表格 -->
    <div class="table-container">
      <div v-if="isLoading" class="loading-mask">載入中...</div>
      <div v-else-if="items.length === 0" class="empty-state">
        <p>尚無供應商資料</p>
      </div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>供應商名稱</th>
            <th>盤查覆蓋度</th>
            <th class="sortable-th" @click="toggleSort('scope1')">
              範疇一（噸CO2e）<span class="sort-indicator">{{ sortIndicator('scope1') }}</span>
            </th>
            <th class="sortable-th" @click="toggleSort('scope2')">
              範疇二（噸CO2e）<span class="sort-indicator">{{ sortIndicator('scope2') }}</span>
            </th>
            <th class="sortable-th" @click="toggleSort('scope3')">
              範疇三（噸CO2e）<span class="sort-indicator">{{ sortIndicator('scope3') }}</span>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.supplier_id">
            <td>{{ maskSupplierName(item.supplier_name) }}</td>
            <td><span class="badge" :class="levelBadgeClass(item.coverage_level)">{{ item.coverage_level }}</span></td>
            <td class="font-mono">{{ scopeCell(item.scope1) }}</td>
            <td class="font-mono">{{ scopeCell(item.scope2) }}</td>
            <td class="font-mono">{{ scopeCell(item.scope3) }}</td>
          </tr>
        </tbody>
      </table>

      <div class="pagination" v-if="!isLoading && items.length > 0">
        <span>第 {{ pagination.current_page }} / {{ pagination.last_page }} 頁（共 {{ pagination.total }} 家）</span>
        <button class="pg-btn" :disabled="pagination.current_page <= 1" @click="goPage(pagination.current_page - 1)">‹</button>
        <button class="pg-btn" :disabled="pagination.current_page >= pagination.last_page" @click="goPage(pagination.current_page + 1)">›</button>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import {
  supplierGhgCoverageApi,
  type SupplierGhgCoverageItem,
  type SupplierGhgScopeValue,
  type SupplierGhgCoverageByGroup,
  type SupplierGhgCoverageTrendPoint,
  type Pagination,
} from '@/api/modules/suppliers'
import { maskSupplierName } from '@/utils/maskName'
import GhgCoverageGroupBarChart from '@/components/suppliers/GhgCoverageGroupBarChart.vue'
import GhgCoverageTrendLineChart from '@/components/suppliers/GhgCoverageTrendLineChart.vue'

export default defineComponent({
  name: 'SupplierGhgCoverageView',
  components: { GhgCoverageGroupBarChart, GhgCoverageTrendLineChart },
  data() {
    return {
      items: [] as SupplierGhgCoverageItem[],
      isLoading: false,
      periodYear: null as number | null,
      byGroupData: null as SupplierGhgCoverageByGroup | null,
      trendData: [] as SupplierGhgCoverageTrendPoint[],
      sortKey: null as 'scope1' | 'scope2' | 'scope3' | null,
      sortDir: 'desc' as 'asc' | 'desc',
      pagination: { current_page: 1, per_page: 20, total: 0, last_page: 1 } as Pagination,
    }
  },
  computed: {
    periodYearOptions(): number[] {
      const currentYear = new Date().getFullYear()
      const years: number[] = []
      for (let y = currentYear; y >= currentYear - 4; y--) years.push(y)
      return years
    },
  },
  methods: {
    maskSupplierName,
    async load() {
      this.isLoading = true
      try {
        const [listRes, byGroupRes] = await Promise.all([
          supplierGhgCoverageApi.list({
            periodYear: this.periodYear ?? undefined,
            sortBy: this.sortKey ?? undefined,
            sortDir: this.sortDir,
            page: this.pagination.current_page,
            perPage: this.pagination.per_page,
          }),
          supplierGhgCoverageApi.byGroup(this.periodYear ?? undefined),
        ])
        this.items = listRes.data.data
        this.pagination = listRes.data.pagination
        this.byGroupData = byGroupRes.data.data
      } finally {
        this.isLoading = false
      }
    },
    async loadTrend() {
      const res = await supplierGhgCoverageApi.trend()
      this.trendData = res.data.data
    },
    onFilterChange() {
      this.pagination.current_page = 1
      this.load()
    },
    onGroupChartYearChange(year: number | null) {
      this.periodYear = year
      this.pagination.current_page = 1
      this.load()
    },
    goPage(page: number) {
      this.pagination.current_page = page
      this.load()
    },
    toggleSort(key: 'scope1' | 'scope2' | 'scope3') {
      if (this.sortKey === key) {
        this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc'
      } else {
        this.sortKey = key
        this.sortDir = 'desc'
      }
      this.pagination.current_page = 1
      this.load()
    },
    sortIndicator(key: 'scope1' | 'scope2' | 'scope3'): string {
      if (this.sortKey !== key) return ''
      return this.sortDir === 'asc' ? '▲' : '▼'
    },
    scopeCell(v: SupplierGhgScopeValue | null): string {
      if (!v) return '—'
      return `${v.numeric_value.toLocaleString()}（${v.period_year}）`
    },
    levelBadgeClass(level: SupplierGhgCoverageItem['coverage_level']): string {
      switch (level) {
        case '含範疇三':
          return 'badge-green'
        case '僅範疇一二':
          return 'badge-yellow'
        default:
          return 'badge-gray'
      }
    },
  },
  mounted() {
    this.load()
    this.loadTrend()
  },
})
</script>

<style scoped>
.sortable-th {
  cursor: pointer;
  user-select: none;
}
.sortable-th:hover {
  color: var(--accent);
}
.sort-indicator {
  display: inline-block;
  margin-left: 4px;
  font-size: 10px;
  color: var(--accent);
}
.charts-grid {
  display: grid;
  grid-template-columns: 1.3fr 1fr;
  gap: 16px;
  margin-bottom: 20px;
}
@media (max-width: 960px) {
  .charts-grid { grid-template-columns: 1fr; }
}
</style>
