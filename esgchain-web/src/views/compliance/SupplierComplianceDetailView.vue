<template>
  <div class="page-container">
    <!-- 頂部導覽 -->
    <div style="margin-bottom:16px;">
      <button class="back-btn" @click="router.push('/compliance')">← 返回合規看板</button>
    </div>

    <div class="page-header">
      <div>
        <h1 class="page-title">
          <span v-if="isLoading" style="color:var(--text-secondary);font-weight:400;font-size:20px;">載入中…</span>
          <template v-else>
            {{ maskSupplierName(supplierName) }}
            <span style="font-weight:400;color:var(--text-secondary);"> — 合規文件</span>
          </template>
        </h1>
      </div>
      <button class="btn btn-primary" @click="showUploadModal = true">+ 上傳文件</button>
    </div>

    <!-- KPI 卡片 -->
    <div v-if="summary" class="kpi-grid">
      <div class="kpi-card kpi-card--green">
        <div class="kpi-value kpi-value--green">{{ summary.valid_count }}</div>
        <div class="kpi-label">有效</div>
      </div>
      <div class="kpi-card kpi-card--orange">
        <div class="kpi-value kpi-value--orange">{{ summary.expiring_soon_count }}</div>
        <div class="kpi-label">即將到期</div>
      </div>
      <div class="kpi-card kpi-card--red">
        <div class="kpi-value kpi-value--red">{{ summary.expired_count }}</div>
        <div class="kpi-label">已過期</div>
      </div>
      <div class="kpi-card kpi-card--gray">
        <div class="kpi-value kpi-value--gray">{{ summary.pending_count }}</div>
        <div class="kpi-label">無效期</div>
      </div>
    </div>

    <!-- 缺漏文件警告 -->
    <div v-if="summary?.missing_required_types.length" class="missing-alert">
      <span class="missing-alert-icon">⚠</span>
      <span class="missing-alert-text">缺漏必要文件：</span>
      <span v-for="t in summary.missing_required_types" :key="t" class="missing-tag">{{ t }}</span>
    </div>

    <div v-if="isLoading" class="loading-mask" style="padding:64px;text-align:center;">載入中...</div>

    <template v-else>
    <!-- 供應材料清單 -->
    <div class="section-header">
      <h2 class="section-title">供應材料清單</h2>
      <span class="section-count">{{ tradeGoods.length }} 項</span>
    </div>
    <div v-if="tradeGoods.length === 0" class="card" style="padding:24px 20px;color:var(--text-secondary);font-size:13px;">
      此供應商尚無貿易商品記錄
    </div>
    <div v-else class="card" style="padding:0;overflow:hidden;margin-bottom:24px;">
      <table class="data-table">
        <thead>
          <tr>
            <th style="padding-left:20px;">材料名稱</th>
            <th style="width:120px;">HS Code</th>
            <th style="width:160px;">物料群組</th>
            <th style="width:220px;">所需合規文件</th>
            <th style="width:110px;text-align:right;padding-right:20px;">年採購值 (USD)</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="tg in tradeGoods" :key="tg.id" class="doc-row">
            <td style="padding-left:20px;font-weight:500;font-size:14px;">{{ tg.name }}</td>
            <td class="font-mono" style="font-size:12px;color:var(--text-secondary);">{{ tg.hs_code || '—' }}</td>
            <td>
              <span v-if="tg.material_group" class="material-chip">{{ tg.material_group.name }}</span>
              <span v-else class="none-indicator">未分類</span>
            </td>
            <td>
              <div v-if="tg.material_group?.required_doc_types?.length" class="req-docs">
                <span
                  v-for="dt in tg.material_group.required_doc_types"
                  :key="dt"
                  class="doc-type-badge"
                  :class="`doc-type--${docTypeColor(dt)}`"
                >{{ docTypeShort(dt) }}</span>
              </div>
              <span v-else class="none-indicator">—</span>
            </td>
            <td class="font-mono" style="font-size:12px;text-align:right;padding-right:20px;">
              {{ tg.annual_value_usd != null ? tg.annual_value_usd.toLocaleString() : '—' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 合規文件清單 -->
    <div class="section-header">
      <h2 class="section-title">合規文件</h2>
      <span class="section-count">{{ docs.length }} 件</span>
    </div>
    <div v-if="docs.length === 0" class="card" style="padding:64px;text-align:center;">
      <div style="font-size:36px;margin-bottom:12px;">📄</div>
      <p style="font-weight:500;margin-bottom:4px;">尚無合規文件</p>
      <p style="color:var(--text-secondary);font-size:13px;">點擊「上傳文件」開始建立</p>
    </div>

    <div v-else class="card" style="padding:0;overflow:hidden;">
      <table class="data-table">
        <thead>
          <tr>
            <th style="width:160px;padding-left:20px;">文件類型</th>
            <th>檔案名稱</th>
            <th style="width:110px;white-space:nowrap;">生效日</th>
            <th style="width:110px;white-space:nowrap;">到期日</th>
            <th style="width:100px;text-align:center;">狀態</th>
            <th style="width:90px;text-align:center;">審核</th>
            <th style="width:60px;"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="doc in docs" :key="doc.id" class="doc-row">
            <td style="padding-left:20px;">
              <span class="doc-type-badge" :class="`doc-type--${docTypeColor(doc.doc_type)}`">{{ docTypeShort(doc.doc_type) }}</span>
            </td>
            <td>
              <div class="file-name">{{ doc.file_name || doc.file_path }}</div>
              <div v-if="doc.notes" class="file-notes">{{ doc.notes }}</div>
            </td>
            <td class="font-mono date-cell">{{ doc.issued_at?.slice(0, 10) || '—' }}</td>
            <td class="font-mono date-cell" :class="dateClass(doc)">{{ doc.expires_at?.slice(0, 10) || '—' }}</td>
            <td style="text-align:center;">
              <span class="status-pill" :class="statusClass(doc.status)">{{ statusLabel(doc.status) }}</span>
            </td>
            <td style="text-align:center;">
              <span v-if="doc.verified_at" class="verified-badge" :title="`審核時間：${doc.verified_at?.slice(0,10)}`">✓ 已審核</span>
              <button v-else class="review-btn" @click="openVerifyModal(doc)">審核…</button>
            </td>
            <td>
              <button class="delete-btn" @click="deleteDoc(doc)" title="刪除">✕</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 關聯採購產品 Section -->
    <div class="section-header" style="margin-top:8px;">
      <h2 class="section-title">關聯採購產品</h2>
      <span class="section-count">{{ bomRequirements.length }} 項產品</span>
    </div>
    <div v-if="bomReqLoading" class="card" style="padding:24px;text-align:center;color:var(--text-secondary);font-size:13px;">載入中...</div>
    <div v-else-if="bomRequirements.length === 0" class="card" style="padding:24px 20px;color:var(--text-secondary);font-size:13px;">
      此供應商尚未被任何產品 BOM 明細指定
    </div>
    <div v-else>
      <div v-for="product in bomRequirements" :key="product.product_id" class="bom-req-card card" style="margin-bottom:10px;padding:0;overflow:hidden;">
        <div class="bom-req-header">
          <span class="bom-req-title">客戶產品 #{{ product.product_index }}</span>
          <div class="bom-req-regs">
            <span
              v-for="reg in product.applicable_regulations"
              :key="reg"
              class="reg-badge-sm"
              :class="`reg-sm--${reg}`"
            >{{ reg }}</span>
          </div>
        </div>
        <table class="data-table" style="margin:0;">
          <thead>
            <tr>
              <th style="padding-left:16px;">物料名稱</th>
              <th style="width:60px;">類型</th>
              <th style="width:140px;">物料群組</th>
              <th style="width:80px;">角色</th>
              <th>合規文件需求</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="line in product.bom_lines" :key="line.bom_line_id">
              <td style="padding-left:16px;font-weight:500;font-size:13px;">{{ line.material_name }}</td>
              <td>
                <span class="bom-type-badge" :class="line.bom_line_type === 'service' ? 'bom-type-service' : 'bom-type-material'">
                  {{ line.bom_line_type === 'service' ? '服務' : '原物料' }}
                </span>
              </td>
              <td>
                <span v-if="line.material_group" class="material-chip">{{ line.material_group }}</span>
                <span v-else class="none-indicator">—</span>
              </td>
              <td>
                <span class="role-badge" :class="line.role === 'primary' ? 'role-primary' : 'role-alternate'">
                  {{ line.role === 'primary' ? '主要' : '替代' }}
                </span>
              </td>
              <td>
                <div v-if="!line.submitted_docs.length" class="none-indicator">無需求</div>
                <div v-else class="bom-doc-list">
                  <span
                    v-for="d in line.submitted_docs"
                    :key="d.doc_type"
                    class="bom-doc-badge"
                    :class="d.status === 'missing' ? 'bom-doc--missing' : d.status === 'valid' ? 'bom-doc--valid' : 'bom-doc--warn'"
                  >
                    {{ docTypeShort(d.doc_type) }}
                    <span v-if="d.status === 'missing'"> ⚠</span>
                    <span v-else-if="d.status === 'valid'"> ✓</span>
                  </span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    </template>

    <!-- 上傳文件 Modal -->
    <div v-if="showUploadModal" class="modal-overlay" @click.self="showUploadModal = false">
      <div class="modal" style="min-width:420px;">
        <div class="modal-header">
          <span class="modal-title">上傳合規文件</span>
          <button class="modal-close" @click="showUploadModal = false">×</button>
        </div>
        <div class="form-group">
          <label class="form-label">文件類型 *</label>
          <select v-model="uploadForm.doc_type" class="form-select">
            <option value="">— 選擇類型 —</option>
            <option v-for="t in DOC_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">文件檔案 * <span style="font-size:11px;color:var(--text-secondary);">（上限 10MB）</span></label>
          <input type="file" class="form-input" @change="uploadForm.file = ($event.target as HTMLInputElement).files?.[0] || null" />
        </div>
        <div class="form-row">
          <div class="form-group" style="flex:1;">
            <label class="form-label">生效日（選填）</label>
            <input v-model="uploadForm.issued_at" type="date" class="form-input" />
          </div>
          <div class="form-group" style="flex:1;">
            <label class="form-label">到期日（選填）</label>
            <input v-model="uploadForm.expires_at" type="date" class="form-input" />
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">備註（選填）</label>
          <textarea v-model="uploadForm.notes" class="form-input" rows="2" />
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showUploadModal = false">取消</button>
          <button class="btn btn-primary" :disabled="isSubmitting || !uploadForm.doc_type || !uploadForm.file" @click="uploadDoc">
            {{ isSubmitting ? '上傳中...' : '上傳' }}
          </button>
        </div>
      </div>
    </div>

    <!-- 驗證 Modal -->
    <div v-if="verifyModal.open" class="modal-overlay" @click.self="verifyModal.open = false">
      <div class="modal" style="min-width:440px;max-width:500px;">
        <div class="modal-header">
          <span class="modal-title">審核文件</span>
          <button class="modal-close" @click="verifyModal.open = false">×</button>
        </div>
        <div style="padding:0 0 16px;">
          <div class="verify-summary">
            <div class="verify-row">
              <span class="verify-label">文件類型</span>
              <span class="verify-value font-mono">{{ verifyModal.doc?.doc_type }}</span>
            </div>
            <div class="verify-row">
              <span class="verify-label">檔案名稱</span>
              <span class="verify-value font-mono" style="font-size:12px;">{{ verifyModal.doc?.file_name || '—' }}</span>
            </div>
            <div class="verify-row">
              <span class="verify-label">上傳日期</span>
              <span class="verify-value font-mono">{{ verifyModal.doc?.created_at?.slice(0,10) || '—' }}</span>
            </div>
          </div>
          <div style="margin:12px 0;">
            <button class="btn btn-secondary btn-sm" :disabled="verifyModal.downloading" @click="downloadModalDoc" style="width:100%;">
              {{ verifyModal.downloading ? '下載中…' : '⬇ 下載文件' }}
            </button>
          </div>
          <div v-if="!verifyModal.doc?.expires_at" style="margin-bottom:12px;">
            <label class="form-label" style="color:#dc2626;">有效期至 <span style="font-size:11px;">（必填，文件未填有效期）</span></label>
            <input v-model="verifyModal.expiresAt" type="date" class="form-input" style="width:100%;" />
            <div v-if="!verifyModal.expiresAt" style="font-size:11px;color:#dc2626;margin-top:4px;">有效期為必填欄位</div>
          </div>
          <div style="margin-bottom:4px;">
            <label class="form-label">審核備註（選填）</label>
            <textarea
              v-model="verifyModal.notes"
              class="form-input"
              rows="3"
              maxlength="500"
              placeholder="審核意見（選填，最多 500 字）"
              style="width:100%;resize:vertical;"
            ></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary btn-sm" @click="verifyModal.open = false">取消</button>
          <button
            class="btn btn-primary btn-sm"
            :disabled="verifyModal.submitting || (!verifyModal.doc?.expires_at && !verifyModal.expiresAt)"
            @click="submitVerify"
          >{{ verifyModal.submitting ? '審核中…' : '確認審核' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { complianceDocApi, supplierTradeGoodsApi, supplierBomRequirementsApi, type SupplierComplianceDoc, type SupplierComplianceSummary, type TradeGood, type BomRequirementProduct } from '@/api/modules/compliance'
import { maskSupplierName } from '@/utils/maskName'

const DOC_TYPES = [
  { value: 'UFLPA_DECLARATION', label: 'UFLPA 聲明（美國新疆棉）' },
  { value: 'EUDR_DDS', label: 'EUDR 盡職調查聲明' },
  { value: 'CMRT', label: 'CMRT 衝突礦產報告' },
  { value: 'SDS', label: 'SDS 安全資料表' },
  { value: 'CE_DOC', label: 'CE 符合性聲明' },
  { value: 'ORIGIN_CERT', label: '原產地證明' },
  { value: 'GRS', label: 'GRS 全球循環標準認證' },
  { value: 'OTHER', label: '其他' },
]

const DOC_TYPE_SHORT: Record<string, string> = {
  UFLPA_DECLARATION: 'UFLPA', EUDR_DDS: 'EUDR', CMRT: 'CMRT',
  SDS: 'SDS', CE_DOC: 'CE', ORIGIN_CERT: '原產地', GRS: 'GRS', OTHER: '其他',
}
const DOC_TYPE_COLOR: Record<string, string> = {
  UFLPA_DECLARATION: 'red', EUDR_DDS: 'orange', CMRT: 'blue',
  SDS: 'purple', CE_DOC: 'green', ORIGIN_CERT: 'gray', GRS: 'green', OTHER: 'gray',
}

export default defineComponent({
  name: 'SupplierComplianceDetailView',
  setup() { return { router: useRouter(), route: useRoute(), DOC_TYPES } },
  data() {
    return {
      isLoading: false,
      isSubmitting: false,
      docs: [] as SupplierComplianceDoc[],
      tradeGoods: [] as TradeGood[],
      summary: null as SupplierComplianceSummary | null,
      supplierName: '',
      showUploadModal: false,
      uploadForm: { doc_type: '', file: null as File | null, issued_at: '', expires_at: '', notes: '' },
      bomRequirements: [] as BomRequirementProduct[],
      bomReqLoading: false,
      verifyModal: {
        open: false,
        doc: null as SupplierComplianceDoc | null,
        expiresAt: '',
        notes: '',
        downloading: false,
        submitting: false,
      },
    }
  },

  computed: {
    supplierId(): string { return this.route.params.id as string },
  },

  mounted() { this.loadData() },

  methods: {
    maskSupplierName,

    async loadData() {
      this.isLoading = true
      this.bomReqLoading = true
      try {
        const [docsRes, summaryRes, goodsRes] = await Promise.all([
          complianceDocApi.list(this.supplierId),
          complianceDocApi.getSupplierSummary(this.supplierId),
          supplierTradeGoodsApi.listBySupplier(this.supplierId),
        ])
        this.docs = docsRes.data.data
        this.summary = summaryRes.data.data
        this.tradeGoods = goodsRes.data.data
        this.supplierName = this.summary?.supplier_name || '供應商'
      } finally { this.isLoading = false }

      try {
        const bomRes = await supplierBomRequirementsApi.get(this.supplierId)
        this.bomRequirements = bomRes.data.data
      } catch { this.bomRequirements = [] }
      finally { this.bomReqLoading = false }
    },

    async uploadDoc() {
      if (!this.uploadForm.doc_type || !this.uploadForm.file) return
      this.isSubmitting = true
      try {
        const form = new FormData()
        form.append('doc_type', this.uploadForm.doc_type)
        form.append('file', this.uploadForm.file)
        if (this.uploadForm.issued_at) form.append('issued_at', this.uploadForm.issued_at)
        if (this.uploadForm.expires_at) form.append('expires_at', this.uploadForm.expires_at)
        if (this.uploadForm.notes) form.append('notes', this.uploadForm.notes)
        await complianceDocApi.create(this.supplierId, form)
        this.showUploadModal = false
        await this.loadData()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '上傳失敗')
      } finally { this.isSubmitting = false }
    },

    openVerifyModal(doc: SupplierComplianceDoc) {
      this.verifyModal.doc = doc
      this.verifyModal.expiresAt = ''
      this.verifyModal.notes = ''
      this.verifyModal.downloading = false
      this.verifyModal.submitting = false
      this.verifyModal.open = true
    },

    async downloadModalDoc() {
      if (!this.verifyModal.doc) return
      this.verifyModal.downloading = true
      try {
        const res = await complianceDocApi.download(this.verifyModal.doc.id)
        const url = URL.createObjectURL(new Blob([res.data]))
        const a = document.createElement('a')
        a.href = url
        a.download = this.verifyModal.doc.file_name || 'document'
        a.click()
        URL.revokeObjectURL(url)
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '下載失敗')
      } finally {
        this.verifyModal.downloading = false
      }
    },

    async submitVerify() {
      const doc = this.verifyModal.doc
      if (!doc) return
      const missingExpiry = !doc.expires_at
      if (missingExpiry && !this.verifyModal.expiresAt) return
      this.verifyModal.submitting = true
      try {
        const body: { expires_at?: string; notes?: string } = {}
        if (this.verifyModal.expiresAt) body.expires_at = this.verifyModal.expiresAt
        if (this.verifyModal.notes.trim()) body.notes = this.verifyModal.notes.trim()
        await complianceDocApi.verify(doc.id, body)
        this.verifyModal.open = false
        await this.loadData()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '審核失敗')
      } finally {
        this.verifyModal.submitting = false
      }
    },

    async deleteDoc(doc: SupplierComplianceDoc) {
      if (!confirm('確定刪除此合規文件？')) return
      await complianceDocApi.destroy(doc.id)
      await this.loadData()
    },

    docTypeShort: (t: string) => DOC_TYPE_SHORT[t] ?? t,
    docTypeColor: (t: string) => DOC_TYPE_COLOR[t] ?? 'gray',

    statusClass(s: string) {
      return ({ valid: 'status--green', expiring_soon: 'status--orange', expired: 'status--red', pending: 'status--gray' })[s] ?? 'status--gray'
    },
    statusLabel(s: string) {
      return ({ valid: '有效', expiring_soon: '即將到期', expired: '已過期', pending: '無效期' })[s] ?? s
    },

    dateClass(doc: SupplierComplianceDoc) {
      if (doc.status === 'expired') return 'date-expired'
      if (doc.status === 'expiring_soon') return 'date-expiring'
      return ''
    },
  },
})
</script>

<style scoped>
.back-btn {
  background: none; border: none; cursor: pointer;
  color: var(--text-secondary); font-size: 13px; padding: 0;
  display: inline-flex; align-items: center; gap: 4px;
}
.back-btn:hover { color: var(--accent); }

/* KPI */
.kpi-grid { display: flex; gap: 12px; margin-bottom: 20px; }
.kpi-card {
  background: var(--surface); border: 1px solid var(--border); border-radius: 10px;
  padding: 14px 24px; text-align: center; min-width: 90px;
}
.kpi-card--green { border-top: 3px solid #16a34a; }
.kpi-card--orange { border-top: 3px solid #d97706; }
.kpi-card--red { border-top: 3px solid #dc2626; }
.kpi-card--gray { border-top: 3px solid #9ca3af; }
.kpi-value { font-size: 30px; font-weight: 700; font-family: 'Fira Code', monospace; line-height: 1; }
.kpi-value--green { color: #16a34a; }
.kpi-value--orange { color: #d97706; }
.kpi-value--red { color: #dc2626; }
.kpi-value--gray { color: #9ca3af; }
.kpi-label { font-size: 11px; color: var(--text-secondary); margin-top: 4px; font-weight: 500; }

/* 缺漏警告 */
.missing-alert {
  display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
  background: #fefce8; border: 1px solid #fde047; border-radius: 8px;
  padding: 10px 16px; margin-bottom: 16px;
}
.missing-alert-icon { font-size: 16px; }
.missing-alert-text { font-size: 13px; font-weight: 600; color: #854d0e; }
.missing-tag {
  font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px;
  background: #fef3c7; color: #92400e; border: 1px solid #fde68a;
}

/* 表格 */
.doc-row { transition: background 0.1s; }
.doc-row:hover td { background: #faf9f7; }

/* 文件類型 badge */
.doc-type-badge {
  display: inline-block; font-size: 11px; font-weight: 700; padding: 3px 10px;
  border-radius: 5px; letter-spacing: 0.3px;
}
.doc-type--red    { background: #fee2e2; color: #b91c1c; }
.doc-type--orange { background: #fef3c7; color: #92400e; }
.doc-type--blue   { background: #dbeafe; color: #1d4ed8; }
.doc-type--purple { background: #ede9fe; color: #5b21b6; }
.doc-type--green  { background: #dcfce7; color: #15803d; }
.doc-type--gray   { background: var(--surface-2); color: var(--text-secondary); }

/* 檔案名稱 */
.file-name { font-size: 13px; color: var(--text-primary); font-family: 'Fira Code', monospace; }
.file-notes { font-size: 11px; color: var(--text-secondary); margin-top: 2px; }

/* 日期 */
.date-cell { font-size: 12px; white-space: nowrap; }
.date-expired { color: #dc2626; font-weight: 600; }
.date-expiring { color: #d97706; font-weight: 600; }

/* 狀態 pill */
.status-pill {
  display: inline-flex; align-items: center; justify-content: center;
  padding: 3px 10px; border-radius: 99px; font-size: 12px; font-weight: 600; white-space: nowrap;
}
.status--green  { background: #dcfce7; color: #15803d; }
.status--orange { background: #fef3c7; color: #b45309; }
.status--red    { background: #fee2e2; color: #b91c1c; }
.status--gray   { background: var(--surface-2); color: var(--text-secondary); }

/* 審核 */
.verified-badge { font-size: 12px; font-weight: 600; color: #15803d; }
.review-btn {
  font-size: 12px; padding: 3px 10px; border-radius: 5px;
  border: 1px solid var(--border); background: var(--surface);
  cursor: pointer; color: var(--text-primary); transition: all 0.15s;
}
.review-btn:hover { border-color: var(--accent); color: var(--accent); }

/* 刪除 */
.delete-btn {
  width: 28px; height: 28px; border-radius: 5px; border: 1px solid transparent;
  background: none; cursor: pointer; font-size: 13px; color: var(--text-secondary);
  display: flex; align-items: center; justify-content: center; transition: all 0.15s;
}
.delete-btn:hover { border-color: #dc2626; color: #dc2626; background: #fef2f2; }

/* 區塊標題 */
.section-header { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.section-title { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0; }
.section-count { font-size: 12px; color: var(--text-secondary); background: var(--surface-2); padding: 2px 8px; border-radius: 99px; }

/* 材料相關 */
.material-chip { font-size: 12px; font-weight: 500; padding: 3px 9px; border-radius: 5px; background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
.none-indicator { font-size: 12px; color: var(--text-secondary); }
.req-docs { display: flex; gap: 4px; flex-wrap: wrap; }

/* Modal */
.form-row { display: flex; gap: 12px; }

/* 關聯採購產品 */
.bom-req-header {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 16px; background: var(--surface-2); border-bottom: 1px solid var(--border);
}
.bom-req-title { font-size: 13px; font-weight: 600; color: var(--text-primary); }
.bom-req-regs { display: flex; gap: 4px; }
.reg-badge-sm {
  font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 4px;
}
.reg-sm--UFLPA { background: #fee2e2; color: #b91c1c; }
.reg-sm--EUDR  { background: #fef3c7; color: #92400e; }
.reg-sm--CMRT  { background: #dbeafe; color: #1d4ed8; }
.reg-sm--REACH { background: #ede9fe; color: #5b21b6; }
.reg-sm--CE    { background: #dcfce7; color: #15803d; }

.bom-doc-list { display: flex; gap: 5px; flex-wrap: wrap; }
.bom-doc-badge {
  font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 4px;
  display: inline-flex; align-items: center; gap: 2px;
}
.bom-doc--valid   { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
.bom-doc--warn    { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
.bom-doc--missing { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

.bom-type-badge { font-size: 10px; font-weight: 600; padding: 2px 6px; border-radius: 4px; white-space: nowrap; }
.bom-type-material { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
.bom-type-service  { background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; }

.role-badge { font-size: 10px; font-weight: 600; padding: 2px 6px; border-radius: 4px; white-space: nowrap; }
.role-primary  { background: #1a4d3e; color: #fff; }
.role-alternate { background: #f0ede6; color: #78716c; border: 1px solid #e2ddd6; }
.verify-summary { background: var(--surface-2); border-radius: 8px; padding: 12px 16px; margin-bottom: 12px; display: flex; flex-direction: column; gap: 6px; }
.verify-row { display: flex; align-items: baseline; gap: 8px; }
.verify-label { font-size: 12px; color: var(--text-secondary); width: 72px; flex-shrink: 0; }
.verify-value { font-size: 13px; color: var(--text-primary); font-weight: 500; }
</style>
