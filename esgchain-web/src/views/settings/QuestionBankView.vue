<template>
  <div class="page-container">
    <div class="breadcrumb">
      <button class="breadcrumb-link" @click="router.push('/questionnaires/templates')">ESG 問卷</button>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current">問卷題庫</span>
    </div>

    <div class="page-header">
      <div>
        <h1 class="page-title">題目庫</h1>
        <p class="page-subtitle">集中管理可複用的 ESG 問卷題目，供各範本引用</p>
      </div>
      <button class="btn btn-primary" @click="openCreateModal">＋ 新增題目</button>
    </div>

    <!-- 過濾列 -->
    <div class="filter-bar" style="margin-bottom:16px;align-items:center;">
      <QuestionBankFilter @change="onFilterChange" />
      <select v-model="activeFilter.compliance_domain" class="filter-select" style="width:130px;" @change="pagination.current_page=1;loadBank()">
        <option value="">全部範疇</option>
        <option v-for="d in COMPLIANCE_DOMAINS" :key="d" :value="d">{{ d }}</option>
      </select>
      <div style="flex:1;"></div>
      <span style="font-size:13px;color:var(--text-secondary);white-space:nowrap;">共 <strong class="font-mono">{{ pagination.total }}</strong> 道</span>
    </div>

    <!-- 列表 -->
    <div class="card" style="padding:0;overflow:visible;">
      <div v-if="isLoading" class="loading-mask">載入中...</div>
      <div v-else-if="questions.length === 0" class="empty-state" style="padding:40px;">
        <p>尚無題庫題目，點擊「新增題目」建立第一道</p>
      </div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>題目內容</th>
            <th style="width:100px;">題型</th>
            <th style="width:150px;">SASB Topic</th>
            <th>框架領域</th>
            <th style="width:160px;">合規範疇</th>
            <th style="width:72px;">引用數</th>
            <th style="width:120px;">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="q in questions" :key="q.id">
            <td class="q-text">{{ truncate(q.question_text, 60) }}</td>
            <td><span class="badge badge-gray" style="font-size:11px;">{{ typeLabel(q.question_type) }}</span></td>
            <td style="font-size:12px;color:var(--text-secondary);">{{ (q as any).sasb_topic?.topic_name || '—' }}</td>
            <td>
              <template v-if="q.question_tags && q.question_tags.length">
                <span v-for="tag in q.question_tags" :key="tag.id" class="tag-chip" style="margin:1px;font-size:11px;">{{ tag.label_zh || tag.l3_topic }}</span>
              </template>
              <span v-else style="color:var(--text-secondary);font-size:12px;">—</span>
            </td>
            <td>
              <template v-if="(q as any).compliance_domains && (q as any).compliance_domains.length">
                <span v-for="d in (q as any).compliance_domains" :key="d" class="domain-chip" :class="`domain-${d.toLowerCase()}`">{{ d }}</span>
              </template>
              <span v-else style="color:var(--text-secondary);font-size:12px;">—</span>
            </td>
            <td class="num">
              <span class="usage-badge" :class="q.usage_count > 0 ? 'usage-active' : 'usage-zero'">
                {{ q.usage_count }}
              </span>
            </td>
            <td>
              <div style="display:flex;gap:6px;">
                <button class="btn btn-secondary btn-sm" @click="openEditModal(q)">編輯</button>
                <button class="btn btn-danger btn-sm" @click="confirmDelete(q)">刪除</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 分頁 -->
    <div class="pagination">
      <span>第 {{ pagination.current_page }} / {{ pagination.last_page }} 頁 · 共 {{ pagination.total }} 筆</span>
      <button class="pg-btn" :disabled="pagination.current_page <= 1" @click="goPage(pagination.current_page - 1)">‹</button>
      <button class="pg-btn" :disabled="pagination.current_page >= pagination.last_page" @click="goPage(pagination.current_page + 1)">›</button>
    </div>

    <!-- 新增/編輯 Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
      <div class="modal modal-wide">
        <div class="modal-header">
          <span class="modal-title">{{ editingQuestion ? '編輯題庫題目' : '新增題庫題目' }}</span>
          <button class="modal-close" @click="closeModal">×</button>
        </div>

        <div class="form-group">
          <label class="form-label">題目內容 *</label>
          <textarea v-model="qForm.question_text" class="form-textarea" rows="3" placeholder="請輸入題目..." />
        </div>

        <div class="form-row">
          <div class="form-group" style="flex:1;">
            <label class="form-label">題型 *</label>
            <select v-model="qForm.question_type" class="form-select" :disabled="!!editingQuestion">
              <option value="boolean">是/否</option>
              <option value="single_choice">單選</option>
              <option value="multiple_choice">多選</option>
              <option value="text">文字</option>
              <option value="number">數字</option>
            </select>
          </div>
        </div>

        <!-- 選項（single/multiple） -->
        <div v-if="qForm.question_type === 'single_choice' || qForm.question_type === 'multiple_choice'" class="form-group">
          <label class="form-label">選項（最少 2 個）</label>
          <div class="options-list">
            <div v-for="(_, oi) in qForm.options" :key="oi" class="option-row">
              <input v-model="qForm.options[oi]" class="form-input" :placeholder="`選項 ${oi+1}`" />
              <button class="btn btn-danger btn-sm icon-btn" :disabled="qForm.options.length <= 2" @click="qForm.options.splice(oi,1)">×</button>
            </div>
          </div>
          <button v-if="qForm.options.length < 10" class="btn btn-secondary btn-sm" style="margin-top:8px;" @click="qForm.options.push('')">＋ 新增選項</button>
        </div>

        <div class="form-row">
          <div class="form-group" style="flex:1;">
            <label class="form-label">SASB 揭露主題（選填）</label>
            <select v-model="qForm.sasb_topic_id" class="form-select">
              <option value="">— 不指定 —</option>
              <option v-for="t in sasbTopics" :key="t.id" :value="t.id">{{ t.esg_category }} · {{ t.topic_name }}</option>
            </select>
          </div>
          <div class="form-group" style="flex:1;">
            <label class="form-label">SASB Metric 代碼（選填）</label>
            <input v-model="qForm.sasb_metric_code" class="form-input" placeholder="如 EM-IS-110a.1" maxlength="40" />
          </div>
        </div>

        <div class="form-group" style="display:flex;align-items:center;gap:8px;">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;">
            <input type="checkbox" v-model="qForm.is_required" />
            <span>必填</span>
          </label>
        </div>

        <!-- 三層標籤選擇器 -->
        <div class="form-group">
          <label class="form-label">分類標籤</label>
          <TagSelector v-model="qForm.selectedTags" />
        </div>

        <!-- Disclosure 欄位對應 -->
        <div class="form-group">
          <label class="form-label">Disclosure 欄位對應（選填）</label>
          <select v-model="qForm.disclosure_field_slug" class="form-select">
            <option value="">— 不對應 —</option>
            <template v-for="(fields, prefix) in disclosureFieldsByPrefix" :key="prefix">
              <optgroup :label="prefix">
                <option
                  v-for="f in fields"
                  :key="f.slug"
                  :value="f.slug"
                  :disabled="f.data_type !== (qForm.question_type === 'boolean' ? 'boolean' : 'numeric')"
                >{{ f.label }}{{ f.unit ? ` (${f.unit})` : '' }}</option>
              </optgroup>
            </template>
          </select>
          <p v-if="qForm.question_type === 'boolean'" style="font-size:12px;color:var(--text-secondary);margin-top:4px;">僅顯示 boolean 類型欄位（已啟用），numeric 欄位已停用</p>
          <p v-else-if="qForm.question_type === 'number'" style="font-size:12px;color:var(--text-secondary);margin-top:4px;">僅顯示 numeric 類型欄位（已啟用），boolean 欄位已停用</p>
        </div>

        <!-- 合規範疇 -->
        <div class="form-group">
          <label class="form-label">合規範疇（選填）</label>
          <div class="chip-selector">
            <span v-for="domain in COMPLIANCE_DOMAINS" :key="domain"
              class="chip-option" :class="{ active: qForm.compliance_domains.includes(domain) }"
              @click="toggleComplianceDomain(domain)">{{ domain }}</span>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary" @click="closeModal">取消</button>
          <button class="btn btn-primary" :disabled="isSubmitting || !qForm.question_text.trim()" @click="saveQuestion">
            {{ isSubmitting ? '儲存中...' : (editingQuestion ? '儲存' : '新增') }}
          </button>
        </div>
      </div>
    </div>

    <!-- 刪除確認 Modal -->
    <div v-if="deleteTarget" class="modal-overlay" @click.self="deleteTarget=null">
      <div class="modal" style="min-width:380px;text-align:center;">
        <div class="modal-header"><span class="modal-title">確認刪除題庫題目</span></div>
        <p style="margin:16px 0;color:var(--text-secondary);">
          「{{ truncate(deleteTarget.question_text, 30) }}」<br>
          <span v-if="deleteTarget.usage_count > 0" style="color:#b45309;">
            此題目已被 {{ deleteTarget.usage_count }} 個範本引用（均為副本，刪除不影響範本）
          </span>
          <span v-else>此操作無法復原。</span>
        </p>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="deleteTarget=null">取消</button>
          <button class="btn btn-danger" :disabled="isSubmitting" @click="doDelete">確認刪除</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { useRouter } from 'vue-router'
