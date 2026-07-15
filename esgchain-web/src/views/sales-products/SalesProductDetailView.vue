<template>
  <div class="page-container">
    <!-- 頁頭 -->
    <div class="page-header">
      <div style="display:flex; align-items:center; gap:12px;">
        <button class="btn btn-secondary btn-sm" @click="$router.push('/sales-products')">← 返回列表</button>
        <div v-if="product">
          <h1 class="page-title">{{ product.name }}</h1>
          <p class="page-subtitle">
            <span v-if="product.product_code" class="font-mono">{{ product.product_code }} · </span>
            <span class="font-mono">{{ product.hs_code }}</span>
            <span v-if="product.customer_name"> · {{ product.customer_name }}</span>
          </p>
        </div>
      </div>
      <div v-if="product" style="display:flex;gap:8px;align-items:center;">
        <span class="badge" :class="complianceBadgeClass(product.upstream_compliance_status)">
          {{ STATUS_LABELS[product.upstream_compliance_status] ?? product.upstream_compliance_status }}
        </span>
        <template v-if="!isEditing">
          <button class="btn btn-secondary btn-sm" @click="enterEditMode">編輯</button>
          <button class="btn btn-danger btn-sm" @click="showDeleteConfirm = true">刪除</button>
        </template>
        <template v-else>
          <button class="btn btn-secondary btn-sm" @click="cancelEdit">取消</button>
          <button class="btn btn-primary btn-sm" :disabled="isSaving" @click="saveEdit">
            {{ isSaving ? '儲存中…' : '儲存' }}
          </button>
        </template>
      </div>
    </div>

    <div v-if="isLoading" class="loading-mask">載入中…</div>
    <div v-else-if="!product" class="empty-state"><p>找不到此銷售商品</p></div>
    <div v-else class="detail-layout">
      <div class="detail-main">

        <!-- Tab 導覽 -->
        <div class="detail-tabs">
          <button
            v-for="t in TABS" :key="t.key"
            class="detail-tab"
            :class="{ active: activeTab === t.key }"
            @click="switchTab(t.key)"
          >{{ t.label }}</button>
        </div>

        <!-- ══ 基本資訊 ══ -->
        <div v-show="activeTab === 'info'" class="detail-section tab-panel">
          <div class="detail-grid">
            <div class="detail-item">
              <span class="detail-label">品項名稱</span>
              <input v-if="isEditing" v-model="editForm.name" class="form-input" />
              <span v-else class="detail-value">{{ product.name }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">SKU 品號</span>
              <input v-if="isEditing" v-model="editForm.product_code" class="form-input font-mono" />
              <span v-else class="detail-value font-mono">{{ product.product_code || '—' }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">型號</span>
              <input v-if="isEditing" v-model="editForm.model_no" class="form-input font-mono" />
              <span v-else class="detail-value font-mono">{{ product.model_no || '—' }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">HS Code</span>
              <input v-if="isEditing" v-model="editForm.hs_code" class="form-input font-mono" />
              <span v-else class="detail-value font-mono">{{ product.hs_code }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">單位</span>
              <input v-if="isEditing" v-model="editForm.unit" class="form-input" />
              <span v-else class="detail-value">{{ product.unit || '—' }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">單價</span>
              <input v-if="isEditing" v-model.number="editForm.unit_price" class="form-input font-mono" type="number" />
              <span v-else class="detail-value font-mono">
                {{ product.unit_price != null ? product.unit_price.toFixed(2) : '—' }}
                <span v-if="product.unit_price != null && product.currency" style="font-size:11px;opacity:0.6;"> {{ product.currency }}</span>
              </span>
            </div>
            <div class="detail-item">
              <span class="detail-label">幣別</span>
              <select v-if="isEditing" v-model="editForm.currency" class="form-select">
                <option>USD</option><option>EUR</option><option>TWD</option>
              </select>
              <span v-else class="detail-value">{{ product.currency || '—' }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">客戶</span>
              <select v-if="isEditing" v-model="editForm.customer_id" class="form-select">
                <option value="">— 不指定 —</option>
                <option v-for="c in allCustomers" :key="c.id" :value="c.id">{{ c.name }} ({{ c.code }})</option>
              </select>
              <span v-else class="detail-value">{{ product.customer_name || '—' }}</span>
            </div>
            <div class="detail-item" style="grid-column:1/-1;">
              <span class="detail-label">說明</span>
              <textarea v-if="isEditing" v-model="editForm.description" class="form-input" rows="2" />
              <span v-else class="detail-value">{{ product.description || '—' }}</span>
            </div>
          </div>

          <!-- 內含碳排量摘要 -->
          <div class="info-card" style="margin-top:20px;">
            <div class="info-card-title">內含碳排量</div>
            <div style="display:flex;align-items:baseline;gap:10px;margin-top:8px;">
              <template v-if="product.embedded_emissions != null">
                <span class="font-mono" style="font-size:22px;font-weight:700;">{{ product.embedded_emissions.toFixed(4) }}</span>
                <span style="font-size:13px;color:var(--text-secondary);">kgCO₂e/u</span>
                <span class="tag" :class="`esb-${product.emissions_source}`" style="margin-left:4px;">
                  {{ { pcf_sync: 'PCF 同步', supplier_reported: '供應商回報', manual: '手動輸入' }[product.emissions_source ?? ''] ?? product.emissions_source }}
                </span>
              </template>
              <span v-else style="color:var(--text-secondary);font-size:14px;">尚未計算 — 請至 PCF 快照 Tab 觸發計算</span>
            </div>
            <div v-if="product.emissions_updated_at" style="font-size:11px;color:var(--text-secondary);margin-top:4px;">
              更新於 {{ product.emissions_updated_at.slice(0, 16) }}
            </div>
          </div>
        </div>

        <!-- ══ 法規適用 ══ -->
        <div v-show="activeTab === 'regulations'" class="detail-section tab-panel">
          <div class="reg-grid">
            <div class="reg-card" :class="product.is_cbam_applicable ? 'reg-card--active' : 'reg-card--inactive'">
              <div class="reg-card-name">CBAM</div>
              <div class="reg-card-status">{{ product.is_cbam_applicable ? '適用' : '不適用' }}</div>
              <div v-if="product.cbam_category" class="reg-card-detail">類別：{{ product.cbam_category }}</div>
            </div>
            <div class="reg-card" :class="product.is_eudr_applicable ? 'reg-card--active' : 'reg-card--inactive'">
              <div class="reg-card-name">EUDR</div>
              <div class="reg-card-status">{{ product.is_eudr_applicable ? '適用' : '不適用' }}</div>
            </div>
          </div>

          <div class="detail-section-head" style="margin-top:24px;">適用法規清單</div>
          <div v-if="product.applicable_regulations?.length" class="reg-tags">
            <span v-for="r in product.applicable_regulations" :key="r" class="tag tag-reg">{{ r }}</span>
          </div>
          <p v-else class="empty-hint">尚無明確適用法規</p>

          <div class="detail-section-head" style="margin-top:20px;">AI 推算法規</div>
          <div v-if="product.inferred_regulations?.length" class="reg-tags">
            <span v-for="r in product.inferred_regulations" :key="r" class="tag tag-inferred">{{ r }}</span>
          </div>
          <p v-else class="empty-hint">尚無 AI 推算結果</p>

          <div style="margin-top:16px;">
            <button class="btn btn-secondary" :disabled="syncingReg" @click="syncRegulations">
              {{ syncingReg ? '推算中…' : '🔄 重新推算法規' }}
            </button>
          </div>
        </div>

        <!-- ══ BOM 明細 ══ -->
        <div v-show="activeTab === 'bom'" class="detail-section tab-panel">
          <div v-if="bomLoading" class="loading-hint">載入中…</div>
          <div v-else>
            <table v-if="bomLines.length" class="data-table">
              <thead>
                <tr>
                  <th>物料名稱</th>
                  <th>HS Code</th>
                  <th>類型</th>
                  <th>數量</th>
                  <th>供應商</th>
                  <th>子產品</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="bl in bomLines" :key="bl.id">
                  <td>
                    {{ bl.child_sales_product_id ? (bl.child_sales_product?.name ?? '—') : (bl.effective_material_name ?? bl.material_name) }}
                    <span v-if="bl.linkage_status === 'unlinked' && !bl.child_sales_product_id" class="badge badge-unlinked" style="margin-left:6px;font-size:10px;">未連結主檔</span>
                  </td>
                  <td class="font-mono" style="font-size:11px;">{{ (bl.effective_hs_code ?? bl.hs_code) || '—' }}</td>
                  <td>
                    <span v-if="bl.child_sales_product_id" class="tag tag-blue">子產品</span>
                    <span v-else class="tag tag-gray">{{ BOM_LINE_TYPE_LABELS[bl.bom_line_type] ?? bl.bom_line_type }}</span>
                  </td>
                  <td class="font-mono">{{ bl.quantity ?? '—' }}</td>
                  <td>
                    <template v-if="bl.primary_supplier">
                      <div class="bom-sup-row">
                        {{ bl.primary_supplier.name }}
                        <span class="tier-badge font-mono">T{{ bl.primary_supplier.tier }}</span>
                        <span
                          v-if="supplierCompliance(bl.primary_supplier.id)"
                          class="status-dot"
                          :class="`status-dot--${supplierCompliance(bl.primary_supplier.id).status}`"
                          :title="STATUS_LABELS[supplierCompliance(bl.primary_supplier.id).status] ?? ''"
                        ></span>
                      </div>
                      <div v-if="supplierCompliance(bl.primary_supplier.id)?.doc_statuses?.length" class="bom-sup-docs">
                        <span
                          v-for="d in supplierCompliance(bl.primary_supplier.id).doc_statuses" :key="d.doc_type"
                          class="doc-chip" :class="`doc-chip--${d.status}`"
                          :title="DOC_STATUS_LABELS[d.status] ?? d.status"
                        >
                          {{ DOC_TYPE_LABELS[d.doc_type] ?? d.doc_type }}
                          <span class="doc-status-dot" :class="`doc-dot--${d.status}`"></span>
                          <span v-if="d.expires_at" class="doc-exp">{{ d.expires_at }}</span>
                        </span>
                      </div>
                    </template>
                    <span v-else class="no-data">—</span>
                  </td>
                  <td>
                    <span v-if="bl.child_sales_product_id" class="font-mono" style="font-size:11px;">
                      {{ bl.child_sales_product?.product_code || bl.child_sales_product_id?.slice(0, 8) }}…
                    </span>
                    <span v-else class="no-data">—</span>
                  </td>
                  <td>
                    <button class="btn btn-danger btn-sm" title="依商業機密保全考量刪除" @click="deleteBomLine(bl.id)">✕</button>
                  </td>
                </tr>
              </tbody>
            </table>
            <p v-else class="empty-hint">尚無 BOM 明細</p>

            <p class="erp-note">BOM 結構由 ERP 同步匯入（不可手動新增）；如涉商業機密，可刪除個別列。</p>
          </div>
        </div>

        <!-- ══ 生產批次 ══ -->
        <div v-show="activeTab === 'batches'" class="detail-section tab-panel">
          <table v-if="productionBatches.length" class="data-table">
            <thead>
              <tr>
                <th>生產批號</th>
                <th>生產工單</th>
                <th>生產日期</th>
                <th style="text-align:right;">數量</th>
                <th style="text-align:right;">批次碳排</th>
                <th>縫製工廠</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="b in productionBatches" :key="b.id">
                <td class="font-mono">{{ b.erp_batch_no }}</td>
                <td class="font-mono" style="font-size:11px;color:var(--text-secondary);">{{ b.erp_order_no || '—' }}</td>
                <td class="font-mono">{{ b.production_date || '—' }}</td>
                <td class="font-mono" style="text-align:right;">{{ b.quantity != null ? Number(b.quantity).toLocaleString() : '—' }} {{ b.unit }}</td>
                <td class="font-mono" style="text-align:right;">
                  <template v-if="b.lot_pcf != null">{{ Number(b.lot_pcf).toFixed(2) }} <span class="unit">kgCO₂e/件</span></template>
                  <template v-else>—</template>
                </td>
                <td>{{ b.supplier_name || '—' }}<span v-if="b.supplier_code" class="font-mono" style="font-size:11px;color:var(--text-secondary);"> · {{ b.supplier_code }}</span></td>
              </tr>
            </tbody>
          </table>
          <p v-else class="empty-hint">尚無生產批次紀錄</p>
        </div>

        <!-- ══ PCF 快照 ══ -->
        <div v-show="activeTab === 'pcf'" class="detail-section tab-panel">
          <div v-if="pcfLoading" class="loading-hint">載入中…</div>
          <div v-else>
            <div v-if="pcfSnapshot" class="pcf-summary">
              <div class="pcf-total">
                <span class="pcf-label">總 PCF</span>
                <span class="pcf-val font-mono">{{ pcfSnapshot.total_pcf?.toFixed(4) ?? '—' }} kgCO₂e/u</span>
              </div>
              <div class="pcf-meta">
                <span class="tag" :class="pcfSnapshot.iso14067_ready ? 'tag-ok' : 'tag-warn'">
                  {{ pcfSnapshot.iso14067_ready ? 'ISO 14067 Ready' : '資料不完整' }}
                </span>
                <span class="font-mono" style="font-size:11px;color:var(--text-secondary);">
                  快照時間：{{ pcfSnapshot.snapshot_at?.slice(0, 16) }}
                </span>
                <span v-if="pcfSnapshot.pcr_incomplete_lines > 0" class="tag tag-warn" style="font-size:11px;">
                  {{ pcfSnapshot.pcr_incomplete_lines }} 筆資料不完整
                </span>
              </div>

              <!-- PCF 明細 -->
              <div v-if="pcfSnapshot.lines?.length" style="margin-top:16px;">
                <div class="detail-section-head">PCF 構成明細</div>
                <table class="data-table" style="margin-top:8px;">
                  <thead><tr><th>項目</th><th>類型</th><th>數量</th><th>kgCO₂e/u</th></tr></thead>
                  <tbody>
                    <tr v-for="(line, i) in pcfSnapshot.lines" :key="i">
                      <td>{{ line.material_name ?? '—' }}</td>
                      <td>{{ line.bom_line_type ?? '—' }}</td>
                      <td class="font-mono">{{ line.quantity ?? '—' }}</td>
                      <td class="font-mono">{{ line.line_pcf?.toFixed(4) ?? '—' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <p v-else class="empty-hint">尚無 PCF 快照</p>

            <div style="margin-top:16px;">
              <button class="btn btn-secondary" :disabled="recalcLoading" @click="recalcPcf">
                {{ recalcLoading ? '計算中…' : '🔄 重新計算 PCF' }}
              </button>
            </div>
          </div>
        </div>

        <!-- ══ 碳排回報 ══ -->
        <div v-show="activeTab === 'emissions'" class="detail-section tab-panel">
          <div v-if="emissionsLoading" class="loading-hint">載入中…</div>
          <div v-else>
            <table v-if="emissions.length" class="data-table">
              <thead>
                <tr>
                  <th>供應商</th>
                  <th>kgCO₂e/unit</th>
                  <th>計算說明</th>
                  <th>回報時間</th>
                  <th>確認狀態</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="e in emissions" :key="e.id">
                  <td>{{ e.supplier?.name ?? '—' }}</td>
                  <td class="font-mono">{{ e.emissions_value.toFixed(4) }}</td>
                  <td>{{ e.calculation_note || '—' }}</td>
                  <td class="font-mono" style="font-size:11px;white-space:nowrap;">{{ e.reported_at?.slice(0, 16) }}</td>
                  <td>
                    <span v-if="e.confirmed_at" class="tag tag-ok">已確認</span>
                    <span v-else class="tag tag-pending">待確認</span>
                  </td>
                  <td>
                    <button
                      v-if="!e.confirmed_at"
                      class="btn btn-secondary"
                      style="font-size:11px;padding:3px 8px;"
                      :disabled="confirmingEmission[e.id]"
                      @click="confirmEmission(e.id)"
                    >
                      {{ confirmingEmission[e.id] ? '確認中…' : '確認採用' }}
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
            <p v-else class="empty-hint">尚無供應商碳排回報</p>
          </div>
        </div>

      </div>
    </div>

    <!-- 刪除確認 Modal -->
    <div v-if="showDeleteConfirm" class="modal-overlay" @click.self="showDeleteConfirm = false">
      <div class="modal" style="min-width:340px;">
        <div class="modal-header">
          <span class="modal-title">確認刪除</span>
          <button class="modal-close" @click="showDeleteConfirm = false">×</button>
        </div>
        <p style="padding:16px 0;font-size:14px;">確定刪除「{{ product?.name }}」？此動作無法復原。</p>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showDeleteConfirm = false">取消</button>
          <button class="btn btn-danger" :disabled="isDeleting" @click="doDelete">
            {{ isDeleting ? '刪除中…' : '確認刪除' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { salesProductApi, type SalesProduct, type SalesProductSupplier, type BomLine, type PcfSnapshot, type ProductionBatch } from '@/api/modules/salesProducts'
import { materialGroupApi } from '@/api/modules/compliance'
import { customersApi, type Customer } from '@/api/modules/customers'

const TABS = [
  { key: 'info',        label: '基本資訊' },
  { key: 'regulations', label: '法規適用' },
  { key: 'bom',         label: 'BOM 明細' },
  { key: 'batches',     label: '生產批次' },
  { key: 'pcf',         label: 'PCF 快照' },
  { key: 'emissions',   label: '碳排回報' },
]

const STATUS_LABELS: Record<string, string> = {
  valid: '合規', expiring_soon: '即將到期', expired: '已過期',
  missing: '缺文件', unconfigured: '未設定',
}

const DOC_TYPE_LABELS: Record<string, string> = {
  UFLPA_DECLARATION: 'UFLPA 聲明',
  ORIGIN_CERT:       '原產地證書',
  EUDR_DDS:          'EUDR 盡職調查',
  REACH_DECLARATION: 'REACH 聲明',
  ROHS_DECLARATION:  'RoHS 聲明',
  CONFLICT_MINERALS: '衝突礦產報告',
  SUPPLIER_COC:      '供應商行為準則',
  PCF_REPORT:        'PCF 報告',
  TEST_REPORT:       '測試報告',
  AUDIT_CERT:        '稽核證書',
}

const DOC_STATUS_LABELS: Record<string, string> = {
  valid: '有效', expiring_soon: '即將到期', expired: '已過期', missing: '缺件',
}

const BOM_LINE_TYPE_LABELS: Record<string, string> = {
  material: '物料', service: '服務',
}

export default defineComponent({
  name: 'SalesProductDetailView',
  data() {
    return {
      isLoading: true,
      isSaving: false,
      isDeleting: false,
      isEditing: false,
      showDeleteConfirm: false,
      product: null as SalesProduct | null,
      productionBatches: [] as ProductionBatch[],
      editForm: {} as Partial<SalesProduct> & { customer_id: string },
      activeTab: 'info',
      // 供應商
      suppliers: [] as SalesProductSupplier[],
      suppLoading: false,
      allSuppliers: [] as any[],
      materialGroups: [] as any[],
      addSupplierForm: { supplier_id: '', material_group_id: '' },
      addingSupplier: false,
      // BOM
      bomLines: [] as BomLine[],
      bomLoading: false,
      addBomForm: { material_name: '', hs_code: '', quantity: null as number | null },
      addingBom: false,
      // PCF
      pcfSnapshot: null as PcfSnapshot | null,
      pcfLoading: false,
      recalcLoading: false,
      // 碳排
      emissions: [] as any[],
      emissionsLoading: false,
      confirmingEmission: {} as Record<string, boolean>,
      // 法規
      syncingReg: false,
      // 輔助資料
      allCustomers: [] as Customer[],
      TABS,
      STATUS_LABELS,
      DOC_TYPE_LABELS,
      DOC_STATUS_LABELS,
      BOM_LINE_TYPE_LABELS,
    }
  },
  async mounted() {
    await this.loadProduct()
    this.loadAuxData()
  },
  methods: {
    async loadProduct() {
      this.isLoading = true
      try {
        const { data } = await salesProductApi.show(this.$route.params.id as string)
        this.product = data.data
        this.productionBatches = data.data.production_batches ?? []
      } finally {
        this.isLoading = false
      }
    },
    async loadAuxData() {
      try {
        const http = (await import('@/api/http')).default
        const [suppRes, mgRes, custRes] = await Promise.all([
          http.get<any>('/api/v1/suppliers?per_page=200'),
          materialGroupApi.list(),
          customersApi.list({ per_page: 200, status: 'active' }),
        ])
        this.allSuppliers = suppRes.data?.data?.data ?? suppRes.data?.data ?? []
        this.materialGroups = mgRes.data.data
        this.allCustomers = custRes.data.data
      } catch { /* silent */ }
    },
    switchTab(key: string) {
      this.activeTab = key
      if (key === 'bom' && !this.bomLines.length && !this.bomLoading) this.loadBom()
      if (key === 'pcf' && !this.pcfSnapshot && !this.pcfLoading) this.loadPcf()
      if (key === 'emissions' && !this.emissions.length && !this.emissionsLoading) this.loadEmissions()
    },
    // BOM 行供應商 → 上游合規資訊（由 show() 的 upstream_details 對映）
    supplierCompliance(supplierId: string | null | undefined): any {
      if (!supplierId) return null
      const details = (this.product as any)?.upstream_details ?? []
      return details.find((d: any) => d.supplier_id === supplierId) ?? null
    },
    // ── 供應商 ──
    async loadSuppliers() {
      this.suppLoading = true
      try {
        const { data } = await salesProductApi.suppliers(this.$route.params.id as string)
        this.suppliers = data.data
      } finally { this.suppLoading = false }
    },
    async addSupplier() {
      if (!this.addSupplierForm.supplier_id) return
      this.addingSupplier = true
      try {
        await salesProductApi.addSupplier(this.$route.params.id as string, {
          supplier_id: this.addSupplierForm.supplier_id,
          material_group_id: this.addSupplierForm.material_group_id || undefined,
        })
        this.addSupplierForm = { supplier_id: '', material_group_id: '' }
        await this.loadSuppliers()
        await this.loadProduct()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '新增失敗')
      } finally { this.addingSupplier = false }
    },
    async removeSupplier(suppId: string) {
      try {
        await salesProductApi.removeSupplier(this.$route.params.id as string, suppId)
        await this.loadSuppliers()
        await this.loadProduct()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '移除失敗')
      }
    },
    // ── BOM ──
    async loadBom() {
      this.bomLoading = true
      try {
        const { data } = await salesProductApi.bomLines(this.$route.params.id as string)
        this.bomLines = data.data
      } finally { this.bomLoading = false }
    },
    async addBomLine() {
      if (!this.addBomForm.material_name) return
      this.addingBom = true
      try {
        await salesProductApi.createBomLine(this.$route.params.id as string, {
          material_name: this.addBomForm.material_name,
          hs_code: this.addBomForm.hs_code || undefined,
          quantity: this.addBomForm.quantity ?? undefined,
          bom_line_type: 'material',
        })
        this.addBomForm = { material_name: '', hs_code: '', quantity: null }
        await this.loadBom()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '新增失敗')
      } finally { this.addingBom = false }
    },
    async deleteBomLine(lineId: string) {
      try {
        await salesProductApi.destroyBomLine(this.$route.params.id as string, lineId)
        await this.loadBom()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '刪除失敗')
      }
    },
    // ── PCF ──
    async loadPcf() {
      this.pcfLoading = true
      try {
        const { data } = await salesProductApi.pcfLatest(this.$route.params.id as string)
        this.pcfSnapshot = data.data
      } finally { this.pcfLoading = false }
    },
    async recalcPcf() {
      this.recalcLoading = true
      try {
        const { data } = await salesProductApi.pcfRecalc(this.$route.params.id as string)
        this.pcfSnapshot = data.data
        await this.loadProduct()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '計算失敗')
      } finally { this.recalcLoading = false }
    },
    // ── 碳排回報 ──
    async loadEmissions() {
      this.emissionsLoading = true
      try {
        const { data } = await salesProductApi.emissionReports(this.$route.params.id as string)
        this.emissions = data.data
      } finally { this.emissionsLoading = false }
    },
    async confirmEmission(emissionId: string) {
      this.confirmingEmission[emissionId] = true
      try {
        await salesProductApi.confirmEmission(this.$route.params.id as string, emissionId)
        await this.loadEmissions()
        await this.loadProduct()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '確認失敗')
      } finally { this.confirmingEmission[emissionId] = false }
    },
    // ── 法規推算 ──
    async syncRegulations() {
      this.syncingReg = true
      try {
        const { data } = await salesProductApi.syncRegulations(this.$route.params.id as string)
        if (this.product) {
          this.product.inferred_regulations = data.data.inferred_regulations
          this.product.applicable_regulations = data.data.applicable_regulations
        }
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '推算失敗')
      } finally { this.syncingReg = false }
    },
    // ── 編輯 ──
    enterEditMode() {
      if (!this.product) return
      this.editForm = {
        name: this.product.name,
        product_code: this.product.product_code ?? '',
        model_no: this.product.model_no ?? '',
        hs_code: this.product.hs_code,
        unit: this.product.unit ?? '',
        unit_price: this.product.unit_price,
        currency: this.product.currency ?? 'USD',
        description: this.product.description ?? '',
        customer_id: this.product.customer_id ?? '',
      }
      this.isEditing = true
    },
    cancelEdit() { this.isEditing = false },
    async saveEdit() {
      if (!this.product) return
      this.isSaving = true
      try {
        const payload = { ...this.editForm, customer_id: (this.editForm.customer_id as string) || null }
        await salesProductApi.update(this.product.id, payload)
        await this.loadProduct()
        this.isEditing = false
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '儲存失敗')
      } finally { this.isSaving = false }
    },
    // ── 刪除 ──
    async doDelete() {
      if (!this.product) return
      this.isDeleting = true
      try {
        await salesProductApi.destroy(this.product.id)
        this.$router.push('/sales-products')
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '刪除失敗')
        this.isDeleting = false
      }
    },
    complianceBadgeClass(status: string) {
      return {
        valid:         'badge-green',
        expiring_soon: 'badge-yellow',
        expired:       'badge-red',
        missing:       'badge-red',
        unconfigured:  'badge-gray',
      }[status] ?? 'badge-gray'
    },
  },
})
</script>

<style scoped>
.detail-layout { display: flex; flex-direction: column; gap: 0; }
.detail-main { display: flex; flex-direction: column; gap: 0; }

/* ── Tab 導覽 ── */
.detail-tabs {
  display: flex;
  border-bottom: 1px solid var(--border);
  background: var(--surface);
  border-radius: 8px 8px 0 0;
  border: 1px solid var(--border);
  border-bottom: none;
  overflow: hidden;
  flex-wrap: wrap;
}
.detail-tab {
  padding: 11px 20px;
  border: none;
  background: none;
  cursor: pointer;
  font-size: 13.5px;
  font-weight: 500;
  color: #57534e;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  transition: all 0.15s;
  white-space: nowrap;
}
.detail-tab:hover { color: var(--text-primary); background: var(--surface-2); }
.detail-tab.active {
  color: var(--accent);
  border-bottom-color: var(--accent);
  font-weight: 600;
  background: var(--surface);
}
.tab-panel {
  border-radius: 0 0 8px 8px !important;
  border-top: none !important;
  margin-bottom: 16px;
}

/* ── Section ── */
.detail-section {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 20px 24px;
  margin-bottom: 16px;
}

/* ── Detail Grid ── */
.detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px 32px;
}
@media (max-width: 640px) { .detail-grid { grid-template-columns: 1fr; } }
.detail-item { display: flex; flex-direction: column; gap: 6px; }
.detail-label { font-size: 11px; color: #a8998f; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; }
.detail-value { font-size: 14px; color: var(--text-primary); line-height: 1.45; }

/* ── Badge（供應商合規狀態）── */
.badge-green    { background: #dcfce7; color: #166534; }
.badge-yellow   { background: #fef9c3; color: #854d0e; }
.badge-red      { background: #fee2e2; color: #991b1b; }
.badge-gray     { background: var(--surface-2); color: #57534e; border: 1px solid var(--border); }
.badge-unlinked { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }

/* ── 補充 tag ── */
.tag-warn { background: #fef9c3; color: #854d0e; border: 1px solid #fde047; }

/* ── 雜項 ── */
.no-data { font-size: 13px; color: var(--text-secondary); }

.reg-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 12px;
}
.reg-card {
  border-radius: 8px;
  border: 1px solid var(--border);
  padding: 16px;
  text-align: center;
}
.reg-card--active { background: #edf7f2; border-color: #a8d4bf; }
.reg-card--inactive { background: var(--surface-2); }
.reg-card-name { font-size: 13px; font-weight: 700; letter-spacing: .04em; color: var(--text-secondary); margin-bottom: 6px; }
.reg-card-status { font-size: 18px; font-weight: 700; }
.reg-card--active .reg-card-status { color: #1a5c3a; }
.reg-card--inactive .reg-card-status { color: var(--text-secondary); }
.reg-card-detail { font-size: 11px; color: var(--text-secondary); margin-top: 4px; text-transform: capitalize; }

.reg-tags { display: flex; flex-wrap: wrap; gap: 6px; }
.tag-reg { background: #ede9fe; color: #6d28d9; border: 1px solid #c4b5fd; }
.tag-inferred { background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; }

.detail-section-head {
  font-size: 11.5px;
  font-weight: 700;
  letter-spacing: .07em;
  text-transform: uppercase;
  color: var(--text-secondary);
  margin-bottom: 8px;
}

.info-card {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 14px 16px;
}
.info-card-title {
  font-size: 11.5px;
  font-weight: 700;
  letter-spacing: .07em;
  text-transform: uppercase;
  color: var(--text-secondary);
}

.pcf-summary {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 16px;
}
.pcf-total { display: flex; align-items: baseline; gap: 10px; margin-bottom: 10px; }
.pcf-label { font-size: 12px; font-weight: 600; color: var(--text-secondary); }
.pcf-val { font-size: 22px; font-weight: 700; }
.pcf-meta { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

.add-row {
  display: flex;
  gap: 8px;
  align-items: center;
  margin-top: 14px;
  padding-top: 12px;
  border-top: 1px dashed var(--border);
}

.loading-hint { font-size: 13px; color: var(--text-secondary); padding: 20px 0; }
.empty-hint { font-size: 13px; color: var(--text-secondary); padding: 16px 0; }
.erp-note { font-size: 12px; color: var(--text-secondary); padding: 10px 2px 0; border-top: 1px dashed var(--border); margin-top: 10px; }
.bom-sup-row { display: flex; align-items: center; gap: 6px; }
.bom-sup-docs { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
.tier-badge { display: inline-block; margin-left: 6px; padding: 1px 6px; border-radius: 3px; font-size: 10px; background: var(--surface-2); color: var(--accent); border: 1px solid var(--border); }

/* 文件 chip */
.doc-chip { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 500; padding: 2px 7px; border-radius: 4px; margin-right: 4px; background: var(--surface); border: 1px solid var(--border); color: var(--text-primary); white-space: nowrap; }
.doc-status-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
.doc-dot--valid         { background: #16a34a; }
.doc-dot--expiring_soon { background: #d97706; }
.doc-dot--expired       { background: #dc2626; }
.doc-dot--missing       { background: #dc2626; }
.doc-exp { font-size: 10px; font-weight: 400; color: var(--text-secondary); }

/* 碳排來源 badge */
.esb-pcf_sync        { background: #dcfce7; color: #15803d; padding: 1px 6px; border-radius: 3px; font-size: 11px; font-weight: 700; }
.esb-supplier_reported { background: #dbeafe; color: #1d4ed8; padding: 1px 6px; border-radius: 3px; font-size: 11px; font-weight: 700; }
.esb-manual          { background: #f3f4f6; color: #6b7280; padding: 1px 6px; border-radius: 3px; font-size: 11px; font-weight: 700; }

.tag-blue { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
.tag-gray { background: #f3f4f6; color: #6b7280; border: 1px solid #e5e7eb; }
</style>
