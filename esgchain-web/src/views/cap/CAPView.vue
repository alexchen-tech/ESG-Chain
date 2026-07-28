<template>
  <div class="page-container">
    <div class="breadcrumb">
      <span class="breadcrumb-parent">風險稽核</span>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current">CAP 矯正</span>
    </div>
    <div class="page-header">
      <div>
        <h1 class="page-title">矯正行動計畫</h1>
        <p class="page-subtitle">
          共 {{ pagination.total }} 筆 CAP
          <span v-if="overdueCount > 0" class="overdue-badge">逾期 {{ overdueCount }} 件</span>
        </p>
      </div>
      <button class="btn btn-primary" @click="openCreateModal">＋ 新增 CAP</button>
    </div>

    <!-- Filter bar -->
    <div class="filter-bar">
      <div class="filter-chips">
        <button
          v-for="opt in STATUS_FILTERS"
          :key="opt.value"
          class="filter-chip"
          :class="[{ active: filterStatus === opt.value }, opt.cls]"
          @click="setFilter(opt.value)"
        >{{ opt.label }}</button>
      </div>
      <input v-model="searchText" class="search-input" placeholder="搜尋供應商名稱…" @input="onSearch" />
    </div>

    <!-- Table -->
    <div class="card table-card">
      <div v-if="isLoading" class="loading-state">
        <div class="loading-spinner"></div>載入中…
      </div>
      <div v-else-if="visibleCaps.length === 0" class="empty-state">
        <div class="empty-icon">📋</div>
        <p>尚無符合條件的 CAP 記錄</p>
      </div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th class="col-num">#</th>
            <th>標題</th>
            <th style="width:180px;">供應商</th>
            <th style="width:90px;text-align:center;">來源</th>
            <th style="width:70px;text-align:center;">優先級</th>
            <th style="width:90px;text-align:center;">狀態</th>
            <th style="width:100px;white-space:nowrap;">到期日</th>
            <th style="width:88px;text-align:center;">操作</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="(cap, i) in visibleCaps" :key="cap.id">
          <tr
            :id="`cap-row-${cap.id}`"
            class="data-row"
            :class="{ 'row-overdue': cap.status === 'overdue', 'row-done': cap.status === 'completed' || cap.status === 'closed', 'row-highlight': cap.id === highlightId }"
          >
            <td class="col-num font-mono">{{ (pagination.current_page - 1) * pagination.per_page + i + 1 }}</td>
            <td>
              <div class="title-line">
                <span v-if="parsedTitle(cap.title).docType" class="doc-type-chip" :class="docTypeChipClass(parsedTitle(cap.title).docType)">
                  {{ parsedTitle(cap.title).docType }}
                </span>
                <span v-if="cap.auto_generated" class="auto-badge">自動生成</span>
                <span v-if="cap.triggered_by_axis" class="axis-badge" :class="axisBadgeClass(cap.triggered_by_axis)">
                  {{ axisLabel(cap.triggered_by_axis) }}
                </span>
              </div>
              <div class="cap-desc">{{ parsedTitle(cap.title).description }}</div>
              <div v-if="cap.description" class="cap-desc" style="color:var(--text-secondary);font-size:12px;margin-top:2px;">{{ cap.description }}</div>
            </td>
            <td class="supplier-cell">{{ supplierName(cap) }}</td>
            <td style="text-align:center;">
              <span class="badge" :class="sourceBadgeClass(cap.source_type)">{{ sourceLabel(cap.source_type) }}</span>
            </td>
            <td style="text-align:center;">
              <span class="badge" :class="priorityBadgeClass(cap.priority)">{{ priorityLabel(cap.priority) }}</span>
            </td>
            <td style="text-align:center;">
              <span class="badge" :class="statusBadgeClass(cap.status)">{{ statusLabel(cap.status) }}</span>
            </td>
            <td style="white-space:nowrap;">
              <span class="font-mono due-date" :class="{ 'due-overdue': isOverdue(cap.due_date) && cap.status !== 'completed' && cap.status !== 'closed' }">
                {{ formatDate(cap.due_date) }}
              </span>
            </td>
            <td style="text-align:center;">
              <div style="display:flex;gap:6px;justify-content:center;align-items:center;">
                <button class="btn-update" @click="openUpdateModal(cap)" :disabled="cap.status === 'completed' || cap.status === 'closed'">
                  更新狀態
                </button>
                <button class="btn-history" @click="toggleHistory(cap)" :title="'歷程與佐證文件'">
                  📎<span v-if="cap.attachments_count">{{ cap.attachments_count }}</span>
                </button>
              </div>
            </td>
          </tr>
          <!-- findings 展開列 -->
          <tr v-if="cap.findings && cap.findings.length" class="findings-row">
            <td colspan="8">
              <div class="findings-list">
                <div v-for="f in cap.findings" :key="f.id" class="finding-item">
                  <div class="finding-header">
                    <span v-if="f.framework" class="framework-chip" :class="frameworkChipClass(f.framework)">
                      {{ f.topic_label_zh || f.topic_slug || f.framework }}
                    </span>
                    <span v-else class="category-chip cat-{{ f.category }}">{{ f.category }}</span>
                    <span v-if="f.source_score != null" class="score-info font-mono">
                      {{ f.source_score }}/100
                      <span class="score-bar-wrap"><span class="score-bar" :style="{ width: f.source_score + '%', background: scoreBarColor(f.source_score) }"></span></span>
                    </span>
                  </div>
                  <div class="finding-text">{{ f.finding }}</div>
                </div>
              </div>
            </td>
          </tr>
          <!-- 歷程與佐證文件展開列 -->
          <tr v-if="expandedHistoryId === cap.id" class="history-row">
            <td colspan="8">
              <div v-if="historyLoading" class="text-muted" style="font-size:12px;padding:8px 0;">載入中…</div>
              <div v-else-if="!cap.updates || cap.updates.length === 0" class="text-muted" style="font-size:12px;padding:8px 0;">
                尚無歷程紀錄
              </div>
              <div v-else class="history-list">
                <div v-for="u in cap.updates" :key="u.id" class="history-item">
                  <div class="history-item-head">
                    <span class="badge" :class="statusBadgeClass(u.status)">{{ statusLabel(u.status) }}</span>
                    <span class="history-meta">{{ u.changed_by?.name ?? '—' }} · {{ formatDateTime(u.created_at) }}</span>
                  </div>
                  <div v-if="u.notes" class="history-notes">{{ u.notes }}</div>
                  <div v-if="u.attachments && u.attachments.length" class="history-attachments">
                    <button
                      v-for="att in u.attachments"
                      :key="att.id"
                      class="attachment-chip"
                      @click="downloadAttachment(att)"
                    >📄 {{ att.file_name }}</button>
                  </div>
                </div>
              </div>
            </td>
          </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="pagination-bar" v-if="pagination.last_page > 1">
      <span class="page-info">第 {{ pagination.current_page }} / {{ pagination.last_page }} 頁</span>
      <button class="pg-btn" :disabled="pagination.current_page <= 1" @click="goPage(pagination.current_page - 1)">‹</button>
      <button class="pg-btn" :disabled="pagination.current_page >= pagination.last_page" @click="goPage(pagination.current_page + 1)">›</button>
    </div>

    <!-- 新增 Modal -->
    <div v-if="showCreateModal" class="modal-overlay" @click.self="showCreateModal = false">
      <div class="modal">
        <div class="modal-header">
          <span class="modal-title">新增矯正行動計畫</span>
          <button class="modal-close" @click="showCreateModal = false">×</button>
        </div>
        <div class="form-group">
          <label class="form-label">標題 <span class="req">*</span></label>
          <input v-model="form.title" class="form-input" placeholder="如：[EUDR_DDS] 合規文件逾期" />
        </div>
        <div class="form-group">
          <label class="form-label">供應商 ID <span class="req">*</span></label>
          <input v-model="form.supplier_id" class="form-input" placeholder="供應商 UUID" />
        </div>
        <div class="form-grid-2">
          <div class="form-group">
            <label class="form-label">優先級</label>
            <select v-model="form.priority" class="form-input">
              <option value="low">低</option>
              <option value="medium">中</option>
              <option value="high">高</option>
              <option value="critical">嚴重</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">到期日</label>
            <input v-model="form.due_date" class="form-input" type="date" />
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">說明</label>
          <textarea v-model="form.description" class="form-input" rows="3" style="resize:vertical;" placeholder="描述需矯正的問題及預期改善行動"></textarea>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showCreateModal = false">取消</button>
          <button class="btn btn-primary" :disabled="isSubmitting || !form.title || !form.supplier_id" @click="createCAP">
            {{ isSubmitting ? '建立中…' : '建立 CAP' }}
          </button>
        </div>
      </div>
    </div>

    <!-- 更新狀態 Modal -->
    <div v-if="showUpdateModal" class="modal-overlay" @click.self="showUpdateModal = false">
      <div class="modal">
        <div class="modal-header">
          <span class="modal-title">更新 CAP 狀態</span>
          <button class="modal-close" @click="showUpdateModal = false">×</button>
        </div>
        <div class="cap-summary">
          <span v-if="parsedTitle(selectedCAP?.title ?? '').docType" class="doc-type-chip" :class="docTypeChipClass(parsedTitle(selectedCAP?.title ?? '').docType)">
            {{ parsedTitle(selectedCAP?.title ?? '').docType }}
          </span>
          <div class="cap-summary-title">{{ parsedTitle(selectedCAP?.title ?? '').description }}</div>
          <div class="cap-summary-supplier">🏭 {{ selectedCAP ? supplierName(selectedCAP) : '' }}</div>
          <div v-if="selectedCAP?.description" class="cap-summary-desc">{{ selectedCAP.description }}</div>
        </div>
        <div class="form-group">
          <label class="form-label">更新為</label>
          <div class="status-option-grid">
            <label
              v-for="opt in UPDATE_STATUS_OPTIONS"
              :key="opt.value"
              class="status-option"
              :class="{ selected: updateForm.status === opt.value }"
            >
              <input type="radio" :value="opt.value" v-model="updateForm.status" hidden />
              <span class="status-option-dot" :class="opt.dotCls"></span>
              {{ opt.label }}
            </label>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">備註（選填）</label>
          <textarea v-model="updateForm.notes" class="form-input" rows="2" style="resize:vertical;" placeholder="說明本次更新的原因或進度…"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">佐證文件（選填，可多選）</label>
          <input ref="fileInput" type="file" multiple class="form-input" @change="onPickFiles" />
          <div v-if="updateForm.files.length" class="picked-files">
            <span v-for="(f, idx) in updateForm.files" :key="idx" class="picked-file-chip">
              {{ f.name }}
              <button type="button" class="picked-file-remove" @click="updateForm.files.splice(idx, 1)">×</button>
            </span>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showUpdateModal = false">取消</button>
          <button class="btn btn-primary" :disabled="isSubmitting" @click="doUpdate">
            {{ isSubmitting ? '更新中…' : '確認更新' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { capApi, type CAP, type CAPAttachment } from '@/api/modules/cap'

const STATUS_FILTERS = [
  { value: '', label: '全部', cls: '' },
  { value: 'open', label: '待處理', cls: 'chip-yellow' },
  { value: 'in_progress', label: '處理中', cls: 'chip-blue' },
  { value: 'overdue', label: '已逾期', cls: 'chip-red' },
  { value: 'completed', label: '已完成', cls: 'chip-green' },
  { value: 'closed', label: '已關閉', cls: 'chip-gray' },
]

const UPDATE_STATUS_OPTIONS = [
  { value: 'open', label: '待處理', dotCls: 'dot-yellow' },
  { value: 'in_progress', label: '處理中', dotCls: 'dot-blue' },
  { value: 'completed', label: '已完成', dotCls: 'dot-green' },
  { value: 'closed', label: '已關閉', dotCls: 'dot-gray' },
]

const DOC_TYPE_COLORS: Record<string, string> = {
  EUDR_DDS: 'chip-eudr',
  UFLPA_DECLARATION: 'chip-uflpa',
  CMRT: 'chip-cmrt',
  SDS: 'chip-sds',
  CE_DOC: 'chip-ce',
  REACH: 'chip-reach',
}

export default defineComponent({
  name: 'CAPView',

  data() {
    return {
      STATUS_FILTERS,
      UPDATE_STATUS_OPTIONS,
      isLoading: false,
      isSubmitting: false,
      caps: [] as CAP[],
      pagination: { current_page: 1, per_page: 20, total: 0, last_page: 1 },
      filterStatus: '',
      searchText: '',
      searchDebounce: null as ReturnType<typeof setTimeout> | null,
      showCreateModal: false,
      showUpdateModal: false,
      selectedCAP: null as CAP | null,
      form: { title: '', supplier_id: '', priority: 'medium', due_date: '', description: '' },
      updateForm: { status: '', notes: '', files: [] as File[] },
      highlightId: '',
      expandedHistoryId: '' as string,
      historyLoading: false,
    }
  },

  computed: {
    overdueCount(): number {
      return this.caps.filter(c => c.status === 'overdue').length
    },
    visibleCaps(): CAP[] {
      if (!this.searchText.trim()) return this.caps
      const kw = this.searchText.trim().toLowerCase()
      return this.caps.filter(c => this.supplierName(c).toLowerCase().includes(kw))
    },
  },

  async mounted() {
    await this.loadData()
    const highlight = this.$route.query.highlight
    if (highlight && typeof highlight === 'string') {
      this.highlightId = highlight
      await this.$nextTick()
      const el = document.getElementById(`cap-row-${highlight}`)
      el?.scrollIntoView({ behavior: 'smooth', block: 'center' })
      // 移除 query 避免重新整理時重複觸發，並在動畫結束後淡出高亮
      this.$router.replace({ query: {} })
      setTimeout(() => { this.highlightId = '' }, 2600)
    }
  },

  methods: {
    async loadData() {
      this.isLoading = true
      try {
        const { data } = await capApi.list({
          status: this.filterStatus || undefined,
          page: this.pagination.current_page,
        })
        this.caps = data.data
        this.pagination = data.pagination
      } finally { this.isLoading = false }
    },

    setFilter(v: string) {
      this.filterStatus = v
      this.pagination.current_page = 1
      this.loadData()
    },

    onSearch() {
      if (this.searchDebounce) clearTimeout(this.searchDebounce)
      this.searchDebounce = setTimeout(() => {
        this.pagination.current_page = 1
        this.loadData()
      }, 350)
    },

    goPage(page: number) { this.pagination.current_page = page; this.loadData() },

    openCreateModal() {
      this.form = { title: '', supplier_id: '', priority: 'medium', due_date: '', description: '' }
      this.showCreateModal = true
    },

    openUpdateModal(cap: CAP) {
      this.selectedCAP = cap
      this.updateForm = { status: cap.status, notes: '', files: [] }
      this.showUpdateModal = true
    },

    onPickFiles(e: Event) {
      const input = e.target as HTMLInputElement
      if (input.files) this.updateForm.files.push(...Array.from(input.files))
      input.value = ''
    },

    async toggleHistory(cap: CAP) {
      if (this.expandedHistoryId === cap.id) {
        this.expandedHistoryId = ''
        return
      }
      this.expandedHistoryId = cap.id
      if (!cap.updates) {
        this.historyLoading = true
        try {
          const { data } = await capApi.get(cap.id)
          cap.updates = data.data.updates
        } finally { this.historyLoading = false }
      }
    },

    formatDateTime(val: string | null | undefined): string {
      if (!val) return '—'
      return val.slice(0, 16).replace('T', ' ')
    },

    async downloadAttachment(att: CAPAttachment) {
      try {
        const res = await capApi.downloadAttachment(att.id)
        const url = URL.createObjectURL(new Blob([res.data as any]))
        const a = document.createElement('a')
        a.href = url
        a.download = att.file_name || 'file'
        a.click()
        URL.revokeObjectURL(url)
      } catch {
        alert('下載失敗')
      }
    },

    parsedTitle(title: string): { docType: string; description: string } {
      const m = title.match(/^(\[[^\]]+\])\s*(.+?)(?:\s*—\s*.+)?$/)
      if (m) return { docType: m[1].replace(/[\[\]]/g, ''), description: m[2] }
      // 沒有 [docType] 前綴的標題（如義務缺口面板建立的「補件：xxx」）：整段當標題本文，不塞進小 chip
      return { docType: '', description: title }
    },

    supplierName(cap: any): string {
      if (cap.supplier?.name) return cap.supplier.name
      const m = cap.title?.match(/—\s*(.+)$/)
      return m ? m[1].trim() : '—'
    },

    isOverdue(date: string | null): boolean {
      if (!date) return false
      return new Date(date) < new Date()
    },

    formatDate(val: string | null | undefined): string {
      if (!val) return '—'
      return val.slice(0, 10)
    },

    docTypeChipClass(docType: string): string {
      return DOC_TYPE_COLORS[docType] ?? 'chip-default'
    },

    sourceLabel(s: string) { return ({ compliance_doc: '合規文件', saq: '問卷', manual: '手動', risk_assessment: '風險評估' })[s] ?? '手動' },
    sourceBadgeClass(s: string) { return ({ compliance_doc: 'badge-orange', saq: 'badge-blue', manual: 'badge-gray', risk_assessment: 'badge-purple' })[s] ?? 'badge-gray' },
    axisLabel(a: string) { return ({ axis1: 'ISO 26000', axis2: 'ISO 20400', axis3: '地緣產業' })[a] ?? a },
    axisBadgeClass(a: string) { return ({ axis1: 'axis-badge-green', axis2: 'axis-badge-blue', axis3: 'axis-badge-orange' })[a] ?? '' },
    frameworkChipClass(fw: string) { return ({ iso26k: 'chip-fw-green', iso20400: 'chip-fw-blue', esg: 'chip-fw-gray' })[fw] ?? 'chip-fw-gray' },
    scoreBarColor(score: number) { return score >= 60 ? '#16a34a' : score >= 40 ? '#d97706' : '#dc2626' },
    statusLabel(s: string) { return ({ open: '待處理', in_progress: '處理中', overdue: '已逾期', completed: '已完成', closed: '已關閉' })[s] ?? s },
    statusBadgeClass(s: string) { return ({ open: 'badge-yellow', in_progress: 'badge-blue', overdue: 'badge-red', completed: 'badge-green', closed: 'badge-gray' })[s] ?? 'badge-gray' },
    priorityLabel(p: string) { return ({ low: '低', medium: '中', high: '高', critical: '嚴重' })[p] ?? p },
    priorityBadgeClass(p: string) { return ({ low: 'badge-gray', medium: 'badge-yellow', high: 'badge-orange', critical: 'badge-red' })[p] ?? 'badge-gray' },

    async createCAP() {
      if (!this.form.title || !this.form.supplier_id) return
      this.isSubmitting = true
      try {
        await capApi.create(this.form)
        this.showCreateModal = false
        this.loadData()
      } finally { this.isSubmitting = false }
    },

    async doUpdate() {
      if (!this.selectedCAP) return
      this.isSubmitting = true
      try {
        const capId = this.selectedCAP.id
        const payload: any = { status: this.updateForm.status }
        if (this.updateForm.notes.trim()) payload.notes = this.updateForm.notes.trim()
        const { data } = await capApi.update(capId, payload)

        // 有選檔案的話，掛在這次剛建立的歷程紀錄底下
        if (this.updateForm.files.length) {
          const updateId = data.data.updates?.[0]?.id
          for (const file of this.updateForm.files) {
            await capApi.uploadAttachment(capId, file, updateId)
          }
        }

        // 已展開歷程的話一併刷新，避免顯示過期資料
        if (this.expandedHistoryId === capId) {
          const fresh = await capApi.get(capId)
          const idx = this.caps.findIndex(c => c.id === capId)
          if (idx !== -1) this.caps[idx].updates = fresh.data.data.updates
        }

        this.showUpdateModal = false
        this.loadData()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '更新失敗')
      } finally { this.isSubmitting = false }
    },
  },
})
</script>

