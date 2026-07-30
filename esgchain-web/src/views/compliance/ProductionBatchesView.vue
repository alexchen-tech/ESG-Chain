<template>
  <div class="page-container">
    <div class="breadcrumb">
      <span class="breadcrumb-parent">商品合規管理</span>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current">生產批號</span>
    </div>

    <div class="page-header">
      <div>
        <h1 class="page-title">生產批號</h1>
        <p class="page-subtitle">管理供應商生產批號、原料溯源與 EUDR DDS 前置資料</p>
      </div>
      <button class="btn btn-secondary" @click="loadBatches" :disabled="loading">
        <span v-if="loading">載入中…</span>
        <span v-else>↻ 重新整理</span>
      </button>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
      <select v-model="filterMatchedStatus" class="filter-select" @change="resetAndLoad">
        <option value="">全部狀態</option>
        <option value="matched">已匹配</option>
        <option value="unmatched">待匹配</option>
      </select>
      <select v-model="filterSupplierId" class="filter-select" @change="resetAndLoad">
        <option value="">全部工廠</option>
        <option v-for="s in supplierOptions" :key="s.id" :value="s.id">{{ maskSupplierName(s.name) }}</option>
      </select>
      <span class="filter-count" v-if="!loading">{{ pagination.total }} 筆</span>
    </div>

    <!-- Table -->
    <div class="card">
      <div v-if="loading" class="loading-state">
        <div class="loading-spinner"></div>
        <span>載入中…</span>
      </div>
      <div v-else-if="batches.length === 0" class="empty-state">
        <div class="empty-icon">📦</div>
        <p>尚無生產批號資料</p>
        <p class="empty-hint">透過 ERP Webhook 或 CSV 匯入批號後資料將自動同步</p>
      </div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th style="width:170px;">批號</th>
            <th>產品</th>
            <th>工廠</th>
            <th style="width:110px;">匹配狀態</th>
            <th style="width:160px;">數量</th>
            <th style="width:110px;">生產日期</th>
            <th style="width:140px;">批次 PCF</th>
            <th style="width:80px;">來源</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="b in batches" :key="b.id"
            class="data-row"
            @click="$router.push(`/compliance/production-batches/${b.id}`)"
          >
            <td>
              <span class="batch-no font-mono">{{ b.erp_batch_no }}</span>
            </td>
            <td>
              <template v-if="b.sales_product_name">
                <div class="product-name">{{ b.sales_product_name }}</div>
                <div class="product-code font-mono">{{ b.sales_product_model_no || b.sales_product_code }}</div>
              </template>
              <span v-else class="text-muted">—</span>
            </td>
            <td>
              <span class="supplier-name">{{ maskSupplierName(b.supplier_name) || '—' }}</span>
            </td>
            <td>
              <span :class="b.matched ? 'status-badge status-matched' : 'status-badge status-pending'">
                {{ b.matched ? '✓ 已匹配' : '待匹配' }}
              </span>
            </td>
            <td class="font-mono qty-cell">
              {{ b.quantity }} <span class="unit-label">{{ b.unit }}</span>
            </td>
            <td class="font-mono date-cell">{{ b.production_date || '—' }}</td>
            <td>
              <span v-if="b.lot_pcf" class="font-mono pcf-val">{{ b.lot_pcf }} <span class="pcf-unit">kgCO₂e</span></span>
              <span v-else class="text-muted">—</span>
            </td>
            <td>
              <span class="source-badge" :class="'source-' + b.source">{{ b.source }}</span>
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
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { productionBatchApi, type ProductionBatch } from '@/api/modules/productionBatch'
import { suppliersApi } from '@/api/modules/suppliers'
import { maskSupplierName } from '@/utils/maskName'

