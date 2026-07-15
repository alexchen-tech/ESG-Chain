<template>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h1 class="page-title">客戶主檔</h1>
        <p class="page-subtitle">管理下游客戶資料、外部料號歸屬與 CBAM 進口商識別</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">+ 新增客戶</button>
    </div>

    <!-- 篩選列 -->
    <div class="filter-bar">
      <input v-model="filters.search" class="form-input" style="width:220px;" placeholder="搜尋名稱 / 代碼" @keyup.enter="loadData" />
      <select v-model="filters.customer_type" class="form-select" @change="loadData">
        <option value="">全部類型</option>
        <option value="brand">品牌商</option>
        <option value="retailer">零售商</option>
        <option value="distributor">代理/經銷</option>
        <option value="agent">銷售代理</option>
        <option value="oem">OEM</option>
      </select>
      <select v-model="filters.status" class="form-select" @change="loadData">
        <option value="">全部狀態</option>
        <option value="active">啟用</option>
        <option value="inactive">停用</option>
      </select>
      <button class="btn btn-ghost" @click="loadData">搜尋</button>
    </div>

    <!-- 列表 -->
    <div class="data-table-wrapper">
      <div v-if="isLoading" class="loading-state">載入中…</div>
      <div v-else-if="customers.length === 0" class="empty-state">
        <p>尚無客戶資料</p>
      </div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>代碼</th>
            <th>客戶名稱</th>
            <th>國家</th>
            <th>類型</th>
            <th>EORI</th>
            <th>狀態</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in customers" :key="c.id" @click="openDetail(c)" style="cursor:pointer;">
            <td class="font-mono" style="font-size:12px;">{{ c.code }}</td>
            <td>{{ c.name }}</td>
            <td class="font-mono" style="font-size:12px;">{{ c.country_code }}</td>
            <td><span class="type-badge" :class="'type-' + c.customer_type">{{ typeLabel(c.customer_type) }}</span></td>
            <td class="font-mono" style="font-size:12px;">{{ c.eori_number || '—' }}</td>
            <td><span class="badge" :class="c.status === 'active' ? 'badge-green' : 'badge-gray'">{{ c.status === 'active' ? '啟用' : '停用' }}</span></td>
            <td @click.stop>
              <button class="btn btn-secondary btn-sm" @click="openEdit(c)">編輯</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 新增/編輯 Modal -->
    <div v-if="modal.open" class="modal-overlay" @click.self="closeModal">
      <div class="modal-panel" style="width:560px;">
        <div class="modal-header">
          <h2>{{ modal.isEdit ? '編輯客戶' : '新增客戶' }}</h2>
          <button class="modal-close" @click="closeModal">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">客戶代碼 *</label>
              <input v-model="modal.form.code" class="form-input" placeholder="CUS-001" :disabled="modal.isEdit" />
            </div>
            <div class="form-group">
              <label class="form-label">客戶名稱 *</label>
              <input v-model="modal.form.name" class="form-input" placeholder="ABC Trading GmbH" />
            </div>
            <div class="form-group">
              <label class="form-label">國家代碼 *</label>
              <input v-model="modal.form.country_code" class="form-input font-mono" placeholder="DE" maxlength="2" style="text-transform:uppercase;" />
            </div>
            <div class="form-group">
              <label class="form-label">客戶類型 *</label>
              <select v-model="modal.form.customer_type" class="form-select">
                <option value="">請選擇</option>
                <option value="brand">品牌商</option>
                <option value="retailer">零售商</option>
                <option value="distributor">代理/經銷</option>
                <option value="agent">銷售代理</option>
                <option value="oem">OEM</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">EORI Number <span class="label-hint">（EU 進口商）</span></label>
              <input v-model="modal.form.eori_number" class="form-input font-mono" placeholder="DE123456789" />
            </div>
            <div class="form-group">
              <label class="form-label">VAT 稅號</label>
              <input v-model="modal.form.vat_number" class="form-input font-mono" />
            </div>
            <div class="form-group" style="grid-column:1/-1;">
              <label class="form-label">地址</label>
              <input v-model="modal.form.address" class="form-input" />
            </div>
            <div class="form-group">
              <label class="form-label">網站</label>
              <input v-model="modal.form.website" class="form-input" placeholder="https://" />
            </div>
            <div class="form-group">
              <label class="form-label">狀態</label>
              <select v-model="modal.form.status" class="form-select">
                <option value="active">啟用</option>
                <option value="inactive">停用</option>
              </select>
            </div>
            <div class="form-group" style="grid-column:1/-1;">
              <label class="form-label">備註</label>
              <textarea v-model="modal.form.notes" class="form-input" rows="2"></textarea>
            </div>
          </div>
          <div v-if="modal.warnings.length > 0" class="warn-block">
            <div v-for="w in modal.warnings" :key="w" class="warn-item">⚠ {{ w }}</div>
          </div>
          <div v-if="modal.error" class="error-text" style="margin-top:8px;">{{ modal.error }}</div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-ghost" @click="closeModal">取消</button>
          <button class="btn btn-primary" :disabled="modal.saving" @click="saveModal">
            {{ modal.saving ? '儲存中…' : '儲存' }}
          </button>
        </div>
      </div>
    </div>

    <!-- 聯絡人 Detail Panel -->
    <div v-if="detail.open" class="modal-overlay" @click.self="closeDetail">
      <div class="modal-panel" style="width:520px;">
        <div class="modal-header">
          <h2>{{ detail.customer?.name }}</h2>
          <button class="modal-close" @click="closeDetail">✕</button>
        </div>
        <div class="modal-body">
          <div class="section-label">聯絡人</div>
          <div v-if="!detail.customer?.contacts?.length" class="empty-inline">尚無聯絡人</div>
          <div v-else class="contact-list">
            <div v-for="c in detail.customer.contacts" :key="c.id" class="contact-row">
              <div>
                <div class="contact-name">{{ c.name }} <span v-if="c.title" class="contact-meta">{{ c.title }}</span></div>
                <div class="contact-meta">{{ c.email }}</div>
              </div>
              <div style="display:flex;gap:6px;align-items:center;">
                <span v-if="c.is_primary" class="badge badge-green" style="font-size:11px;">主要</span>
                <button class="icon-btn" @click="deleteContact(c)">✕</button>
              </div>
            </div>
          </div>
          <!-- 新增聯絡人 -->
          <div class="add-contact-form">
            <div class="section-label" style="margin-top:16px;">新增聯絡人</div>
            <div class="form-grid" style="grid-template-columns:1fr 1fr;">
              <input v-model="contactForm.name" class="form-input" placeholder="姓名 *" />
              <input v-model="contactForm.email" class="form-input" placeholder="Email *" />
              <input v-model="contactForm.phone" class="form-input" placeholder="電話" />
              <input v-model="contactForm.title" class="form-input" placeholder="職稱" />
            </div>
            <div style="margin-top:8px;display:flex;align-items:center;gap:8px;">
              <label style="font-size:13px;display:flex;align-items:center;gap:4px;">
                <input type="checkbox" v-model="contactForm.is_primary" /> 設為主要聯絡人
              </label>
              <button class="btn btn-primary btn-sm" :disabled="contactSaving" @click="addContact">
                {{ contactSaving ? '新增中…' : '新增' }}
              </button>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-ghost" @click="closeDetail">關閉</button>
          <button class="btn btn-secondary btn-sm" @click="openEdit(detail.customer)">編輯資料</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import {
  customersApi,
  customerContactApi,
  type Customer,
  type CustomerContact,
} from '@/api/modules/customers'

