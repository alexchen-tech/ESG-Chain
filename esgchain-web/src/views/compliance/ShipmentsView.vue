<template>
  <div class="page-wrapper">
    <div class="breadcrumb">
      <span class="breadcrumb-parent">商品合規管理</span>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current">出口申報</span>
    </div>
    <div class="page-header">
      <div>
        <h1 class="page-title">出口申報</h1>
        <p class="page-subtitle">管理出口批次、EUDR DDS 申報草稿與批號分配</p>
      </div>
      <button class="btn btn-primary" @click="openCreateModal">+ 新增申報</button>
    </div>

    <div class="card">
      <div v-if="loading" class="loading-state">載入中…</div>
      <div v-else-if="shipments.length === 0" class="empty-state">
        <p>尚無出口申報記錄</p>
      </div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>申報批號</th>
            <th>客戶</th>
            <th>目標市場</th>
            <th style="white-space:nowrap;">出口日期</th>
            <th>EUDR 狀態</th>
            <th>商品項目</th>
            <th style="white-space:nowrap;">建立時間</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in shipments" :key="s.id" class="data-row">
            <td class="font-mono">
              <router-link :to="`/compliance/shipments/${s.id}`" class="link">{{ s.shipment_no }}</router-link>
            </td>
            <td class="text-muted" style="font-size:12px;">{{ (s as any).customer_name || '—' }}</td>
            <td><span class="market-badge">{{ s.target_market }}</span></td>
            <td style="white-space:nowrap;">{{ s.export_date || '—' }}</td>
            <td><span :class="eudrBadgeClass(s.eudr_dds_status)" class="badge">{{ eudrLabel(s.eudr_dds_status) }}</span></td>
            <td>{{ s.line_count }} 項</td>
            <td class="text-muted" style="white-space:nowrap;">{{ s.created_at.slice(0,10) }}</td>
            <td><button class="remove-btn" @click.stop="deleteShipment(s)">×</button></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create Modal -->
    <div v-if="createModal" class="modal-overlay" @click.self="createModal = false">
      <div class="modal">
        <div class="modal-header">
          <h3>新增出口申報</h3>
          <button class="modal-close" @click="createModal = false">×</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">申報批號（留空自動產生）</label>
            <input v-model="form.shipment_no" class="form-input font-mono" placeholder="SHIP-202606-001" />
          </div>
          <div class="form-group">
            <label class="form-label">客戶（下游採購方）</label>
            <select v-model="form.customer_id" class="form-input" @change="onCustomerChange">
              <option value="">— 不指定 —</option>
              <option v-for="c in allCustomers" :key="c.id" :value="c.id">{{ c.name }} ({{ c.code }})</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">客戶 PO 單號</label>
            <input v-model="form.customer_po_no" class="form-input font-mono" placeholder="PO-2026-001" />
          </div>
          <div class="form-group">
            <label class="form-label">目標市場</label>
            <select v-model="form.target_market" class="form-input">
              <option value="">選擇市場</option>
              <option value="EU">EU（歐盟）</option>
              <option value="UK">UK（英國）</option>
              <option value="US">US（美國）</option>
              <option value="JP">JP（日本）</option>
              <option value="OTHER">其他</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">出口日期</label>
            <input v-model="form.export_date" class="form-input" type="date" />
          </div>
          <div class="form-group">
            <label class="form-label">EUDR 狀態</label>
            <select v-model="form.eudr_dds_status" class="form-input">
              <option value="draft">草稿（需申報）</option>
              <option value="not_required">不需申報</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">備註</label>
            <textarea v-model="form.notes" class="form-input" rows="2" placeholder="補充說明…"></textarea>
          </div>
          <div v-if="warnings.length" class="warn-block">
            <div v-for="w in warnings" :key="w" class="warn-item">⚠ {{ w }}</div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="createModal = false">取消</button>
          <button class="btn btn-primary" :disabled="saving" @click="submitCreate">
            {{ saving ? '建立中…' : '建立申報' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { shipmentApi, type Shipment } from '@/api/modules/shipment'
import { customersApi, type Customer } from '@/api/modules/customers'

const EUDR_LABELS: Record<string, string> = {
  not_required: '不需申報',
  draft: '草稿',
  submitted: '已送出',
  approved: '已核准',
}

export default defineComponent({
  name: 'ShipmentsView',

  data() {
    return {
      loading: false,
      shipments: [] as Shipment[],
      allCustomers: [] as Customer[],
      createModal: false,
      saving: false,
      warnings: [] as string[],
      form: { shipment_no: '', customer_id: '', customer_po_no: '', target_market: '', export_date: '', eudr_dds_status: 'draft', notes: '' },
    }
  },

  async mounted() {
    await this.load()
    this.loadCustomers()
  },

  methods: {
    async load() {
      this.loading = true
      try {
        const res = await shipmentApi.list()
        this.shipments = res.data.data
      } finally {
        this.loading = false
      }
    },

    async loadCustomers() {
      try {
        const { data } = await customersApi.list({ per_page: 200, status: 'active' })
        this.allCustomers = data.data
      } catch { /* silent */ }
    },
    openCreateModal() {
      this.form = { shipment_no: '', customer_id: '', customer_po_no: '', target_market: '', export_date: '', eudr_dds_status: 'draft', notes: '' }
      this.warnings = []
      this.createModal = true
    },

    async submitCreate() {
      this.saving = true
      try {
        const payload: any = { eudr_dds_status: this.form.eudr_dds_status }
        if (this.form.shipment_no)  payload.shipment_no  = this.form.shipment_no
        if (this.form.target_market) payload.target_market = this.form.target_market
        if (this.form.export_date)  payload.export_date  = this.form.export_date
        if (this.form.notes)        payload.notes        = this.form.notes
        if (this.form.customer_id)  payload.customer_id  = this.form.customer_id
        if (this.form.customer_po_no) payload.customer_po_no = this.form.customer_po_no
        const res = await shipmentApi.create(payload)
        const { data: shipment, warnings } = res.data
        if (warnings?.length) {
          this.warnings = warnings
        }
        this.shipments.unshift(shipment)
        this.createModal = false
        this.$router.push(`/compliance/shipments/${shipment.id}`)
      } finally {
        this.saving = false
      }
    },

    async deleteShipment(s: Shipment) {
      if (!confirm(`確認刪除申報批次 ${s.shipment_no}？`)) return
      await shipmentApi.destroy(s.id)
      this.shipments = this.shipments.filter(x => x.id !== s.id)
    },

    onCustomerChange() {
      const cust = this.allCustomers.find(c => c.id === this.form.customer_id)
      if (cust && !this.form.target_market) {
        const EU = ['AT','BE','BG','CY','CZ','DE','DK','EE','ES','FI','FR','GR','HR','HU','IE','IT','LT','LU','LV','MT','NL','PL','PT','RO','SE','SI','SK']
        const NA = ['US','CA','MX']
        const APAC = ['JP','KR','CN','TW','SG','AU','NZ','TH','VN','ID','MY','PH','IN']
        const cc = cust.country_code.toUpperCase()
        if (EU.includes(cc)) this.form.target_market = 'EU'
        else if (NA.includes(cc)) this.form.target_market = 'US'
        else this.form.target_market = cc
      }
    },
    eudrLabel(status: string) { return EUDR_LABELS[status] || status },
    eudrBadgeClass(status: string) {
      return {
        'badge-gray': status === 'not_required',
        'badge-orange': status === 'draft',
        'badge-blue': status === 'submitted',
        'badge-green': status === 'approved',
      }
    },
  },
})
</script>

<style scoped>
.page-wrapper { padding: 24px; max-width: 1100px; }

.card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
.loading-state, .empty-state { padding: 48px; text-align: center; color: var(--text-secondary); font-size: 14px; }
.data-row:last-child td { border-bottom: none; }
.font-mono { font-family: 'Fira Code', monospace; }
.text-muted { color: var(--text-secondary); }
.link { color: var(--accent); text-decoration: none; font-weight: 600; }
.link:hover { text-decoration: underline; }

.market-badge { background: #f1f5f9; border-radius: 4px; padding: 2px 6px; font-size: 11px; font-weight: 700; font-family: 'Fira Code', monospace; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
.remove-btn { background: none; border: none; color: #dc2626; cursor: pointer; font-size: 16px; }

/* Modal */
.modal-body { padding: 0 24px; display: flex; flex-direction: column; gap: 14px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-label { font-size: 12px; font-weight: 600; color: var(--text-secondary); }
.req { color: #dc2626; }
.form-input { border: 1px solid var(--border); border-radius: 6px; padding: 8px 10px; font-size: 13px; background: var(--surface); color: var(--text-primary); }
.form-input:focus { outline: none; border-color: var(--accent); }
.warn-block { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 6px; padding: 10px 14px; }
.warn-item { font-size: 13px; color: #c2410c; }
</style>
