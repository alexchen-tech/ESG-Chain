<template>
  <div class="pcf-requests-view">
    <div class="page-header">
      <h1 class="page-title">碳排請求管理</h1>
      <button class="btn-primary" @click="openSendModal" :disabled="sendModal.submitting">
        發送碳排請求
      </button>
    </div>

    <!-- 篩選列 -->
    <div class="filter-bar">
      <select v-model="filters.status" @change="resetAndLoad" class="filter-select">
        <option value="">全部狀態</option>
        <option value="pending">待申報</option>
        <option value="submitted">已提交</option>
        <option value="verified">已驗證</option>
        <option value="overdue">已逾期</option>
      </select>
      <input
        v-model="filters.period_year"
        type="number"
        placeholder="申報年度"
        class="filter-input"
        @keyup.enter="resetAndLoad"
        style="width:120px"
      />
      <button class="btn-ghost" @click="resetAndLoad">搜尋</button>
    </div>

    <!-- 列表 -->
    <div class="data-table-wrapper">
      <div v-if="loading" class="loading-state">載入中…</div>
      <div v-else-if="requests.length === 0" class="empty-state">
        <p>尚無碳排請求</p>
        <p class="empty-hint">點擊「發送碳排請求」向供應商發送申報邀請</p>
      </div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>供應商</th>
            <th style="white-space:nowrap;">申報週期</th>
            <th style="white-space:nowrap;">截止日</th>
            <th>進度</th>
            <th>狀態</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="req in requests" :key="req.id">
            <td>{{ req.supplier_name ? maskSupplierName(req.supplier_name) : '-' }}</td>
            <td class="font-mono" style="white-space:nowrap;">{{ req.period_start }} ~ {{ req.period_end }}</td>
            <td class="font-mono" style="white-space:nowrap;">{{ req.due_date }}</td>
            <td>
              <div class="progress-bar-wrap">
                <div
                  class="progress-bar-fill"
                  :style="{ width: progressPct(req) + '%' }"
                  :class="progressClass(req)"
                ></div>
              </div>
              <span class="progress-label font-mono">
                {{ req.progress.submitted }}/{{ req.progress.total }}
              </span>
            </td>
            <td>
              <span class="status-badge" :class="'status-' + req.status">
                {{ statusLabel(req.status) }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 分頁 -->
    <div class="pagination">
      <span>第 {{ pagination.current_page }} / {{ pagination.last_page }} 頁</span>
      <button class="pg-btn" :disabled="pagination.current_page <= 1" @click="goPage(pagination.current_page - 1)">‹</button>
      <button class="pg-btn" :disabled="pagination.current_page >= pagination.last_page" @click="goPage(pagination.current_page + 1)">›</button>
    </div>

    <!-- 發送請求 Modal -->
    <div v-if="sendModal.open" class="modal-overlay" @click.self="closeSendModal">
      <div class="modal-panel" style="width:600px">
        <div class="modal-header">
          <h2>發送碳排請求</h2>
          <button class="modal-close" @click="closeSendModal">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">申報週期起始</label>
            <input v-model="sendModal.form.period_start" type="date" class="form-input" />
          </div>
          <div class="form-group">
            <label class="form-label">申報週期結束</label>
            <input v-model="sendModal.form.period_end" type="date" class="form-input" />
          </div>
          <div class="form-group">
            <label class="form-label">截止日</label>
            <input v-model="sendModal.form.due_date" type="date" class="form-input" />
          </div>
          <div class="form-group">
            <label class="form-label">供應商 ID</label>
            <input v-model="sendModal.form.supplier_id" type="text" class="form-input" placeholder="輸入供應商 UUID" />
          </div>
          <div class="form-group">
            <label class="form-label">BomLine IDs（每行一個 UUID）</label>
            <textarea
              v-model="sendModal.form.bom_line_ids_text"
              class="form-input"
              rows="4"
              placeholder="每行一個 BomLine UUID"
            ></textarea>
          </div>
          <div v-if="sendModal.result" class="send-result">
            <p>✓ 已建立 {{ sendModal.result.created }} 筆請求</p>
            <p v-if="sendModal.result.skipped?.length">略過 {{ sendModal.result.skipped.length }} 筆（重複）</p>
            <p v-if="sendModal.result.errors?.length" class="error-text">{{ sendModal.result.errors.join('、') }}</p>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-ghost" @click="closeSendModal">取消</button>
          <button class="btn-primary" @click="submitSend" :disabled="sendModal.submitting">
            {{ sendModal.submitting ? '送出中…' : '確認發送' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { pcfRequestApi, type PcfRequestSummary } from '@/api/modules/pcf'
import { maskSupplierName } from '@/utils/maskName'

export default defineComponent({
  name: 'PcfRequestsView',
  data() {
    return {
      loading: false,
      requests: [] as PcfRequestSummary[],
      pagination: { current_page: 1, per_page: 20, total: 0, last_page: 1 },
      filters: {
        status: '',
        period_year: '',
      },
      sendModal: {
        open: false,
        submitting: false,
        result: null as any,
        form: {
          supplier_id: '',
          period_start: '',
          period_end: '',
          due_date: '',
          bom_line_ids_text: '',
        },
      },
    }
  },
  mounted() {
    this.loadData()
  },
  methods: {
    maskSupplierName,
    async loadData() {
      this.loading = true
      try {
        const params: any = {
          page: this.pagination.current_page,
          per_page: this.pagination.per_page,
        }
        if (this.filters.status) params.status = this.filters.status
        if (this.filters.period_year) params.period_year = this.filters.period_year
        const res = await pcfRequestApi.list(params)
        this.requests = res.data.data || []
        this.pagination = res.data.pagination
      } catch {
        this.requests = []
      } finally {
        this.loading = false
      }
    },

    goPage(page: number) { this.pagination.current_page = page; this.loadData() },
    resetAndLoad() { this.pagination.current_page = 1; this.loadData() },
    progressPct(req: PcfRequestSummary) {
      if (!req.progress.total) return 0
      return Math.round((req.progress.submitted / req.progress.total) * 100)
    },
    progressClass(req: PcfRequestSummary) {
      const pct = this.progressPct(req)
      if (pct === 100) return 'fill-done'
      if (pct > 0) return 'fill-partial'
      return 'fill-empty'
    },
    statusLabel(status: string) {
      const map: Record<string, string> = {
        pending: '待申報',
        submitted: '已提交',
        verified: '已驗證',
        overdue: '已逾期',
      }
      return map[status] || status
    },
    openSendModal() {
      this.sendModal.open = true
      this.sendModal.result = null
    },
    closeSendModal() {
      this.sendModal.open = false
      this.sendModal.result = null
      this.sendModal.form = {
        supplier_id: '',
        period_start: '',
        period_end: '',
        due_date: '',
        bom_line_ids_text: '',
      }
    },
    async submitSend() {
      const f = this.sendModal.form
      if (!f.supplier_id || !f.period_start || !f.period_end || !f.due_date) return

      const bomLineIds = f.bom_line_ids_text
        .split('\n')
        .map((s) => s.trim())
        .filter(Boolean)

      if (!bomLineIds.length) return

      this.sendModal.submitting = true
      try {
        const res = await pcfRequestApi.batchCreate([
          {
            supplier_id: f.supplier_id,
            bom_line_ids: bomLineIds,
            period_start: f.period_start,
            period_end: f.period_end,
            due_date: f.due_date,
          },
        ])
        this.sendModal.result = res.data.data
        await this.loadData()
      } catch {
        this.sendModal.result = { created: 0, skipped: [], errors: ['送出失敗，請確認欄位'] }
      } finally {
        this.sendModal.submitting = false
      }
    },
  },
})
</script>

<style scoped>
.pcf-requests-view { padding: 24px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-title { font-size: 20px; font-weight: 600; color: var(--text-primary); }

.filter-bar { display: flex; gap: 8px; margin-bottom: 16px; align-items: center; }
.filter-select, .filter-input {
  padding: 6px 10px;
  border: 1px solid var(--border);
  border-radius: 6px;
  font-size: 13px;
  background: var(--surface);
  color: var(--text-primary);
}

.data-table-wrapper { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { padding: 10px 14px; text-align: left; font-size: 12px; font-weight: 600; color: var(--text-secondary); background: var(--surface-2); border-bottom: 1px solid var(--border); }
.data-table td { padding: 12px 14px; font-size: 13px; color: var(--text-primary); border-bottom: 1px solid var(--border); }
.data-table tr:last-child td { border-bottom: none; }

.progress-bar-wrap { width: 100px; height: 6px; background: var(--border); border-radius: 3px; display: inline-block; vertical-align: middle; margin-right: 6px; }
.progress-bar-fill { height: 100%; border-radius: 3px; transition: width 0.3s; }
.fill-done { background: #16a34a; }
.fill-partial { background: var(--accent); }
.fill-empty { background: var(--border); }
.progress-label { font-size: 12px; color: var(--text-secondary); }

.status-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
.status-pending { background: #fef9c3; color: #854d0e; }
.status-submitted { background: #dcfce7; color: #166534; }
.status-verified { background: #d1fae5; color: #065f46; }
.status-overdue { background: #fee2e2; color: #991b1b; }

.loading-state, .empty-state { padding: 40px; text-align: center; color: var(--text-secondary); }
.empty-hint { font-size: 12px; margin-top: 4px; }

.btn-primary { padding: 8px 16px; background: var(--accent); color: #fff; border: none; border-radius: 6px; font-size: 13px; cursor: pointer; font-weight: 500; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-ghost { padding: 8px 16px; background: transparent; color: var(--text-secondary); border: 1px solid var(--border); border-radius: 6px; font-size: 13px; cursor: pointer; }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal-panel { background: var(--surface); border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.15); display: flex; flex-direction: column; max-height: 90vh; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 18px 24px; border-bottom: 1px solid var(--border); }
.modal-header h2 { font-size: 16px; font-weight: 600; margin: 0; }
.modal-close { background: none; border: none; font-size: 18px; cursor: pointer; color: var(--text-secondary); }
.modal-body { padding: 20px 24px; overflow-y: auto; }
.modal-footer { padding: 14px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 8px; }

.form-group { margin-bottom: 14px; }
.form-label { display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 4px; }
.form-input { width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; background: var(--surface); color: var(--text-primary); box-sizing: border-box; }

.send-result { background: var(--surface-2); border-radius: 6px; padding: 10px 14px; margin-top: 12px; font-size: 13px; }
.error-text { color: #dc2626; }
.font-mono { font-family: 'Fira Code', monospace; }
</style>