const TYPE_LABELS: Record<string, string> = {
  brand: '品牌商', retailer: '零售商', distributor: '代理/經銷',
  agent: '銷售代理', oem: 'OEM',
}

export default defineComponent({
  name: 'CustomerMdmView',
  data() {
    return {
      isLoading: false,
      customers: [] as Customer[],
      filters: { search: '', customer_type: '', status: '' },
      modal: {
        open: false,
        isEdit: false,
        saving: false,
        warnings: [] as string[],
        error: '',
        editId: '' as string,
        form: {
          code: '', name: '', country_code: '', customer_type: '' as string,
          eori_number: '', vat_number: '', address: '', website: '',
          notes: '', status: 'active',
        },
      },
      detail: { open: false, customer: null as Customer | null },
      contactForm: { name: '', email: '', phone: '', title: '', is_primary: false },
      contactSaving: false,
    }
  },
  mounted() {
    this.loadData()
  },
  methods: {
    async loadData() {
      this.isLoading = true
      try {
        const res = await customersApi.list({
          search: this.filters.search || undefined,
          customer_type: this.filters.customer_type || undefined,
          status: this.filters.status || undefined,
        })
        this.customers = res.data.data || []
      } finally {
        this.isLoading = false
      }
    },
    openCreate() {
      this.modal = {
        open: true, isEdit: false, saving: false, warnings: [], error: '', editId: '',
        form: { code: '', name: '', country_code: '', customer_type: '', eori_number: '', vat_number: '', address: '', website: '', notes: '', status: 'active' },
      }
    },
    openEdit(customer: Customer | null) {
      if (!customer) return
      this.detail.open = false
      this.modal = {
        open: true, isEdit: true, saving: false, warnings: [], error: '', editId: customer.id,
        form: {
          code: customer.code, name: customer.name, country_code: customer.country_code,
          customer_type: customer.customer_type, eori_number: customer.eori_number || '',
          vat_number: customer.vat_number || '', address: customer.address || '',
          website: customer.website || '', notes: customer.notes || '', status: customer.status,
        },
      }
    },
    async openDetail(customer: Customer) {
      const res = await customersApi.get(customer.id)
      this.detail = { open: true, customer: res.data.data }
    },
    closeModal() {
      this.modal.open = false
    },
    closeDetail() {
      this.detail.open = false
    },
    async saveModal() {
      this.modal.saving = true
      this.modal.error = ''
      this.modal.warnings = []
      try {
        const f = this.modal.form
        const payload: any = { ...f }
        if (!payload.eori_number) delete payload.eori_number
        if (!payload.vat_number) delete payload.vat_number

        if (this.modal.isEdit) {
          await customersApi.update(this.modal.editId, payload)
        } else {
          const res = await customersApi.create(payload)
          this.modal.warnings = res.data.warnings || []
        }
        await this.loadData()
        if (!this.modal.warnings.length) this.modal.open = false
      } catch (err: any) {
        const errors = err?.response?.data?.errors
        if (errors) {
          this.modal.error = Object.values(errors).flat().join('；')
        } else {
          this.modal.error = err?.response?.data?.message || '儲存失敗'
        }
      } finally {
        this.modal.saving = false
      }
    },
    async addContact() {
      if (!this.detail.customer || !this.contactForm.name || !this.contactForm.email) return
      this.contactSaving = true
      try {
        await customerContactApi.create(this.detail.customer.id, { ...this.contactForm })
        const res = await customersApi.get(this.detail.customer.id)
        this.detail.customer = res.data.data
        this.contactForm = { name: '', email: '', phone: '', title: '', is_primary: false }
      } catch (err: any) {
        alert(err?.response?.data?.message || '新增失敗')
      } finally {
        this.contactSaving = false
      }
    },
    async deleteContact(contact: CustomerContact) {
      if (!this.detail.customer) return
      await customerContactApi.destroy(this.detail.customer.id, contact.id)
      const res = await customersApi.get(this.detail.customer.id)
      this.detail.customer = res.data.data
    },
    typeLabel: (t: string) => TYPE_LABELS[t] ?? t,
  },
})
</script>

