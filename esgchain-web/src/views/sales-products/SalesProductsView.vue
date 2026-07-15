<template>
  <div class="page-container">
    <div class="breadcrumb">
      <span class="breadcrumb-parent">合規管理</span>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current">銷售產品</span>
    </div>
    <div class="page-header">
      <div>
        <h1 class="page-title">銷售產品</h1>
        <p class="page-subtitle">管理銷售品項、BOM 碳排構成、CBAM / EUDR 法規暴露與上游供應商</p>
      </div>
      <button class="btn btn-secondary" @click="$router.push('/sales-products/import')">↑ CSV 批次匯入</button>
    </div>

    <!-- 篩選列 -->
    <div class="filter-bar">
      <input v-model="search" class="filter-input" style="width:220px;" placeholder="搜尋品項 / SKU / 型號 / 生產批號 / HS" @input="onSearch" />
      <select v-model="cbamFilter" class="filter-select">
        <option value="all">CBAM：全部</option>
        <option value="yes">CBAM 適用</option>
        <option value="no">非 CBAM</option>
      </select>
      <select v-model="eudrFilter" class="filter-select">
        <option value="all">EUDR：全部</option>
        <option value="yes">EUDR 適用</option>
        <option value="no">非 EUDR</option>
      </select>
      <button v-if="search || cbamFilter !== 'all' || eudrFilter !== 'all'" class="btn btn-secondary btn-sm" @click="search=''; cbamFilter='all'; eudrFilter='all'">✕ 清除</button>
    </div>

    <!-- 清單 -->
    <div v-if="isLoading" class="empty-state" style="padding:60px 0;text-align:center;color:var(--text-secondary);">載入中…</div>
    <div v-else-if="!filteredProducts.length" class="empty-state" style="padding:60px 0;text-align:center;color:var(--text-secondary);">
      <div style="font-size:28px;margin-bottom:12px;">📦</div>
      <p style="font-size:15px;font-weight:600;margin-bottom:6px;">尚無銷售產品</p>
      <p style="font-size:13px;opacity:0.75;">請使用右上角「CSV 批次匯入」從 ERP 匯出檔案建立產品，或透過 ERP Webhook 自動同步</p>
    </div>

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

      <div v-for="p in filteredProducts" :key="p.id" class="good-card">
        <div class="good-row" @click="$router.push(`/sales-products/${p.id}`)">
          <div class="good-name-col">
            <div class="good-name">{{ p.name }}</div>
            <div class="good-meta">
              <span v-if="p.product_code" class="good-code"><span class="code-prefix">SKU</span> <span class="code-val font-mono">{{ p.product_code }}</span></span>
              <span v-if="p.model_no" class="good-code"><span class="code-prefix">型號</span> <span class="code-val font-mono">{{ p.model_no }}</span></span>
              <span v-if="p.batch_nos?.length" class="good-code"><span class="code-prefix">批次</span> <span class="code-val font-mono">×{{ p.batch_nos.length }}</span></span>
            </div>
          </div>
          <div class="good-customer">
            <span v-if="p.customer_name" class="customer-chip">{{ p.customer_name }}</span>
            <span v-else class="no-data">—</span>
          </div>
          <div class="good-hs font-mono">{{ p.hs_code }}</div>
          <div class="good-tags">
            <span v-if="p.is_cbam_applicable" class="tag tag-cbam">CBAM · {{ p.cbam_category }}</span>
            <span v-if="p.is_eudr_applicable" class="tag tag-eudr">EUDR</span>
            <template v-if="p.applicable_regulations?.length">
              <span v-for="r in p.applicable_regulations" :key="r" class="tag tag-reg">{{ r }}</span>
            </template>
            <span v-if="!p.is_cbam_applicable && !p.is_eudr_applicable && !p.applicable_regulations?.length" class="tag tag-none">無管制</span>
          </div>
          <div class="good-emissions">
            <template v-if="p.embedded_emissions != null">
              <span class="font-mono">{{ p.embedded_emissions.toFixed(2) }} <span class="unit">kgCO₂e/u</span></span>
              <span v-if="p.emissions_source" class="emissions-source-badge" :class="`esb-${p.emissions_source}`">
                {{ { pcf_sync: 'PCF', supplier_reported: '供應商', manual: '手動' }[p.emissions_source] ?? p.emissions_source }}
              </span>
            </template>
            <span v-else class="no-emissions">
              <span class="no-emissions-dash">—</span>
              <span class="no-emissions-hint">待 PCF 計算</span>
            </span>
          </div>
          <div class="good-status">
            <span class="status-dot" :class="`status-dot--${p.upstream_compliance_status}`"></span>
            <span class="status-label">{{ STATUS_LABELS[p.upstream_compliance_status] }}</span>
          </div>
          <div class="good-actions" @click.stop>
            <button class="btn btn-secondary btn-sm" @click="openEdit(p)" title="編輯">✎</button>
            <button class="btn btn-danger btn-sm" @click="confirmDelete(p)" title="刪除">✕</button>
          </div>
          <div class="row-arrow">›</div>
        </div>
      </div>
    </div>

    <!-- 編輯 Modal（僅限 ESG-Chain 擁有欄位，ERP 同步欄位唯讀）-->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal" style="min-width:440px;">
        <div class="modal-header">
          <span class="modal-title">編輯品項</span>
          <button class="modal-close" @click="showModal = false">×</button>
        </div>
        <!-- ERP 擁有欄位（唯讀）-->
        <div style="background:#f8f6f3;border:1px solid var(--border);border-radius:6px;padding:12px 14px;margin-bottom:16px;">
          <p style="font-size:11px;font-weight:700;color:var(--text-secondary);margin-bottom:8px;text-transform:uppercase;letter-spacing:0.05em;">ERP 同步欄位（唯讀）</p>
          <div class="form-row" style="margin-bottom:6px;">
            <div class="form-group" style="flex:2;">
              <label class="form-label">品項名稱</label>
              <div class="readonly-field">{{ editingProduct?.name }}</div>
            </div>
            <div class="form-group" style="flex:1;">
              <label class="form-label">SKU 品號</label>
              <div class="readonly-field font-mono">{{ editingProduct?.product_code || '—' }}</div>
            </div>
            <div class="form-group" style="width:100px;">
              <label class="form-label">HS Code</label>
              <div class="readonly-field font-mono">{{ editingProduct?.hs_code }}</div>
            </div>
          </div>
        </div>
        <!-- ESG-Chain 擁有欄位（可編輯）-->
        <div class="form-row">
          <div class="form-group" style="flex:1;">
            <label class="form-label">型號</label>
            <input v-model="form.model_no" class="form-input font-mono" placeholder="VT26-POL-012" />
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
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showModal = false">取消</button>
          <button class="btn btn-primary" :disabled="isSubmitting" @click="save">
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
import { salesProductApi, type SalesProduct } from '@/api/modules/salesProducts'
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
  name: 'SalesProductsView',
  data() {
    return {
      isLoading: false,
      isSubmitting: false,
      search: '',
      cbamFilter: 'all',
      eudrFilter: 'all',
      products: [] as SalesProduct[],
      allCustomers: [] as Customer[],
      showModal: false,
      editingProduct: null as SalesProduct | null,
      deleteTarget: null as SalesProduct | null,
      form: { name: '', product_code: '', model_no: '', hs_code: '', unit: '', unit_price: null as number | null, currency: 'USD', description: '', customer_id: '' },
      STATUS_LABELS,
    }
  },
  computed: {
    filteredProducts(): SalesProduct[] {
      let list = this.products
      if (this.search) {
        const kw = this.search.toLowerCase()
        list = list.filter(p =>
          p.name.toLowerCase().includes(kw) ||
          p.hs_code.includes(kw) ||
          (p.product_code ?? '').toLowerCase().includes(kw) ||
          (p.model_no ?? '').toLowerCase().includes(kw) ||
          (p.batch_nos ?? []).some(no => no.toLowerCase().includes(kw)) // 以生產批號反查產品
        )
      }
      if (this.cbamFilter === 'yes') list = list.filter(p => p.is_cbam_applicable)
      if (this.cbamFilter === 'no')  list = list.filter(p => !p.is_cbam_applicable)
      if (this.eudrFilter === 'yes') list = list.filter(p => p.is_eudr_applicable)
      if (this.eudrFilter === 'no')  list = list.filter(p => !p.is_eudr_applicable)
      return list
    },
  },
  async mounted() {
    await this.loadData()
    this.loadCustomers()
  },
  methods: {
    async loadData() {
      this.isLoading = true
      try {
        const { data } = await salesProductApi.list()
        this.products = data.data
      } finally { this.isLoading = false }
    },
    async loadCustomers() {
      try {
        const { data } = await customersApi.list({ per_page: 200, status: 'active' })
        this.allCustomers = data.data
      } catch { /* silent */ }
    },
    onSearch() { /* filteredProducts computed 即時更新，無需額外觸發 */ },
    openEdit(p: SalesProduct) {
      this.editingProduct = p
      this.form = { name: p.name, product_code: p.product_code ?? '', model_no: p.model_no ?? '', hs_code: p.hs_code, unit: p.unit ?? '', unit_price: p.unit_price, currency: p.currency ?? 'USD', description: (p as any).description ?? '', customer_id: p.customer_id ?? '' }
      this.showModal = true
    },
    async save() {
      if (!this.editingProduct) return
      this.isSubmitting = true
      try {
        const payload = { model_no: this.form.model_no || null, unit: this.form.unit, unit_price: this.form.unit_price ?? undefined, currency: this.form.currency, description: this.form.description, customer_id: this.form.customer_id || null }
        await salesProductApi.update(this.editingProduct.id, payload)
        this.showModal = false
        await this.loadData()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '儲存失敗')
      } finally { this.isSubmitting = false }
    },
    confirmDelete(p: SalesProduct) { this.deleteTarget = p },
    async doDelete() {
      if (!this.deleteTarget) return
      this.isSubmitting = true
      try {
        await salesProductApi.destroy(this.deleteTarget.id)
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
/* 品項卡片列表 */
.goods-list { display: flex; flex-direction: column; gap: 0; }
.goods-header { display: flex; align-items: center; gap: 16px; padding: 6px 16px; margin-bottom: 6px; }
.gh-label { font-size: 11px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.04em; }
.good-actions-spacer { width: 80px; flex-shrink: 0; }
.good-card { margin-bottom: 8px; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; overflow: hidden; }
.good-row { display: flex; align-items: center; gap: 16px; padding: 14px 16px; cursor: pointer; transition: background 0.15s; }
.good-row:hover { background: var(--surface-2); }

/* 欄寬定義 */
.good-name-col { flex: 1; min-width: 0; }
.good-customer { width: 110px; flex-shrink: 0; }
.good-hs { font-size: 12px; color: var(--text-secondary); width: 88px; flex-shrink: 0; }
.good-tags { display: flex; gap: 4px; width: 220px; flex-shrink: 0; flex-wrap: wrap; align-items: center; }
.good-emissions { width: 140px; flex-shrink: 0; font-size: 13px; }
.good-status { display: flex; align-items: center; gap: 6px; width: 80px; flex-shrink: 0; }
.good-actions { display: flex; gap: 4px; align-items: center; }

/* 品項名稱 */
.good-name { font-size: 14px; font-weight: 600; color: var(--text-primary); }
.good-meta { display: flex; flex-wrap: wrap; gap: 4px 10px; margin-top: 3px; }
.good-code { font-size: 11px; color: var(--text-secondary); }
.code-prefix { color: var(--text-secondary); margin-right: 3px; }
.code-val { background: var(--surface-2); padding: 1px 5px; border-radius: 3px; }

/* 客戶 chip */
.customer-chip { background: var(--surface-2); border: 1px solid var(--border); border-radius: 4px; padding: 2px 7px; font-size: 11px; color: var(--text-secondary); white-space: nowrap; }

/* 碳排量 */
.unit { font-size: 10px; opacity: 0.7; }
.no-emissions { display: flex; flex-direction: column; gap: 1px; }
.no-emissions-dash { font-size: 14px; color: var(--text-secondary); }
.no-emissions-hint { font-size: 10px; color: #94a3b8; }
.emissions-source-badge { display: inline-block; margin-left: 4px; padding: 1px 6px; border-radius: 3px; font-size: 10px; font-weight: 700; vertical-align: middle; }
.esb-pcf_sync { background: #dcfce7; color: #15803d; }
.esb-supplier_reported { background: #dbeafe; color: #1d4ed8; }
.esb-manual { background: #f3f4f6; color: #6b7280; }

/* 狀態 */
.status-label { font-size: 12px; color: var(--text-secondary); }

/* 導航箭頭 */
.row-arrow { font-size: 16px; color: var(--text-secondary); margin-left: 2px; user-select: none; }

/* 唯讀欄位（ERP 同步，不可編輯）*/
.readonly-field {
  padding: 7px 10px;
  font-size: 13px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 6px;
  color: var(--text-secondary);
  min-height: 34px;
}

/* 法規 tag（補充色）*/
.tag-reg { background: #ede9fe; color: #6d28d9; border: 1px solid #c4b5fd; }
</style>
