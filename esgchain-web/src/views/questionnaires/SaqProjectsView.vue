<template>
  <div class="page-container">
    <div class="breadcrumb">
      <span class="breadcrumb-parent">永續倡議問卷</span>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current">評核專案</span>
    </div>
    <div class="page-header">
      <div>
        <h1 class="page-title">問卷專案</h1>
        <p class="page-subtitle">管理問卷發送專案與供應商回覆進度</p>
      </div>
      <button class="btn btn-primary" @click="openCreateModal">＋ 建立問卷專案</button>
    </div>

    <!-- 狀態 Tab -->
    <div class="tab-bar">
      <button
        v-for="tab in TABS"
        :key="tab.value"
        class="tab-btn"
        :class="{ active: activeTab === tab.value }"
        @click="activeTab = tab.value"
      >
        {{ tab.label }}
        <span class="tab-count">{{ countByStatus(tab.value) }}</span>
      </button>
    </div>

    <!-- 列表 -->
    <div class="card" style="padding:0;">
      <div v-if="isLoading" class="loading-mask">載入中...</div>
      <div v-else-if="filteredProjects.length === 0" class="empty-state" style="padding:48px;">
        <p>{{ activeTab === 'all' ? '尚無問卷專案' : '此狀態下沒有專案' }}</p>
      </div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th style="min-width:220px;">專案名稱</th>
            <th style="width:100px;">評核框架</th>
            <th style="width:180px;">評核系列</th>
            <th style="width:180px;">問卷範本</th>
            <th style="width:90px;">狀態</th>
            <th style="width:110px;white-space:nowrap;">截止日期</th>
            <th style="width:110px;white-space:nowrap;">建立日期</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="p in filteredProjects"
            :key="p.id"
            class="clickable-row"
            @click="router.push(`/questionnaires/projects/${p.id}`)"
          >
            <td style="padding-left:0;">
              <div class="project-row-inner">
                <div class="project-accent-bar" :style="{ background: domainColor(p.template?.scoring_framework) }"></div>
                <div>
                  <div style="font-weight:500;display:flex;align-items:center;gap:6px;">
                    {{ p.name }}
                    <span v-if="p.is_comparable === false" style="font-size:11px;font-weight:600;background:#fef3c7;color:#b45309;padding:1px 6px;border-radius:4px;flex-shrink:0;">⚠ 升版</span>
                  </div>
                  <div v-if="p.description" style="font-size:12px;color:var(--text-secondary);margin-top:2px;">{{ truncate(p.description, 60) }}</div>
                </div>
              </div>
            </td>
            <td>
              <span v-if="p.template?.scoring_framework" class="fw-badge" :style="domainBadgeStyle(p.template.scoring_framework)">{{ p.template.scoring_framework }}</span>
              <span v-else style="color:var(--text-secondary);">—</span>
            </td>
            <td style="font-size:13px;">
              <router-link v-if="p.series" :to="`/questionnaires/series/${p.series.id}`" class="series-link" @click.stop>{{ p.series.name }}</router-link>
              <span v-else style="color:var(--text-secondary);">—</span>
            </td>
            <td style="font-size:13px;color:var(--text-secondary);">{{ p.template?.name || '—' }}</td>
            <td><span class="badge" :class="statusBadge(p.status)">{{ statusLabel(p.status) }}</span></td>
            <td class="font-mono" style="font-size:12px;white-space:nowrap;">{{ p.due_date?.slice(0, 10) || '—' }}</td>
            <td class="font-mono" style="font-size:12px;white-space:nowrap;">{{ p.created_at.slice(0, 10) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 建立專案 Modal -->
    <div v-if="showCreateModal" class="modal-overlay" @click.self="showCreateModal = false">
      <div class="modal" style="min-width:460px;">
        <div class="modal-header">
          <span class="modal-title">建立問卷專案</span>
          <button class="modal-close" @click="showCreateModal = false">×</button>
        </div>

        <div class="form-group">
          <label class="form-label">專案名稱 *</label>
          <input v-model="createForm.name" class="form-input" placeholder="2025 Q1 ESG 問卷" />
        </div>
        <div class="form-group">
          <label class="form-label">歸屬系列 *</label>
          <select v-model="createForm.series_id" class="form-select">
            <option value="">— 選擇評核系列 —</option>
            <option v-for="s in seriesList.filter(s => s.status === 'active')" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
          <p style="font-size:12px;color:var(--text-secondary);margin-top:4px;">
            建立後繼承此系列的範本與 weight 設定
          </p>
        </div>

        <!-- 繼承自系列的範本與框架（唯讀顯示） -->
        <div v-if="selectedSeriesInfo" style="background:var(--surface-2);border-radius:6px;padding:10px 14px;margin-bottom:12px;font-size:13px;">
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <span style="color:var(--text-secondary);">繼承範本：</span>
            <strong style="color:var(--text-primary);">{{ selectedSeriesInfo.template?.name ?? '—' }}</strong>
            <span v-if="selectedSeriesInfo.template?.scoring_framework"
              class="badge"
              :style="domainBadgeStyle(selectedSeriesInfo.template.scoring_framework)">
              {{ selectedSeriesInfo.template.scoring_framework }}
            </span>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">供應商群組（選填）</label>
          <select v-model="createForm.supplier_group_id" class="form-select">
            <option value="">— 不指定群組 —</option>
            <option v-for="g in supplierGroups" :key="g.id" :value="g.id">{{ g.name }}</option>
          </select>
        </div>

        <!-- 合規推薦提示 -->
        <div v-if="createForm.supplier_group_id" style="margin-bottom:12px;">
          <div v-if="inferredLoading" style="font-size:12px;color:var(--text-secondary);padding:6px 0;">推導中...</div>
          <div v-else-if="inferredDomains.length" class="compliance-hint compliance-hint--found">
            此群組涉及合規範疇：
            <span v-for="d in inferredDomains" :key="d" class="domain-badge">{{ d }}</span>
          </div>
          <div v-else class="compliance-hint compliance-hint--empty">
            此群組尚無物料記錄，無合規推薦
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">截止日期（選填）</label>
          <input v-model="createForm.due_date" type="date" class="form-input" />
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showCreateModal = false">取消</button>
          <button class="btn btn-primary" :disabled="isSubmitting || !createForm.name || !createForm.series_id" @click="doCreate">
            {{ isSubmitting ? '建立中...' : '建立' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { useRouter } from 'vue-router'
import { saqProjectApi, assessmentSeriesApi, type SaqProject, type AssessmentSeries } from '@/api/modules/saq'
import { supplierGroupsApi, type SupplierGroup } from '@/api/modules/settings'

const TABS = [
  { label: '全部', value: 'all' },
  { label: '草稿', value: 'draft' },
  { label: '進行中', value: 'active' },
  { label: '已結案', value: 'closed' },
]

export default defineComponent({
  name: 'SaqProjectsView',
  setup() { return { router: useRouter(), TABS } },

  data() {
    return {
      isLoading: false,
      isSubmitting: false,
      projects: [] as SaqProject[],
      seriesList: [] as AssessmentSeries[],
      supplierGroups: [] as SupplierGroup[],
      activeTab: 'all' as string,
      showCreateModal: false,
      createForm: { name: '', due_date: '', series_id: '', supplier_group_id: '' },
      inferredDomains: [] as string[],
      inferredLoading: false,
    }
  },

  computed: {
    filteredProjects(): SaqProject[] {
      if (this.activeTab === 'all') return this.projects
      return this.projects.filter(p => p.status === this.activeTab)
    },
    selectedSeriesInfo(): AssessmentSeries | undefined {
      return (this.seriesList as AssessmentSeries[]).find((s: AssessmentSeries) => s.id === this.createForm.series_id)
    },
  },

  mounted() { this.loadProjects(); this.loadSeriesList(); this.loadSupplierGroups() },

  watch: {
    'createForm.supplier_group_id'(id: string) {
      if (id) this.loadInferredDomains(id)
      else this.inferredDomains = []
    },
  },

  methods: {
    async loadProjects() {
      this.isLoading = true
      try {
        const { data } = await saqProjectApi.list()
        this.projects = data.data
      } finally { this.isLoading = false }
    },

    async loadSeriesList() {
      try {
        const { data } = await assessmentSeriesApi.list()
        this.seriesList = data.data as AssessmentSeries[]
      } catch { /**/ }
    },

    async loadSupplierGroups() {
      try {
        const { data } = await supplierGroupsApi.list()
        this.supplierGroups = data.data
      } catch { /**/ }
    },

    async loadInferredDomains(groupId: string) {
      this.inferredLoading = true
      try {
        const { data } = await supplierGroupsApi.inferredMaterialGroups(groupId)
        this.inferredDomains = data.data.compliance_domains
      } catch { this.inferredDomains = [] }
      finally { this.inferredLoading = false }
    },

    domainColor(domain: string | null | undefined): string {
      const COLORS: Record<string, string> = {
        'ESG': '#1a4d3e', 'ISO20400': '#1e40af', 'ISO26000': '#6d28d9',
        'Geo-Risk': '#b45309', 'Product-Compliance': '#0f766e',
      }
      return COLORS[domain ?? ''] ?? '#94a3b8'
    },

    domainBadgeStyle(domain: string | null): Record<string, string> {
      const color = this.domainColor(domain)
      return { background: color + '15', color, border: `1px solid ${color}35`, fontWeight: '600' }
    },

    countByStatus(tab: string): number {
      if (tab === 'all') return this.projects.length
      return this.projects.filter(p => p.status === tab).length
    },

    openCreateModal() {
      this.createForm = { name: '', due_date: '', series_id: '', supplier_group_id: '' }
      this.inferredDomains = []
      this.showCreateModal = true
    },

    async doCreate() {
      if (!this.createForm.name || !this.createForm.series_id) return
      this.isSubmitting = true
      try {
        await saqProjectApi.create({
          name: this.createForm.name,
          series_id: this.createForm.series_id,
          due_date: this.createForm.due_date || null,
        } as any)
        this.showCreateModal = false
        await this.loadProjects()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '建立失敗')
      } finally { this.isSubmitting = false }
    },

    statusLabel(s: string) {
      return ({ draft: '草稿', active: '進行中', closed: '已結案' })[s] ?? s
    },

    statusBadge(s: string) {
      return ({ draft: 'badge-gray', active: 'badge-blue', closed: 'badge-green' })[s] ?? 'badge-gray'
    },

    truncate: (s: string, n: number) => s.length > n ? s.slice(0, n) + '...' : s,
  },
})
</script>

<style scoped>
.tab-bar { display: flex; gap: 2px; margin-bottom: 16px; border-bottom: 1px solid var(--border); }
.tab-btn {
  background: none; border: none; cursor: pointer;
  padding: 8px 16px; font-size: 14px; color: var(--text-secondary);
  border-bottom: 2px solid transparent; margin-bottom: -1px;
}
.tab-btn:hover { color: var(--text-primary); }
.tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); font-weight: 500; }
.tab-count {
  display: inline-flex; align-items: center; justify-content: center;
  background: var(--surface-2); border-radius: 99px;
  font-size: 11px; padding: 0 6px; min-width: 20px; margin-left: 6px;
}
.clickable-row { cursor: pointer; }
.clickable-row:hover td { background: var(--surface-2); }
.project-row-inner { display: flex; align-items: stretch; gap: 0; }
.project-accent-bar { width: 3px; border-radius: 2px; flex-shrink: 0; margin-right: 12px; align-self: stretch; min-height: 32px; }
.fw-badge { font-size: 11px; padding: 2px 8px; border-radius: 4px; white-space: nowrap; }

/* 合規推薦 */
.compliance-hint { font-size: 13px; padding: 8px 12px; border-radius: 6px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.compliance-hint--found { background: #ecfdf5; color: #065f46; border: 1px solid #bbf7d0; }
.compliance-hint--empty { background: var(--surface-2); color: var(--text-secondary); border: 1px solid var(--border); }
.domain-badge { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 4px; background: #d1fae5; color: #065f46; }

/* 題目預覽 */
.question-preview-list { max-height: 180px; overflow-y: auto; border: 1px solid var(--border); border-radius: 6px; }
.question-preview-item { display: flex; align-items: center; justify-content: space-between; padding: 7px 12px; border-bottom: 1px solid var(--border); font-size: 13px; }
.question-preview-item:last-child { border-bottom: none; }
.q-preview-text { flex: 1; color: var(--text-primary); }
.compliance-tag { font-size: 11px; font-weight: 600; color: #b45309; background: #fef3c7; padding: 2px 7px; border-radius: 4px; flex-shrink: 0; margin-left: 8px; }
.series-link { color: var(--accent); text-decoration: none; font-size: 13px; }
.series-link:hover { text-decoration: underline; }
</style>