<style scoped>
/* ── Filter Bar ── */
.filter-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}
.filter-chips { display: flex; gap: 6px; flex-wrap: wrap; }
.filter-chip {
  padding: 5px 14px;
  border-radius: 999px;
  font-size: 13px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--text-secondary);
  cursor: pointer;
  transition: all 0.12s;
  white-space: nowrap;
}
.filter-chip:hover { border-color: var(--accent); color: var(--accent); }
.filter-chip.active { background: var(--accent); color: #fff; border-color: var(--accent); font-weight: 600; }
.filter-chip.chip-red.active  { background: #dc2626; border-color: #dc2626; }
.filter-chip.chip-yellow.active { background: #d97706; border-color: #d97706; }
.filter-chip.chip-blue.active { background: #2563eb; border-color: #2563eb; }
.filter-chip.chip-green.active { background: #16a34a; border-color: #16a34a; }
.filter-chip.chip-gray.active { background: #6b7280; border-color: #6b7280; }
.search-input {
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 6px 12px;
  font-size: 13px;
  background: var(--surface);
  color: var(--text-primary);
  min-width: 200px;
}
.search-input:focus { outline: none; border-color: var(--accent); }

/* ── Overdue badge in subtitle ── */
.overdue-badge {
  display: inline-flex;
  align-items: center;
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fca5a5;
  border-radius: 999px;
  padding: 1px 9px;
  font-size: 12px;
  font-weight: 600;
  margin-left: 8px;
}

/* ── Table Card ── */
.table-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
  padding: 0;
}

.loading-state {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 64px;
  color: var(--text-secondary);
  font-size: 14px;
}
.loading-spinner {
  width: 18px;
  height: 18px;
  border: 2px solid var(--border);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.empty-state {
  padding: 64px 32px;
  text-align: center;
  color: var(--text-secondary);
  font-size: 14px;
}
.empty-icon { font-size: 32px; margin-bottom: 10px; }

/* ── Data Table ── */
.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.data-table th {
  background: var(--surface-2);
  padding: 10px 12px;
  text-align: left;
  font-size: 11px;
  font-weight: 700;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  border-bottom: 1px solid var(--border);
}
.col-num { width: 44px; text-align: center; color: var(--text-secondary); font-size: 12px; }
.data-table td { padding: 12px 12px; border-bottom: 1px solid var(--border); vertical-align: middle; }
.data-row:last-child td { border-bottom: none; }
.data-row { transition: background 0.1s; }
.data-row:hover { background: var(--surface-2); }
.data-row.row-overdue { background: #fff8f8; }
.data-row.row-overdue:hover { background: #fef2f2; }
.data-row.row-done { opacity: 0.65; }
.data-row.row-highlight { animation: cap-row-flash 2.6s ease-out; }
@keyframes cap-row-flash {
  0%   { background: #dcf1dc; }
  70%  { background: #dcf1dc; }
  100% { background: transparent; }
}

/* ── Doc type chip ── */
.doc-type-chip {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-family: 'Fira Code', monospace;
  font-weight: 700;
  letter-spacing: 0.02em;
  white-space: nowrap;
}
.chip-eudr   { background: #dbeafe; color: #1d4ed8; }
.chip-uflpa  { background: #ede9fe; color: #6d28d9; }
.chip-cmrt   { background: #fef3c7; color: #92400e; }
.chip-sds    { background: #dcfce7; color: #15803d; }
.chip-ce     { background: #f0fdf4; color: #166534; }
.chip-reach  { background: #fce7f3; color: #9d174d; }
.chip-default { background: var(--surface-2); color: var(--text-secondary); border: 1px solid var(--border); }

.title-line { display: flex; align-items: center; gap: 8px; margin-bottom: 3px; }
.cap-desc { font-size: 13px; font-weight: 500; color: var(--text-primary); padding-left: 2px; }
.supplier-cell { font-size: 13px; color: var(--text-primary); font-weight: 500; }

/* ── Due date ── */
.due-date { font-size: 12px; color: var(--text-secondary); }
.due-overdue { color: #dc2626; font-weight: 600; }

/* ── Badges ── */
.badge {
  display: inline-block;
  padding: 3px 9px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
  white-space: nowrap;
}
.badge-red    { background: #fee2e2; color: #dc2626; }
.badge-orange { background: #fff7ed; color: #ea580c; }
.badge-yellow { background: #fef9c3; color: #854d0e; }
.badge-blue   { background: #eff6ff; color: #2563eb; }
.badge-green  { background: #dcfce7; color: #16a34a; }
.badge-gray   { background: var(--surface-2); color: var(--text-secondary); border: 1px solid var(--border); }
.badge-purple { background: #f5f3ff; color: #7c3aed; }

/* ── Auto-generated & axis badges ── */
.auto-badge {
  font-size: 0.65rem; padding: 1px 6px; border-radius: 9999px;
  background: #fef9c3; color: #854d0e; font-weight: 600; margin-left: 4px;
}
.axis-badge {
  font-size: 0.65rem; padding: 1px 6px; border-radius: 9999px;
  font-weight: 600; margin-left: 4px;
}
.axis-badge-green  { background: #dcfce7; color: #15803d; }
.axis-badge-blue   { background: #eff6ff; color: #1d4ed8; }
.axis-badge-orange { background: #fff7ed; color: #c2410c; }

/* ── Findings ── */
.findings-row td { padding: 0 0 8px 48px; background: var(--surface-1); }
.findings-list { display: flex; flex-direction: column; gap: 6px; padding: 8px 0; }
.finding-item { background: var(--surface-2); border-radius: 6px; padding: 8px 12px; }
.finding-header { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
.framework-chip { font-size: 0.65rem; padding: 2px 8px; border-radius: 9999px; font-weight: 600; }
.chip-fw-green  { background: #dcfce7; color: #15803d; }
.chip-fw-blue   { background: #eff6ff; color: #1d4ed8; }
.chip-fw-gray   { background: var(--surface-3,#e5e7eb); color: #374151; }
.category-chip  { font-size: 0.65rem; padding: 2px 8px; border-radius: 9999px; font-weight: 700; background: #f3f4f6; color: #374151; }
.score-info { display: flex; align-items: center; gap: 6px; font-size: 0.75rem; color: var(--text-secondary); }
.score-bar-wrap { width: 60px; height: 5px; background: #e5e7eb; border-radius: 9999px; overflow: hidden; display: inline-block; vertical-align: middle; }
.score-bar { height: 100%; border-radius: 9999px; transition: width 0.3s; }
.finding-text { font-size: 0.8rem; color: var(--text-primary); }

/* ── Update button ── */
.btn-update {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 5px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--text-primary);
  cursor: pointer;
  transition: all 0.12s;
  white-space: nowrap;
}
.btn-update:hover:not(:disabled) { border-color: var(--accent); color: var(--accent); }
.btn-update:disabled { opacity: 0.4; cursor: not-allowed; }

.btn-history {
  display: inline-flex; align-items: center; gap: 3px;
  padding: 5px 8px; border-radius: 6px; font-size: 12px;
  border: 1px solid var(--border); background: var(--surface);
  color: var(--text-secondary); cursor: pointer; transition: all 0.12s;
}
.btn-history:hover { border-color: var(--accent); color: var(--accent); }
.text-muted { color: var(--text-secondary); }

/* ── 歷程展開列 ── */
.history-row td { padding: 12px 20px; background: var(--surface-2); }
.history-list { display: flex; flex-direction: column; gap: 10px; }
.history-item { border-left: 2px solid var(--border); padding-left: 12px; }
.history-item-head { display: flex; align-items: center; gap: 8px; }
.history-meta { font-size: 11px; color: var(--text-secondary); }
.history-notes { font-size: 12px; color: var(--text-primary); margin-top: 4px; }
.history-attachments { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
.attachment-chip {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 11px; padding: 3px 9px; border-radius: 999px;
  background: var(--surface); border: 1px solid var(--border);
  color: var(--text-primary); cursor: pointer;
}
.attachment-chip:hover { border-color: var(--accent); color: var(--accent); }

/* ── 更新 Modal 檔案選取 ── */
.picked-files { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.picked-file-chip {
  display: inline-flex; align-items: center; gap: 6px;
  font-size: 12px; padding: 3px 8px 3px 10px; border-radius: 999px;
  background: var(--surface-2); border: 1px solid var(--border);
}
.picked-file-remove {
  background: none; border: none; cursor: pointer;
  color: var(--text-secondary); font-size: 14px; line-height: 1; padding: 0;
}
.picked-file-remove:hover { color: #dc2626; }

/* ── Pagination ── */
.pagination-bar {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 16px;
  font-size: 13px;
  color: var(--text-secondary);
}
.pg-btn {
  width: 30px;
  height: 30px;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--text-primary);
  font-size: 16px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.12s;
}
.pg-btn:hover:not(:disabled) { border-color: var(--accent); color: var(--accent); }
.pg-btn:disabled { opacity: 0.4; cursor: not-allowed; }

/* ── Modal ── */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.3);
  z-index: 300;
  display: flex;
  align-items: center;
  justify-content: center;
}
.modal {
  background: var(--surface);
  border-radius: 12px;
  width: 480px;
  max-width: 96vw;
  box-shadow: 0 8px 40px rgba(0,0,0,.16);
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 4px;
  border-bottom: 1px solid var(--border);
}
.modal-title { font-size: 15px; font-weight: 700; color: var(--text-primary); }
.modal-close {
  background: none;
  border: none;
  font-size: 22px;
  line-height: 1;
  cursor: pointer;
  color: var(--text-secondary);
  padding: 2px 4px;
  border-radius: 4px;
}
.modal-close:hover { background: var(--border); }
.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding-top: 4px;
  border-top: 1px solid var(--border);
  margin-top: 4px;
}

/* ── CAP summary in update modal ── */
.cap-summary {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-left: 3px solid var(--accent);
  border-radius: 8px;
  padding: 14px 16px;
}
.cap-summary-title {
  font-size: 17px;
  font-weight: 700;
  color: var(--text-primary);
  line-height: 1.4;
  margin-top: 6px;
}
.cap-summary-supplier { font-size: 13px; color: var(--text-secondary); margin-top: 6px; }
.cap-summary-desc { font-size: 13px; color: var(--text-secondary); line-height: 1.6; margin-top: 8px; white-space: pre-wrap; }

/* ── Status option grid ── */
.status-option-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}
.status-option {
  display: flex;
  align-items: center;
  gap: 8px;
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 10px 14px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;
  color: var(--text-primary);
  transition: all 0.12s;
  user-select: none;
}
.status-option:hover { border-color: var(--accent); }
.status-option.selected { border-color: var(--accent); background: #f0fdf4; font-weight: 600; }
.status-option-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}
.dot-yellow { background: #d97706; }
.dot-blue   { background: #2563eb; }
.dot-green  { background: #16a34a; }
.dot-gray   { background: #9ca3af; }

/* ── Form ── */
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-label { font-size: 12px; font-weight: 600; color: var(--text-secondary); }
.req { color: #dc2626; }
.form-input {
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 8px 10px;
  font-size: 13px;
  background: var(--surface);
  color: var(--text-primary);
  font-family: inherit;
}
.form-input:focus { outline: none; border-color: var(--accent); }
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

/* ── Buttons ── */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  border: none;
  padding: 8px 16px;
  transition: opacity 0.15s;
}
.btn:disabled { opacity: .5; cursor: not-allowed; }
.btn-primary { background: var(--accent); color: #fff; }
.btn-primary:hover:not(:disabled) { opacity: .85; }
.btn-secondary {
  background: var(--surface-2);
  color: var(--text-primary);
  border: 1px solid var(--border);
}
.btn-secondary:hover:not(:disabled) { background: var(--border); }

.font-mono { font-family: 'Fira Code', monospace; }
</style>
