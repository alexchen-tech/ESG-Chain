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
      <select v-model="filterMatchedStatus" class="filter-select" @change="loadBatches">
        <option value="">全部狀態</option>
        <option value="matched">已匹配</option>
        <option value="unmatched">待匹配</option>
      </select>
      <select v-model="filterSupplierId" class="filter-select" @change="loadBatches">
        <option value="">全部工廠</option>
        <option v-for="s in supplierOptions" :key="s.id" :value="s.id">{{ s.name }}</option>
      </select>
      <span class="filter-count" v-if="!loading">{{ batches.length }} 筆</span>
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
            v-for="b in batches"
            :key="b.id"
            class="data-row"
            :class="{ active: selectedBatch?.id === b.id }"
            @click="openDrawer(b)"
          >
            <td>
              <span class="batch-no font-mono">{{ b.erp_batch_no }}</span>
            </td>
            <td>
              <template v-if="b.sales_product_name">
                <div class="product-name">{{ b.sales_product_name }}</div>
                <div class="product-code font-mono">{{ b.sales_product_model_no || b.sales_product_code }}</div>
              </template>
              <span v-else-if="b.trade_good_name">{{ b.trade_good_name }}</span>
              <span v-else class="text-muted">—</span>
            </td>
            <td>
              <span class="supplier-name">{{ b.supplier_name || '—' }}</span>
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

    <!-- Right Drawer -->
    <div v-if="drawerOpen" class="drawer-overlay" @click.self="closeDrawer">
      <div class="drawer">
        <div class="drawer-header">
          <div>
            <div class="drawer-title font-mono">{{ selectedBatch?.erp_batch_no }}</div>
            <div class="drawer-subtitle">{{ selectedBatch?.supplier_name }}</div>
          </div>
          <button class="drawer-close" @click="closeDrawer">×</button>
        </div>

        <div class="drawer-body">
          <!-- Batch Info -->
          <div class="drawer-section">
            <div class="drawer-section-title">批號資訊</div>
            <div class="info-grid">
              <div class="info-row">
                <span class="info-label">ERP 採購單號</span>
                <span class="info-value font-mono">{{ selectedBatch?.erp_order_no || '—' }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">生產日期</span>
                <span class="info-value">{{ selectedBatch?.production_date || '—' }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">數量</span>
                <span class="info-value font-mono">{{ selectedBatch?.quantity }} {{ selectedBatch?.unit }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">批次 PCF</span>
                <span class="info-value font-mono">
                  <span v-if="selectedBatch?.lot_pcf">
                    {{ selectedBatch.lot_pcf }} kgCO₂e
                    <span class="pcf-source"> ({{ selectedBatch.lot_pcf_source }})</span>
                  </span>
                  <span v-else class="text-muted">—</span>
                </span>
              </div>
              <div class="info-row">
                <span class="info-label">來源</span>
                <span class="source-badge" :class="selectedBatch ? 'source-' + selectedBatch.source : ''">{{ selectedBatch?.source }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">匹配狀態</span>
                <span :class="selectedBatch?.matched ? 'status-badge status-matched' : 'status-badge status-pending'">
                  {{ selectedBatch?.matched ? '✓ 已匹配' : '待匹配' }}
                </span>
              </div>
              <div class="info-row">
                <span class="info-label">所屬產品</span>
                <span v-if="selectedBatch?.sales_product_name" class="info-value">
                  {{ selectedBatch.sales_product_name }}
                  <span class="font-mono" style="font-size:11px;color:var(--text-secondary);">
                    {{ selectedBatch.sales_product_model_no || selectedBatch.sales_product_code }}
                  </span>
                </span>
                <span v-else class="info-value text-muted">—</span>
              </div>
            </div>
          </div>

          <!-- 出口市場審查 -->
          <div class="drawer-section">
            <div class="drawer-section-header">
              <div class="drawer-section-title">出口市場審查</div>
              <div style="display:flex;gap:6px;align-items:center;">
                <select v-model="reviewMarket" class="form-select" style="width:96px;">
                  <option v-for="m in ['EU','US','UK','JP']" :key="m" :value="m">{{ m }}</option>
                </select>
                <button class="btn btn-secondary btn-sm" :disabled="reviewing" @click="runReview">
                  {{ reviewing ? '審查中…' : '執行審查' }}
                </button>
              </div>
            </div>

            <div v-if="reviewsLoading" class="text-muted" style="font-size:12px;">載入中…</div>
            <div v-else-if="!exportReviews.length" class="text-muted" style="font-size:12px;padding:8px 0;">
              尚未設定出口市場，選擇市場後執行合規審查
            </div>
            <div v-else>
              <div v-for="r in exportReviews" :key="r.id" class="review-card">
                <div class="review-head">
                  <span class="review-market font-mono">{{ r.market }}</span>
                  <span class="review-status" :class="`review-status--${r.status}`">
                    {{ { pass: '✓ 通過', warning: '⚠ 警告', fail: '✕ 未通過', pending: '待審' }[r.status] }}
                  </span>
                  <span class="review-time font-mono">{{ r.reviewed_at?.slice(0, 10) }}</span>
                  <button class="btn btn-danger btn-sm" style="margin-left:auto;" @click="removeReview(r)">✕</button>
                </div>
                <div v-for="f in r.findings" :key="f.check" class="review-finding">
                  <span class="finding-dot" :class="`finding-dot--${f.status}`"></span>
                  <span class="finding-label">{{ f.label }}</span>
                  <span class="finding-detail">{{ f.detail }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Raw Material Origins -->
          <div class="drawer-section">
            <div class="drawer-section-header">
              <div class="drawer-section-title">原料溯源</div>
              <button class="btn btn-secondary btn-sm" @click="showOriginForm = !showOriginForm">
                {{ showOriginForm ? '取消' : '+ 新增' }}
              </button>
            </div>

            <!-- Add Form -->
            <div v-if="showOriginForm" class="origin-form">
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">原料名稱 <span class="req">*</span></label>
                  <input v-model="originForm.material_name" class="form-input" placeholder="如：有機棉" />
                </div>
                <div class="form-group">
                  <label class="form-label">原產國 (ISO) <span class="req">*</span></label>
                  <input v-model="originForm.origin_country" class="form-input" placeholder="TW" maxlength="2" style="text-transform:uppercase;" />
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">農場 / 設施名稱</label>
                <input v-model="originForm.facility_name" class="form-input" placeholder="Green Farm Co." />
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">GPS 緯度</label>
                  <input v-model="originForm.gps_lat" class="form-input font-mono" placeholder="23.697810" type="number" step="0.000001" />
                </div>
                <div class="form-group">
                  <label class="form-label">GPS 經度</label>
                  <input v-model="originForm.gps_lng" class="form-input font-mono" placeholder="120.960515" type="number" step="0.000001" />
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">收成年份</label>
                  <input v-model="originForm.harvest_year" class="form-input font-mono" placeholder="2025" type="number" maxlength="4" />
                </div>
                <div class="form-group">
                  <label class="form-label">認證編號</label>
                  <input v-model="originForm.certification_ref" class="form-input" placeholder="GOTS-2025-XXXX" />
                </div>
              </div>
              <button
                class="btn btn-primary btn-sm"
                :disabled="!originForm.material_name || !originForm.origin_country || originSaving"
                @click="submitOrigin"
              >{{ originSaving ? '儲存中…' : '新增溯源記錄' }}</button>
            </div>

            <!-- Origins List -->
            <div v-if="selectedBatch?.raw_material_origins?.length === 0 && !showOriginForm" class="origin-empty">
              尚無原料溯源資料
            </div>
            <div v-for="o in selectedBatch?.raw_material_origins" :key="o.id" class="origin-card">
              <div class="origin-header">
                <span class="origin-name">{{ o.material_name }}</span>
                <span class="origin-country">{{ o.origin_country }}</span>
                <button class="remove-btn" @click="deleteOrigin(o.id)">×</button>
              </div>
              <div v-if="o.facility_name" class="origin-detail">{{ o.facility_name }}</div>
              <div v-if="o.gps_lat && o.gps_lng" class="origin-gps">
                <span class="font-mono">{{ formatGps(o.gps_lat, o.gps_lng) }}</span>
                <a :href="googleMapsUrl(o.gps_lat, o.gps_lng)" target="_blank" class="maps-link">在地圖查看</a>
              </div>
              <div v-if="o.harvest_year" class="origin-detail">收成年份：{{ o.harvest_year }}</div>
              <div v-if="o.certification_ref" class="origin-detail cert">{{ o.certification_ref }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { productionBatchApi, rawMaterialOriginApi, type ProductionBatch, type RawMaterialOrigin, type BatchExportReview } from '@/api/modules/productionBatch'

const ORIGIN_FORM_DEFAULT = {
  material_name: '',
  origin_country: '',
  facility_name: '',
  gps_lat: '',
  gps_lng: '',
  harvest_year: '',
  certification_ref: '',
}

export default defineComponent({
  name: 'ProductionBatchesView',

  data() {
    return {
      loading: false,
      batches: [] as ProductionBatch[],
      filterMatchedStatus: '' as '' | 'matched' | 'unmatched',
      filterSupplierId: '',
      supplierOptions: [] as { id: string; name: string }[],

      // Drawer
      drawerOpen: false,
      selectedBatch: null as ProductionBatch | null,

      // Origins
      showOriginForm: false,
      // 出口市場審查
      exportReviews: [] as BatchExportReview[],
      reviewsLoading: false,
      reviewMarket: 'EU',
      reviewing: false,
      originForm: { ...ORIGIN_FORM_DEFAULT } as Record<string, string>,
      originSaving: false,
    }
  },

  async mounted() {
    await this.loadBatches()
  },

  methods: {
    async loadBatches() {
      this.loading = true
      try {
        const filters: Record<string, string> = {}
        if (this.filterMatchedStatus) filters.matched_status = this.filterMatchedStatus
        if (this.filterSupplierId) filters.supplier_id = this.filterSupplierId

        const res = await productionBatchApi.list(filters)
        this.batches = res.data.data

        const seen = new Set<string>()
        this.supplierOptions = []
        for (const b of this.batches) {
          if (b.supplier_id && !seen.has(b.supplier_id)) {
            seen.add(b.supplier_id)
            this.supplierOptions.push({ id: b.supplier_id, name: b.supplier_name || b.supplier_id })
          }
        }
      } finally {
        this.loading = false
      }
    },

    async openDrawer(batch: ProductionBatch) {
      this.selectedBatch = batch
      this.drawerOpen = true
      this.showOriginForm = false
      this.originForm = { ...ORIGIN_FORM_DEFAULT }
      this.loadReviews()
    },
    // ── 出口市場審查 ──
    async loadReviews() {
      if (!this.selectedBatch) return
      this.reviewsLoading = true
      try {
        const { data } = await productionBatchApi.exportReviews(this.selectedBatch.id)
        this.exportReviews = data.data
      } catch { this.exportReviews = [] } finally { this.reviewsLoading = false }
    },
    async runReview() {
      if (!this.selectedBatch) return
      this.reviewing = true
      try {
        await productionBatchApi.runExportReview(this.selectedBatch.id, this.reviewMarket)
        await this.loadReviews()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '審查失敗')
      } finally { this.reviewing = false }
    },
    async removeReview(r: BatchExportReview) {
      if (!this.selectedBatch) return
      try {
        await productionBatchApi.deleteExportReview(this.selectedBatch.id, r.id)
        await this.loadReviews()
      } catch { /* silent */ }
    },

    closeDrawer() {
      this.drawerOpen = false
      this.selectedBatch = null
    },

    async submitOrigin() {
      if (!this.selectedBatch) return
      this.originSaving = true
      try {
        const payload: any = {
          material_name: this.originForm.material_name,
          origin_country: this.originForm.origin_country.toUpperCase(),
        }
        if (this.originForm.facility_name)    payload.facility_name    = this.originForm.facility_name
        if (this.originForm.gps_lat)          payload.gps_lat          = parseFloat(this.originForm.gps_lat)
        if (this.originForm.gps_lng)          payload.gps_lng          = parseFloat(this.originForm.gps_lng)
        if (this.originForm.harvest_year)     payload.harvest_year     = parseInt(this.originForm.harvest_year)
        if (this.originForm.certification_ref) payload.certification_ref = this.originForm.certification_ref

        const res = await rawMaterialOriginApi.create(this.selectedBatch.id, payload)
        this.selectedBatch.raw_material_origins.push(res.data.data)
        this.originForm = { ...ORIGIN_FORM_DEFAULT }
        this.showOriginForm = false
      } finally {
        this.originSaving = false
      }
    },

    async deleteOrigin(originId: string) {
      if (!this.selectedBatch) return
      if (!confirm('確認刪除此溯源記錄？')) return
      await rawMaterialOriginApi.destroy(this.selectedBatch.id, originId)
      this.selectedBatch.raw_material_origins = this.selectedBatch.raw_material_origins.filter(o => o.id !== originId)
    },

    formatGps(lat: string | null, lng: string | null): string {
      if (!lat || !lng) return ''
      const latN = parseFloat(lat)
      const lngN = parseFloat(lng)
      return `${Math.abs(latN).toFixed(6)}°${latN >= 0 ? 'N' : 'S'} ${Math.abs(lngN).toFixed(6)}°${lngN >= 0 ? 'E' : 'W'}`
    },

    googleMapsUrl(lat: string | null, lng: string | null): string {
      return `https://www.google.com/maps?q=${lat},${lng}`
    },
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
.data-row.active { background: #f0fdf4; }

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

/* 出口市場審查 */
.review-card { border: 1px solid var(--border); border-radius: 6px; padding: 10px 12px; margin-bottom: 8px; background: var(--surface-1, #fff); }
.review-head { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.review-market { font-size: 12px; font-weight: 700; padding: 1px 8px; border-radius: 3px; background: var(--surface-2); }
.review-status { font-size: 12px; font-weight: 600; }
.review-status--pass { color: #166534; }
.review-status--warning { color: #b45309; }
.review-status--fail { color: #991b1b; }
.review-status--pending { color: var(--text-secondary); }
.review-time { font-size: 11px; color: var(--text-secondary); }
.review-finding { display: flex; align-items: baseline; gap: 6px; font-size: 12px; padding: 2px 0; }
.finding-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; align-self: center; }
.finding-dot--pass { background: #16a34a; }
.finding-dot--warning { background: #f59e0b; }
.finding-dot--fail { background: #dc2626; }
.finding-label { font-weight: 600; color: var(--text-primary); white-space: nowrap; }
.finding-detail { color: var(--text-secondary); }
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

/* Drawer */
.drawer-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.28);
  z-index: 300;
  display: flex;
  justify-content: flex-end;
}
.drawer {
  width: 420px;
  background: var(--surface);
  height: 100%;
  display: flex;
  flex-direction: column;
  box-shadow: -6px 0 32px rgba(0,0,0,.12);
}
.drawer-header {
  padding: 20px;
  border-bottom: 1px solid var(--border);
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-shrink: 0;
  background: var(--surface-2);
}
.drawer-title {
  font-size: 15px;
  font-weight: 700;
  color: var(--text-primary);
  letter-spacing: 0.02em;
}
.drawer-subtitle {
  font-size: 12px;
  color: var(--text-secondary);
  margin-top: 3px;
}
.drawer-close {
  background: none;
  border: none;
  font-size: 22px;
  cursor: pointer;
  color: var(--text-secondary);
  line-height: 1;
  padding: 2px 4px;
  border-radius: 4px;
}
.drawer-close:hover { background: var(--border); }
.drawer-body {
  flex: 1;
  overflow-y: auto;
  padding: 0;
}
.drawer-section {
  padding: 16px 20px;
  border-bottom: 1px solid var(--border);
}
.drawer-section:last-child { border-bottom: none; }
.drawer-section-title {
  font-size: 11px;
  font-weight: 700;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: .06em;
  margin-bottom: 12px;
}
.drawer-section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}
.drawer-section-header .drawer-section-title { margin-bottom: 0; }

.info-grid { display: flex; flex-direction: column; gap: 10px; }
.info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
  padding: 4px 0;
  border-bottom: 1px dashed var(--border);
}
.info-row:last-child { border-bottom: none; }
.info-label { color: var(--text-secondary); font-size: 12px; }
.info-value { color: var(--text-primary); font-weight: 500; }
.pcf-source { color: var(--text-secondary); font-weight: 400; font-size: 11px; }

/* Origin Form */
.origin-form {
  background: var(--surface-2);
  border-radius: 8px;
  padding: 14px;
  margin-bottom: 12px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  border: 1px solid var(--border);
}
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.form-group { display: flex; flex-direction: column; gap: 4px; }
.form-label { font-size: 12px; font-weight: 600; color: var(--text-secondary); }
.req { color: #dc2626; }
.form-input {
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 7px 10px;
  font-size: 13px;
  background: var(--surface);
  color: var(--text-primary);
}
.form-input:focus { outline: none; border-color: var(--accent); }

/* Origin Cards */
.origin-empty {
  font-size: 13px;
  color: var(--text-secondary);
  text-align: center;
  padding: 20px 0;
  font-style: italic;
}
.origin-card {
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 12px;
  margin-bottom: 8px;
  background: var(--surface);
}
.origin-card:hover { border-color: var(--accent); }
.origin-header { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.origin-name { font-weight: 600; font-size: 13px; flex: 1; color: var(--text-primary); }
.origin-country {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 4px;
  padding: 1px 7px;
  font-size: 11px;
  font-family: 'Fira Code', monospace;
  font-weight: 700;
  color: #15803d;
}
.remove-btn { background: none; border: none; color: #dc2626; cursor: pointer; font-size: 16px; line-height: 1; padding: 0; opacity: 0.6; }
.remove-btn:hover { opacity: 1; }
.origin-detail { font-size: 12px; color: var(--text-secondary); margin-top: 4px; }
.origin-gps { display: flex; align-items: center; gap: 10px; margin-top: 4px; font-size: 12px; }
.maps-link { color: var(--accent); text-decoration: none; font-size: 12px; }
.maps-link:hover { text-decoration: underline; }
.cert { font-family: 'Fira Code', monospace; color: #7c3aed; }

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  border: none;
  padding: 8px 14px;
  transition: opacity 0.15s;
  gap: 6px;
}
.btn:disabled { opacity: .5; cursor: not-allowed; }
.btn-primary { background: var(--accent); color: #fff; }
.btn-primary:hover:not(:disabled) { opacity: .85; }
.btn-secondary {
  background: var(--surface-2);
  color: var(--text-primary);
  border: 1px solid var(--border);
}
.btn-secondary:hover:not(:disabled) { background: var(--border); }
.btn-sm { padding: 5px 10px; font-size: 12px; }

.font-mono { font-family: 'Fira Code', monospace; }
</style>
