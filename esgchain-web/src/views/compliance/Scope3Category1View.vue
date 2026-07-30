<template>
  <div class="page-container">
    <div class="breadcrumb">
      <span class="breadcrumb-parent">商品合規管理</span>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current">產品碳足跡調查</span>
    </div>
    <div class="page-header">
      <div>
        <h1 class="page-title">產品碳足跡調查</h1>
        <p class="page-subtitle">採購商品與服務排放，依物料維度彙總</p>
      </div>
    </div>

    <div class="disclaimer-banner">
      此為系統依 BOM 配方量與批次數量估算，非實際物料耗用量，亦非經第三方查證數字。
    </div>

    <!-- 期間篩選 -->
    <div class="filter-bar">
      <label class="filter-label">批次生產期間（起）</label>
      <input type="date" v-model="periodFrom" class="filter-input" />
      <label class="filter-label">批次生產期間（迄）</label>
      <input type="date" v-model="periodTo" class="filter-input" />
      <button class="btn btn-primary btn-sm" :disabled="isLoading || !periodFrom || !periodTo" @click="loadRollup">
        {{ isLoading ? '查詢中…' : '查詢' }}
      </button>
    </div>

    <div v-if="isLoading" class="loading-mask" style="padding:64px;text-align:center;">載入中...</div>

    <template v-else-if="rollup">
      <div class="kpi-grid">
        <div class="kpi-card kpi-card--green">
          <div class="kpi-value kpi-value--green font-mono">{{ formatNumber(rollup.total_emissions) }}</div>
          <div class="kpi-label">期間總排放量（kgCO2e）</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-value font-mono">{{ rollup.groups.length }}</div>
          <div class="kpi-label">涉及物料群組數</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-value font-mono">{{ totalMaterialCount }}</div>
          <div class="kpi-label">涉及物料項目數</div>
        </div>
      </div>

      <div v-if="rollup.groups.length === 0" class="card" style="padding:48px;text-align:center;color:var(--text-secondary);">
        此期間無生產批次或無對應 PCF 快照資料
      </div>

      <div v-for="group in rollup.groups" :key="group.material_group_id ?? 'ungrouped'" class="card" style="padding:0;overflow:hidden;margin-bottom:16px;">
        <div class="section-title" style="padding:16px 20px 0;display:flex;justify-content:space-between;align-items:baseline;">
          <span>{{ group.material_group_name }}</span>
          <span class="font-mono" style="font-size:13px;color:var(--text-secondary);">
            {{ formatNumber(group.total_emissions) }} kgCO2e
          </span>
        </div>
        <table class="data-table">
          <thead>
            <tr>
              <th style="padding-left:20px;">物料項目</th>
              <th style="width:140px;text-align:right;">排放量（kgCO2e）</th>
              <th style="width:90px;text-align:center;">佔比</th>
              <th style="width:220px;">資料品質分布</th>
              <th style="width:32px;"></th>
            </tr>
          </thead>
          <tbody>
            <template v-for="material in group.materials" :key="material.material_item_id">
              <tr class="clickable-row" @click="toggleDrill(material.material_item_id)">
                <td style="padding-left:20px;">{{ material.material_name ?? material.material_item_id }}</td>
                <td style="text-align:right;" class="font-mono">{{ formatNumber(material.total_emissions) }}</td>
                <td style="text-align:center;" class="font-mono">{{ material.percentage }}%</td>
                <td>
                  <span
                    v-for="(pct, quality) in material.data_quality_breakdown"
                    :key="quality"
                    class="stat-badge"
                    :class="qualityBadgeClass(String(quality))"
                    style="margin-right:6px;"
                  >
                    {{ qualityLabel(String(quality)) }} {{ pct }}%
                  </span>
                </td>
                <td style="text-align:center;">{{ drillOpenId === material.material_item_id ? '▲' : '▼' }}</td>
              </tr>
              <tr v-if="drillOpenId === material.material_item_id">
                <td colspan="5" style="padding:0;background:var(--surface-2);">
                  <div v-if="drillLoading" style="padding:16px;color:var(--text-secondary);">下鑽資料載入中…</div>
                  <div v-else-if="drillData" style="padding:16px 20px;">
                    <div style="font-size:13px;color:var(--text-secondary);margin-bottom:8px;">
                      此物料在期間內用於以下產品／批次：
                    </div>
                    <div v-for="product in drillData.products" :key="product.sales_product_id" style="margin-bottom:12px;">
                      <div style="font-weight:600;display:flex;justify-content:space-between;">
                        <span>{{ product.product_name ?? product.sales_product_id }}（{{ product.product_code }}）</span>
                        <span class="font-mono">{{ formatNumber(product.contribution) }} kgCO2e</span>
                      </div>
                      <div style="font-size:12px;color:var(--text-secondary);margin:4px 0;">
                        供應商：{{ product.material_supplier_name ? maskSupplierName(product.material_supplier_name) : '未指定' }}
                        ｜資料品質：{{ qualityLabel(product.data_quality ?? 'unknown') }}
                        <span v-if="product.is_estimated">（估算）</span>
                      </div>
                      <table class="data-table" style="margin-top:4px;">
                        <thead>
                          <tr>
                            <th>批號</th>
                            <th>生產日期</th>
                            <th style="text-align:right;">批次數量</th>
                            <th style="text-align:right;">貢獻量（kgCO2e）</th>
                            <th>供應商</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="batch in product.batches" :key="batch.batch_id">
                            <td>{{ batch.erp_batch_no ?? batch.batch_id }}</td>
                            <td>{{ batch.production_date }}</td>
                            <td style="text-align:right;" class="font-mono">{{ formatNumber(batch.quantity) }} {{ batch.unit }}</td>
                            <td style="text-align:right;" class="font-mono">{{ formatNumber(batch.contribution) }}</td>
                            <td>{{ batch.supplier_name ? maskSupplierName(batch.supplier_name) : '—' }}</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { scope3Category1Api, type Scope3MaterialRollup, type Scope3MaterialDrill } from '@/api/modules/compliance'