import {
  questionBankApi, sasbTopicsApi, disclosureFieldsApi,
  type QuestionBankItem, type QuestionType, type QuestionTag, type SasbDisclosureTopic, type DisclosureField,
} from '@/api/modules/settings'
import QuestionBankFilter from '@/components/common/QuestionBankFilter.vue'
import TagSelector from '@/components/common/TagSelector.vue'
import type { BankFilterPayload } from '@/constants/questionBank'

const TYPE_LABELS: Record<string, string> = {
  boolean: '是/否', single_choice: '單選', multiple_choice: '多選', text: '文字', number: '數字',
}
const COMPLIANCE_DOMAINS = ['UFLPA', 'EUDR', 'CMRT', 'SDS', 'CE'] as const

function blankForm() {
  return {
    question_text: '',
    question_type: 'boolean' as QuestionType,
    options: ['', ''] as string[],
    weight: null as number | null, is_required: false,
    sasb_topic_id: '', sasb_metric_code: '',
    selectedTags: [] as QuestionTag[],
    compliance_domains: [] as string[],
    disclosure_field_slug: '' as string,
  }
}

export default defineComponent({
  name: 'QuestionBankView',
  components: { QuestionBankFilter, TagSelector },
  setup() { return { router: useRouter(), COMPLIANCE_DOMAINS } },

  data() {
    return {
      isLoading: false, isSubmitting: false,
      questions: [] as QuestionBankItem[],
      sasbTopics: [] as SasbDisclosureTopic[],
      disclosureFields: [] as DisclosureField[],
      pagination: { current_page: 1, per_page: 20, total: 0, last_page: 1 },
      activeFilter: { keyword: '', l1: '', l2: '', l3: '', compliance_domain: '' } as BankFilterPayload & { compliance_domain: string },
      showModal: false,
      editingQuestion: null as QuestionBankItem | null,
      deleteTarget: null as QuestionBankItem | null,
      qForm: blankForm(),
    }
  },

  computed: {
    disclosureFieldsByPrefix(): Record<string, DisclosureField[]> {
      const map: Record<string, DisclosureField[]> = {}
      for (const f of this.disclosureFields) {
        const prefix = f.slug.split('.')[0]
        if (!map[prefix]) map[prefix] = []
        map[prefix].push(f)
      }
      return map
    },
  },

  mounted() { this.loadBank(); this.loadSasbTopics(); this.loadDisclosureFields() },

  methods: {
    onFilterChange(payload: BankFilterPayload) {
      this.activeFilter = payload
      this.pagination.current_page = 1
      this.loadBank()
    },

    goPage(page: number) { this.pagination.current_page = page; this.loadBank() },

    async loadBank() {
      this.isLoading = true
      try {
        const params: any = { page: this.pagination.current_page, per_page: 20 }
        if (this.activeFilter.keyword) params.keyword = this.activeFilter.keyword
        if ((this.activeFilter as any).l1) params.l1 = (this.activeFilter as any).l1
        if ((this.activeFilter as any).l2) params.l2 = (this.activeFilter as any).l2
        if ((this.activeFilter as any).l3) params.l3 = (this.activeFilter as any).l3
        if (this.activeFilter.compliance_domain) params.compliance_domain = this.activeFilter.compliance_domain
        const { data } = await questionBankApi.list(params)
        this.questions = data.data
        if (data.pagination) this.pagination = data.pagination
      } finally { this.isLoading = false }
    },

    async loadSasbTopics() {
      try {
        const { data } = await sasbTopicsApi.list()
        this.sasbTopics = data.data
      } catch { /**/ }
    },

    async loadDisclosureFields() {
      try {
        const { data } = await disclosureFieldsApi.list()
        this.disclosureFields = data.data
      } catch { /**/ }
    },

    openCreateModal() {
      this.editingQuestion = null
      this.qForm = blankForm()
      this.showModal = true
    },

    openEditModal(q: QuestionBankItem) {
      this.editingQuestion = q
      this.qForm = {
        question_text: q.question_text,
        question_type: q.question_type,
        options: q.options?.length ? [...q.options] : ['', ''],
        weight: q.weight, is_required: q.is_required,
        sasb_topic_id: (q as any).sasb_topic_id ?? '',
        sasb_metric_code: (q as any).sasb_metric_code ?? '',
        selectedTags: q.question_tags ? [...q.question_tags] : [],
        compliance_domains: (q as any).compliance_domains ? [...(q as any).compliance_domains] : [],
        disclosure_field_slug: q.disclosure_field_slug ?? '',
      }
      this.showModal = true
    },

    closeModal() { this.showModal = false; this.editingQuestion = null },

    async saveQuestion() {
      const needsOptions = ['single_choice', 'multiple_choice'].includes(this.qForm.question_type)
      const payload = {
        question_text: this.qForm.question_text.trim(),
        question_type: this.qForm.question_type,
        options: needsOptions ? this.qForm.options.filter(o => o.trim()) : null,
        weight: this.qForm.weight,
        is_required: this.qForm.is_required,
        sasb_topic_id: this.qForm.sasb_topic_id || null,
        sasb_metric_code: this.qForm.sasb_metric_code || null,
        tag_ids: this.qForm.selectedTags.map(t => t.id),
        compliance_domains: this.qForm.compliance_domains,
        disclosure_field_slug: this.qForm.disclosure_field_slug || null,
      }
      this.isSubmitting = true
      try {
        if (this.editingQuestion) {
          await questionBankApi.update(this.editingQuestion.id, payload)
        } else {
          await questionBankApi.create(payload)
        }
        this.closeModal()
        await this.loadBank()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '儲存失敗')
      } finally { this.isSubmitting = false }
    },

    confirmDelete(q: QuestionBankItem) { this.deleteTarget = q },

    async doDelete() {
      if (!this.deleteTarget) return
      this.isSubmitting = true
      try {
        await questionBankApi.remove(this.deleteTarget.id)
        this.deleteTarget = null
        await this.loadBank()
      } finally { this.isSubmitting = false }
    },

    toggleComplianceDomain(domain: string) {
      const idx = this.qForm.compliance_domains.indexOf(domain)
      if (idx >= 0) this.qForm.compliance_domains.splice(idx, 1)
      else this.qForm.compliance_domains.push(domain)
    },

    typeLabel: (t: string) => TYPE_LABELS[t] ?? t,
    truncate: (s: string, n: number) => s.length > n ? s.slice(0, n) + '...' : s,
  },
})
</script>

