<template>
  <div class="page-container">
    <div class="breadcrumb">
      <span class="breadcrumb-parent">永續倡議問卷</span>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current">審核管理</span>
    </div>
    <div class="page-header">
      <div>
        <h1 class="page-title">問卷審核</h1>
        <p class="page-subtitle">依專案審核供應商 SAQ 問卷</p>
      </div>
    </div>

    <!-- KPI -->
    <div class="kpi-row">
      <div class="kpi-card" :class="{ 'kpi-card--active': filterStatus==='submitted' && !filterOverdue }" @click="setFilter('submitted')">
        <div class="kpi-label">待審隊列</div>
        <div class="kpi-value font-mono" style="color:#2563eb;">{{ counts.just_submitted_count }}</div>
        <div class="kpi-sub">已提交，待開始審核</div>
      </div>
      <div class="kpi-card" :class="{ 'kpi-card--active': filterStatus==='under_review' && !filterOverdue }" @click="setFilter('under_review')">
        <div class="kpi-label">審核中</div>
        <div class="kpi-value font-mono" style="color:#7c3aed;">{{ counts.under_review_count ?? 0 }}</div>
        <div class="kpi-sub">已開始審核，未完成</div>
      </div>
      <div class="kpi-card kpi-card--warn" :class="{ 'kpi-card--active': filterOverdue }" @click="setFilter('overdue')">
        <div class="kpi-label">逾期未提交</div>
        <div class="kpi-value font-mono" style="color:#dc2626;">{{ counts.overdue_count ?? 0 }}</div>
        <div class="kpi-sub">截止日已過，尚未提交</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">累計提交</div>
        <div class="kpi-value font-mono">{{ counts.submitted_count }}</div>
        <div class="kpi-sub">歷史總量</div>
      </div>
    </div>

    <!-- 篩選 -->
    <div class="filter-bar">
      <select v-model="filterProjectId" class="filter-select" style="width:220px;" @change="resetAndLoad">
        <option value="">所有專案</option>
        <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <select v-model="filterStatus" class="filter-select" @change="filterOverdue=false;resetAndLoad()">
        <option value="">所有狀態</option>
        <option v-for="s in STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
      </select>
      <select v-model="filterSupplierId" class="filter-select" style="width:200px;" @change="resetAndLoad">
        <option value="">所有供應商</option>
        <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
      </select>
    </div>

    <!-- 表格 -->
    <div class="table-container">
      <div v-if="isLoading" class="loading-mask">載入中...</div>
      <div v-else-if="questionnaires.length === 0" class="empty-state"><p>尚無問卷記錄</p></div>
      <table v-else class="data-table" style="table-layout:fixed;min-width:860px;width:100%;">
        <colgroup>
          <col style="width:40px;">
          <col style="width:180px;">
          <col>
          <col style="width:84px;">
          <col style="width:96px;">
          <col style="width:96px;">
          <col style="width:110px;">
          <col style="width:140px;">
        </colgroup>
        <thead>
          <tr><th>#</th><th>供應商</th><th>所屬專案</th><th>狀態</th><th>截止日</th><th>提交日</th><th>分數／等級</th><th>操作</th></tr>
        </thead>
        <tbody>
          <tr
            v-for="(q, i) in questionnaires"
            :key="q.id"
            :class="{ 'row-overdue': isOverdue(q) }"
          >
            <td class="num">{{ (pagination.current_page - 1) * pagination.per_page + i + 1 }}</td>
            <td class="cell-ellipsis cell-name" :title="q.supplier?.name">
              {{ q.supplier?.name ?? q.supplier_id.slice(0,8) }}
            </td>
            <td class="cell-ellipsis cell-project" :title="(q as any).project?.name">{{ (q as any).project?.name ?? '—' }}</td>
            <td>
              <div class="status-cell">
                <span class="badge" :class="statusBadgeClass(q.status)">{{ statusLabel(q.status) }}</span>
                <span v-if="isOverdue(q)" class="overdue-tag">逾期</span>
              </div>
            </td>
            <td class="cell-date" :style="isOverdue(q) ? 'color:#dc2626;' : ''">
              {{ ((q as any).project?.due_date ?? q.deadline)?.slice(0, 10) ?? '—' }}
            </td>
            <td class="cell-date">{{ q.submitted_at ? q.submitted_at.slice(0, 10) : '—' }}</td>
            <td class="num cell-score">
              <template v-if="q.score != null">
                <span class="score-val">{{ q.score.toFixed(1) }}</span>
                <span class="badge badge-blue">{{ q.grade }}</span>
              </template>
              <span v-else-if="q.status === 'submitted' || q.status === 'under_review'" class="pending-score">計分中</span>
              <span v-else class="no-data">—</span>
            </td>
            <td>
              <div class="row-actions">
                <router-link :to="`/questionnaires/review/${q.id}`" class="action-link">查看</router-link>
                <button v-if="q.status === 'submitted'" class="btn-review" :disabled="isSubmitting" @click="doAction(q, 'startReview')">開始審核</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="pagination">
      <span>第 {{ pagination.current_page }} / {{ pagination.last_page }} 頁 · 共 {{ pagination.total }} 筆</span>
      <button class="pg-btn" :disabled="pagination.current_page <= 1" @click="goPage(pagination.current_page - 1)">‹</button>
      <button class="pg-btn" :disabled="pagination.current_page >= pagination.last_page" @click="goPage(pagination.current_page + 1)">›</button>
    </div>

  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { questionnaireApi, type Questionnaire, type QuestionnaireCounts } from '@/api/modules/questionnaire'