import { maskSupplierName } from '@/utils/maskName'

export default defineComponent({
  name: 'Scope3Category1View',
  data() {
    return {
      periodFrom: '' as string,
      periodTo: '' as string,
      isLoading: false,
      rollup: null as Scope3MaterialRollup | null,
      drillOpenId: '' as string,
      drillLoading: false,
      drillData: null as Scope3MaterialDrill | null,
    }
  },
  computed: {
    totalMaterialCount(): number {
      if (!this.rollup) return 0
      return this.rollup.groups.reduce((sum, g) => sum + g.materials.length, 0)
    },
  },
  created() {
    const today = new Date()
    const start = new Date(today.getFullYear(), 0, 1)
    this.periodFrom = start.toISOString().slice(0, 10)
    this.periodTo = today.toISOString().slice(0, 10)
    this.loadRollup()
  },
  methods: {
    maskSupplierName,
    formatNumber(v: number): string {
      return Number(v ?? 0).toLocaleString('zh-TW', { maximumFractionDigits: 2 })
    },
    qualityLabel(quality: string): string {
      const map: Record<string, string> = {
        primary: '實測',
        secondary: '次級',
        estimated: '估算',
        missing: '缺值',
        unknown: '未知',
      }
      return map[quality] ?? quality
    },
    qualityBadgeClass(quality: string): string {
      const map: Record<string, string> = {
        primary: 'stat-badge--green',
        secondary: 'stat-badge--gray',
        estimated: 'stat-badge--orange',
        missing: 'stat-badge--red',
      }
      return map[quality] ?? 'stat-badge--gray'
    },
    async loadRollup() {
      if (!this.periodFrom || !this.periodTo) return
      this.isLoading = true
      this.drillOpenId = ''
      this.drillData = null
      try {
        const res = await scope3Category1Api.materialRollup({ period_from: this.periodFrom, period_to: this.periodTo, page: 1, per_page: 20 })
        this.rollup = res.data.data
      } finally {
        this.isLoading = false
      }
    },
    async toggleDrill(materialItemId: string) {
      if (this.drillOpenId === materialItemId) {
        this.drillOpenId = ''
        this.drillData = null
        return
      }
      this.drillOpenId = materialItemId
      this.drillLoading = true
      this.drillData = null
      try {
        const res = await scope3Category1Api.materialDrill(materialItemId, { period_from: this.periodFrom, period_to: this.periodTo })
        this.drillData = res.data.data
      } finally {
        this.drillLoading = false
      }
    },
  },
})
</script>

<style scoped>
.disclaimer-banner {
  background: var(--accent-soft);
  border: 1px solid var(--accent-soft-border);
  color: var(--text-primary);
  font-size: 13px;
  padding: 10px 16px;
  border-radius: 6px;
  margin-bottom: 16px;
}

.filter-label {
  font-size: 13px;
  color: var(--text-secondary);
  white-space: nowrap;
}

.kpi-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.75rem;
  margin-bottom: 1.25rem;
}
.kpi-card {
  background: var(--surface-2);
  border-radius: 8px;
  padding: 16px 20px;
}
.kpi-card--green {
  background: var(--accent-soft);
  border: 1px solid var(--accent-soft-border);
}
.kpi-value {
  font-size: 24px;
  font-weight: 700;
  color: var(--text-primary);
}
.kpi-value--green {
  color: var(--accent);
}
.kpi-label {
  font-size: 13px;
  color: var(--text-secondary);
  margin-top: 4px;
}

.clickable-row {
  cursor: pointer;
}
.clickable-row:hover {
  background: var(--surface-2);
}
</style>
