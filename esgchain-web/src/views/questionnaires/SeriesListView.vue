<template>
  <div class="page-container">
    <div class="breadcrumb">
      <span class="breadcrumb-parent">永續倡議問卷</span>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current">評核系列</span>
    </div>

    <div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;">
      <div>
        <h1 class="page-title">評核系列</h1>
        <p class="page-subtitle">將相關問卷專案組織為系列，追蹤供應商跨期 ESG 改善趨勢</p>
      </div>
      <button class="btn btn-primary" style="white-space:nowrap;flex-shrink:0;" @click="openCreateModal">＋ 建立系列</button>
    </div>

    <!-- 篩選列 -->
    <div v-if="series.length" class="filter-bar">
      <button
        v-for="f in ['all','active','archived']" :key="f"
        class="filter-tab" :class="{ active: statusFilter === f }"
        @click="statusFilter = f"
      >
        {{ { all: '全部', active: '進行中', archived: '已封存' }[f] }}
        <span class="filter-count">{{ filterCount(f) }}</span>
      </button>
    </div>

    <div v-if="isLoading" class="loading-mask">載入中...</div>

    <div v-else-if="!series.length" class="empty-state">
      <div class="empty-icon">📋</div>
      <p class="empty-title">尚未建立任何評核系列</p>
      <p class="empty-desc">將問卷專案組成系列，可追蹤供應商跨年度的 ESG 改善軌跡</p>
      <button class="btn btn-primary" @click="openCreateModal">建立第一個系列</button>
    </div>

    <div v-else class="series-list">
      <div
        v-for="s in filteredSeries" :key="s.id"
        class="series-card" :class="{ archived: s.status === 'archived' }"
        @click="router.push(`/questionnaires/series/${s.id}`)"
      >
        <div class="series-card__accent" :style="{ background: domainColor(s.template?.scoring_framework) }"></div>
        <div class="series-card__body">
          <div class="series-card__top">
            <div class="series-card__info">
              <div class="series-card__name">{{ s.name }}</div>
              <div v-if="s.description" class="series-card__desc">{{ s.description }}</div>
            </div>
            <div class="series-card__badges">
              <span class="badge" :style="domainBadgeStyle(s.template?.scoring_framework)">{{ s.template?.scoring_framework ?? '通用' }}</span>
              <span :class="['badge', s.status === 'active' ? 'badge-green' : 'badge-gray']">
                {{ s.status === 'active' ? '進行中' : '已封存' }}
              </span>
            </div>
          </div>
          <div class="series-card__meta">
            <span class="meta-item">
              <span class="meta-icon">📁</span>
              <span class="font-mono">{{ s.projects_count ?? 0 }}</span> 個專案
            </span>
            <span class="meta-sep">·</span>
            <span class="meta-item">
              最近專案：<span class="font-mono">{{ s.latest_project_date ?? '—' }}</span>
            </span>
            <span class="meta-actions" @click.stop>
              <button
                v-if="s.status === 'active'"
                class="btn-link btn-link--danger"
                :disabled="isSubmitting"
                @click="doArchive(s)"
              >封存</button>
            </span>
          </div>
        </div>
      </div>

      <div v-if="!filteredSeries.length" class="empty-filter">
        此篩選條件下無結果
      </div>
    </div>

    <!-- 建立 Modal -->
    <div v-if="showCreateModal" class="modal-overlay" @click.self="showCreateModal = false">
      <div class="modal" style="min-width:440px;">
        <div class="modal-header">
          <span class="modal-title">建立評核系列</span>
          <button class="modal-close" @click="showCreateModal = false">×</button>
        </div>
        <div class="form-group">
          <label class="form-label">系列名稱 <span style="color:#ef4444">*</span></label>
          <input v-model="createForm.name" class="form-input" placeholder="例：2025 年度 ESG 評核" />
        </div>
        <div class="form-group">
          <label class="form-label">評核範本 <span style="color:#ef4444">*</span></label>
          <select v-model="createForm.template_id" class="form-select" :disabled="templatesLoading">
            <option value="">{{ templatesLoading ? '載入中...' : '— 請選擇範本 —' }}</option>
            <option v-for="t in templates" :key="t.id" :value="t.id">
              {{ t.name }} <template v-if="t.scoring_framework">（{{ t.scoring_framework }}）</template>
            </option>
          </select>
          <p class="form-hint">範本決定系列的評核框架，建立後不可更換</p>
        </div>
        <div class="form-group">
          <label class="form-label">說明</label>
          <textarea v-model="createForm.description" class="form-input" rows="2" placeholder="選填：說明此系列的目的或範圍" />
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showCreateModal = false">取消</button>
          <button class="btn btn-primary" :disabled="isSubmitting || !createForm.name.trim() || !createForm.template_id" @click="doCreate">
            {{ isSubmitting ? '建立中...' : '建立系列' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { useRouter } from 'vue-router'
import { assessmentSeriesApi, type AssessmentSeries } from '@/api/modules/saq'
import http from '@/api/http'

const DOMAIN_COLORS: Record<string, string> = {
  ESG:                '#1a4d3e',
  ISO20400:           '#1e40af',
  ISO26000:           '#6d28d9',
  'Geo-Risk':         '#b45309',
  'Product-Compliance': '#0f766e',
}

export default defineComponent({
  name: 'SeriesListView',
  setup() { return { router: useRouter() } },

  data() {
    return {
      isLoading: false,
      isSubmitting: false,
      templatesLoading: false,
      series: [] as AssessmentSeries[],
      templates: [] as Array<{ id: string; name: string; scoring_framework: string | null }>,
      statusFilter: 'all' as 'all' | 'active' | 'archived',
      showCreateModal: false,
      createForm: { name: '', description: '', template_id: '' },
    }
  },

  computed: {
    filteredSeries(): AssessmentSeries[] {
      if (this.statusFilter === 'all') return this.series
      return this.series.filter(s => s.status === (this.statusFilter === 'active' ? 'active' : 'archived'))
    },
  },

  mounted() {
    this.loadSeries()
    this.loadTemplates()
  },

  methods: {
    async loadSeries() {
      this.isLoading = true
      try {
        const { data } = await assessmentSeriesApi.list()
        this.series = data.data
      } finally { this.isLoading = false }
    },

    async loadTemplates() {
      this.templatesLoading = true
      try {
        const { data } = await http.get<{ success: boolean; data: any[] }>('/api/v1/questionnaire-templates?per_page=100')
        this.templates = (data.data ?? []).map((t: any) => ({
          id: t.id,
          name: t.name,
          scoring_framework: t.scoring_framework ?? null,
        }))
      } finally { this.templatesLoading = false }
    },

    filterCount(f: string): number {
      if (f === 'all') return this.series.length
      return this.series.filter(s => s.status === (f === 'active' ? 'active' : 'archived')).length
    },

    domainColor(domain: string | null): string {
      return DOMAIN_COLORS[domain ?? ''] ?? '#94a3b8'
    },

    domainBadgeStyle(domain: string | null): Record<string, string> {
      const color = DOMAIN_COLORS[domain ?? ''] ?? '#64748b'
      return {
        background: color + '18',
        color,
        border: `1px solid ${color}40`,
      }
    },

    openCreateModal() {
      this.createForm = { name: '', description: '', template_id: '' }
      this.showCreateModal = true
    },

    async doCreate() {
      if (!this.createForm.name.trim() || !this.createForm.template_id) return
      this.isSubmitting = true
      try {
        await assessmentSeriesApi.create({
          name: this.createForm.name,
          template_id: this.createForm.template_id,
          description: this.createForm.description || null,
        })
        this.showCreateModal = false
        await this.loadSeries()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '建立失敗')
      } finally { this.isSubmitting = false }
    },

    async doArchive(s: AssessmentSeries) {
      if (!confirm(`確定封存「${s.name}」？封存後無法加入新專案。`)) return
      this.isSubmitting = true
      try {
        await assessmentSeriesApi.archive(s.id)
        await this.loadSeries()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '封存失敗')
      } finally { this.isSubmitting = false }
    },
  },
})
</script>