import { saqProjectApi, type SaqProject } from '@/api/modules/saq'
import { suppliersApi, type Supplier } from '@/api/modules/suppliers'
import { SAQ_STATUS_LABEL, SAQ_STATUS_BADGE } from '@/utils/saqStatus'

const STATUSES = [
  { value: 'sent',            label: '待填寫' },
  { value: 'in_progress',     label: '填寫中' },
  { value: 'submitted',       label: '待審核' },
  { value: 'under_review',    label: '審核中' },
  { value: 'review_returned', label: '已退回' },
  { value: 'completed',       label: '審核完成' },
  { value: 'reviewed',        label: '已複核' },
]

export default defineComponent({
  name: 'QuestionnaireView',

  data() {
    return {
      STATUSES,
      isLoading: false,
      isSubmitting: false,
      questionnaires: [] as Questionnaire[],
      suppliers: [] as Supplier[],
      projects: [] as SaqProject[],
      pagination: { current_page: 1, per_page: 20, total: 0, last_page: 1 },
      counts: { just_submitted_count: 0, under_review_count: 0, overdue_count: 0, submitted_count: 0 } as QuestionnaireCounts,
      filterStatus: 'submitted',
      filterOverdue: false,
      filterSupplierId: '',
      filterProjectId: '',
    }
  },

  mounted() {
    this.loadData()
    this.loadSuppliers()
    this.loadCounts()
    this.loadProjects()
  },

  methods: {
    setFilter(type: 'submitted' | 'under_review' | 'overdue') {
      if (type === 'overdue') {
        this.filterOverdue = true
        this.filterStatus = ''
      } else {
        this.filterOverdue = false
        this.filterStatus = type
      }
      this.pagination.current_page = 1
      this.loadData()
    },
    resetAndLoad() { this.pagination.current_page = 1; this.loadData() },
    async loadData() {
      this.isLoading = true
      try {
        const params: any = { page: this.pagination.current_page, per_page: 20 }
        if (this.filterOverdue) {
          params.overdue = 1
        } else if (this.filterStatus) {
          params.status = this.filterStatus
        }
        if (this.filterSupplierId) params.supplier_id = this.filterSupplierId
        if (this.filterProjectId) params.project_id = this.filterProjectId
        const { data } = await questionnaireApi.list(params)
        this.questionnaires = data.data
        this.pagination = data.pagination
      } finally { this.isLoading = false }
    },
    async loadCounts() {
      try {
        const { data } = await questionnaireApi.counts()
        this.counts = data.data
      } catch { /* ignore */ }
    },
    async loadSuppliers() {
      try {
        const { data } = await suppliersApi.list({ per_page: 100 })
        this.suppliers = data.data
      } catch { /* ignore */ }
    },
    async loadProjects() {
      try {
        const { data } = await saqProjectApi.list({ per_page: 100 })
        this.projects = data.data
      } catch { /* ignore */ }
    },
    goPage(page: number) { this.pagination.current_page = page; this.loadData() },
    statusLabel: (s: string) => SAQ_STATUS_LABEL[s] ?? s,
    statusBadgeClass: (s: string) => SAQ_STATUS_BADGE[s] ?? 'badge-gray',
    isOverdue(q: Questionnaire): boolean {
      const dueDate = (q as any).project?.due_date ?? q.deadline
      if (!dueDate) return false
      if (!['sent', 'in_progress'].includes(q.status)) return false
      return new Date(dueDate) < new Date(new Date().toDateString())
    },
    async doAction(q: Questionnaire, action: string) {
      this.isSubmitting = true
      try {
        if (action === 'startReview') await questionnaireApi.startReview(q.id)
        this.loadData(); this.loadCounts()
      } finally { this.isSubmitting = false }
    },
  },
})
</script>