export default defineComponent({
  name: 'ProductionBatchesView',

  data() {
    return {
      loading: false,
      batches: [] as ProductionBatch[],
      pagination: { current_page: 1, per_page: 20, total: 0, last_page: 1 },
      filterMatchedStatus: '' as '' | 'matched' | 'unmatched',
      filterSupplierId: '',
      supplierOptions: [] as { id: string; name: string }[],
    }
  },

  async mounted() {
    await Promise.all([this.loadBatches(), this.loadSupplierOptions()])
  },

  methods: {
    maskSupplierName,
    async loadBatches() {
      this.loading = true
      try {
        const filters: Record<string, string | number> = {
          page: this.pagination.current_page,
          per_page: this.pagination.per_page,
        }
        if (this.filterMatchedStatus) filters.matched_status = this.filterMatchedStatus
        if (this.filterSupplierId) filters.supplier_id = this.filterSupplierId

        const res = await productionBatchApi.list(filters)
        this.batches = res.data.data
        this.pagination = res.data.pagination
      } finally {
        this.loading = false
      }
    },

    // 下拉選單獨立取得全部工廠清單，不可依賴分頁後的批號列表（否則未出現在當頁的工廠會消失於選單）
    async loadSupplierOptions() {
      try {
        const res = await suppliersApi.list({ per_page: 200 })
        this.supplierOptions = res.data.data.map(s => ({ id: s.id, name: s.name }))
      } catch { /* silent */ }
    },

    goPage(page: number) { this.pagination.current_page = page; this.loadBatches() },
    resetAndLoad() { this.pagination.current_page = 1; this.loadBatches() },
  },
})
</script>

<style scoped>
.filter-bar {
  display: flex;
  gap: 10px;
  margin-bottom: 16px;
  align-items: center;
}
.filter-select {
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 6px 28px 6px 10px;
  font-size: 13px;
  background: var(--surface);
  color: var(--text-primary);
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23888' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 8px center;
  cursor: pointer;
}
.filter-count {
  font-size: 12px;
  color: var(--text-secondary);
  margin-left: 4px;
}

.card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
}

.loading-state {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 60px;
  color: var(--text-secondary);
  font-size: 14px;
}
.loading-spinner {
  width: 18px;
  height: 18px;
  border: 2px solid var(--border);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.empty-state {
  padding: 64px 32px;
  text-align: center;
  color: var(--text-secondary);
  font-size: 14px;
}
.empty-icon { font-size: 32px; margin-bottom: 10px; }
.empty-hint { font-size: 12px; margin-top: 4px; opacity: 0.75; }

.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.data-table th {
  background: var(--surface-2);
  padding: 10px 16px;
  text-align: left;
  font-size: 11px;
  font-weight: 700;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  border-bottom: 1px solid var(--border);
}
.data-table td {
  padding: 12px 16px;
  border-bottom: 1px solid var(--border);
  color: var(--text-primary);
  vertical-align: middle;
}
.data-row:last-child td { border-bottom: none; }
.data-row {
  cursor: pointer;
  transition: background 0.1s;
}
.data-row:hover { background: var(--surface-2); }

.batch-no {
  font-size: 13px;
  font-weight: 600;
  color: var(--text-primary);
  letter-spacing: 0.01em;
}
.supplier-name {
  font-size: 13px;
  color: var(--text-primary);
}
.product-name { font-size: 13px; color: var(--text-primary); }
.product-code { font-size: 11px; color: var(--text-secondary); margin-top: 1px; }

.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 9px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
  white-space: nowrap;
}
.status-matched {
  background: #dcfce7;
  color: #16a34a;
}
.status-pending {
  background: #fff7ed;
  color: #ea580c;
}

.qty-cell { color: var(--text-primary); }
.unit-label { font-size: 11px; color: var(--text-secondary); margin-left: 2px; }
.date-cell { color: var(--text-secondary); font-size: 12px; }

.pcf-val { color: var(--text-primary); font-size: 12px; }
.pcf-unit { font-size: 10px; color: var(--text-secondary); }

.text-muted { color: var(--text-secondary); }

.source-badge {
  display: inline-block;
  padding: 2px 7px;
  border-radius: 4px;
  font-size: 11px;
  font-family: 'Fira Code', monospace;
  font-weight: 600;
}
.source-webhook { background: #eff6ff; color: #2563eb; }
.source-csv { background: #f5f3ff; color: #7c3aed; }
.source-manual { background: var(--surface-2); color: var(--text-secondary); border: 1px solid var(--border); }
</style>
