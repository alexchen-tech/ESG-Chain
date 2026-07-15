<template>
  <div class="page-wrapper">
    <div class="breadcrumb">
      <span class="breadcrumb-parent">商品合規管理</span>
      <span class="breadcrumb-sep">›</span>
      <router-link to="/compliance/shipments" class="breadcrumb-link">出口申報</router-link>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current font-mono">{{ shipment?.shipment_no }}</span>
    </div>

    <div v-if="loading" class="loading-state">載入中…</div>
    <div v-else-if="!shipment" class="loading-state">找不到此申報</div>
    <template v-else>
      <!-- Header -->
      <div class="page-header">
        <div>
          <h1 class="page-title font-mono">{{ shipment.shipment_no }}</h1>
          <div class="header-meta">
            <span class="market-badge">{{ shipment.target_market }}</span>
            <span class="text-muted">{{ shipment.export_date || '日期未設定' }}</span>
            <span :class="eudrBadgeClass(shipment.eudr_dds_status)" class="badge">{{ eudrLabel(shipment.eudr_dds_status) }}</span>
            <span v-if="shipment.eudr_dds_ref" class="font-mono text-sm text-muted">{{ shipment.eudr_dds_ref }}</span>
          </div>
        </div>
        <div class="header-actions">
          <button
            v-if="shipment.eudr_dds_status === 'draft' || shipment.eudr_dds_status === 'submitted'"
            class="btn btn-secondary"
            @click="openSubmitModal"
          >標記已送出</button>
          <button class="btn btn-secondary" @click="toggleDdsPanel">
            {{ ddsOpen ? '收合 DDS 草稿' : '查看 DDS 草稿' }}
          </button>
          <button class="btn btn-primary" @click="openAddLineModal">+ 新增商品項目</button>
        </div>
      </div>

      <div class="main-layout" :class="{ 'with-dds': ddsOpen }">
        <!-- Lines Table -->
        <div class="lines-area">
          <div class="card">
            <div v-if="!shipment.lines || shipment.lines.length === 0" class="empty-state">
              尚無商品項目，點「新增商品項目」開始
            </div>
            <div v-else>
              <div v-for="line in shipment.lines" :key="line.id" class="line-block">
                <div class="line-header">
                  <div class="line-info">
                    <span class="line-name">{{ line.trade_good_name }}</span>
                    <span class="line-code font-mono">{{ line.trade_good_code }}</span>
                    <span v-if="line.is_eudr" class="eudr-tag">EUDR</span>
                    <span class="line-qty font-mono">{{ line.total_quantity }} {{ line.unit }}</span>
                    <span v-if="line.weighted_pcf" class="line-pcf font-mono">PCF: {{ line.weighted_pcf }} kgCO₂e</span>
                  </div>
                  <div class="line-actions">
                    <button class="btn btn-secondary btn-sm" @click="openAllocateModal(line)">+ 分配批號</button>
                    <button class="remove-btn" @click="removeLine(line)">×</button>
                  </div>
                </div>

                <!-- Batch rows -->
                <div v-if="line.line_batches.length > 0" class="batch-list">
                  <div v-for="lb in line.line_batches" :key="lb.id" class="batch-row">
                    <span class="batch-no font-mono">{{ lb.erp_batch_no }}</span>
                    <span class="batch-supplier text-muted">{{ lb.supplier_name }}</span>
                    <span class="batch-qty font-mono">{{ lb.allocated_quantity }} {{ line.unit }}</span>
                    <span v-if="lb.lot_pcf" class="batch-pcf font-mono text-muted">{{ lb.lot_pcf }} kgCO₂e/unit</span>
                    <span v-if="lb.origins_count === 0" class="origins-warn">⚠ 缺原料溯源</span>
                    <button class="remove-btn-sm" @click="removeBatch(line, lb)">×</button>
                  </div>
                </div>
                <div v-else class="batch-empty">尚未分配生產批號</div>
              </div>
            </div>
          </div>
        </div>

        <!-- DDS Panel -->
        <div v-if="ddsOpen" class="dds-panel">
          <div class="dds-header">
            <span class="dds-title">EUDR DDS 草稿</span>
            <button class="btn btn-secondary btn-sm" @click="copyDds">{{ copied ? '已複製' : '複製 JSON' }}</button>
          </div>
          <div v-if="ddsLoading" class="dds-loading">載入中…</div>
          <div v-else-if="!dds" class="dds-empty">無法取得草稿（此批次不適用 EUDR）</div>
          <div v-else class="dds-content">
            <div class="dds-meta">
              <div class="dds-row"><span class="dds-label">申報批號</span><span class="font-mono">{{ dds.shipment_no }}</span></div>
              <div class="dds-row"><span class="dds-label">目標市場</span><span>{{ dds.target_market }}</span></div>
              <div class="dds-row"><span class="dds-label">出口日期</span><span>{{ dds.export_date || '—' }}</span></div>
              <div class="dds-row"><span class="dds-label">DDS 狀態</span><span :class="eudrBadgeClass(dds.eudr_dds_status)" class="badge">{{ eudrLabel(dds.eudr_dds_status) }}</span></div>
            </div>

            <div v-for="(c, ci) in dds.commodities" :key="ci" class="dds-commodity">
              <div class="dds-commodity-header">
                <span class="font-mono">{{ c.trade_good_code }}</span>
                <span>{{ c.trade_good_name }}</span>
                <span class="text-muted font-mono">HS {{ c.hs_code }}</span>
                <span class="font-mono">{{ c.total_quantity }} {{ c.unit }}</span>
              </div>
              <div v-if="c.weighted_pcf" class="dds-pcf">加權 PCF: {{ c.weighted_pcf }} kgCO₂e</div>

              <div v-for="(b, bi) in c.production_batches" :key="bi" class="dds-batch">
                <div class="dds-batch-header" :class="{ 'origins-warn-block': b.origins_missing }">
                  <span class="font-mono">{{ b.batch_no }}</span>
                  <span class="text-muted">{{ b.supplier }}</span>
                  <span class="font-mono">{{ b.allocated_quantity }}</span>
                  <span v-if="b.origins_missing" class="warn-label">⚠ 缺溯源</span>
                </div>
                <div v-for="(o, oi) in b.raw_material_origins" :key="oi" class="dds-origin">
                  <span class="origin-flag">{{ o.country }}</span>
                  <span>{{ o.material }}</span>
                  <span v-if="o.facility" class="text-muted">{{ o.facility }}</span>
                  <span v-if="o.gps" class="font-mono text-muted">{{ o.gps }}</span>
                  <span v-if="o.certification" class="cert-tag">{{ o.certification }}</span>
                </div>
              </div>
            </div>

            <div class="dds-generated">產生時間：{{ dds.generated_at?.slice(0,19).replace('T',' ') }}</div>
          </div>
        </div>
      </div>
    </template>

    <!-- Add Line Modal -->
    <div v-if="addLineModal" class="modal-overlay" @click.self="addLineModal = false">
      <div class="modal">
        <div class="modal-header">
          <h3>新增商品項目</h3>
          <button class="modal-close" @click="addLineModal = false">×</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">出口商品 <span class="req">*</span></label>
            <input
              v-model="lineForm.search"
              class="form-input"
              placeholder="搜尋商品名稱或料號…"
              @input="searchTradeGoods"
            />
            <div v-if="tradeGoodResults.length > 0" class="search-results">
              <div
                v-for="tg in tradeGoodResults"
                :key="tg.id"
                class="search-item"
                :class="{ selected: lineForm.sales_product_id === tg.id }"
                @click="selectTradeGood(tg)"
              >
                <span>{{ tg.name }}</span>
                <span class="font-mono text-muted">{{ tg.product_code }}</span>
                <span v-if="tg.is_eudr_applicable" class="eudr-tag">EUDR</span>
              </div>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">數量 <span class="req">*</span></label>
              <input v-model="lineForm.total_quantity" class="form-input font-mono" type="number" min="0" />
            </div>
            <div class="form-group">
              <label class="form-label">單位</label>
              <input v-model="lineForm.unit" class="form-input" placeholder="pcs" />
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="addLineModal = false">取消</button>
          <button class="btn btn-primary" :disabled="!lineForm.sales_product_id || !lineForm.total_quantity || lineFormSaving" @click="submitAddLine">
            {{ lineFormSaving ? '新增中…' : '新增' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Allocate Batch Modal -->
    <div v-if="allocateModal" class="modal-overlay" @click.self="allocateModal = false">
      <div class="modal">
        <div class="modal-header">
          <h3>分配生產批號</h3>
          <button class="modal-close" @click="allocateModal = false">×</button>
        </div>
        <div class="modal-body">
          <p class="modal-hint">商品：<strong>{{ activeLine?.trade_good_name }}</strong></p>
          <div v-if="availableBatches.length === 0" class="text-muted" style="padding:12px 0;">
            尚無可用批號。請先在「生產批號」頁面建立批號。
          </div>
          <div v-else class="batch-select-list">
            <div
              v-for="b in availableBatches"
              :key="b.id"
              class="batch-select-row"
              :class="{ selected: allocateForm.production_batch_id === b.id }"
              @click="allocateForm.production_batch_id = b.id"
            >
              <span class="font-mono">{{ b.erp_batch_no }}</span>
              <span class="text-muted">{{ b.supplier_name }}</span>
              <span class="font-mono">{{ b.quantity }} {{ b.unit }}</span>
              <span v-if="b.lot_pcf" class="text-muted font-mono">PCF: {{ b.lot_pcf }}</span>
            </div>
          </div>
          <div class="form-group" style="margin-top:12px;">
            <label class="form-label">分配數量 <span class="req">*</span></label>
            <input v-model="allocateForm.allocated_quantity" class="form-input font-mono" type="number" min="0.001" step="0.001" />
          </div>
          <div v-if="allocateWarnings.length > 0" class="warnings-box">
            <div v-for="(w, i) in allocateWarnings" :key="i" class="warning-item">⚠ {{ w }}</div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="allocateModal = false">取消</button>
          <button class="btn btn-primary" :disabled="!allocateForm.production_batch_id || !allocateForm.allocated_quantity || allocateSaving" @click="submitAllocate">
            {{ allocateSaving ? '分配中…' : '確認分配' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Submit DDS Modal -->
    <div v-if="submitModal" class="modal-overlay" @click.self="submitModal = false">
      <div class="modal">
        <div class="modal-header">
          <h3>標記 DDS 已送出</h3>
          <button class="modal-close" @click="submitModal = false">×</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">EUDR 申報編號（DDS Reference）</label>
            <input v-model="ddsRef" class="form-input font-mono" placeholder="EU-DDS-XXXXX" />
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="submitModal = false">取消</button>
          <button class="btn btn-primary" :disabled="submitSaving" @click="submitDdsRef">
            {{ submitSaving ? '更新中…' : '確認送出' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { shipmentApi, shipmentLineApi, shipmentLineBatchApi, ddsDraftApi, type Shipment, type ShipmentLine, type ShipmentLineBatch, type DdsDraft } from '@/api/modules/shipment'
import { productionBatchApi, type ProductionBatch } from '@/api/modules/productionBatch'
import http from '@/api/http'

const EUDR_LABELS: Record<string, string> = {
  not_required: '不需申報', draft: '草稿', submitted: '已送出', approved: '已核准',
}

interface TradeGoodResult { id: string; name: string; product_code: string | null; hs_code: string | null; is_eudr_applicable: boolean }

export default defineComponent({
  name: 'ShipmentDetailView',

  data() {
    return {
      loading: false,
      shipment: null as Shipment | null,

      // DDS
      ddsOpen: false,
      ddsLoading: false,
      dds: null as DdsDraft | null,
      copied: false,

      // Add Line
      addLineModal: false,
      lineFormSaving: false,
      lineForm: { search: '', sales_product_id: '', total_quantity: '', unit: 'pcs' },
      tradeGoodResults: [] as TradeGoodResult[],

      // Allocate
      allocateModal: false,
      activeLine: null as ShipmentLine | null,
      availableBatches: [] as ProductionBatch[],
      allocateForm: { production_batch_id: '', allocated_quantity: '' },
      allocateWarnings: [] as string[],
      allocateSaving: false,

      // Submit DDS
      submitModal: false,
      ddsRef: '',
      submitSaving: false,
    }
  },

  async mounted() {
    await this.load()
  },

  methods: {
    async load() {
      this.loading = true
      try {
        const res = await shipmentApi.get(this.$route.params.id as string)
        this.shipment = res.data.data
      } finally {
        this.loading = false
      }
    },

    async toggleDdsPanel() {
      this.ddsOpen = !this.ddsOpen
      if (this.ddsOpen && !this.dds) await this.loadDds()
    },

    async loadDds() {
      if (!this.shipment) return
      this.ddsLoading = true
      try {
        const res = await ddsDraftApi.get(this.shipment.id)
        this.dds = res.data.data
      } catch { this.dds = null } finally {
        this.ddsLoading = false
      }
    },

    copyDds() {
      if (!this.dds) return
      navigator.clipboard.writeText(JSON.stringify(this.dds, null, 2))
      this.copied = true
      setTimeout(() => { this.copied = false }, 2000)
    },

    openAddLineModal() {
      this.lineForm = { search: '', sales_product_id: '', total_quantity: '', unit: 'pcs' }
      this.tradeGoodResults = []
      this.addLineModal = true
    },

    async searchTradeGoods() {
      if (!this.lineForm.search.trim()) { this.tradeGoodResults = []; return }
      try {
        const res = await http.get<{ success: boolean; data: TradeGoodResult[] }>(`/api/v1/trade-goods/search?q=${encodeURIComponent(this.lineForm.search)}`)
        this.tradeGoodResults = res.data.data
      } catch { this.tradeGoodResults = [] }
    },

    selectTradeGood(tg: TradeGoodResult) {
      this.lineForm.sales_product_id = tg.id
      this.lineForm.search = tg.name
      this.tradeGoodResults = []
    },

    async submitAddLine() {
      if (!this.shipment) return
      this.lineFormSaving = true
      try {
        await shipmentLineApi.create(this.shipment.id, {
          sales_product_id: this.lineForm.sales_product_id,
          total_quantity: parseFloat(this.lineForm.total_quantity),
          unit: this.lineForm.unit || 'pcs',
        })
        this.addLineModal = false
        await this.load()
        this.dds = null
      } finally {
        this.lineFormSaving = false
      }
    },

    async openAllocateModal(line: ShipmentLine) {
      this.activeLine = line
      this.allocateForm = { production_batch_id: '', allocated_quantity: '' }
      this.allocateWarnings = []
      this.allocateModal = true
      const res = await productionBatchApi.list({ matched_status: 'matched' })
      this.availableBatches = res.data.data
    },

    async submitAllocate() {
      if (!this.shipment || !this.activeLine) return
      this.allocateSaving = true
      try {
        const res = await shipmentLineBatchApi.create(this.shipment.id, this.activeLine.id, {
          production_batch_id: this.allocateForm.production_batch_id,
          allocated_quantity: parseFloat(this.allocateForm.allocated_quantity),
        })
        this.allocateWarnings = res.data.warnings || []
        if (this.allocateWarnings.length === 0) {
          this.allocateModal = false
        }
        await this.load()
        this.dds = null
      } finally {
        this.allocateSaving = false
      }
    },

    async removeLine(line: ShipmentLine) {
      if (!this.shipment || !confirm(`確認移除商品項目「${line.trade_good_name}」？`)) return
      await shipmentLineApi.destroy(this.shipment.id, line.id)
      await this.load()
      this.dds = null
    },

    async removeBatch(line: ShipmentLine, lb: ShipmentLineBatch) {
      if (!this.shipment || !confirm(`確認移除批號 ${lb.erp_batch_no}？`)) return
      await shipmentLineBatchApi.destroy(this.shipment.id, line.id, lb.id)
      await this.load()
      this.dds = null
    },

    openSubmitModal() {
      this.ddsRef = this.shipment?.eudr_dds_ref || ''
      this.submitModal = true
    },

    async submitDdsRef() {
      if (!this.shipment) return
      this.submitSaving = true
      try {
        const res = await shipmentApi.update(this.shipment.id, { eudr_dds_ref: this.ddsRef })
        this.shipment = { ...this.shipment, ...res.data.data }
        this.submitModal = false
      } finally {
        this.submitSaving = false
      }
    },

    eudrLabel(s: string) { return EUDR_LABELS[s] || s },
    eudrBadgeClass(s: string) {
      return { 'badge-gray': s === 'not_required', 'badge-orange': s === 'draft', 'badge-blue': s === 'submitted', 'badge-green': s === 'approved' }
    },
  },
})
</script>

<style scoped>
.page-wrapper { padding: 24px; }
.breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-secondary); margin-bottom: 12px; }
.breadcrumb-sep { color: var(--border); }
.breadcrumb-current { color: var(--text-primary); }
.breadcrumb-link { color: var(--accent); text-decoration: none; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
.page-title { font-size: 20px; font-weight: 700; margin: 0 0 8px; }
.header-meta { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.header-actions { display: flex; gap: 8px; }
.loading-state { padding: 48px; text-align: center; color: var(--text-secondary); }

.main-layout { display: grid; gap: 16px; }
.main-layout.with-dds { grid-template-columns: 1fr 380px; }

.card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
.empty-state { padding: 48px; text-align: center; color: var(--text-secondary); font-size: 14px; }

.line-block { border-bottom: 1px solid var(--border); }
.line-block:last-child { border-bottom: none; }
.line-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: var(--surface); }
.line-info { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.line-name { font-weight: 600; font-size: 14px; }
.line-code { font-size: 12px; color: var(--text-secondary); }
.line-qty { font-size: 13px; color: var(--text-primary); }
.line-pcf { font-size: 12px; color: #7c3aed; }
.line-actions { display: flex; gap: 8px; align-items: center; }
.eudr-tag { background: #fef9c3; color: #854d0e; border-radius: 4px; padding: 1px 6px; font-size: 11px; font-weight: 700; }

.batch-list { background: var(--surface-2); padding: 4px 16px 8px 32px; }
.batch-row { display: flex; align-items: center; gap: 10px; padding: 5px 0; font-size: 12px; border-bottom: 1px solid var(--border); }
.batch-row:last-child { border-bottom: none; }
.batch-no { color: var(--accent); min-width: 120px; }
.batch-supplier { flex: 1; }
.batch-qty { color: var(--text-primary); }
.batch-pcf { color: var(--text-secondary); }
.origins-warn { color: #ea580c; font-size: 11px; }
.batch-empty { padding: 8px 16px 8px 32px; font-size: 12px; color: var(--text-secondary); }

.remove-btn { background: none; border: none; color: #dc2626; cursor: pointer; font-size: 18px; line-height: 1; }
.remove-btn-sm { background: none; border: none; color: #dc2626; cursor: pointer; font-size: 14px; line-height: 1; margin-left: auto; }

/* DDS Panel */
.dds-panel { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; display: flex; flex-direction: column; max-height: 80vh; overflow: hidden; }
.dds-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.dds-title { font-size: 13px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .05em; }
.dds-loading, .dds-empty { padding: 24px; text-align: center; color: var(--text-secondary); font-size: 13px; }
.dds-content { flex: 1; overflow-y: auto; padding: 12px 16px; }
.dds-meta { margin-bottom: 12px; display: flex; flex-direction: column; gap: 6px; }
.dds-row { display: flex; gap: 8px; font-size: 12px; }
.dds-label { color: var(--text-secondary); min-width: 60px; }
.dds-commodity { border: 1px solid var(--border); border-radius: 8px; padding: 10px; margin-bottom: 10px; }
.dds-commodity-header { display: flex; gap: 8px; align-items: center; font-size: 13px; flex-wrap: wrap; margin-bottom: 6px; }
.dds-pcf { font-size: 12px; color: #7c3aed; margin-bottom: 8px; }
.dds-batch { background: var(--surface-2); border-radius: 6px; padding: 8px; margin-bottom: 6px; }
.dds-batch-header { display: flex; gap: 8px; align-items: center; font-size: 12px; margin-bottom: 4px; flex-wrap: wrap; }
.dds-batch-header.origins-warn-block { background: #fff7ed; border-radius: 4px; padding: 4px; }
.warn-label { color: #ea580c; font-size: 11px; font-weight: 700; }
.dds-origin { display: flex; gap: 8px; align-items: center; font-size: 11px; padding: 2px 0; flex-wrap: wrap; }
.origin-flag { background: #f1f5f9; border-radius: 3px; padding: 1px 5px; font-family: 'Fira Code', monospace; font-weight: 700; font-size: 10px; }
.cert-tag { background: #f5f3ff; color: #7c3aed; border-radius: 4px; padding: 1px 5px; font-size: 10px; font-family: 'Fira Code', monospace; }
.dds-generated { font-size: 11px; color: var(--text-secondary); text-align: right; margin-top: 8px; }

/* Batch select */
.batch-select-list { max-height: 200px; overflow-y: auto; border: 1px solid var(--border); border-radius: 6px; }
.batch-select-row { display: flex; gap: 10px; align-items: center; padding: 8px 12px; cursor: pointer; font-size: 13px; border-bottom: 1px solid var(--border); }
.batch-select-row:last-child { border-bottom: none; }
.batch-select-row:hover { background: var(--surface-2); }
.batch-select-row.selected { background: #f0fdf4; }
.search-results { border: 1px solid var(--border); border-radius: 6px; max-height: 180px; overflow-y: auto; margin-top: 4px; }
.search-item { display: flex; gap: 8px; align-items: center; padding: 8px 12px; cursor: pointer; font-size: 13px; border-bottom: 1px solid var(--border); }
.search-item:last-child { border-bottom: none; }
.search-item:hover { background: var(--surface-2); }
.search-item.selected { background: #f0fdf4; }
.warnings-box { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 6px; padding: 10px 12px; }
.warning-item { font-size: 13px; color: #ea580c; }
.modal-hint { font-size: 13px; color: var(--text-secondary); margin: 0 0 12px; }

/* Shared */
.font-mono { font-family: 'Fira Code', monospace; }
.text-muted { color: var(--text-secondary); }
.text-sm { font-size: 12px; }
.market-badge { background: #f1f5f9; border-radius: 4px; padding: 2px 8px; font-size: 11px; font-weight: 700; font-family: 'Fira Code', monospace; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
.badge-gray { background: var(--surface-2); color: var(--text-secondary); }
.badge-orange { background: #fff7ed; color: #ea580c; }
.badge-blue { background: #eff6ff; color: #2563eb; }
.badge-green { background: #dcfce7; color: #16a34a; }
.btn { display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; padding: 8px 14px; transition: opacity 0.15s; }
.btn:disabled { opacity: .5; cursor: not-allowed; }
.btn-primary { background: var(--accent); color: #fff; }
.btn-primary:hover:not(:disabled) { opacity: .85; }
.btn-secondary { background: var(--surface-2); color: var(--text-primary); border: 1px solid var(--border); }
.btn-sm { padding: 5px 10px; font-size: 12px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-label { font-size: 12px; font-weight: 600; color: var(--text-secondary); }
.req { color: #dc2626; }
.form-input { border: 1px solid var(--border); border-radius: 6px; padding: 8px 10px; font-size: 13px; background: var(--surface); color: var(--text-primary); }
.form-input:focus { outline: none; border-color: var(--accent); }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 400; display: flex; align-items: center; justify-content: center; }
.modal { background: var(--surface); border-radius: 10px; padding: 0; min-width: 440px; max-width: 560px; max-height: 90vh; overflow-y: auto; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px 0; margin-bottom: 16px; }
.modal-header h3 { font-size: 16px; font-weight: 700; margin: 0; }
.modal-close { background: none; border: none; font-size: 22px; cursor: pointer; color: var(--text-secondary); }
.modal-body { padding: 0 24px; display: flex; flex-direction: column; gap: 14px; }
.modal-footer { display: flex; justify-content: flex-end; gap: 8px; padding: 20px 24px; }
</style>
