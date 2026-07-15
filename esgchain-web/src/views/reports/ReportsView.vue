<template>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h1 class="page-title">Scope 3 排放報告</h1>
        <p class="page-subtitle">GHG Protocol 供應鏈排放 15 類別彙總</p>
      </div>
      <div style="display:flex;gap:8px;align-items:center;">
        <select v-model="selectedYear" class="filter-select" @change="loadReport">
          <option v-for="y in availableYears" :key="y" :value="y">{{ y }} 年</option>
        </select>
        <button class="btn btn-secondary" :disabled="isExporting" @click="downloadExport">
          {{ isExporting ? '匯出中...' : '匯出 Excel' }}
        </button>
      </div>
    </div>

    <div v-if="isLoading" class="loading-mask">載入報告資料...</div>

    <template v-else-if="report">
      <!-- 總排放量 -->
      <div class="card total-card">
        <div class="card-title">{{ selectedYear }} 年度 Scope 3 總排放量</div>
        <div class="total-value font-mono">{{ formatCo2e(report.total_co2e) }}</div>
        <div class="total-sub">GHG Protocol Categories 1–15 合計</div>
      </div>

      <!-- 15 類別長條圖 -->
      <div class="card">
        <div class="card-title" style="margin-bottom:16px;">各類別排放明細</div>
        <div class="bar-chart">
          <div v-for="cat in sortedCategories" :key="cat.category_number" class="bar-row">
            <div class="bar-label">
              <span class="bar-num font-mono">{{ String(cat.category_number).padStart(2, '0') }}</span>
              <span class="bar-name">{{ cat.category_name }}</span>
            </div>
            <div class="bar-track">
              <div
                class="bar-fill"
                :style="{ width: barWidth(cat.co2e) + '%' }"
                :class="cat.co2e > 0 ? 'bar-active' : 'bar-empty'"
              ></div>
            </div>
            <div class="bar-value font-mono">{{ formatCo2e(cat.co2e) }}</div>
          </div>
        </div>
      </div>
    </template>

    <div v-else class="empty-state">
      <p>尚無 {{ selectedYear }} 年度排放資料</p>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { reportsApi, type Scope3Report, type Scope3Category } from '@/api/modules/reports'

export default defineComponent({
  name: 'ReportsView',

  data() {
    const currentYear = new Date().getFullYear()
    return {
      isLoading: false,
      isExporting: false,
      selectedYear: currentYear,
      availableYears: Array.from({ length: 6 }, (_, i) => currentYear - i),
      report: null as Scope3Report | null,
    }
  },

  computed: {
    sortedCategories(): Scope3Category[] {
      if (!this.report) return []
      return [...this.report.categories].sort((a, b) => b.co2e - a.co2e)
    },
    maxCo2e(): number {
      if (!this.report?.categories.length) return 1
      return Math.max(...this.report.categories.map(c => c.co2e), 1)
    },
  },

  mounted() { this.loadReport() },

  methods: {
    async loadReport() {
      this.isLoading = true
      this.report = null
      try {
        const { data } = await reportsApi.scope3(this.selectedYear)
        if (data.success && data.data.total_co2e > 0) {
          this.report = data.data
        }
      } catch {
        this.report = null
      } finally {
        this.isLoading = false
      }
    },

    async downloadExport() {
      this.isExporting = true
      try {
        await reportsApi.exportScope3(this.selectedYear, 'xlsx')
      } catch {
        alert('匯出失敗，請稍後再試')
      } finally {
        this.isExporting = false
      }
    },

    barWidth(co2e: number): number {
      return (co2e / this.maxCo2e) * 100
    },

    formatCo2e(val: number): string {
      if (val >= 1_000_000) return (val / 1_000_000).toFixed(2) + ' MtCO2e'
      if (val >= 1_000) return (val / 1_000).toFixed(2) + ' tCO2e'
      return val.toFixed(1) + ' kgCO2e'
    },
  },
})
</script>

<style scoped>
.total-card { margin-bottom: 16px; text-align: center; padding: 32px; }
.total-value { font-size: 48px; font-weight: 800; color: var(--accent); margin: 8px 0; }
.total-sub { font-size: 13px; color: var(--text-secondary); }

.bar-chart { display: flex; flex-direction: column; gap: 10px; }

.bar-row { display: flex; align-items: center; gap: 12px; }

.bar-label { display: flex; align-items: center; gap: 8px; min-width: 240px; }
.bar-num { font-size: 12px; color: var(--text-secondary); min-width: 24px; }
.bar-name { font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.bar-track { flex: 1; height: 20px; background: var(--surface-2); border-radius: 4px; overflow: hidden; }
.bar-fill { height: 100%; border-radius: 4px; transition: width 0.5s ease; }
.bar-active { background: var(--accent); }
.bar-empty { background: var(--surface-2); }

.bar-value { font-size: 12px; min-width: 90px; text-align: right; color: var(--text-secondary); }
</style>