<style scoped>
.kpi-row {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}
.kpi-card {
  flex: 1;
  min-width: 140px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 14px 18px;
  cursor: pointer;
  transition: border-color 0.15s;
}
.kpi-card:hover { border-color: var(--accent); }
.kpi-card--active { border-color: var(--accent); background: #1a4d3e08; }
.kpi-card--warn { border-left: 3px solid #dc2626; }
.kpi-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); }
.kpi-value { font-size: 26px; font-weight: 700; color: var(--text-primary); line-height: 1.2; margin: 4px 0 2px; }
.kpi-sub { font-size: 11px; color: var(--text-secondary); }

.row-overdue td { background: #fff5f5 !important; }
.overdue-tag {
  display: inline-block;
  font-size: 10px;
  background: #fee2e2;
  color: #991b1b;
  border-radius: 4px;
  padding: 1px 5px;
  margin-left: 4px;
  font-weight: 600;
}

/* cell helpers */
.cell-ellipsis { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cell-name { font-size: 13px; font-weight: 500; }
.cell-project { font-size: 12px; color: var(--text-secondary); }
.cell-date { font-size: 12px; font-family: var(--font-mono); white-space: nowrap; }
.status-cell { display: flex; align-items: center; flex-wrap: nowrap; gap: 0; }

.cell-score { display: flex; align-items: center; gap: 4px; justify-content: flex-start; }
.score-val { font-size: 13px; font-weight: 600; font-variant-numeric: tabular-nums; }
.pending-score { font-size: 11px; color: var(--text-secondary); }
.no-data { color: var(--text-secondary); }

/* action buttons */
.row-actions { display: flex; align-items: center; gap: 5px; }
.action-link {
  font-size: 12px;
  color: var(--text-secondary);
  text-decoration: none;
  padding: 3px 8px;
  border-radius: 5px;
  border: 1px solid var(--border);
  background: var(--surface);
  white-space: nowrap;
  transition: all 0.12s;
  line-height: 1.5;
}
.action-link:hover { border-color: var(--accent); color: var(--accent); background: #1a4d3e08; }
.btn-review {
  font-size: 12px;
  font-weight: 600;
  color: #fff;
  background: var(--accent);
  border: none;
  border-radius: 5px;
  padding: 3px 10px;
  cursor: pointer;
  white-space: nowrap;
  transition: opacity 0.12s;
  line-height: 1.5;
}
.btn-review:hover:not(:disabled) { opacity: 0.85; }
.btn-review:disabled { opacity: 0.45; cursor: not-allowed; }
</style>