<style scoped>
/* 麵包屑 */
.breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 13px; margin-bottom: 16px; }
.breadcrumb-link { background: none; border: none; color: var(--accent); cursor: pointer; padding: 0; font-size: 13px; }
.breadcrumb-link:hover { text-decoration: underline; }
.breadcrumb-sep { color: var(--text-secondary); }
.breadcrumb-current { color: var(--text-primary); font-weight: 500; }

/* 分類 badge */
.cat-E { background: #dcfce7; color: #166534; }
.cat-S { background: #dbeafe; color: #1e40af; }
.cat-G { background: #f3e8ff; color: #6b21a8; }

/* Tag chips */
.tag-chips { display: flex; flex-wrap: wrap; gap: 4px; }
.tag-chip { font-size: 11px; padding: 2px 7px; border-radius: 99px; font-weight: 500; }
.tag-geo { background: #fef9c3; color: #854d0e; }
.tag-iso { background: #f1f5f9; color: #475569; }

/* 引用數 badge */
.usage-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 24px; height: 22px; border-radius: 99px; font-size: 12px; font-weight: 600; padding: 0 7px; }
.usage-active { background: #dcfce7; color: #166534; }
.usage-zero { background: var(--surface-2); color: var(--text-secondary); }

/* ISO 26000 radio 選擇 */
.iso-radio-group { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
.iso-radio-item {
  padding: 4px 12px; border-radius: 99px; border: 1px solid var(--border);
  cursor: pointer; font-size: 13px; user-select: none; transition: all 0.1s;
}
.iso-radio-item:hover { border-color: var(--accent); color: var(--accent); }
.iso-radio-item.selected { background: var(--accent); color: #fff; border-color: var(--accent); }

/* Modal */
.modal-wide { min-width: 560px; max-width: 680px; }
.form-row { display: flex; gap: 16px; }
.options-list { display: flex; flex-direction: column; gap: 6px; }
.option-row { display: flex; gap: 8px; align-items: center; }
.option-row .form-input { flex: 1; }
.icon-btn { padding: 4px 8px; min-width: 28px; }
.q-text { font-size: 13px; max-width: 320px; }

/* 合規範疇 chips */
.domain-chip { display: inline-block; font-size: 11px; font-weight: 600; padding: 2px 7px; border-radius: 4px; margin: 1px; }
.domain-uflpa { background: #fee2e2; color: #b91c1c; }
.domain-eudr  { background: #dcfce7; color: #15803d; }
.domain-cmrt  { background: #dbeafe; color: #1d4ed8; }
.domain-sds   { background: #ffedd5; color: #c2410c; }
.domain-ce    { background: #ede9fe; color: #6d28d9; }
.chip-selector { display: flex; flex-wrap: wrap; gap: 6px; }
.chip-option { padding: 4px 12px; border-radius: 99px; border: 1px solid var(--border); cursor: pointer; font-size: 13px; user-select: none; transition: all 0.1s; }
.chip-option:hover { border-color: var(--accent); color: var(--accent); }
.chip-option.active { background: var(--accent); color: #fff; border-color: var(--accent); }
</style>
