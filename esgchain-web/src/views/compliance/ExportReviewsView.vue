<template>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h1 class="page-title">出口審查</h1>
        <p class="page-subtitle">共 {{ pagination.total }} 筆生產批號（跨批號彙總，含尚未審查者）</p>
      </div>
    </div>

    <!-- 篩選 -->
    <div class="filter-bar">
      <select v-model="filters.market" class="filter-select" @change="resetAndLoad">
        <option value="">所有市場</option>
        <option v-for="m in MARKETS" :key="m" :value="m">{{ m }}</option>
      </select>
      <select v-model="filters.status" class="filter-select" @change="resetAndLoad">
        <option value="">所有狀態</option>
        <option value="unreviewed">未審查</option>
        <option value="pending">待審查</option>
        <option value="pass">通過</option>
        <option value="warning">警示</option>
        <option value="fail">未通過</option>
      </select>
      <input v-model="filters.production_date_from" type="date" class="filter-input" @change="resetAndLoad" />
      <span style="color:var(--text-secondary);font-size:13px;">～</span>
      <input v-model="filters.production_date_to" type="date" class="filter-input" @change="resetAndLoad" />
      <button v-if="hasActiveFilters" class="btn btn-secondary btn-sm" @click="clearFilters">✕ 清除</button>
      <button
        v-if="filters.status === 'unreviewed' && filters.market"
        class="btn btn-primary btn-sm"
        style="margin-left:auto;"
        :disabled="bulkReviewing || !unreviewedCount"
        @click="bulkReviewCurrentPage"
      >{{ bulkReviewing ? `審查中…（${bulkReviewProgress}/${unreviewedCount}）` : `一鍵審查本頁未審查批號（${unreviewedCount}）` }}</button>
    </div>

    <!-- 表格 -->
    <div class="table-container">
      <div v-if="isLoading" class="loading-mask">載入中...</div>
      <div v-else-if="items.length === 0" class="empty-state">
        <p>尚無符合條件的生產批號</p>
      </div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th style="width:20px;"></th>
            <th>生產批號</th>
            <th>生產工單</th>
            <th>生產日期</th>
            <th>供應商</th>
            <th>產品</th>
            <th>市場</th>
            <th>審查狀態</th>
            <th>審查時間</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="item in items" :key="item.id">
            <tr class="clickable-row" @click="toggleExpand(item)">
              <td class="expand-chevron" :class="{ open: expanded[item.id] }">›</td>
              <td class="font-mono">
                <router-link
                  :to="`/compliance/production-batches/${item.id}`"
                  target="_blank"
                  class="finding-fix-link"
                  style="color:var(--text-primary);text-decoration:none;"
                  title="在新分頁開啟批號詳情頁（可補溯源資料）"
                  @click.stop
                >{{ item.erp_batch_no }}</router-link>
              </td>
              <td class="font-mono" style="font-size:11px;color:var(--text-secondary);">{{ item.erp_order_no || '—' }}</td>
              <td class="font-mono">{{ item.production_date || '—' }}</td>
              <td>{{ item.supplier_name ? maskSupplierName(item.supplier_name) : '—' }}<span v-if="item.supplier_code" class="font-mono" style="font-size:11px;color:var(--text-secondary);"> · {{ item.supplier_code }}</span></td>
              <td>{{ item.product_name || '—' }}</td>
              <td>{{ item.market || '—' }}</td>
              <td>
                <span class="status-dot" :class="`status-dot--${statusDotClass(item.status)}`"></span>
                {{ STATUS_LABELS[item.status] ?? item.status }}
                <span v-if="item.possibly_stale" class="tag tag-pending" style="margin-left:4px;" title="審查後相關供應商的合規文件有更新，建議重新執行審查">⚠ 可能已過期</span>
              </td>
              <td class="font-mono" style="font-size:11px;color:var(--text-secondary);">{{ item.reviewed_at?.slice(0, 16) || '—' }}</td>
            </tr>
            <tr v-if="expanded[item.id]">
              <td colspan="9" class="panel-cell">
                <div class="review-panel">
                  <div class="section-title-row">
                    <div class="section-title">出口市場審查</div>
                    <div class="review-toolbar">
                      <router-link
                        :to="`/compliance/production-batches/${item.id}`"
                        target="_blank"
                        class="btn btn-secondary btn-sm"
                        title="在新分頁開啟批號詳情頁，可補齊原料溯源、確認實際供應商"
                      >📝 前往補齊溯源資料</router-link>
                      <div class="toolbar-divider"></div>
                      <label class="field-inline">
                        <span class="field-inline-label">市場</span>
                        <select v-model="panel(item.id).market" class="form-select" style="width:88px;" @change="onPanelMarketChanged(item.id)">
                          <option v-for="m in MARKETS" :key="m" :value="m">{{ m }}</option>
                        </select>
                      </label>
                      <label class="field-inline">
                        <span class="field-inline-label">範疇</span>
                        <select v-model="panel(item.id).program" class="form-select" style="width:104px;" title="篩選只審查哪個法規範疇，留空為完整審查">
                          <option value="">完整審查</option>
                          <option v-for="p in PROGRAMS" :key="p" :value="p">{{ PROGRAM_LABELS[p] }}</option>
                        </select>
                      </label>
                      <button class="btn btn-primary btn-sm" :disabled="panel(item.id).reviewing" @click="runReview(item)">
                        {{ panel(item.id).reviewing ? '審查中…' : '執行審查' }}
                      </button>
                      <button class="btn btn-secondary btn-sm" :disabled="panel(item.id).ddsLoading" @click="openDdsDraft(item.id)">
                        {{ panel(item.id).ddsLoading ? '產出中…' : (panel(item.id).ddsOpen ? '收起合規文件' : '產出合規文件') }}
                      </button>
                    </div>
                  </div>

                  <div v-if="panel(item.id).loading" class="empty-inline">載入中…</div>
                  <div v-else-if="!panel(item.id).reviews.length" class="empty-inline">此批號尚未執行過任何出口市場審查，選擇市場與範疇後點擊「執行審查」</div>
                  <div v-else>
                    <div v-for="r in panel(item.id).reviews" :key="r.id" class="review-card">
                      <div class="review-head">
                        <span class="tag tag-gray">{{ r.market }}</span>
                        <span class="tag tag-gray">{{ r.program ? PROGRAM_LABELS[r.program] ?? r.program : '完整審查' }}</span>
                        <span class="review-status" :class="`review-status--${r.status}`">
                          {{ { pass: '✓ 通過', warning: '⚠ 警告', fail: '✕ 未通過', pending: '待審' }[r.status] }}
                        </span>
                        <span class="review-time font-mono">{{ r.reviewed_at?.slice(0, 10) }}</span>
                        <span v-if="r.possibly_stale" class="tag tag-pending" title="審查後相關供應商的合規文件有更新，建議重新執行審查">⚠ 可能已過期</span>
                        <button class="btn btn-danger btn-sm" style="margin-left:auto;" @click="removeReview(item.id, r)">✕</button>
                      </div>
                      <div v-for="f in otherFindings(r)" :key="f.check" class="review-finding">
                        <span class="finding-dot" :class="findingDotClass(f)"></span>
                        <span class="finding-label">{{ f.doc_type ? docTypeLabel(f.doc_type) : f.label }}</span>
                        <span class="finding-detail">{{ f.detail }}</span>
                        <router-link
                          v-if="f.doc_type && f.supplier_id"
                          :to="`/compliance/suppliers/${f.supplier_id}?tab=compliance&doc_type=${f.doc_type}`"
                          class="finding-fix-link"
                        >前往補件 →</router-link>
                      </div>

                      <!-- DPP 揭露逐項確認 -->
                      <div v-if="dppFindings(r).length" class="checklist-wrap">
                        <div class="checklist-title">DPP 揭露逐項確認</div>
                        <div class="checklist-grid">
                          <div v-for="f in dppFindings(r)" :key="f.check" class="checklist-item" :class="`checklist-item--${f.status}`">
                            <span class="checklist-icon">{{ f.status === 'pass' ? '✓' : '!' }}</span>
                            <div class="checklist-body">
                              <span class="checklist-label">{{ f.label }}</span>
                              <span class="checklist-detail">{{ f.detail }}</span>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- 批次護照（Batch Passport） -->
                  <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border);">
                    <div class="section-subtitle-row">
                      <div class="section-subtitle">批次護照（Batch Passport）</div>
                      <div style="display:flex;gap:6px;">
                        <button class="btn btn-secondary btn-sm" :disabled="panel(item.id).passportLoading" @click="loadPassport(item.id)">
                          {{ panel(item.id).passportLoading ? '產出中…' : (panel(item.id).passport ? '重新產出批次護照' : '📋 查看完整批次護照 JSON') }}
                        </button>
                        <button
                          v-if="panel(item.id).passport"
                          class="btn btn-secondary btn-sm"
                          @click="copyJson(item.id, panel(item.id).passport)"
                        >{{ copiedBatchId === item.id ? '✓ 已複製' : '📄 複製 JSON' }}</button>
                      </div>
                    </div>
                    <pre v-if="panel(item.id).passport" class="passport-json">{{ JSON.stringify(panel(item.id).passport, null, 2) }}</pre>
                  </div>

                  <!-- 出口合規文件草稿 -->
                  <div v-if="panel(item.id).ddsOpen && panel(item.id).ddsDraft" style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border);">
                    <div class="section-subtitle" style="margin-bottom:10px;">出口合規文件草稿（{{ panel(item.id).ddsDraft.market }}）</div>
                    <div class="review-card">
                      <div class="review-head">
                        <span class="review-status" :class="`review-status--${panel(item.id).ddsDraft.export_review.status}`">
                          {{ { pass: '✓ 通過', warning: '⚠ 警告', fail: '✕ 未通過', missing: '未審查' }[panel(item.id).ddsDraft.export_review.status] }}
                        </span>
                        <span class="tag tag-gray">{{ panel(item.id).ddsDraft.export_review.program ? (PROGRAM_LABELS[panel(item.id).ddsDraft.export_review.program] ?? panel(item.id).ddsDraft.export_review.program) : '完整審查' }}</span>
                        <span v-if="panel(item.id).ddsDraft.export_review.possibly_stale" class="tag tag-pending" title="審查後相關供應商的合規文件有更新，建議重新執行審查">⚠ 可能已過期</span>
                      </div>
                      <div v-for="f in panel(item.id).ddsDraft.export_review.findings" :key="f.check ?? f.label" class="review-finding">
                        <span class="finding-dot" :class="`finding-dot--${f.status}`"></span>
                        <span class="finding-label">{{ f.label }}</span>
                        <span class="finding-detail">{{ f.detail }}</span>
                      </div>
                    </div>
                    <div class="detail-list">
                      <div class="detail-list-row"><span class="detail-label">品名</span><span class="detail-value">{{ panel(item.id).ddsDraft.trade_good_name ?? '—' }}</span></div>
                      <div class="detail-list-row"><span class="detail-label">HS Code</span><span class="detail-value font-mono">{{ panel(item.id).ddsDraft.hs_code ?? '—' }}</span></div>
                      <div class="detail-list-row"><span class="detail-label">供應商</span><span class="detail-value">{{ panel(item.id).ddsDraft.supplier ? maskSupplierName(panel(item.id).ddsDraft.supplier) : '—' }}</span></div>
                      <div class="detail-list-row"><span class="detail-label">數量</span><span class="detail-value font-mono">{{ panel(item.id).ddsDraft.quantity }} {{ panel(item.id).ddsDraft.unit }}</span></div>
                      <div class="detail-list-row"><span class="detail-label">批次 PCF</span><span class="detail-value font-mono">{{ panel(item.id).ddsDraft.lot_pcf ?? '—' }}</span></div>
                    </div>
                    <div v-if="panel(item.id).ddsDraft.origins_missing" class="origin-empty">尚無原料溯源資料，DDS 佐證力不足</div>
                  </div>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- 分頁 -->
    <div class="pagination">
      <span>第 {{ pagination.current_page }} / {{ pagination.last_page }} 頁</span>
      <button class="pg-btn" :disabled="pagination.current_page <= 1" @click="goPage(pagination.current_page - 1)">‹</button>
      <button class="pg-btn" :disabled="pagination.current_page >= pagination.last_page" @click="goPage(pagination.current_page + 1)">›</button>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import {
  exportReviewQueueApi, productionBatchApi,
  type ExportReviewQueueItem, type Pagination,
  type BatchExportReview, type BatchExportReviewFinding, type DdsDraft, type BatchPassport,
} from '@/api/modules/productionBatch'
import { EXPORT_MARKETS } from '@/constants/markets'
import { maskSupplierName } from '@/utils/maskName'

