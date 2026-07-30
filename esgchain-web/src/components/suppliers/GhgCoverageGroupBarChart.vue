<template>
  <div class="card">
    <div class="chart-header">
      <div class="card-title">碳盤查涵蓋度（依供應商群組）</div>
      <select
        class="filter-select"
        :value="periodYear ?? ''"
        @change="onYearChange($event)"
      >
        <option value="">全部期間（最近一次填報）</option>
        <option v-for="y in periodYearOptions" :key="y" :value="y">{{ y }} 年度</option>
      </select>
    </div>

    <div v-if="!data || data.overall.total === 0" class="empty-state">
      <p>尚無供應商資料</p>
    </div>
    <template v-else>
      <div class="overall-box">
        <span class="overall-label">總覆蓋率（全體供應商）</span>
        <span class="overall-value font-mono">{{ data.overall.coverage_rate }}%</span>
        <span class="overall-sub">
          共 {{ data.overall.total }} 家：未盤查 {{ data.overall.not_surveyed_count }}／
          僅範疇一二 {{ data.overall.partial_count }}／含範疇三 {{ data.overall.full_count }}
        </span>
      </div>
      <div class="chart-wrap">
        <Bar :data="chartData" :options="chartOptions" />
      </div>
    </template>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import type { PropType } from 'vue'
import { Bar } from 'vue-chartjs'
import {
  Chart as ChartJS, CategoryScale, LinearScale, BarElement,
  Title, Tooltip, Legend,
} from 'chart.js'
import type { SupplierGhgCoverageByGroup } from '@/api/modules/suppliers'

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend)

export default defineComponent({
  name: 'GhgCoverageGroupBarChart',
  components: { Bar },
  props: {
    data: {
      type: Object as PropType<SupplierGhgCoverageByGroup | null>,
      default: null,
    },
    periodYear: {
      type: Number as PropType<number | null>,
      default: null,
    },
  },
  emits: ['update:periodYear'],
  computed: {
    periodYearOptions(): number[] {
      const currentYear = new Date().getFullYear()
      const years: number[] = []
      for (let y = currentYear; y >= currentYear - 4; y--) years.push(y)
      return years
    },
    chartData() {
      const groups = this.data?.groups ?? []
      return {
        labels: groups.map((g) => g.group_name),
        datasets: [
          {
            label: '未盤查',
            data: groups.map((g) => g.not_surveyed_count),
            backgroundColor: '#fca5a5',
          },
          {
            label: '僅範疇一二',
            data: groups.map((g) => g.partial_count),
            backgroundColor: '#fde047',
          },
          {
            label: '含範疇三',
            data: groups.map((g) => g.full_count),
            backgroundColor: '#4ade80',
          },
        ],
      }
    },
    chartOptions() {
      return {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: { stacked: true },
          y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } },
        },
        plugins: {
          legend: { position: 'bottom' as const },
        },
      }
    },
  },
  methods: {
    onYearChange(evt: Event) {
      const value = (evt.target as HTMLSelectElement).value
      this.$emit('update:periodYear', value === '' ? null : Number(value))
    },
  },
})
</script>

<style scoped>
.chart-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
  gap: 12px;
}
.overall-box {
  display: flex;
  align-items: baseline;
  gap: 12px;
  flex-wrap: wrap;
  background: var(--accent-soft);
  border: 1px solid var(--accent-soft-border);
  border-radius: 8px;
  padding: 12px 16px;
  margin-bottom: 16px;
}
.overall-label { font-size: 13px; color: var(--text-secondary); }
.overall-value { font-size: 24px; font-weight: 700; color: var(--accent); }
.overall-sub { font-size: 12.5px; color: var(--text-secondary); }
.chart-wrap { position: relative; height: 320px; }
</style>
