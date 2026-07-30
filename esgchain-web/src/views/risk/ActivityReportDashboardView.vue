<template>
  <div class="activity-report-dashboard">
    <div class="page-header">
      <div>
        <h1>Scope 3 活動資料彙總</h1>
        <p class="page-desc">跨供應商彙總各廠區能源活動資料填報、核實與推送狀態</p>
      </div>
    </div>

    <div class="summary-cards">
      <div class="summary-card">
        <div class="summary-label">待核實</div>
        <div class="summary-value font-mono">{{ summary?.by_status?.submitted ?? 0 }}</div>
      </div>
      <div class="summary-card accent">
        <div class="summary-label">已核實</div>
        <div class="summary-value font-mono">{{ summary?.by_status?.verified ?? 0 }}</div>
      </div>
      <div class="summary-card success">
        <div class="summary-label">推送成功</div>
        <div class="summary-value font-mono">{{ summary?.by_push_status?.success ?? 0 }}</div>
      </div>
      <div class="summary-card danger">
        <div class="summary-label">推送失敗</div>
        <div class="summary-value font-mono">{{ summary?.by_push_status?.failed ?? 0 }}</div>
      </div>
      <div class="summary-card">
        <div class="summary-label">尚未推送</div>
        <div class="summary-value font-mono">{{ summary?.by_push_status?.not_pushed ?? 0 }}</div>
      </div>
    </div>

    <div class="filter-bar">
      <select v-model="filters.status" @change="onFilterChange">
        <option value="">全部狀態</option>
        <option value="draft">草稿</option>
        <option value="submitted">已送出</option>
        <option value="verified">已核實</option>
      </select>
      <select v-model="filters.push_status" @change="onFilterChange">
        <option value="">全部推送狀態</option>
        <option value="not_pushed">尚未推送</option>
        <option value="success">推送成功</option>
        <option value="failed">推送失敗</option>
      </select>
      <input v-model="filters.report_period" type="text" placeholder="填報期間，如 2026-06" @change="onFilterChange" />
      <button class="btn-secondary" @click="resetFilters">清除篩選</button>
    </div>

    <div v-if="loading" class="loading-state">載入中...</div>
    <div v-else-if="!reports.length" class="empty-state">尚無活動資料填報記錄</div>
    <div v-else class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>供應商</th>
            <th>廠區</th>
            <th>填報期間</th>
            <th>活動資料摘要</th>
            <th>狀態</th>
            <th>推送狀態</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in reports" :key="r.id" class="data-row">
            <td>
              <a
                v-if="r.facility?.supplier_id"
                :href="`/suppliers/${r.facility.supplier_id}`"
                target="_blank"
                class="supplier-link"
              >{{ maskSupplierName(r.facility?.supplier?.name) }}</a>
              <span v-else>{{ maskSupplierName(r.facility?.supplier?.name) }}</span>
            </td>
            <td>{{ r.facility?.name ?? '—' }}</td>
            <td class="font-mono">{{ r.report_period }}</td>
            <td class="font-mono activity-brief">{{ activityBrief(r) }}</td>
            <td><span class="status-badge" :class="`status-${r.status}`">{{ statusLabel(r.status) }}</span></td>
            <td><span class="status-badge" :class="`push-${pushStatus(r)}`">{{ pushStatusLabel(pushStatus(r)) }}</span></td>
            <td class="row-actions">
              <button
                v-if="r.status === 'submitted'"
                class="btn-primary btn-sm"
                :disabled="actioning[r.id]"
                @click="verifyReport(r)"
              >{{ actioning[r.id] ? '處理中...' : '核實' }}</button>
              <button
                v-if="pushStatus(r) === 'failed' || (pushStatus(r) === 'not_pushed' && r.status === 'verified')"
                class="btn-secondary btn-sm"
                :disabled="actioning[r.id]"
                @click="pushReport(r)"
              >{{ actioning[r.id] ? '處理中...' : '重新推送' }}</button>
            </td>
          </tr>
        </tbody>
      </table>

      <div class="pagination" v-if="meta.last_page > 1">
        <button class="btn-secondary btn-sm" :disabled="meta.current_page <= 1" @click="goPage(meta.current_page - 1)">上一頁</button>
        <span class="font-mono">{{ meta.current_page }} / {{ meta.last_page }}</span>
        <button class="btn-secondary btn-sm" :disabled="meta.current_page >= meta.last_page" @click="goPage(meta.current_page + 1)">下一頁</button>
      </div>
    </div>
  </div>