const MARKETS: string[] = [...EXPORT_MARKETS]

// 對應後端 MarketComplianceRule::PROGRAMS，篩選「執行審查」只跑哪個法規範疇
const PROGRAMS = ['dpp', 'cbam', 'eudr', 'uflpa', 'general']
const PROGRAM_LABELS: Record<string, string> = {
  dpp: 'DPP', cbam: 'CBAM', eudr: 'EUDR', uflpa: 'UFLPA', general: '一般文件',
}

const STATUS_LABELS: Record<string, string> = {
  unreviewed: '未審查', pending: '待審查', pass: '通過', warning: '警示', fail: '未通過',
}

// 比照 ProductionBatchDetailView.vue 既有的文件類型標籤，維持全站一致的顯示名稱
const DOC_TYPE_LABELS: Record<string, string> = {
  UFLPA_DECLARATION: 'UFLPA 聲明', EUDR_DDS: 'EUDR DDS', CMRT: 'CMRT 衝突礦產報告',
  SDS: 'SDS 安全資料表', CE_DOC: 'CE 認證', ORIGIN_CERT: '原產地證明', GRS: 'GRS 認證', OTHER: '其他',
}

interface ReviewPanelState {
  loading: boolean
  reviews: BatchExportReview[]
  market: string
  program: string
  reviewing: boolean
  ddsOpen: boolean
  ddsLoading: boolean
  ddsDraft: DdsDraft | null
  passport: BatchPassport | null
  passportLoading: boolean
}

