<template>
  <div class="page-container">
    <!-- 頁籤切換 -->
    <div class="tab-bar">
      <button class="tab-btn" :class="{ active: activeTab === 'docs' }" @click="activeTab = 'docs'">合規文件</button>
      <button class="tab-btn" :class="{ active: activeTab === 'emissions' }" @click="activeTab = 'emissions'">碳排回報</button>
      <button class="tab-btn" :class="{ active: activeTab === 'material-emissions' }" @click="activeTab = 'material-emissions'">物料碳排</button>
    </div>

    <!-- ────────── 合規文件 Tab ────────── -->
    <template v-if="activeTab === 'docs'">
      <div class="page-header" style="margin-top:16px;">
        <div>
          <h1 class="page-title">合規文件</h1>
          <p class="page-subtitle">上傳並管理您的合規文件</p>
        </div>
        <button class="btn btn-primary" @click="showUploadModal = true">+ 上傳文件</button>
      </div>

      <div v-if="isLoading" class="loading-mask">載入中...</div>
      <div v-else-if="docs.length === 0" class="card empty-state" style="padding:48px;">
        <p>尚未上傳任何合規文件</p>
      </div>
      <div v-else class="card" style="padding:0;">
        <table class="data-table">
          <thead>
            <tr>
              <th style="width:160px;">文件類型</th>
              <th>檔案名稱</th>
              <th style="width:100px;">生效日</th>
              <th style="width:100px;">到期日</th>
              <th style="width:90px;">狀態</th>
              <th style="width:80px;">審核</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="doc in docs" :key="doc.id">
              <td><span class="badge badge-gray" style="font-size:11px;">{{ doc.doc_type }}</span></td>
              <td style="font-size:13px;">{{ doc.file_name || doc.file_path }}</td>
              <td class="font-mono" style="font-size:12px;">{{ doc.issued_at?.slice(0, 10) || '—' }}</td>
              <td class="font-mono" style="font-size:12px;">{{ doc.expires_at?.slice(0, 10) || '—' }}</td>
              <td><span class="badge" :class="statusBadge(doc.status)">{{ statusLabel(doc.status) }}</span></td>
              <td>
                <span v-if="doc.verified_at" class="badge badge-green" style="font-size:11px;">已審核</span>
                <span v-else style="font-size:12px;color:var(--text-secondary);">待審核</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- ────────── 碳排回報 Tab ────────── -->
    <template v-if="activeTab === 'emissions'">
      <div class="page-header" style="margin-top:16px;">
        <div>
          <h1 class="page-title">碳排回報</h1>
          <p class="page-subtitle">填報您供應品項的嵌入式碳排放數值</p>
        </div>
      </div>

      <div v-if="isLoadingGoods" class="loading-mask">載入中...</div>
      <div v-else-if="tradeGoods.length === 0" class="card empty-state" style="padding:48px;">
        <p>目前尚無需要填報碳排的品項</p>
      </div>
      <div v-else class="goods-list">
        <div v-for="g in tradeGoods" :key="g.id" class="card emission-card">
          <div class="emission-card__header">
            <div class="emission-card__info">
              <div class="emission-card__name">{{ g.name }}</div>
              <div class="emission-card__meta">
                <span class="font-mono" style="font-size:12px;color:var(--text-secondary);">HS {{ g.hs_code }}</span>
                <span v-if="g.product_code" class="font-mono" style="font-size:12px;color:var(--text-secondary);margin-left:12px;">{{ g.product_code }}</span>
                <span v-if="g.is_cbam_applicable" class="badge badge-cbam" style="margin-left:8px;">CBAM</span>
              </div>
            </div>
            <div class="emission-card__status">
              <span v-if="g.reported" class="status-dot status-dot--reported"></span>
              <span v-else class="status-dot status-dot--pending"></span>
              <span style="font-size:13px;">{{ g.reported ? '已填報' : '待填報' }}</span>
            </div>
          </div>

          <!-- 已填報資訊 -->
          <div v-if="g.reported" class="emission-card__reported">
            <div class="reported-row">
              <span class="reported-label">最新碳排</span>
              <span class="reported-value font-mono">{{ g.latest_emissions?.toFixed(4) }} <span style="color:var(--text-secondary);font-size:11px;">tCO₂e</span></span>
            </div>
            <div class="reported-row">
              <span class="reported-label">回報時間</span>
              <span class="reported-value font-mono" style="font-size:12px;">{{ g.reported_at?.slice(0, 10) || '—' }}</span>
            </div>
            <div v-if="g.confirmed_at" class="reported-row">
              <span class="reported-label">中心廠確認</span>
              <span class="reported-value font-mono" style="font-size:12px;color:#15803d;">{{ g.confirmed_at.slice(0, 10) }} ✓</span>
            </div>
            <button class="btn btn-secondary btn-sm" style="margin-top:8px;" @click="openReportModal(g)">更新數值</button>
          </div>

          <!-- 待填報 CTA -->
          <div v-else class="emission-card__pending">
            <button class="btn btn-primary btn-sm" @click="openReportModal(g)">填報碳排</button>
          </div>
        </div>
      </div>
    </template>

    <!-- 上傳文件 Modal -->
    <div v-if="showUploadModal" class="modal-overlay" @click.self="showUploadModal = false">
      <div class="modal" style="min-width:400px;">
        <div class="modal-header">
          <span class="modal-title">上傳合規文件</span>
          <button class="modal-close" @click="showUploadModal = false">×</button>
        </div>
        <div class="form-group">
          <label class="form-label">文件類型 *</label>
          <select v-model="form.doc_type" class="form-select">
            <option value="">— 選擇類型 —</option>
            <option v-for="t in DOC_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">文件檔案 * <span style="font-size:11px;color:var(--text-secondary);">（上限 10MB）</span></label>
          <input type="file" class="form-input" @change="form.file = ($event.target as HTMLInputElement).files?.[0] || null" />
        </div>
        <div class="form-group">
          <label class="form-label">生效日（選填）</label>
          <input v-model="form.issued_at" type="date" class="form-input" />
        </div>
        <div class="form-group">
          <label class="form-label">到期日（選填）</label>
          <input v-model="form.expires_at" type="date" class="form-input" />
        </div>
        <div class="form-group">
          <label class="form-label">備註（選填）</label>
          <textarea v-model="form.notes" class="form-input" rows="2" />
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showUploadModal = false">取消</button>
          <button class="btn btn-primary" :disabled="isSubmitting || !form.doc_type || !form.file" @click="upload">
            {{ isSubmitting ? '上傳中...' : '上傳' }}
          </button>
        </div>
      </div>
    </div>

    <!-- 碳排填報 Modal -->
    <div v-if="showReportModal" class="modal-overlay" @click.self="closeReportModal">
      <div class="modal" style="min-width:420px;">
        <div class="modal-header">
          <span class="modal-title">填報碳排 — {{ reportingGood?.name }}</span>
          <button class="modal-close" @click="closeReportModal">×</button>
        </div>
        <div class="form-group">
          <label class="form-label">嵌入式碳排放量 (tCO₂e) *</label>
          <input
            v-model.number="emissionForm.emissions_value"
            type="number"
            class="form-input font-mono"
            placeholder="例如：1.2345"
            min="0.0001"
            step="0.0001"
          />
        </div>
        <div class="form-group">
          <label class="form-label">計算說明（選填）</label>
          <textarea
            v-model="emissionForm.calculation_note"
            class="form-input"
            rows="3"
            placeholder="說明計算方法、依據標準或假設條件..."
          />
        </div>
        <div v-if="reportSuccess" class="alert alert-success">
          填報成功！中心廠收到後將進行確認。
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="closeReportModal">取消</button>
          <button
            class="btn btn-primary"
            :disabled="isSubmitting || !emissionForm.emissions_value"
            @click="submitEmission"
          >
            {{ isSubmitting ? '提交中...' : '送出填報' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ────────── 物料碳排 Tab ────────── -->
    <template v-if="activeTab === 'material-emissions'">
      <div class="page-header" style="margin-top:16px;">
        <div>
          <h1 class="page-title">物料碳排提報</h1>
          <p class="page-subtitle">填報您供應的物料每單位碳排放量（kgCO₂e），協助採購商計算產品碳足跡（PCF）</p>
        </div>
        <button class="btn btn-secondary" @click="openProactiveReportModal">主動填報其他物料</button>
      </div>

      <div v-if="isLoadingMatEmissions" style="padding:32px;text-align:center;color:var(--text-secondary);">載入中...</div>

      <div v-else-if="matEmissionItems.length === 0" class="card" style="padding:40px;text-align:center;color:var(--text-secondary);">
        尚無需提報的物料，或您尚未被指定為任何 BOM 料件的主供應商。
      </div>

      <div v-else class="mat-emission-list">
        <div v-for="item in matEmissionItems" :key="item.material_item_id" class="mat-emission-card">
          <div class="mat-emission-info">
            <div style="display:flex;align-items:center;gap:8px;">
              <span class="font-mono" style="font-size:12px;color:var(--accent);font-weight:700;">{{ item.item_code }}</span>
              <span style="font-weight:500;font-size:14px;">{{ item.name }}</span>
              <span v-if="item.hs_code" class="hs-chip font-mono">{{ item.hs_code }}</span>
              <span v-if="!item.in_bom" class="badge badge-gray" style="font-size:10px;">未指定於 BOM</span>
            </div>
            <div v-if="item.latest_emission" class="mat-latest">
              <template v-if="item.latest_emission.is_estimated">
                <span class="mat-estimated-box">🤖 估算值：<strong class="font-mono">{{ item.latest_emission.emissions_value.toFixed(4) }}</strong> kgCO₂e / {{ item.unit || '件' }}</span>
                <span style="font-size:11px;color:var(--text-secondary);">建議提報實際數據以提升 PCF 精度</span>
              </template>
              <template v-else>
                <span style="font-size:13px;font-weight:600;color:var(--accent);" class="font-mono">{{ item.latest_emission.emissions_value.toFixed(4) }}</span>
                <span style="font-size:12px;color:var(--text-secondary);">kgCO₂e / {{ item.unit || '件' }}</span>
                <span v-if="item.latest_emission.reported_period" class="badge badge-gray" style="font-size:10px;">{{ item.latest_emission.reported_period }}</span>
              </template>
            </div>
            <div v-else style="font-size:12px;color:var(--text-secondary);">尚未提報</div>
          </div>
          <div class="mat-emission-action">
            <span class="status-dot"
              :class="item.status === 'reported' ? 'dot-green' : item.status === 'ai_estimated' ? 'dot-amber' : 'dot-red'">
            </span>
            <span style="font-size:12px;color:var(--text-secondary);">
              {{ item.status === 'reported' ? '已提報' : item.status === 'ai_estimated' ? '待確認（AI估算）' : '待提報' }}
            </span>
            <button class="btn btn-primary btn-sm" @click="openMatReportModal(item)">
              {{ item.status === 'reported' ? '更新數值' : item.status === 'ai_estimated' ? '填報實際數據' : '填報碳排' }}
            </button>
          </div>
        </div>
      </div>
    </template>

    <!-- 物料碳排填報 Modal -->
    <div v-if="matReportModal.show" class="modal-overlay" @click.self="matReportModal.show=false">
      <div class="modal" style="min-width:440px;">
        <div class="modal-header">
          <span class="modal-title">{{ matReportModal.isProactive ? '主動填報物料碳排' : `填報碳排 — ${matReportModal.itemName}` }}</span>
          <button class="modal-close" @click="matReportModal.show=false">×</button>
        </div>

        <!-- 主動填報：搜尋物料 -->
        <div v-if="matReportModal.isProactive" class="form-group">
          <label class="form-label">搜尋物料（料號 / 品名 / HS Code）</label>
          <input
            v-model="matReportModal.searchKeyword"
            class="form-input"
            placeholder="輸入關鍵字搜尋..."
            @input="onSearchMaterial"
          />
          <div v-if="matReportModal.searchResults.length" class="mat-search-results">
            <div
              v-for="r in matReportModal.searchResults"
              :key="r.id"
              class="mat-search-item"
              :class="{ selected: matReportModal.selectedMaterialId === r.id }"
              @click="selectMaterial(r)"
            >
              <span class="font-mono" style="font-size:11px;color:var(--accent);">{{ r.item_code }}</span>
              <span style="font-size:13px;">{{ r.name }}</span>
              <span v-if="r.hs_code" style="font-size:11px;color:var(--text-secondary);">{{ r.hs_code }}</span>
            </div>
          </div>
        </div>

        <div v-if="!matReportModal.isProactive || matReportModal.selectedMaterialId">
          <div class="form-group">
            <label class="form-label">碳排值（kgCO₂e/{{ matReportModal.unit || '件' }}）*</label>
            <input v-model.number="matReportModal.emissions_value" type="number" min="0" step="0.000001" class="form-input font-mono" placeholder="0.000000" />
          </div>
          <div style="display:flex;gap:12px;">
            <div class="form-group" style="flex:1;">
              <label class="form-label">提報期間</label>
              <select v-model="matReportModal.reported_period" class="form-select">
                <option value="">不指定</option>
                <option v-for="p in matPeriodOptions" :key="p" :value="p">{{ p }}</option>
              </select>
            </div>
            <div class="form-group" style="flex:1;">
              <label class="form-label">計算方法</label>
              <input v-model="matReportModal.calculation_method" class="form-input" placeholder="ISO 14067..." />
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">備注（選填）</label>
            <textarea v-model="matReportModal.calculation_note" class="form-textarea" rows="2" placeholder="計算說明..."></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary" @click="matReportModal.show=false">取消</button>
          <button
            class="btn btn-primary"
            :disabled="isSubmitting || !matReportModal.emissions_value || (matReportModal.isProactive && !matReportModal.selectedMaterialId)"
            @click="submitMatReport"
          >
            {{ isSubmitting ? '提交中...' : '送出填報' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { complianceDocApi, type SupplierComplianceDoc } from '@/api/modules/compliance'
import { portalTradeGoodApi, type PortalTradeGood } from '@/api/modules/tradeGoods'
import { portalMaterialEmissionApi, type PortalMaterialEmissionItem, type MaterialItemSearchResult } from '@/api/modules/materialEmissions'

let matSearchTimer: ReturnType<typeof setTimeout> | null = null

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

export default defineComponent({
  name: 'SupplierCompliancePortalView',
  setup() { return { DOC_TYPES } },
  data() {
    return {
      activeTab: 'docs' as 'docs' | 'emissions' | 'material-emissions',
      isLoading: false,
      isLoadingGoods: false,
      isSubmitting: false,
      docs: [] as SupplierComplianceDoc[],
      tradeGoods: [] as PortalTradeGood[],
      showUploadModal: false,
      showReportModal: false,
      reportingGood: null as PortalTradeGood | null,
      reportSuccess: false,
      form: { doc_type: '', file: null as File | null, issued_at: '', expires_at: '', notes: '' },
      emissionForm: { emissions_value: null as number | null, calculation_note: '' },

      // 物料碳排 tab
      isLoadingMatEmissions: false,
      matEmissionItems: [] as PortalMaterialEmissionItem[],
      matReportModal: {
        show: false,
        isProactive: false,
        itemId: '',
        itemName: '',
        unit: '',
        emissions_value: null as number | null,
        reported_period: '',
        calculation_method: '',
        calculation_note: '',
        searchKeyword: '',
        searchResults: [] as MaterialItemSearchResult[],
        selectedMaterialId: '',
      },
    }
  },

  computed: {
    matPeriodOptions(): string[] {
      const year = new Date().getFullYear()
      const opts: string[] = []
      for (let y = year; y >= year - 2; y--) {
        for (const q of ['Q4', 'Q3', 'Q2', 'Q1']) opts.push(`${y}-${q}`)
      }
      return opts
    },
  },

  watch: {
    activeTab(tab: string) {
      if (tab === 'emissions' && this.tradeGoods.length === 0) this.loadTradeGoods()
      if (tab === 'material-emissions' && this.matEmissionItems.length === 0) this.loadMatEmissions()
    },
  },

  mounted() { this.loadDocs() },

  methods: {
    async loadDocs() {
      const supplierId = useAuthStore().user?.supplier_id
      if (!supplierId) return
      this.isLoading = true
      try {
        const { data } = await complianceDocApi.list(supplierId)
        this.docs = data.data
      } finally { this.isLoading = false }
    },

    async loadTradeGoods() {
      this.isLoadingGoods = true
      try {
        const { data } = await portalTradeGoodApi.list()
        this.tradeGoods = data.data
      } finally { this.isLoadingGoods = false }
    },

    openReportModal(good: PortalTradeGood) {
      this.reportingGood = good
      this.emissionForm = { emissions_value: null, calculation_note: '' }
      this.reportSuccess = false
      this.showReportModal = true
    },

    closeReportModal() {
      if (this.reportSuccess) this.loadTradeGoods()
      this.showReportModal = false
      this.reportingGood = null
      this.reportSuccess = false
    },

    async submitEmission() {
      if (!this.reportingGood || !this.emissionForm.emissions_value) return
      this.isSubmitting = true
      try {
        await portalTradeGoodApi.reportEmission(this.reportingGood.id, {
          emissions_value: this.emissionForm.emissions_value,
          calculation_note: this.emissionForm.calculation_note || undefined,
        })
        this.reportSuccess = true
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '填報失敗，請稍後再試')
      } finally { this.isSubmitting = false }
    },

    async upload() {
      const supplierId = useAuthStore().user?.supplier_id
      if (!supplierId || !this.form.doc_type || !this.form.file) return
      this.isSubmitting = true
      try {
        const fd = new FormData()
        fd.append('doc_type', this.form.doc_type)
        fd.append('file', this.form.file)
        if (this.form.issued_at) fd.append('issued_at', this.form.issued_at)
        if (this.form.expires_at) fd.append('expires_at', this.form.expires_at)
        if (this.form.notes) fd.append('notes', this.form.notes)
        await complianceDocApi.create(supplierId, fd)
        this.showUploadModal = false
        await this.loadDocs()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '上傳失敗')
      } finally { this.isSubmitting = false }
    },

    statusBadge(s: string) {
      return ({ valid: 'badge-green', expiring_soon: 'badge-orange', expired: 'badge-red', pending: 'badge-gray' })[s] ?? 'badge-gray'
    },

    statusLabel(s: string) {
      return ({ valid: '有效', expiring_soon: '即將到期', expired: '已過期', pending: '無效期' })[s] ?? s
    },

    // 物料碳排 tab
    async loadMatEmissions() {
      this.isLoadingMatEmissions = true
      try {
        const { data } = await portalMaterialEmissionApi.list()
        this.matEmissionItems = data.data
      } finally { this.isLoadingMatEmissions = false }
    },

    openMatReportModal(item: PortalMaterialEmissionItem) {
      this.matReportModal = {
        show: true,
        isProactive: false,
        itemId: item.material_item_id,
        itemName: item.name,
        unit: item.unit,
        emissions_value: null,
        reported_period: '',
        calculation_method: '',
        calculation_note: '',
        searchKeyword: '',
        searchResults: [],
        selectedMaterialId: '',
      }
    },

    openProactiveReportModal() {
      this.matReportModal = {
        show: true,
        isProactive: true,
        itemId: '',
        itemName: '',
        unit: '件',
        emissions_value: null,
        reported_period: '',
        calculation_method: '',
        calculation_note: '',
        searchKeyword: '',
        searchResults: [],
        selectedMaterialId: '',
      }
    },

    onSearchMaterial() {
      if (matSearchTimer) clearTimeout(matSearchTimer)
      matSearchTimer = setTimeout(async () => {
        const kw = this.matReportModal.searchKeyword
        if (!kw) { this.matReportModal.searchResults = []; return }
        const { data } = await portalMaterialEmissionApi.searchMaterials(kw)
        this.matReportModal.searchResults = data.data
      }, 300)
    },

    selectMaterial(r: MaterialItemSearchResult) {
      this.matReportModal.selectedMaterialId = r.id
      this.matReportModal.itemName = r.name
      this.matReportModal.unit = r.unit
      this.matReportModal.searchResults = []
    },

    async submitMatReport() {
      const materialItemId = this.matReportModal.isProactive
        ? this.matReportModal.selectedMaterialId
        : this.matReportModal.itemId
      if (!materialItemId || !this.matReportModal.emissions_value) return
      this.isSubmitting = true
      try {
        await portalMaterialEmissionApi.create({
          material_item_id: materialItemId,
          emissions_value: this.matReportModal.emissions_value,
          reported_period: this.matReportModal.reported_period || undefined,
          calculation_method: this.matReportModal.calculation_method || undefined,
          calculation_note: this.matReportModal.calculation_note || undefined,
        })
        this.matReportModal.show = false
        await this.loadMatEmissions()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '填報失敗')
      } finally { this.isSubmitting = false }
    },
  },
})
</script>

<style scoped>
.badge-orange { background: #ffedd5; color: #c2410c; }
.badge-cbam { background: #dbeafe; color: #1d4ed8; font-size: 11px; }

.tab-bar {
  display: flex;
  gap: 4px;
  border-bottom: 1px solid var(--border);
  padding-bottom: 0;
  margin-bottom: 0;
}
.tab-btn {
  padding: 8px 20px;
  border: none;
  background: none;
  cursor: pointer;
  font-size: 14px;
  color: var(--text-secondary);
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
}
.tab-btn.active {
  color: var(--accent);
  border-bottom-color: var(--accent);
  font-weight: 600;
}

.goods-list { display: flex; flex-direction: column; gap: 12px; }

.emission-card { padding: 16px 20px; }
.emission-card__header { display: flex; align-items: flex-start; justify-content: space-between; }
.emission-card__name { font-size: 15px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px; }
.emission-card__meta { display: flex; align-items: center; flex-wrap: wrap; gap: 4px; }
.emission-card__status { display: flex; align-items: center; gap: 6px; white-space: nowrap; }

.status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.status-dot--reported { background: #16a34a; }
.status-dot--pending { background: #d97706; }

.emission-card__reported {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid var(--border);
}
.reported-row { display: flex; align-items: center; gap: 16px; margin-bottom: 4px; }
.reported-label { font-size: 12px; color: var(--text-secondary); width: 72px; flex-shrink: 0; }
.reported-value { font-size: 14px; color: var(--text-primary); }

.emission-card__pending { margin-top: 12px; }

.btn-sm { padding: 4px 12px; font-size: 13px; }

.alert-success {
  background: #dcfce7;
  color: #15803d;
  border: 1px solid #86efac;
  border-radius: 6px;
  padding: 10px 14px;
  font-size: 13px;
  margin-bottom: 12px;
}

/* 物料碳排 Tab */
.mat-emission-list { display: flex; flex-direction: column; gap: 8px; }
.mat-emission-card { display: flex; align-items: center; justify-content: space-between; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 14px 16px; gap: 16px; }
.mat-emission-info { flex: 1; display: flex; flex-direction: column; gap: 6px; }
.mat-latest { display: flex; align-items: center; gap: 8px; }
.mat-estimated-box { font-size: 12px; background: #f3f4f6; border: 1px dashed #d1d5db; border-radius: 6px; padding: 3px 8px; color: #6b7280; }
.mat-emission-action { display: flex; align-items: center; gap: 10px; }
.status-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.dot-green { background: #22c55e; }
.dot-amber { background: #f59e0b; }
.dot-red   { background: #ef4444; }
.hs-chip { font-size: 11px; background: var(--surface-2); color: var(--text-secondary); padding: 2px 6px; border-radius: 4px; border: 1px solid var(--border); }

/* 物料搜尋結果 */
.mat-search-results { border: 1px solid var(--border); border-radius: 6px; background: var(--surface); max-height: 200px; overflow-y: auto; margin-top: 4px; }
.mat-search-item { display: flex; align-items: center; gap: 10px; padding: 8px 12px; cursor: pointer; font-size: 13px; }
.mat-search-item:hover, .mat-search-item.selected { background: var(--surface-2); }
</style>