</template>

<script>
import { activityReportDashboardApi } from '@/api/modules/suppliers'
import { maskSupplierName } from '@/utils/maskName'

export default {
  name: 'ActivityReportDashboardView',
  data() {
    return {
      reports: [],
      summary: null,
      loading: false,
      actioning: {},
      filters: { status: '', push_status: '', report_period: '', supplier_id: '' },
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
    }
  },
  mounted() {
    this.loadSummary()
    this.loadReports()
  },
  methods: {
    maskSupplierName,
    async loadSummary() {
      try {
        const res = await activityReportDashboardApi.summary()
        this.summary = res.data.data
      } catch (e) { console.error(e) }
    },
    async loadReports(page = 1) {
      this.loading = true
      try {
        const params = { page, per_page: this.meta.per_page }
        if (this.filters.status) params.status = this.filters.status
        if (this.filters.push_status) params.push_status = this.filters.push_status
        if (this.filters.report_period) params.report_period = this.filters.report_period
        if (this.filters.supplier_id) params.supplier_id = this.filters.supplier_id

        const res = await activityReportDashboardApi.list(params)
        this.reports = res.data.data
        this.meta = res.data.meta
      } catch (e) { console.error(e) } finally { this.loading = false }
    },
    onFilterChange() {
      this.loadReports(1)
    },
    resetFilters() {
      this.filters = { status: '', push_status: '', report_period: '', supplier_id: '' }
      this.loadReports(1)
    },
    goPage(page) {
      this.loadReports(page)
    },
    pushStatus(r) {
      if (!r.push_log) return 'not_pushed'
      if (r.push_log.status === 'success') return 'success'
      if (r.push_log.status === 'pending') return 'pending'
      return 'failed'
    },
    activityBrief(r) {
      const parts = []
      if (r.electricity_kwh) parts.push(`電力 ${r.electricity_kwh} kWh`)
      if (r.natural_gas_gj) parts.push(`天然氣 ${r.natural_gas_gj} GJ`)
      if (r.fuel_oil_l) parts.push(`燃油 ${r.fuel_oil_l} L`)
      return parts.length ? parts.join(' / ') : '—'
    },
    statusLabel(s) {
      return { draft: '草稿', submitted: '待核實', verified: '已核實' }[s] ?? s
    },
    pushStatusLabel(s) {
      return { not_pushed: '尚未推送', pending: '推送中…', success: '推送成功', failed: '推送失敗' }[s] ?? s
    },
    async verifyReport(r) {
      this.actioning = { ...this.actioning, [r.id]: true }
      try {
        const res = await activityReportDashboardApi.verify(r.id)
        Object.assign(r, res.data.data)
        await this.loadSummary()
      } catch (e) {
        console.error(e)
      } finally {
        this.actioning = { ...this.actioning, [r.id]: false }
      }
    },
    async pushReport(r) {
      this.actioning = { ...this.actioning, [r.id]: true }
      try {
        await activityReportDashboardApi.push(r.id)
        r.push_log = { status: 'pending' }
        alert('已重新排程推送，處理需要幾秒鐘，稍後將自動更新此列的推送狀態')
        // 推送是非同步流程（Laravel 派工 → esgchain-ai Celery → 回呼寫回），
        // 送出當下後端還沒有最終結果，延遲重抓這筆資料以顯示真實狀態
        setTimeout(() => this.refreshRow(r.id), 4000)
      } catch (e) {
        alert(e?.response?.data?.message ?? '推送請求送出失敗，請稍後再試')
      } finally {
        this.actioning = { ...this.actioning, [r.id]: false }
      }
    },
    async refreshRow(reportId) {
      try {
        const res = await activityReportDashboardApi.list({ page: this.meta.current_page, per_page: this.meta.per_page, ...this.activeFilterParams() })
        const fresh = res.data.data.find(item => item.id === reportId)
        const target = this.reports.find(item => item.id === reportId)
        if (fresh && target) Object.assign(target, fresh)
        await this.loadSummary()
      } catch (e) {
        console.error(e)
      }
    },
    activeFilterParams() {
      const params = {}
      if (this.filters.status) params.status = this.filters.status
      if (this.filters.push_status) params.push_status = this.filters.push_status
      if (this.filters.report_period) params.report_period = this.filters.report_period
      if (this.filters.supplier_id) params.supplier_id = this.filters.supplier_id
      return params
    },
  },
}
</script>

