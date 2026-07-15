<template>
  <div class="templates-view">
    <div class="breadcrumb">
      <span class="breadcrumb-parent">永續倡議問卷</span>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current">評核範本</span>
    </div>
    <div class="page-header">
      <div class="page-title-area">
        <h1 class="page-title">問卷範本設計</h1>
        <p class="page-subtitle">建立與管理問卷範本，可從題庫匯入題目</p>
      </div>
      <button class="btn btn-primary" @click="openCreateModal">＋ 新增範本</button>
    </div>

    <!-- Tabs -->
    <div class="tabs-bar">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        class="tab-btn"
        :class="{ active: activeTab === tab.key }"
        @click="switchTab(tab.key)"
      >
        {{ tab.label }}
        <span v-if="tab.count !== null" class="tab-count">{{ tab.count }}</span>
      </button>
    </div>

    <!-- 狀態 -->
    <div v-if="loading" class="loading-mask">載入中…</div>
    <div v-else-if="rows.length === 0" class="empty-state">
      <p>{{ emptyText }}</p>
      <button v-if="activeTab !== 'archived'" class="btn btn-primary" style="margin-top:12px;" @click="openCreateModal">
        新增第一個範本
      </button>
    </div>

    <!-- 卡片列表 -->
    <div v-else class="card-list">
      <div
        v-for="row in rows"
        :key="row.id"
        class="template-card"
        :class="{ 'card-highlighted': highlightId === row.id, 'card-archived': activeTab === 'archived' }"
      >
        <!-- 左側色條 -->
        <div class="card-accent" :style="{ background: templateColor(row.name) }"></div>

        <!-- 主體 -->
        <div class="card-body">
          <div class="card-top">
            <div class="card-meta-row">
              <span class="template-domain-badge" :style="{ background: templateColor(row.name) + '18', color: templateColor(row.name), border: `1px solid ${templateColor(row.name)}40` }">
                {{ templateDomain(row.name) }}
              </span>
              <span class="version-badge font-mono">v{{ row.version }}</span>
              <span class="question-count-badge">
                <span class="font-mono" style="font-weight:700;">{{ row.questions_count ?? 0 }}</span>
                <span style="font-size:11px;margin-left:3px;">題</span>
              </span>
              <span v-if="row.sasb_industry_id && sasbMap[row.sasb_industry_id]" class="sasb-badge">
                {{ sasbMap[row.sasb_industry_id] }}
              </span>
            </div>

            <router-link :to="`/settings/questionnaire-templates/${row.id}`" class="template-name">
              {{ row.name }}
            </router-link>

            <div class="card-date">建立於 {{ formatDate(row.created_at) }}</div>
          </div>

          <!-- 操作列 -->
          <div class="card-actions">
            <template v-if="activeTab !== 'archived'">
              <router-link :to="`/settings/questionnaire-templates/${row.id}`" class="btn btn-secondary btn-sm">
                編輯題目
              </router-link>
              <button
                class="btn btn-secondary btn-sm"
                :disabled="cloningId === row.id"
                @click="cloneTemplate(row)"
              >
                {{ cloningId === row.id ? '複製中…' : '複製' }}
              </button>
              <div style="flex:1;"></div>
              <button
                class="btn btn-sm"
                :class="row.is_active ? 'btn-warn-outline' : 'btn-success-outline'"
                @click="toggleActive(row)"
              >
                {{ row.is_active ? '停用' : '啟用' }}
              </button>
              <button class="btn btn-sm btn-danger-outline" @click="confirmArchive(row)">封存</button>
            </template>
            <template v-else>
              <button class="btn btn-sm btn-success-outline" @click="doUnarchive(row)">取消封存</button>
            </template>
          </div>
        </div>
      </div>
    </div>

    <!-- 分頁 -->
    <div v-if="pagination.last_page > 1" class="pagination">
      <span>第 {{ pagination.current_page }} / {{ pagination.last_page }} 頁 · 共 {{ pagination.total }} 筆</span>
      <button class="pg-btn" :disabled="pagination.current_page <= 1" @click="changePage(pagination.current_page - 1)">‹</button>
      <button class="pg-btn" :disabled="pagination.current_page >= pagination.last_page" @click="changePage(pagination.current_page + 1)">›</button>
    </div>

    <!-- 新增範本 Modal -->
    <div v-if="showCreateModal" class="modal-overlay" @click.self="closeCreateModal">
      <div class="modal">
        <div class="modal-header">
          <span class="modal-title">新增問卷範本</span>
          <button class="modal-close" @click="closeCreateModal">×</button>
        </div>
        <form @submit.prevent="submitCreate">
          <div class="form-group">
            <label class="form-label">範本名稱 <span style="color:#dc2626;">*</span></label>
            <input v-model="form.name" class="form-input" placeholder="例：2025 Q2 ESG 評估" required />
          </div>
          <div class="form-group">
            <label class="form-label">計分框架 <span style="color:#dc2626;">*</span></label>
            <select v-model="form.scoring_framework" class="form-input" required>
              <option value="">請選擇計分框架</option>
              <option value="ESG">ESG — 環境 / 社會 / 治理</option>
              <option value="ISO20400">ISO 20400 — 永續採購</option>
              <option value="ISO26000">ISO 26000 — 社會責任</option>
              <option value="Geo-Risk">Geo-Risk — 地緣風險</option>
              <option value="Product-Compliance">Product-Compliance — 產品合規</option>
            </select>
            <p style="font-size:11px;color:var(--text-secondary);margin-top:4px;">決定此範本的計分維度，建立後不可修改</p>
          </div>
          <div class="form-group">
            <label class="form-label">版本號</label>
            <input v-model="form.version" class="form-input font-mono" placeholder="1.0.0" />
          </div>
          <div class="form-group">
            <label class="form-label">描述（選填）</label>
            <textarea v-model="form.description" class="form-textarea" rows="3" placeholder="說明此範本的用途或適用對象…" />
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="closeCreateModal">取消</button>
            <button type="submit" class="btn btn-primary" :disabled="submitting">
              {{ submitting ? '建立中…' : '建立範本' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- 封存確認 Modal -->
    <div v-if="archiveTarget" class="modal-overlay" @click.self="archiveTarget = null">
      <div class="modal" style="max-width:420px;">
        <div class="modal-header">
          <span class="modal-title">確認封存範本</span>
          <button class="modal-close" @click="archiveTarget = null">×</button>
        </div>
        <p style="color:var(--text-secondary);font-size:14px;margin-bottom:8px;">封存後此範本將無法編輯，問卷發送時不會出現。</p>
        <p style="font-weight:600;font-size:15px;margin-bottom:20px;">「{{ archiveTarget.name }}」</p>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="archiveTarget = null">取消</button>
          <button class="btn btn-danger" :disabled="archiving" @click="doArchive">
            {{ archiving ? '封存中…' : '確認封存' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { settingsApi, type QuestionnaireTemplate } from '@/api/modules/settings'

type TabKey = 'active' | 'inactive' | 'archived'

const DOMAIN_META: { match: string; label: string; color: string }[] = [
  { match: 'ISO 20400', label: 'ISO 20400', color: '#1e40af' },
  { match: 'ISO 26000', label: 'ISO 26000', color: '#6d28d9' },
  { match: 'ISO 28000', label: 'ISO 28000', color: '#374151' },
  { match: 'GPR',       label: 'Geo-Risk',  color: '#b45309' },
  { match: '全維度',    label: '全維度',    color: '#1a4d3e' },
]

export default defineComponent({
  name: 'QuestionnaireTemplatesView',
  data() {
    return {
      activeTab: 'active' as TabKey,
      rows: [] as QuestionnaireTemplate[],
      pagination: { current_page: 1, last_page: 1, total: 0, per_page: 20 },
      counts: { active: null as number | null, inactive: null as number | null, archived: null as number | null },
      loading: false,
      sasbMap: {} as Record<string, string>,

      showCreateModal: false,
      form: { name: '', version: '1.0.0', description: '', scoring_framework: '' },
      submitting: false,

      archiveTarget: null as QuestionnaireTemplate | null,
      archiving: false,

      cloningId: null as string | null,
      highlightId: null as string | null,
    }
  },
  computed: {
    tabs(): { key: TabKey; label: string; count: number | null }[] {
      return [
        { key: 'active',   label: '啟用中', count: this.counts.active },
        { key: 'inactive', label: '停用',   count: this.counts.inactive },
        { key: 'archived', label: '已封存', count: this.counts.archived },
      ]
    },
    emptyText(): string {
      const map: Record<TabKey, string> = {
        active:   '目前沒有啟用中的範本',
        inactive: '目前沒有停用的範本',
        archived: '目前沒有已封存的範本',
      }
      return map[this.activeTab]
    },
  },
  mounted() {
    this.loadSasb()
    this.loadRows()
  },
  methods: {
    templateColor(name: string): string {
      const match = DOMAIN_META.find(d => name.includes(d.match))
      return match ? match.color : '#64748b'
    },
    templateDomain(name: string): string {
      const match = DOMAIN_META.find(d => name.includes(d.match))
      return match ? match.label : '自訂'
    },

    async loadSasb() {
      try {
        const res = await settingsApi.sasb.list()
        const map: Record<string, string> = {}
        ;(res.data.data as any[]).forEach((s: any) => { map[s.id] = s.industry })
        this.sasbMap = map
      } catch {}
    },

    async loadRows(page = 1) {
      this.loading = true
      try {
        const params: Record<string, any> = { page, per_page: 20 }
        if (this.activeTab === 'archived') {
          params.is_archived = true
        } else {
          params.is_active = this.activeTab === 'active'
        }
        const res = await settingsApi.templates.list(params)
        this.rows = res.data.data
        this.pagination = res.data.pagination
        this.counts[this.activeTab] = res.data.pagination.total
      } finally {
        this.loading = false
      }
    },

    switchTab(tab: TabKey) { this.activeTab = tab; this.loadRows(1) },
    changePage(page: number) { this.loadRows(page) },

    openCreateModal() {
      this.form = { name: '', version: '1.0.0', description: '', scoring_framework: '' }
      this.showCreateModal = true
    },
    closeCreateModal() { this.showCreateModal = false },

    async submitCreate() {
      if (!this.form.name.trim()) return
      this.submitting = true
      try {
        const res = await settingsApi.templates.create({
          name: this.form.name.trim(),
          version: this.form.version.trim() || '1.0.0',
          description: this.form.description.trim() || undefined,
          scoring_framework: this.form.scoring_framework || undefined,
        })
        this.closeCreateModal()
        this.$router.push(`/settings/questionnaire-templates/${res.data.data.id}`)
      } catch {
        alert('建立失敗，請稍後再試')
      } finally {
        this.submitting = false
      }
    },

    async toggleActive(row: QuestionnaireTemplate) {
      try {
        await settingsApi.templates.update(row.id, { is_active: !row.is_active })
        await this.loadRows(this.pagination.current_page)
      } catch { alert('更新失敗') }
    },

    confirmArchive(row: QuestionnaireTemplate) { this.archiveTarget = row },

    async doArchive() {
      if (!this.archiveTarget) return
      this.archiving = true
      try {
        await settingsApi.templates.archive(this.archiveTarget.id)
        this.archiveTarget = null
        await this.loadRows(1)
      } catch { alert('封存失敗') } finally { this.archiving = false }
    },

    async doUnarchive(row: QuestionnaireTemplate) {
      try {
        await settingsApi.templates.unarchive(row.id)
        await this.loadRows(1)
      } catch { alert('取消封存失敗') }
    },

    async cloneTemplate(row: QuestionnaireTemplate) {
      this.cloningId = row.id
      try {
        await settingsApi.templates.clone(row.id)
        this.activeTab = 'inactive'
        const res = await settingsApi.templates.list({ is_active: false, per_page: 20 })
        this.rows = res.data.data
        this.pagination = res.data.pagination
        this.counts.inactive = res.data.pagination.total
        if (this.rows.length > 0) {
          this.highlightId = this.rows[0].id
          setTimeout(() => { this.highlightId = null }, 3000)
        }
      } catch { alert('複製失敗') } finally { this.cloningId = null }
    },

    formatDate(iso: string) { return iso ? iso.slice(0, 10) : '—' },
  },
})
</script>

<style scoped>
.templates-view { padding: 32px; max-width: 1100px; }

/* Tabs */
.tabs-bar { display: flex; gap: 4px; border-bottom: 2px solid var(--border); margin-bottom: 24px; }
.tab-btn { background: none; border: none; padding: 8px 16px; font-size: 14px; color: var(--text-secondary); cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; display: flex; align-items: center; gap: 6px; transition: color 0.15s; }
.tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); font-weight: 600; }
.tab-count { background: var(--surface-2); border-radius: 10px; padding: 1px 7px; font-size: 12px; font-family: 'Fira Code', monospace; color: var(--text-secondary); }

/* Card list */
.card-list { display: flex; flex-direction: column; gap: 12px; }

.template-card {
  display: flex;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
  transition: box-shadow 0.15s, border-color 0.15s;
}
.template-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,0.07); border-color: #c7c3bb; }
.card-highlighted { border-color: var(--accent) !important; background: #f0fdf4 !important; }
.card-archived { opacity: 0.65; }

.card-accent { width: 5px; flex-shrink: 0; }

.card-body { flex: 1; padding: 16px 20px; display: flex; flex-direction: column; gap: 10px; min-width: 0; }

.card-top { display: flex; flex-direction: column; gap: 6px; }

.card-meta-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

.template-domain-badge {
  font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px;
  letter-spacing: 0.03em; white-space: nowrap;
}
.version-badge {
  font-size: 11px; color: var(--text-secondary); background: var(--surface-2);
  border: 1px solid var(--border); border-radius: 4px; padding: 1px 7px;
}
.question-count-badge {
  font-size: 13px; color: var(--text-primary); background: var(--surface-2);
  border-radius: 6px; padding: 2px 9px; display: inline-flex; align-items: center;
}
.sasb-badge {
  font-size: 11px; color: #0369a1; background: #e0f2fe; border-radius: 4px; padding: 2px 8px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px;
}

.template-name {
  font-size: 16px; font-weight: 600; color: var(--text-primary); text-decoration: none;
  line-height: 1.3;
}
.template-name:hover { color: var(--accent); }

.card-date { font-size: 12px; color: var(--text-secondary); }

.card-actions {
  display: flex; align-items: center; gap: 6px; padding-top: 8px;
  border-top: 1px solid var(--border);
}

/* Outline button variants */
.btn-warn-outline    { color: #d97706; border: 1px solid #d97706; background: none; }
.btn-warn-outline:hover { background: #fffbeb; }
.btn-success-outline { color: #16a34a; border: 1px solid #16a34a; background: none; }
.btn-success-outline:hover { background: #f0fdf4; }
.btn-danger-outline  { color: #dc2626; border: 1px solid #dc2626; background: none; }
.btn-danger-outline:hover { background: #fef2f2; }

.font-mono { font-family: 'Fira Code', monospace; }
</style>