<style scoped>
/* 篩選 Tab */
.filter-bar {
  display: flex;
  gap: 4px;
  margin-bottom: 20px;
}
.filter-tab {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 20px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--text-secondary);
  font-size: 0.85rem;
  cursor: pointer;
  transition: all 0.15s;
}
.filter-tab:hover { border-color: var(--accent); color: var(--accent); }
.filter-tab.active { background: var(--accent); color: #fff; border-color: var(--accent); }
.filter-count {
  font-size: 0.78rem;
  font-variant-numeric: tabular-nums;
  background: rgba(255,255,255,0.25);
  border-radius: 10px;
  padding: 0 6px;
  min-width: 18px;
  text-align: center;
}
.filter-tab:not(.active) .filter-count { background: var(--surface-2); color: var(--text-secondary); }

/* 系列卡片列表 */
.series-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.series-card {
  display: flex;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: var(--surface);
  cursor: pointer;
  transition: box-shadow 0.15s, border-color 0.15s;
  overflow: hidden;
}
.series-card:hover {
  border-color: var(--accent);
  box-shadow: 0 2px 12px rgba(26,77,62,0.08);
}
.series-card.archived {
  opacity: 0.6;
}
.series-card.archived:hover {
  border-color: var(--border);
  box-shadow: none;
}

/* 左側 Domain 色條 */
.series-card__accent {
  width: 4px;
  flex-shrink: 0;
}

/* 主體 */
.series-card__body {
  flex: 1;
  padding: 16px 20px;
  min-width: 0;
}

.series-card__top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 10px;
}

.series-card__info { min-width: 0; }

.series-card__name {
  font-weight: 600;
  font-size: 0.975rem;
  color: var(--text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.series-card__desc {
  font-size: 0.82rem;
  color: var(--text-secondary);
  margin-top: 3px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.series-card__badges {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}

/* Meta row */
.series-card__meta {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.82rem;
  color: var(--text-secondary);
}
.meta-item { display: flex; align-items: center; gap: 4px; }
.meta-icon { font-size: 0.85rem; }
.meta-sep { color: var(--border); }
.meta-actions { margin-left: auto; }

.btn-link {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 0.82rem;
  padding: 2px 8px;
  border-radius: 4px;
  transition: background 0.15s;
}
.btn-link--danger { color: #ef4444; }
.btn-link--danger:hover { background: #fef2f2; }
.btn-link:disabled { opacity: 0.4; cursor: not-allowed; }

/* Empty states */
.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: var(--text-secondary);
}
.empty-icon { font-size: 2.5rem; margin-bottom: 12px; }
.empty-title { font-size: 1rem; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; }
.empty-desc { font-size: 0.85rem; margin-bottom: 20px; max-width: 360px; margin-left: auto; margin-right: auto; }

.form-hint {
  font-size: 0.78rem;
  color: var(--text-secondary);
  margin-top: 4px;
}

.empty-filter {
  text-align: center;
  padding: 32px;
  color: var(--text-secondary);
  font-size: 0.875rem;
}
</style>