function freshPanel(defaultMarket?: string | null): ReviewPanelState {
  return {
    loading: false,
    reviews: [],
    market: defaultMarket || 'EU',
    program: '',
    reviewing: false,
    ddsOpen: false,
    ddsLoading: false,
    ddsDraft: null,
    passport: null,
    passportLoading: false,
  }
}

export default defineComponent({
  name: 'ExportReviewsView',
  data() {
    return {
      isLoading: false,
      items: [] as ExportReviewQueueItem[],
      pagination: { current_page: 1, per_page: 20, total: 0, last_page: 1 } as Pagination,
      filters: {
        market: '',
        status: '',
        production_date_from: '',
        production_date_to: '',
      },
      MARKETS,
      PROGRAMS,
      PROGRAM_LABELS,
      STATUS_LABELS,
      expanded: {} as Record<string, boolean>,
      panels: {} as Record<string, ReviewPanelState>,
      bulkReviewing: false,
      bulkReviewProgress: 0,
      copiedBatchId: '',
    }
  },
  computed: {
    hasActiveFilters(): boolean {
      return !!(this.filters.market || this.filters.status || this.filters.production_date_from || this.filters.production_date_to)
    },
    unreviewedCount(): number {
      return this.items.filter(i => i.status === 'unreviewed').length
    },
  },
  async mounted() {
    await this.loadData()
  },
  methods: {
    maskSupplierName,

    async loadData() {
      this.isLoading = true
      try {
        const { data } = await exportReviewQueueApi.list({
          ...(this.filters.market ? { market: this.filters.market } : {}),
          ...(this.filters.status ? { status: this.filters.status } : {}),
          ...(this.filters.production_date_from ? { production_date_from: this.filters.production_date_from } : {}),
          ...(this.filters.production_date_to ? { production_date_to: this.filters.production_date_to } : {}),
          page: this.pagination.current_page,
          per_page: this.pagination.per_page,
        })
        this.items = data.data
        this.pagination = data.pagination
      } finally {
        this.isLoading = false
      }
    },
    resetAndLoad() {
      this.pagination.current_page = 1
      this.loadData()
    },
    clearFilters() {
      this.filters = { market: '', status: '', production_date_from: '', production_date_to: '' }
      this.resetAndLoad()
    },
    goPage(page: number) {
      this.pagination.current_page = page
      this.loadData()
    },
    statusDotClass(status: string): string {
      return ({
        unreviewed: 'unconfigured',
        pending: 'unconfigured',
        pass: 'valid',
        warning: 'expiring_soon',
        fail: 'expired',
      } as Record<string, string>)[status] ?? 'unconfigured'
    },

    // ── 展開面板 ──
    panel(batchId: string, defaultMarket?: string | null): ReviewPanelState {
      if (!this.panels[batchId]) this.panels[batchId] = freshPanel(defaultMarket)
      return this.panels[batchId]
    },
    toggleExpand(item: ExportReviewQueueItem) {
      this.expanded[item.id] = !this.expanded[item.id]
      const p = this.panel(item.id, item.market)
      if (this.expanded[item.id] && !p.reviews.length && !p.loading) {
        this.loadReviews(item.id)
      }
    },

    // 切換市場後，先前已產出的 DDS 草稿是對應舊市場的內容，不再有效，收起來避免誤導
    onPanelMarketChanged(batchId: string) {
      const p = this.panel(batchId)
      p.ddsOpen = false
      p.ddsDraft = null
    },

    // ── 出口市場審查 ──
    async loadReviews(batchId: string) {
      const p = this.panel(batchId)
      p.loading = true
      try {
        const { data } = await productionBatchApi.exportReviews(batchId)
        p.reviews = data.data
      } catch { p.reviews = [] } finally { p.loading = false }
    },
    async runReview(item: ExportReviewQueueItem) {
      const p = this.panel(item.id)
      const existing = p.reviews.find(r => r.market === p.market)
      if (existing && !confirm(`${p.market} 市場已有審查結果（${existing.reviewed_at?.slice(0, 10)}），重新執行會覆蓋舊結果，且不會保留歷史紀錄，確定要重跑嗎？`)) {
        return
      }
      p.reviewing = true
      try {
        const { data } = await productionBatchApi.runExportReview(item.id, p.market, p.program)
        await this.loadReviews(item.id)
        // 同步更新清單列，不需整頁重新查詢
        item.market = data.data.market
        item.status = data.data.status
        item.reviewed_at = data.data.reviewed_at
        item.possibly_stale = false
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '審查失敗')
      } finally { p.reviewing = false }
    },
    // 一鍵審查本頁「未審查」批號：僅限已篩選特定市場時可用（審查一定要指定市場），
    // 依序逐筆執行（非同時大量請求），失敗不中斷其餘批號
    async bulkReviewCurrentPage() {
      if (!this.filters.market) return
      const targets = this.items.filter(i => i.status === 'unreviewed')
      if (!targets.length) return

      this.bulkReviewing = true
      this.bulkReviewProgress = 0
      const failed: string[] = []
      try {
        for (const item of targets) {
          try {
            const { data } = await productionBatchApi.runExportReview(item.id, this.filters.market)
            item.market = data.data.market
            item.status = data.data.status
            item.reviewed_at = data.data.reviewed_at
            item.possibly_stale = false
          } catch {
            failed.push(item.erp_batch_no)
          } finally {
            this.bulkReviewProgress++
          }
        }
        if (failed.length) {
          alert(`完成，但以下批號審查失敗：${failed.join('、')}`)
        }
      } finally {
        this.bulkReviewing = false
      }
    },
    async removeReview(batchId: string, r: BatchExportReview) {
      try {
        await productionBatchApi.deleteExportReview(batchId, r.id)
        await this.loadReviews(batchId)
        // 刪除後同步更新清單列摘要，避免列上仍顯示已刪除審查的舊市場/狀態/時間
        this.syncListRowFromPanel(batchId)
      } catch { /* silent */ }
    },
    // 以面板目前已載入的審查紀錄，重新推導清單列要顯示的市場/狀態/時間摘要
    // （取最近一次審查的那筆；沒有任何審查紀錄則回到「未審查」）
    syncListRowFromPanel(batchId: string) {
      const item = this.items.find(i => i.id === batchId)
      if (!item) return
      const reviews = this.panel(batchId).reviews
      const latest = [...reviews].sort((a, b) => (b.reviewed_at ?? '').localeCompare(a.reviewed_at ?? ''))[0]
      if (latest) {
        item.market = latest.market
        item.status = latest.status
        item.reviewed_at = latest.reviewed_at
        item.possibly_stale = latest.possibly_stale ?? false
      } else {
        item.market = null
        item.status = 'unreviewed'
        item.reviewed_at = null
        item.possibly_stale = false
      }
    },

    // ── 出口合規文件草稿 ──
    async openDdsDraft(batchId: string) {
      const p = this.panel(batchId)
      if (p.ddsOpen) { p.ddsOpen = false; return }
      p.ddsLoading = true
      try {
        const { data } = await productionBatchApi.ddsDraft(batchId, p.market)
        p.ddsDraft = data.data
        p.ddsOpen = true
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '產出合規文件失敗')
      } finally { p.ddsLoading = false }
    },

    // ── 批次護照 ──
    async loadPassport(batchId: string) {
      const p = this.panel(batchId)
      p.passportLoading = true
      try {
        const { data } = await productionBatchApi.passport(batchId)
        p.passport = data.data
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '產出批次護照失敗')
      } finally { p.passportLoading = false }
    },

    async copyJson(batchId: string, data: unknown) {
      const text = JSON.stringify(data, null, 2)
      try {
        await navigator.clipboard.writeText(text)
      } catch {
        // clipboard API 在非安全環境（如非 HTTPS）可能不可用，退回傳統 execCommand 方式
        const textarea = document.createElement('textarea')
        textarea.value = text
        textarea.style.position = 'fixed'
        textarea.style.opacity = '0'
        document.body.appendChild(textarea)
        textarea.select()
        try { document.execCommand('copy') } catch { /* silent */ }
        document.body.removeChild(textarea)
      }
      this.copiedBatchId = batchId
      setTimeout(() => {
        if (this.copiedBatchId === batchId) this.copiedBatchId = ''
      }, 2000)
    },

    docTypeLabel(docType: string): string {
      return DOC_TYPE_LABELS[docType] ?? docType
    },
    findingDotClass(f: BatchExportReviewFinding): string {
      if (f.doc_status) return `finding-dot--${f.doc_status}`
      return `finding-dot--${f.status}`
    },
    dppFindings(r: BatchExportReview): BatchExportReviewFinding[] {
      return (r.findings ?? []).filter(f => f.check?.startsWith('dpp_'))
    },
    otherFindings(r: BatchExportReview): BatchExportReviewFinding[] {
      return (r.findings ?? []).filter(f => !f.check?.startsWith('dpp_'))
    },
  },
})
</script>

