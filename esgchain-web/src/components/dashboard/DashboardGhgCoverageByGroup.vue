<template>
  <div class="card">
    <div class="widget-header">
      <div class="card-title">碳盤查涵蓋度（依群組）</div>
      <select
        class="filter-select filter-select-sm"
        :value="periodYear ?? ''"
        @change="onYearChange($event)"
      >
        <option value="">全部期間</option>
        <option v-for="y in periodYearOptions" :key="y" :value="y">{{ y }} 年度</option>
      </select>
    </div>

    <div v-if="!data || data.overall.total === 0" class="empty-state">
      <p>尚無供應商資料</p>
    </div>
    <template v-else>
      <div class="overall-box">
        <span class="overall-label">總覆蓋率</span>
        <span class="overall-value font-mono">{{ data.overall.coverage_rate }}%</span>
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
  name: 'DashboardGhgCoverageByGroup',
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
          { label: '未盤查', data: groups.map((g) => g.not_surveyed_count), backgroundColor: '#fca5a5' },
          { label: '僅範疇一二', data: groups.map((g) => g.partial_count), backgroundColor: '#fde047' },
          { label: '含範疇三', data: groups.map((g) => g.full_count), backgroundColor: '#4ade80' },
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
        plugins: { legend: { position: 'bottom' as const } },
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
.widget-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; gap: 8px; }
.filter-select-sm { font-size: 12.5px; padding: 4px 8px; }
.overall-box {
  display: flex;
  align-items: baseline;
  gap: 10px;
  background: var(--accent-soft);
  border: 1px solid var(--accent-soft-border);
  border-radius: 8px;
  padding: 8px 12px;
  margin-bottom: 12px;
}
.overall-label { font-size: 12.5px; color: var(--text-secondary); }
.overall-value { font-size: 18px; font-weight: 700; color: var(--accent); }
.chart-wrap { position: relative; height: 220px; }
</style>
