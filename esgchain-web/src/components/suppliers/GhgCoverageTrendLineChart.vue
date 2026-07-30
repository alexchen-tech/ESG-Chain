<template>
  <div class="card">
    <div class="card-title" style="margin-bottom:16px">年度覆蓋率成長曲線</div>
    <div v-if="!data || data.length === 0" class="empty-state">
      <p>尚無歷史填報資料</p>
    </div>
    <div v-else class="chart-wrap">
      <Line :data="chartData" :options="chartOptions" />
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import type { PropType } from 'vue'
import { Line } from 'vue-chartjs'
import {
  Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement,
  Title, Tooltip, Legend, Filler,
} from 'chart.js'
import type { SupplierGhgCoverageTrendPoint } from '@/api/modules/suppliers'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler)

export default defineComponent({
  name: 'GhgCoverageTrendLineChart',
  components: { Line },
  props: {
    data: {
      type: Array as PropType<SupplierGhgCoverageTrendPoint[]>,
      default: () => [],
    },
  },
  computed: {
    chartData() {
      return {
        labels: this.data.map((p) => `${p.period_year}`),
        datasets: [
          {
            label: '覆蓋率（%）',
            data: this.data.map((p) => p.coverage_rate),
            borderColor: '#1a4d3e',
            backgroundColor: 'rgba(26, 77, 62, 0.12)',
            fill: true,
            tension: 0.3,
            pointRadius: 4,
          },
        ],
      }
    },
    chartOptions() {
      return {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
            max: 100,
            ticks: { callback: (v: number | string) => `${v}%` },
          },
        },
        plugins: {
          legend: { position: 'bottom' as const },
          tooltip: {
            callbacks: {
              label: (ctx: { raw: unknown }) => `覆蓋率 ${ctx.raw}%`,
            },
          },
        },
      }
    },
  },
})
</script>

<style scoped>
.chart-wrap { position: relative; height: 300px; }
</style>
