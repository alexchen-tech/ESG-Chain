<template>
  <div class="page-container">
    <div class="breadcrumb">
      <span class="breadcrumb-parent">商品合規管理</span>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current">出口商品合規</span>
    </div>
    <div class="page-header">
      <div>
        <h1 class="page-title">出口商品合規</h1>
        <p class="page-subtitle">管理出口品項、CBAM / EUDR 法規暴露與上游供應商碳排申報</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">+ 新增品項</button>
    </div>

    <!-- 篩選列 -->
    <div class="filter-bar">
      <input v-model="search" class="form-input" style="width:220px;" placeholder="搜尋品項名稱 / HS Code" />
      <select v-model="targetMarketFilter" class="form-select" @change="onMarketFilterChange">
        <option value="">目標市場（合規檢查）</option>
        <option value="EU">EU</option>
        <option value="US">US</option>
        <option value="NA">NA</option>
        <option value="APAC">APAC</option>
        <option value="GB">GB</option>
        <option value="JP">JP</option>
      </select>
      <span v-if="marketComplianceLoading" class="compliance-loading">檢查中…</span>
      <template v-if="!targetMarketFilter">
        <select v-model="cbamFilter" class="form-select">
          <option value="all">全部 CBAM</option>
          <option value="yes">CBAM 適用</option>
          <option value="no">非 CBAM</option>
        </select>
        <select v-model="eudrFilter" class="form-select">
          <option value="all">全部 EUDR</option>
          <option value="yes">EUDR 適用</option>
          <option value="no">非 EUDR</option>
        </select>
      </template>
    </div>

    <!-- 品項清單 -->
    <div v-if="isLoading" class="card empty-state">載入中…</div>
    <div v-else-if="!filteredGoods.length" class="card empty-state">
      <div class="empty-icon">📦</div>
      <div>尚無貿易商品</div>
      <div class="empty-hint">點選右上角「新增品項」，輸入 HS Code 後系統自動判定 CBAM 適用性</div>
    </div>

    <!-- 欄位標題列 -->
    <div v-else class="goods-list">
      <div class="goods-header">
        <div class="good-name-col gh-label">品項名稱</div>
        <div class="good-customer gh-label">客戶</div>
        <div class="good-hs gh-label">HS Code</div>
        <div class="good-tags gh-label">法規適用</div>
        <div class="good-emissions gh-label">內含碳排量</div>
        <div class="good-status gh-label">上游合規</div>
        <div class="good-actions-spacer"></div>
      </div>
      <div
        v-for="g in filteredGoods"
        :key="g.id"
        class="good-card"
      >
        <!-- 主列 -->
        <div class="good-row" @click="togglePanel(g.id)">
          <div class="good-name-col">
            <div class="good-name">{{ g.name }}</div>
            <div v-if="g.product_code" class="good-code"><span class="code-prefix">型號 SKU</span> <span class="code-val font-mono">{{ g.product_code }}</span></div>
          </div>
          <div class="good-customer">
            <span v-if="(g as any).customer_name" class="customer-chip">{{ (g as any).customer_name }}</span>
            <span v-else class="no-data">—</span>
          </div>
          <div class="good-hs font-mono">{{ g.hs_code }}</div>
          <div class="good-tags">
            <template v-if="targetMarketFilter && marketComplianceResults[g.id]">
              <span
                class="compliance-badge"
                :class="`compliance-badge--${marketComplianceResults[g.id].overall}`"
                :title="`${targetMarketFilter} 合規狀態`"
                @click.stop="expandedCompliance[g.id] = !expandedCompliance[g.id]"
              >
                {{ { pass: '✅', warning: '⚠', fail: '❌' }[marketComplianceResults[g.id].overall] }}
                {{ targetMarketFilter }}
              </span>
              <div v-if="expandedCompliance[g.id]" class="compliance-detail" @click.stop>
                <div
                  v-for="r in marketComplianceResults[g.id].results"
                  :key="r.doc_type"
                  class="compliance-detail-row"
                >
                  <span class="cd-icon">{{ { valid: '✅', expiring_soon: '⚠', expired: '❌', missing: '❌' }[r.status] }}</span>
                  <span class="cd-type font-mono">{{ r.doc_type }}</span>
                  <span class="cd-status">{{ { valid: '有效', expiring_soon: '即將到期', expired: '已過期', missing: '缺件' }[r.status] }}</span>
                </div>
                <div v-if="!marketComplianceResults[g.id].results.length" class="cd-none">此市場無需特定文件</div>
              </div>
            </template>
            <template v-else-if="!targetMarketFilter">
              <span v-if="g.is_cbam_applicable" class="tag tag-cbam">CBAM · {{ g.cbam_category }}</span>
              <span v-if="g.is_eudr_applicable" class="tag tag-eudr">EUDR</span>
              <span v-if="!g.is_cbam_applicable && !g.is_eudr_applicable" class="tag tag-none">無管制</span>
            </template>
            <template v-else>
              <span class="tag tag-none">—</span>
            </template>
          </div>
          <div class="good-emissions">
            <template v-if="g.embedded_emissions != null">
              <span class="font-mono">{{ g.embedded_emissions.toFixed(2) }} <span class="unit">kgCO₂e/u</span></span>
              <span v-if="g.emissions_source" class="emissions-source-badge" :class="`esb-${g.emissions_source}`">
                {{ { pcf_sync: 'PCF', supplier_reported: '供應商', manual: '手動' }[g.emissions_source] }}
              </span>
            </template>
            <span v-else class="no-emissions">
              <span class="no-emissions-dash">—</span>
              <span class="no-emissions-hint">待 PCF 計算</span>
            </span>
          </div>
          <div class="good-status">
            <span class="status-dot" :class="`status-dot--${g.upstream_compliance_status}`"></span>
            <span class="status-label">{{ STATUS_LABELS[g.upstream_compliance_status] }}</span>
          </div>
          <div class="good-actions" @click.stop>
            <button class="action-btn" @click="openEdit(g)" title="編輯">✎</button>
            <button class="action-btn action-btn--danger" @click="confirmDelete(g)" title="刪除">✕</button>
          </div>
          <div class="panel-chevron" :class="{ open: openPanels[g.id] }">›</div>
        </div>

        <!-- 展開面板 -->
        <div v-if="openPanels[g.id]" class="good-panel">
          <div class="panel-tabs">
            <button :class="['panel-tab', panelTab[g.id] === 'suppliers' && 'active']" @click="setTab(g.id, 'suppliers')">上游供應商 ({{ g.supplier_count }})</button>
            <button :class="['panel-tab', panelTab[g.id] === 'emissions' && 'active']" @click="loadEmissions(g.id); setTab(g.id, 'emissions')">碳排回報</button>
            <button :class="['panel-tab', panelTab[g.id] === 'export-links' && 'active']" @click="loadExportLinks(g.id); setTab(g.id, 'export-links')">採購連結</button>
          </div>

          <!-- 供應商面板 -->
          <div v-if="panelTab[g.id] === 'suppliers'">
            <div v-if="suppLoading[g.id]" class="panel-loading">載入中…</div>
            <div v-else>
              <table v-if="suppliers[g.id]?.length" class="panel-table">
                <thead><tr><th>供應商</th><th>物料群組</th><th>合規狀態</th><th>文件明細</th><th></th></tr></thead>
                <tbody>
                  <tr v-for="s in suppliers[g.id]" :key="s.id">
                    <td>{{ s.supplier_name }}</td>
                    <td>{{ s.material_group || '—' }}</td>
                    <td><span class="status-dot" :class="`status-dot--${s.status}`"></span> {{ STATUS_LABELS[s.status] }}</td>
                    <td>
                      <span v-for="d in s.doc_statuses" :key="d.doc_type" class="doc-chip" :class="`doc-chip--${d.status}`">
                        {{ d.doc_type }} <span v-if="d.expires_at" class="doc-exp">{{ d.expires_at }}</span>
                      </span>
                      <span v-if="!s.doc_statuses.length" class="no-data">無需求文件</span>
                    </td>
                    <td><button class="action-btn action-btn--danger" @click="removeSupplier(g.id, s.id)">✕</button></td>
                  </tr>
                </tbody>
              </table>
              <div v-else class="panel-empty">尚未設定上游供應商</div>

              <!-- 新增供應商 -->
              <div class="add-supplier-row">
                <select v-model="addSupplierForm[g.id].supplier_id" class="form-input" style="width:180px;">
                  <option value="">選擇供應商</option>
                  <option v-for="s in allSuppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
                <select v-model="addSupplierForm[g.id].material_group_id" class="form-input" style="width:160px;">
                  <option value="">物料群組（選填）</option>
                  <option v-for="mg in materialGroups" :key="mg.id" :value="mg.id">{{ mg.name }}</option>
                </select>
                <button
                  class="btn btn-secondary"
                  :disabled="!addSupplierForm[g.id]?.supplier_id || addingSupplier[g.id]"
                  @click="addSupplier(g.id)"
                >{{ addingSupplier[g.id] ? '新增中…' : '+ 新增' }}</button>
              </div>
            </div>
          </div>

          <!-- 採購連結面板 -->
          <div v-if="panelTab[g.id] === 'export-links'">
            <div v-if="exportLinksLoading[g.id]" class="panel-loading">載入中…</div>
            <div v-else>
              <table v-if="exportLinks[g.id]?.length" class="panel-table">
                <thead>
                  <tr>
                    <th>採購產品</th>
                    <th>型號 SKU</th>
                    <th>關聯類型</th>
                    <th>ERP 料號</th>
                    <th>備註</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="lk in exportLinks[g.id]" :key="lk.id">
                    <td>{{ lk.buyer_product_name || '—' }}</td>
                    <td class="font-mono" style="font-size:11px;">{{ lk.buyer_product_sku || '—' }}</td>
                    <td>
                      <span class="tag" :class="lk.relation_type === 'finished_good' ? 'tag-blue' : 'tag-gray'">
                        {{ lk.relation_type === 'finished_good' ? '成品出口' : lk.relation_type }}
                      </span>
                    </td>
                    <td>
                      <span v-if="lk.erp_product_code" class="font-mono erp-code">{{ lk.erp_product_code }}</span>
                      <span v-else class="no-data">未設定</span>
                    </td>
                    <td>{{ lk.note || '—' }}</td>
                  </tr>
                </tbody>
              </table>
              <div v-else class="panel-empty">尚無採購連結（請至採購品合規頁面設定出口連結）</div>
            </div>
          </div>

          <!-- 碳排面板 -->
          <div v-if="panelTab[g.id] === 'emissions'">
            <div v-if="emissionsLoading[g.id]" class="panel-loading">載入中…</div>
            <div v-else>
              <table v-if="emissions[g.id]?.length" class="panel-table">
                <thead><tr><th>供應商</th><th>kgCO₂e/unit</th><th>計算說明</th><th>回報時間</th><th>確認狀態</th><th></th></tr></thead>
                <tbody>
                  <tr v-for="e in emissions[g.id]" :key="e.id">
                    <td>{{ e.supplier?.name }}</td>
                    <td class="font-mono">{{ e.emissions_value.toFixed(4) }}</td>
                    <td>{{ e.calculation_note || '—' }}</td>
                    <td class="font-mono" style="font-size:11px;">{{ e.reported_at?.slice(0,16) }}</td>
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
                        @click="confirmEmission(g.id, e.id)"
                      >{{ confirmingEmission[e.id] ? '確認中…' : '確認採用' }}</button>
                    </td>
                  </tr>
                </tbody>
              </table>
              <div v-else class="panel-empty">尚無供應商碳排回報</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 新增 / 編輯 Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal" style="min-width:440px;">
        <div class="modal-header">
          <span class="modal-title">{{ editingGood ? '編輯品項' : '新增品項' }}</span>
          <button class="modal-close" @click="showModal = false">×</button>
        </div>
        <div class="form-group">
          <label class="form-label">品項名稱 *</label>
          <input v-model="form.name" class="form-input" placeholder="鋼製零件 A" />
        </div>
        <div class="form-row">
          <div class="form-group" style="flex:1;">
            <label class="form-label">型號 SKU</label>
            <input v-model="form.product_code" class="form-input font-mono" placeholder="STE-001" />
          </div>
          <div class="form-group" style="flex:1;">
            <label class="form-label">HS Code *</label>
            <input v-model="form.hs_code" class="form-input font-mono" placeholder="7208.10" />
          </div>
        </div>
        <div class="form-row">
          <div class="form-group" style="flex:1;">
            <label class="form-label">單位</label>
            <input v-model="form.unit" class="form-input" placeholder="kg / pcs" />
          </div>
          <div class="form-group" style="flex:1;">
            <label class="form-label">單價</label>
            <input v-model="form.unit_price" class="form-input font-mono" type="number" placeholder="0.00" />
          </div>
          <div class="form-group" style="width:90px;">
            <label class="form-label">幣別</label>
            <select v-model="form.currency" class="form-input">
              <option>USD</option><option>EUR</option><option>TWD</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">說明</label>
          <textarea v-model="form.description" class="form-input" rows="2" />
        </div>
        <div class="form-group">
          <label class="form-label">客戶（下游採購方）</label>
          <select v-model="form.customer_id" class="form-input">
            <option value="">— 不指定 —</option>
            <option v-for="c in allCustomers" :key="c.id" :value="c.id">{{ c.name }} ({{ c.code }})</option>
          </select>
        </div>
        <div v-if="form.hs_code" class="cbam-preview">
          <span v-if="cbamPreview.is_applicable" class="tag tag-cbam">CBAM 適用：{{ cbamPreview.category }}</span>
          <span v-else class="tag tag-none">非 CBAM 管制品</span>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showModal = false">取消</button>
          <button class="btn btn-primary" :disabled="isSubmitting || !form.name || !form.hs_code" @click="save">
            {{ isSubmitting ? '儲存中…' : '儲存' }}
          </button>
        </div>
      </div>
    </div>

    <!-- 刪除確認 -->
    <div v-if="deleteTarget" class="modal-overlay" @click.self="deleteTarget = null">
      <div class="modal" style="min-width:340px;">
        <div class="modal-header"><span class="modal-title">確認刪除</span><button class="modal-close" @click="deleteTarget = null">×</button></div>
        <p style="padding:16px 0;font-size:14px;">確定刪除「{{ deleteTarget.name }}」？此動作無法復原。</p>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="deleteTarget = null">取消</button>
          <button class="btn btn-danger" :disabled="isSubmitting" @click="doDelete">{{ isSubmitting ? '刪除中…' : '確認刪除' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { tradeGoodApi, marketComplianceApi, type TradeGood, type TradeGoodSupplier, type EmissionReport, type MarketComplianceResult } from '@/api/modules/tradeGoods'
import { materialGroupApi } from '@/api/modules/compliance'
import { customersApi, type Customer } from '@/api/modules/customers'

const STATUS_LABELS: Record<string, string> = {
  valid: '合規', expiring_soon: '即將到期', expired: '已過期',
  missing: '缺文件', unconfigured: '未設定',
}

const HS_CBAM_MAP: Record<string, string> = {
  '72': 'steel', '73': 'steel', '25': 'cement',
  '76': 'aluminium', '28': 'hydrogen', '31': 'fertiliser', '27': 'electricity',
}

function checkCbam(hs: string) {
  const prefix = hs.replace(/\D/g, '').slice(0, 2)
  const cat = HS_CBAM_MAP[prefix] ?? null
  return { is_applicable: !!cat, category: cat }
}

export default defineComponent({
  name: 'TradeGoodsView',
  data() {
    return {
      isLoading: false,
      isSubmitting: false,
      search: '',
      cbamFilter: 'all',
      eudrFilter: 'all',
      targetMarketFilter: '' as string,
      marketComplianceResults: {} as Record<string, MarketComplianceResult>,
      marketComplianceLoading: false,
      expandedCompliance: {} as Record<string, boolean>,
      goods: [] as TradeGood[],
      allSuppliers: [] as any[],
      materialGroups: [] as any[],
      allCustomers: [] as Customer[],
      showModal: false,
      editingGood: null as TradeGood | null,
      deleteTarget: null as TradeGood | null,
      form: { name: '', product_code: '', hs_code: '', unit: '', unit_price: null as number | null, currency: 'USD', description: '', customer_id: '' },
      openPanels: {} as Record<string, boolean>,
      panelTab: {} as Record<string, string>,
      suppliers: {} as Record<string, TradeGoodSupplier[]>,
      suppLoading: {} as Record<string, boolean>,
      addSupplierForm: {} as Record<string, { supplier_id: string; material_group_id: string }>,
      addingSupplier: {} as Record<string, boolean>,
      emissions: {} as Record<string, EmissionReport[]>,
      emissionsLoading: {} as Record<string, boolean>,
      confirmingEmission: {} as Record<string, boolean>,
      exportLinks: {} as Record<string, any[]>,
      exportLinksLoading: {} as Record<string, boolean>,
      STATUS_LABELS,
    }
  },
  computed: {
    filteredGoods(): TradeGood[] {
      let list = this.goods
      if (this.search) {
        const kw = this.search.toLowerCase()
        list = list.filter(g => g.name.toLowerCase().includes(kw) || g.hs_code.includes(kw) || (g.product_code ?? '').toLowerCase().includes(kw))
      }
      if (this.cbamFilter === 'yes') list = list.filter(g => g.is_cbam_applicable)
      if (this.cbamFilter === 'no')  list = list.filter(g => !g.is_cbam_applicable)
      if (this.eudrFilter === 'yes') list = list.filter(g => g.is_eudr_applicable)
      if (this.eudrFilter === 'no')  list = list.filter(g => !g.is_eudr_applicable)
      return list
    },
    cbamPreview() {
      return checkCbam(this.form.hs_code)
    },
  },
  async mounted() {
    await this.loadData()
    this.loadSuppliers()
    this.loadMaterialGroups()
    this.loadCustomers()
  },
  methods: {
    async loadData() {
      this.isLoading = true
      try {
        const { data } = await tradeGoodApi.list()
        this.goods = data.data
      } finally { this.isLoading = false }
    },
    async loadSuppliers() {
      try {
        const http = (await import('@/api/http')).default
        const { data } = await http.get<any>('/api/v1/suppliers?per_page=200')
        this.allSuppliers = data.data?.data ?? data.data ?? []
      } catch { /* silent */ }
    },
    async loadMaterialGroups() {
      try {
        const { data } = await materialGroupApi.list()
        this.materialGroups = data.data
      } catch { /* silent */ }
    },
    async loadCustomers() {
      try {
        const { data } = await customersApi.list({ per_page: 200, status: 'active' })
        this.allCustomers = data.data
      } catch { /* silent */ }
    },
    async onMarketFilterChange() {
      if (!this.targetMarketFilter) {
        this.marketComplianceResults = {}
        return
      }
      await this.loadMarketCompliance()
    },
    async loadMarketCompliance() {
      if (!this.targetMarketFilter || !this.goods.length) return
      this.marketComplianceLoading = true
      try {
        const ids = this.goods.map((g: TradeGood) => g.id)
        const { data } = await marketComplianceApi.batch(this.targetMarketFilter, ids)
        this.marketComplianceResults = data.data
      } catch { /* silent */ } finally {
        this.marketComplianceLoading = false
      }
    },
    togglePanel(id: string) {
      this.openPanels[id] = !this.openPanels[id]
      if (this.openPanels[id] && !this.panelTab[id]) {
        this.panelTab[id] = 'suppliers'
        this.loadSupplierPanel(id)
      }
    },
    setTab(id: string, tab: string) {
      this.panelTab[id] = tab
      if (tab === 'suppliers' && !this.suppliers[id]) this.loadSupplierPanel(id)
    },
    async loadSupplierPanel(id: string) {
      if (this.suppLoading[id]) return
      this.suppLoading[id] = true
      if (!this.addSupplierForm[id]) this.addSupplierForm[id] = { supplier_id: '', material_group_id: '' }
      try {
        const { data } = await tradeGoodApi.suppliers(id)
        this.suppliers[id] = data.data
      } finally { this.suppLoading[id] = false }
    },
    async addSupplier(goodId: string) {
      const f = this.addSupplierForm[goodId]
      if (!f?.supplier_id) return
      this.addingSupplier[goodId] = true
      try {
        await tradeGoodApi.addSupplier(goodId, { supplier_id: f.supplier_id, material_group_id: f.material_group_id || undefined })
        f.supplier_id = ''
        f.material_group_id = ''
        await this.loadSupplierPanel(goodId)
        await this.loadData()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '新增失敗')
      } finally { this.addingSupplier[goodId] = false }
    },
    async removeSupplier(goodId: string, suppId: string) {
      try {
        await tradeGoodApi.removeSupplier(goodId, suppId)
        await this.loadSupplierPanel(goodId)
        await this.loadData()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '移除失敗')
      }
    },
    async loadEmissions(goodId: string) {
      if (this.emissionsLoading[goodId]) return
      this.emissionsLoading[goodId] = true
      try {
        const { data } = await tradeGoodApi.emissionReports(goodId)
        this.emissions[goodId] = data.data
      } finally { this.emissionsLoading[goodId] = false }
    },
    async loadExportLinks(goodId: string) {
      if (this.exportLinksLoading[goodId]) return
      this.exportLinksLoading[goodId] = true
      try {
        const { data } = await tradeGoodApi.exportLinks(goodId)
        this.exportLinks[goodId] = data.data
      } finally { this.exportLinksLoading[goodId] = false }
    },
    async confirmEmission(goodId: string, emissionId: string) {
      this.confirmingEmission[emissionId] = true
      try {
        await tradeGoodApi.confirmEmission(goodId, emissionId)
        await this.loadEmissions(goodId)
        await this.loadData()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '確認失敗')
      } finally { this.confirmingEmission[emissionId] = false }
    },
    openCreate() {
      this.editingGood = null
      this.form = { name: '', product_code: '', hs_code: '', unit: '', unit_price: null, currency: 'USD', description: '', customer_id: '' }
      this.showModal = true
    },
    openEdit(g: TradeGood) {
      this.editingGood = g
      this.form = { name: g.name, product_code: g.product_code ?? '', hs_code: g.hs_code, unit: g.unit ?? '', unit_price: g.unit_price, currency: g.currency ?? 'USD', description: (g as any).description ?? '', customer_id: (g as any).customer_id ?? '' }
      this.showModal = true
    },
    async save() {
      if (!this.form.name || !this.form.hs_code) return
      this.isSubmitting = true
      try {
        const payload = { ...this.form, unit_price: this.form.unit_price ?? undefined, customer_id: this.form.customer_id || null }
        if (this.editingGood) await tradeGoodApi.update(this.editingGood.id, payload)
        else await tradeGoodApi.create(payload)
        this.showModal = false
        await this.loadData()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '儲存失敗')
      } finally { this.isSubmitting = false }
    },
    confirmDelete(g: TradeGood) { this.deleteTarget = g },
    async doDelete() {
      if (!this.deleteTarget) return
      this.isSubmitting = true
      try {
        await tradeGoodApi.destroy(this.deleteTarget.id)
        this.deleteTarget = null
        await this.loadData()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '刪除失敗')
      } finally { this.isSubmitting = false }
    },
  },
})
</script>

