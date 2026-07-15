<template>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h1 class="page-title">匯入審核儀表板</h1>
        <p class="page-subtitle">批次 {{ batchId?.slice(0,8) }}... 清洗結果</p>
      </div>
    </div>

    <div v-if="!batchId" class="empty-state"><p>請從匯入頁面進入，或提供批次 ID</p></div>

    <template v-else>
      <!-- 統計 Summary Bar -->
      <div class="summary-bar" style="margin-bottom:20px;">
        <div class="summary-item" v-for="s in summaryItems" :key="s.key" :class="s.cls">
          <span class="s-label">{{ s.label }}</span>
          <span class="s-val font-mono">{{ status[s.key] ?? 0 }}</span>
        </div>
      </div>

      <!-- Tab 切換 -->
      <div class="settings-tabs" style="margin-bottom:16px;">
        <button v-for="t in TABS" :key="t.key" class="settings-tab" :class="{ active: activeTab===t.key }" @click="activeTab=t.key; loadItems()">
          {{ t.label }}
        </button>
      </div>

      <!-- 異常清單 -->
      <div class="card" style="padding:0;">
        <div v-if="isLoading" class="loading-mask">載入中...</div>
        <div v-else-if="items.length===0" class="empty-state" style="padding:32px;"><p>此分類無記錄</p></div>
        <table v-else class="data-table">
          <thead>
            <tr>
              <th>廠商名稱</th>
              <th>VAT Number</th>
              <th>Email</th>
              <th>狀態</th>
              <th>問題說明</th>
              <th style="width:180px;">操作</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td style="font-weight:500;">{{ item.vendor_name }}</td>
              <td class="font-mono" style="font-size:12px;">{{ item.vat_number || '—' }}</td>
              <td style="font-size:12px;">
                <span v-if="editingId===item.id">
                  <input v-model="editEmail" class="form-input" style="width:180px;font-size:12px;" placeholder="輸入正確 Email" />
                </span>
                <span v-else>{{ item.primary_email || '—' }}</span>
              </td>
              <td>
                <span class="badge" :class="statusBadge(item.cleanse_status)">{{ statusLabel(item.cleanse_status) }}</span>
              </td>
              <td>
                <div class="failure-chips">
                  <span v-for="code in (item.failure_codes||[])" :key="code" class="failure-chip">
                    {{ FAILURE_CODE_LABELS[code] || code }}
                  </span>
                  <span v-if="item.notes" style="font-size:11px;color:var(--text-secondary);">{{ item.notes }}</span>
                </div>
              </td>
              <td>
                <div style="display:flex;gap:4px;flex-wrap:wrap;">
                  <template v-if="item.cleanse_status==='rejected'">
                    <template v-if="editingId===item.id">
                      <button class="btn btn-primary btn-sm" :disabled="isSubmitting" @click="saveEmail(item)">儲存</button>
                      <button class="btn btn-secondary btn-sm" @click="editingId=null">取消</button>
                    </template>
                    <template v-else>
                      <button class="btn btn-secondary btn-sm" @click="startEdit(item)">補齊 Email</button>
                      <button class="btn btn-secondary btn-sm" @click="openExempt(item)">豁免</button>
                    </template>
                  </template>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 放行按鈕 -->
      <div v-if="status.cleansed > 0 || status.exempt > 0" style="margin-top:20px;text-align:right;">
        <button class="btn btn-primary" :disabled="isSubmitting" @click="showApproveModal=true">
          放行已清洗供應商（{{ (status.cleansed||0) + (status.exempt||0) }} 筆）→
        </button>
      </div>
    </template>

    <!-- 豁免 Modal -->
    <div v-if="exemptTarget" class="modal-overlay" @click.self="exemptTarget=null">
      <div class="modal" style="min-width:380px;">
        <div class="modal-header"><span class="modal-title">申請豁免</span><button class="modal-close" @click="exemptTarget=null">×</button></div>
        <p style="font-size:13px;color:var(--text-secondary);margin-bottom:12px;">廠商：{{ exemptTarget.vendor_name }}</p>
        <div class="form-group">
          <label class="form-label">豁免原因 *</label>
          <textarea v-model="exemptNotes" class="form-textarea" placeholder="如：對方為台電，無主要聯絡信箱" />
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="exemptTarget=null">取消</button>
          <button class="btn btn-primary" :disabled="isSubmitting||!exemptNotes" @click="doExempt">確認豁免</button>
        </div>
      </div>
    </div>

    <!-- 放行確認 Modal -->
    <div v-if="showApproveModal" class="modal-overlay" @click.self="showApproveModal=false">
      <div class="modal" style="min-width:360px;text-align:center;">
        <div class="modal-header"><span class="modal-title">確認放行</span></div>
        <p style="margin:16px 0;color:var(--text-secondary);">
          將 {{ (status.cleansed||0)+(status.exempt||0) }} 筆已清洗/豁免供應商寫入主表，並設定為待補齊狀態（供應商需登入 Portal 完成主檔覆核）。
        </p>
        <div v-if="approveResult" class="result-summary">
          <p>已放行：<strong>{{ approveResult.approved_count }}</strong> 筆</p>
          <p v-if="approveResult.skipped_count > 0">略過（VAT 重複）：{{ approveResult.skipped_count }} 筆</p>
        </div>
        <div class="modal-footer">
          <button v-if="!approveResult" class="btn btn-secondary" @click="showApproveModal=false">取消</button>
          <button v-if="!approveResult" class="btn btn-primary" :disabled="isSubmitting" @click="doApprove">確認放行</button>
          <button v-else class="btn btn-primary" @click="showApproveModal=false; loadStatus()">完成</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { supplierImportApi, type SupplierImport, type ImportBatchStatus, FAILURE_CODE_LABELS } from '@/api/modules/supplierImport'