<style scoped>
.clickable-row { cursor: pointer; }
.clickable-row:hover { background: var(--surface-2); }
.expand-chevron { color: var(--text-secondary); transition: transform 0.15s; }
.expand-chevron.open { transform: rotate(90deg); }
.panel-cell { background: var(--surface-2); padding: 0 !important; }
.review-panel { padding: 16px 20px; }

.section-title-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 8px; }
.section-title { font-size: 13px; font-weight: 700; color: var(--text-primary); }
.section-subtitle { font-size: 12.5px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .06em; }
.section-subtitle-row { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; }
.empty-inline { font-size: 13px; color: var(--text-secondary); padding: 8px 0; }

.review-toolbar { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.toolbar-divider { width: 1px; align-self: stretch; background: var(--border); margin: 0 2px; }
.field-inline { display: flex; align-items: center; gap: 5px; }
.field-inline-label { font-size: 11px; color: var(--text-secondary); white-space: nowrap; }

/* 出口市場審查卡片 */
.review-card { border: 1px solid var(--border); border-radius: 6px; padding: 10px 12px; margin-bottom: 8px; background: var(--surface); }
.review-head { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.review-status { font-size: 12px; font-weight: 600; }
.review-status--pass { color: #166534; }
.review-status--warning { color: #b45309; }
.review-status--fail { color: #991b1b; }
.review-status--pending { color: var(--text-secondary); }
.review-status--missing { color: var(--text-secondary); }
.review-time { font-size: 11px; color: var(--text-secondary); }
.review-finding { display: flex; align-items: baseline; gap: 6px; font-size: 12px; padding: 2px 0; }
.finding-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; align-self: center; }
.finding-dot--pass { background: #16a34a; }
.finding-dot--warning { background: #f59e0b; }
.finding-dot--fail { background: #dc2626; }
.finding-dot--missing { background: #dc2626; }
.finding-dot--expired { background: #ea580c; }
.finding-dot--expiring_soon { background: #f59e0b; }
.finding-label { font-weight: 600; color: var(--text-primary); white-space: nowrap; }
.finding-detail { color: var(--text-secondary); }
.finding-fix-link { margin-left: auto; font-size: 11.5px; color: var(--accent); text-decoration: none; white-space: nowrap; }
.finding-fix-link:hover { text-decoration: underline; }

.checklist-wrap { margin-top: 8px; }
.checklist-title { font-size: 11px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; }
.checklist-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 6px; }
.checklist-item { display: flex; align-items: flex-start; gap: 6px; border: 1px solid var(--border); border-radius: 6px; padding: 6px 8px; background: var(--surface); }
.checklist-item--pass .checklist-icon { color: #16a34a; }
.checklist-item--warning .checklist-icon, .checklist-item--fail .checklist-icon { color: #dc2626; }
.checklist-body { display: flex; flex-direction: column; }
.checklist-label { font-size: 12px; font-weight: 600; }
.checklist-detail { font-size: 11px; color: var(--text-secondary); }

/* 出口合規文件草稿的品項摘要：單列表格，一列一項，避免最後一項落單時 2 欄格線留白 */
.detail-list { margin-top: 10px; border-top: 1px solid var(--border); }
.detail-list-row { display: flex; align-items: baseline; justify-content: space-between; gap: 16px; padding: 10px 2px; border-bottom: 1px solid var(--border); }
.detail-list-row .detail-value { text-align: right; }

.passport-json {
  margin-top: 10px; max-height: 400px; overflow: auto; font-size: 11px; line-height: 1.5;
  background: var(--surface); border: 1px solid var(--border); border-radius: 6px; padding: 10px;
}
.origin-empty { font-size: 13px; color: var(--text-secondary); text-align: center; padding: 12px 0; font-style: italic; }
</style>