<style scoped>
.filter-bar { display: flex; gap: 10px; margin-bottom: 20px; }

.goods-list { display: flex; flex-direction: column; gap: 0; }
.goods-header {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 6px 16px 6px 16px;
  margin-bottom: 6px;
}
.gh-label {
  font-size: 11px;
  font-weight: 700;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.good-actions-spacer { width: 72px; flex-shrink: 0; }
.good-card { margin-bottom: 8px; }
.good-card { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; overflow: hidden; }
.good-row {
  display: flex; align-items: center; gap: 16px; padding: 14px 16px;
  cursor: pointer; transition: background 0.15s;
}
.good-row:hover { background: var(--surface-2); }
.good-name-col { flex: 1; min-width: 0; }
.good-name { font-size: 14px; font-weight: 600; color: var(--text-primary); }
.good-code { font-size: 11px; color: var(--text-secondary); margin-top: 2px; }
.code-prefix { color: var(--text-secondary); }
.code-val { background: var(--surface-2); padding: 1px 5px; border-radius: 3px; }
.good-customer { width: 120px; flex-shrink: 0; font-size: 12px; }
.customer-chip { background: var(--surface-2); border: 1px solid var(--border); border-radius: 4px; padding: 2px 6px; font-size: 11px; color: var(--text-secondary); }
.good-hs { font-size: 12px; color: var(--text-secondary); width: 90px; flex-shrink: 0; }
.good-tags { display: flex; gap: 6px; width: 220px; flex-shrink: 0; flex-wrap: wrap; }
.good-emissions { width: 130px; flex-shrink: 0; font-size: 12px; color: var(--text-secondary); }
.unit { font-size: 10px; opacity: 0.7; }
.good-status { display: flex; align-items: center; gap: 6px; width: 100px; flex-shrink: 0; }
.good-actions { display: flex; gap: 4px; }
.panel-chevron { font-size: 16px; color: var(--text-secondary); transition: transform 0.2s; margin-left: 4px; }
.panel-chevron.open { transform: rotate(90deg); }

.compliance-badge {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;
  cursor: pointer; border: 1px solid var(--border);
}
.compliance-badge--pass    { background: #dcfce7; color: #15803d; border-color: #86efac; }
.compliance-badge--warning { background: #fef9c3; color: #854d0e; border-color: #fde047; }
.compliance-badge--fail    { background: #fee2e2; color: #b91c1c; border-color: #fca5a5; }
.compliance-detail {
  margin-top: 6px; background: var(--surface-2); border: 1px solid var(--border);
  border-radius: 6px; padding: 8px 10px; font-size: 12px;
}
.compliance-detail-row { display: flex; align-items: center; gap: 8px; padding: 3px 0; }
.cd-icon { width: 16px; text-align: center; }
.cd-type { font-family: 'Fira Code', monospace; flex: 1; }
.cd-status { color: var(--text-secondary); }
.cd-none { color: var(--text-secondary); font-style: italic; }
.compliance-loading { font-size: 12px; color: var(--text-secondary); align-self: center; }
.tag-ok { background: #dcfce7; color: #15803d; }
.tag-pending { background: #fef3c7; color: #92400e; }
.tag-blue { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
.tag-gray { background: var(--surface-2); color: var(--text-secondary); border: 1px solid var(--border); }
.erp-code { font-size: 11px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; color: var(--text-secondary); }

.status-label { font-size: 12px; color: var(--text-secondary); }

.good-panel { border-top: 1px solid var(--border); padding: 16px; background: var(--surface-2); }
.panel-tabs { display: flex; gap: 0; margin-bottom: 14px; border-bottom: 1px solid var(--border); }
.panel-tab { font-size: 13px; padding: 6px 16px; background: none; border: none; border-bottom: 2px solid transparent; cursor: pointer; color: var(--text-secondary); }
.panel-tab.active { color: var(--accent); border-bottom-color: var(--accent); font-weight: 600; }
.panel-loading, .panel-empty { font-size: 13px; color: var(--text-secondary); padding: 12px 0; }
.panel-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.panel-table th { text-align: left; padding: 6px 10px; color: var(--text-secondary); font-size: 11px; font-weight: 600; border-bottom: 1px solid var(--border); }
.panel-table td { padding: 8px 10px; border-bottom: 1px solid var(--border); }

.add-supplier-row { display: flex; gap: 8px; align-items: center; margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--border); }

.doc-chip { font-size: 10px; font-weight: 600; padding: 1px 5px; border-radius: 3px; margin-right: 4px; }
.doc-chip--valid { background: #dcfce7; color: #15803d; }
.doc-chip--expiring_soon { background: #fef3c7; color: #92400e; }
.doc-chip--expired, .doc-chip--missing { background: #fee2e2; color: #b91c1c; }
.doc-exp { font-weight: 400; margin-left: 3px; opacity: 0.7; }

.cbam-preview { margin: 8px 0; }
.no-data { color: var(--text-secondary); }
.no-emissions { display: flex; flex-direction: column; gap: 1px; }
.no-emissions-dash { font-size: 14px; color: var(--text-secondary); line-height: 1; }
.no-emissions-hint { font-size: 10px; color: #94a3b8; }
.emissions-source-badge { display: inline-block; margin-left: 4px; padding: 1px 5px; border-radius: 3px; font-size: 10px; font-weight: 600; vertical-align: middle; }
.esb-pcf_sync { background: #dcfce7; color: #15803d; }
.esb-supplier_reported { background: #dbeafe; color: #1d4ed8; }
.esb-manual { background: #f3f4f6; color: #6b7280; }
.font-mono { font-family: 'Fira Code', monospace; }

.form-row { display: flex; gap: 12px; }
.form-group { margin-bottom: 14px; }
.form-label { display: block; font-size: 12px; font-weight: 500; color: var(--text-secondary); margin-bottom: 4px; }
.form-input { width: 100%; padding: 7px 10px; border: 1px solid var(--border); border-radius: 5px; font-size: 13px; background: var(--surface); color: var(--text-primary); box-sizing: border-box; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.35); display: flex; align-items: center; justify-content: center; z-index: 100; }
.modal { background: var(--surface); border-radius: 10px; padding: 24px; max-height: 90vh; overflow-y: auto; }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; }
.modal-title { font-size: 16px; font-weight: 700; }
.modal-close { background: none; border: none; font-size: 20px; cursor: pointer; color: var(--text-secondary); }
.modal-footer { display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px; }
.btn { padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; }
.btn-primary { background: var(--accent); color: #fff; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-secondary { background: var(--surface-2); color: var(--text-primary); border: 1px solid var(--border); }
.btn-secondary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-danger { background: #ef4444; color: #fff; }
.action-btn { background: none; border: 1px solid var(--border); border-radius: 5px; padding: 4px 8px; font-size: 13px; cursor: pointer; color: var(--text-secondary); }
.action-btn:hover { background: var(--surface-2); }
.action-btn--danger:hover { background: #fee2e2; color: #b91c1c; border-color: #fca5a5; }
.empty-state { text-align: center; padding: 60px 32px; color: var(--text-secondary); font-size: 14px; }
.empty-icon { font-size: 32px; margin-bottom: 12px; }
.empty-hint { font-size: 12px; color: var(--text-secondary); margin-top: 6px; opacity: 0.75; }
.form-select { padding: 7px 10px; border: 1px solid var(--border); border-radius: 5px; font-size: 13px; background: var(--surface); color: var(--text-primary); width: 140px; }
</style>