const TABS = [
  { key: 'rejected', label: '需補救' },
  { key: 'cleansed', label: '已清洗' },
  { key: 'exempt',   label: '豁免' },
  { key: 'approved', label: '已放行' },
]

export default defineComponent({
  name: 'SupplierImportReviewView',
  setup() { return { router: useRouter(), route: useRoute(), FAILURE_CODE_LABELS } },

  data() {
    return {
      TABS,
      activeTab: 'rejected' as string,
      batchId: '' as string,
      isLoading: false,
      isSubmitting: false,
      status: {} as Partial<ImportBatchStatus>,
      items: [] as SupplierImport[],
      editingId: null as string | null,
      editEmail: '',
      exemptTarget: null as SupplierImport | null,
      exemptNotes: '',
      showApproveModal: false,
      approveResult: null as { approved_count: number; skipped_count: number } | null,
      summaryItems: [
        { key: 'cleansed', label: '已清洗', cls: 'sum-green' },
        { key: 'rejected', label: '需補救', cls: 'sum-red' },
        { key: 'exempt',   label: '豁免',   cls: 'sum-yellow' },
        { key: 'approved', label: '已放行', cls: 'sum-blue' },
      ],
    }
  },

  mounted() {
    this.batchId = (this.route.query.batch as string) || ''
    if (this.batchId) { this.loadStatus(); this.loadItems() }
  },

  methods: {
    async loadStatus() {
      try {
        const { data } = await supplierImportApi.status(this.batchId)
        this.status = data
      } catch { /**/ }
    },

    async loadItems() {
      this.isLoading = true
      try {
        const { data } = await supplierImportApi.list(this.batchId, this.activeTab as any)
        this.items = data.data
      } finally { this.isLoading = false }
    },

    startEdit(item: SupplierImport) {
      this.editingId = item.id
      this.editEmail = item.primary_email || ''
    },

    async saveEmail(item: SupplierImport) {
      this.isSubmitting = true
      try {
        await supplierImportApi.updateItem(this.batchId, item.id, { primary_email: this.editEmail })
        this.editingId = null
        await this.loadItems()
        await this.loadStatus()
      } finally { this.isSubmitting = false }
    },

    openExempt(item: SupplierImport) {
      this.exemptTarget = item
      this.exemptNotes = ''
    },

    async doExempt() {
      if (!this.exemptTarget || !this.exemptNotes) return
      this.isSubmitting = true
      try {
        await supplierImportApi.exempt(this.batchId, this.exemptTarget.id, this.exemptNotes)
        this.exemptTarget = null
        await this.loadItems()
        await this.loadStatus()
      } finally { this.isSubmitting = false }
    },

    async doApprove() {
      this.isSubmitting = true
      try {
        const { data } = await supplierImportApi.approve(this.batchId)
        this.approveResult = data.data
        await this.loadStatus()
      } finally { this.isSubmitting = false }
    },

    statusLabel: (s: string) => ({ staged:'暫存', cleansed:'已清洗', rejected:'需補救', exempt:'豁免', approved:'已放行' }[s] ?? s),
    statusBadge: (s: string) => ({ cleansed:'badge-green', rejected:'badge-red', exempt:'badge-yellow', approved:'badge-blue', staged:'badge-gray' }[s] ?? 'badge-gray'),
  },
})
</script>

<style scoped>
.summary-bar { display: flex; gap: 12px; flex-wrap: wrap; }
.summary-item { flex: 1; min-width: 100px; padding: 14px 16px; border-radius: 8px; display: flex; flex-direction: column; gap: 4px; }
.s-label { font-size: 12px; font-weight: 600; }
.s-val { font-size: 28px; font-weight: 800; }
.sum-green { background: #dcfce7; color: #166534; }
.sum-red   { background: #fee2e2; color: #991b1b; }
.sum-yellow{ background: #fef9c3; color: #854d0e; }
.sum-blue  { background: #dbeafe; color: #1e40af; }

.failure-chips { display: flex; flex-wrap: wrap; gap: 4px; }
.failure-chip { font-size: 11px; background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 99px; }

.badge-red    { background: #fee2e2; color: #991b1b; }
.badge-yellow { background: #fef9c3; color: #854d0e; }
.badge-blue   { background: #dbeafe; color: #1e40af; }

.result-summary { background: var(--surface-2); border-radius: 6px; padding: 12px; margin: 8px 0; font-size: 14px; }
</style>