<style scoped>
.filter-bar { display: flex; gap: 8px; margin-bottom: 16px; align-items: center; }
.form-select { padding: 6px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; background: var(--surface); color: var(--text-primary); }

.data-table-wrapper { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { padding: 10px 14px; text-align: left; font-size: 12px; font-weight: 600; color: var(--text-secondary); background: var(--surface-2); border-bottom: 1px solid var(--border); }
.data-table td { padding: 12px 14px; font-size: 13px; color: var(--text-primary); border-bottom: 1px solid var(--border); }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: var(--surface-2); }

.type-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; background: var(--surface-2); color: var(--text-secondary); border: 1px solid var(--border); }
.type-agent { background: #fff7ed; color: #c2410c; border-color: #fed7aa; }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal-panel { background: var(--surface); border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.15); display: flex; flex-direction: column; max-height: 90vh; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 18px 24px; border-bottom: 1px solid var(--border); }
.modal-header h2 { font-size: 16px; font-weight: 600; margin: 0; }
.modal-close { background: none; border: none; font-size: 18px; cursor: pointer; color: var(--text-secondary); }
.modal-body { padding: 20px 24px; overflow-y: auto; }
.modal-footer { padding: 14px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 8px; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-group { display: flex; flex-direction: column; gap: 4px; }
.form-label { font-size: 12px; font-weight: 600; color: var(--text-secondary); }
.label-hint { font-weight: 400; color: var(--text-secondary); font-size: 11px; }
.form-input { padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; background: var(--surface); color: var(--text-primary); width: 100%; box-sizing: border-box; }
.form-select { padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; background: var(--surface); color: var(--text-primary); width: 100%; }

.section-label { font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 8px; }
.contact-list { display: flex; flex-direction: column; gap: 6px; }
.contact-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: var(--surface-2); border-radius: 6px; }
.contact-name { font-size: 13px; font-weight: 500; }
.contact-meta { font-size: 12px; color: var(--text-secondary); }
.icon-btn { background: none; border: none; cursor: pointer; color: var(--text-secondary); font-size: 13px; padding: 2px 6px; }
.icon-btn:hover { color: #dc2626; }

.warn-block { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 6px; padding: 10px 14px; margin-top: 12px; }
.warn-item { font-size: 13px; color: #c2410c; }
.error-text { color: #dc2626; font-size: 13px; }

.loading-state, .empty-state { padding: 40px; text-align: center; color: var(--text-secondary); }
.font-mono { font-family: 'Fira Code', monospace; }
.btn { padding: 8px 16px; border-radius: 6px; font-size: 13px; cursor: pointer; border: none; font-weight: 500; }
.btn-primary { background: var(--accent); color: #fff; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-secondary { background: var(--surface-2); color: var(--text-primary); border: 1px solid var(--border); }
.btn-ghost { background: transparent; color: var(--text-secondary); border: 1px solid var(--border); }
.btn-sm { padding: 5px 12px; font-size: 12px; }
</style>