<style scoped>
.activity-report-dashboard { padding: 1.5rem; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem; }
.page-desc { color: var(--text-muted, #888); font-size: 0.875rem; margin-top: 0.25rem; }

.summary-cards { display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.75rem; margin-bottom: 1.25rem; }
.summary-card { padding: 0.9rem 1rem; background: var(--surface-2, #f5f5f5); border-radius: 6px; }
.summary-card.accent { background: var(--accent-soft, #eaf3ef); border: 1px solid var(--accent-soft-border, #cfe3da); }
.summary-card.success { background: #e7f7ee; }
.summary-card.danger { background: #fdecec; }
.summary-label { font-size: 0.8rem; color: var(--text-muted, #888); margin-bottom: 0.3rem; }
.summary-value { font-size: 1.5rem; font-weight: 600; }

.filter-bar { display: flex; gap: 0.6rem; margin-bottom: 1rem; flex-wrap: wrap; }
.filter-bar select, .filter-bar input { padding: 0.45rem 0.65rem; border: 1px solid var(--border, #ddd); border-radius: 4px; font-size: 0.875rem; background: var(--bg, #fff); color: var(--text, #111); }

.data-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.data-table th { padding: 0.6rem 1rem; background: var(--surface, #f5f5f5); border-bottom: 2px solid var(--border, #ddd); text-align: left; white-space: nowrap; }
.data-row td { padding: 0.6rem 1rem; border-bottom: 1px solid var(--border, #eee); }
.activity-brief { font-size: 0.8rem; }

.supplier-link { color: var(--accent, #1a4d3e); text-decoration: none; }
.supplier-link:hover { text-decoration: underline; }

.status-badge { font-size: 0.75rem; padding: 0.15rem 0.5rem; border-radius: 3px; white-space: nowrap; }
.status-draft { background: #f1f1f1; color: #666; }
.status-submitted { background: #fef9c3; color: #a16207; }
.status-verified { background: #dcfce7; color: #15803d; }
.push-not_pushed { background: #f1f1f1; color: #666; }
.push-pending { background: #fef3c7; color: #92400e; }
.push-success { background: #dcfce7; color: #15803d; }
.push-failed { background: #fee2e2; color: #b91c1c; }

.row-actions { display: flex; gap: 0.5rem; }
.btn-primary { padding: 0.5rem 1.25rem; background: var(--accent, #1a4d3e); color: #fff; border: none; border-radius: 4px; cursor: pointer; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-secondary { padding: 0.5rem 1.25rem; border: 1px solid var(--border, #ddd); border-radius: 4px; cursor: pointer; background: transparent; }
.btn-secondary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-sm { padding: 0.3rem 0.7rem; font-size: 0.8rem; }

.pagination { display: flex; align-items: center; gap: 0.75rem; justify-content: flex-end; padding: 0.75rem 1rem; }

.loading-state, .empty-state { padding: 2rem; text-align: center; color: var(--text-muted, #888); }
</style>
