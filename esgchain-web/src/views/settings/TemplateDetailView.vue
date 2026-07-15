<template>
  <div class="page-container">
    <!-- 麵包屑 -->
    <div class="breadcrumb">
      <button class="breadcrumb-link" @click="router.push('/questionnaires/templates')">ESG 問卷</button>
      <span class="breadcrumb-sep">›</span>
      <button class="breadcrumb-link" @click="router.push('/questionnaires/templates')">問卷範本設計</button>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current">{{ template?.name ?? '載入中...' }}</span>
    </div>

    <div v-if="isLoading" class="loading-mask">載入中...</div>
    <div v-else-if="!template" class="empty-state">
      <p>找不到此問卷範本</p>
      <button class="btn btn-secondary" @click="router.push('/settings')">返回設定</button>
    </div>
    <template v-else>
      <!-- 封存 Banner -->
      <div v-if="isArchived" class="archive-banner">
        ⚠ 此範本已於 {{ formatDate(template.archived_at) }} 封存，無法編輯。
      </div>

      <!-- Draft Banner -->
      <div v-if="!isArchived && template.has_draft" class="draft-banner">
        <span>✎ 草稿中，尚未發佈。確認後將升版並封存目前版本。</span>
        <button class="btn btn-primary btn-sm" style="margin-left:auto;" @click="showPublishConfirm=true">確認發佈</button>
      </div>

      <!-- 發佈確認 Modal -->
      <div v-if="showPublishConfirm" class="modal-overlay" @click.self="showPublishConfirm=false">
        <div class="modal" style="min-width:380px;">
          <div class="modal-header">
            <span class="modal-title">確認發佈新版本</span>
            <button class="modal-close" @click="showPublishConfirm=false">×</button>
          </div>
          <div style="padding:8px 0 16px;color:var(--text-secondary);font-size:14px;">
            發佈後版本將升至 <strong>v{{ nextVersion }}</strong>，目前版本 v{{ template.version }} 將封存。
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="showPublishConfirm=false">取消</button>
            <button class="btn btn-primary" :disabled="isPublishing" @click="doPublish">
              {{ isPublishing ? '發佈中...' : '確認發佈' }}
            </button>
          </div>
        </div>
      </div>

      <!-- 範本基本資訊 -->
      <div class="page-header">
        <div>
          <div style="display:flex;align-items:center;gap:8px;">
            <h1 class="page-title">{{ template.name }}</h1>
            <button v-if="!isArchived" class="btn btn-secondary btn-sm" @click="openInfoModal">編輯</button>
          </div>
          <p class="page-subtitle">
            <span class="font-mono">v{{ template.version }}</span>
            <span v-if="template.scoring_framework"
                  class="badge"
                  :style="{ marginLeft: '8px', background: frameworkColor(template.scoring_framework), color: '#fff', fontSize: '11px' }">
              {{ template.scoring_framework }}
            </span>
            <span v-else class="badge badge-gray" style="margin-left:8px;font-size:11px;">未設定計分框架</span>
            <span v-if="template.description" style="margin-left:8px;">· {{ template.description }}</span>
          </p>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
          <span class="badge" :class="template.is_active ? 'badge-green' : 'badge-gray'">
            {{ template.is_active ? '啟用' : '停用' }}
          </span>
          <button class="btn btn-secondary btn-sm" :disabled="isArchived" @click="showBankModal=true">從題目庫選題</button>
          <button class="btn btn-primary btn-sm" :disabled="isArchived" @click="openCreateModal">+ 新增題目</button>
        </div>
      </div>

      <!-- 題目列表 -->
      <div style="margin-bottom:12px;">
        <QuestionBankFilter @change="onBankFilterChange" />
      </div>
      <div class="card" style="padding:0;">
        <div v-if="questions.length === 0" class="empty-state" style="padding:40px;">
          <p>尚無題目，點擊「+ 新增題目」建立第一道題</p>
        </div>
        <div v-else-if="filteredQuestions.length === 0" class="empty-state" style="padding:24px;text-align:center;">
          <p style="color:var(--text-secondary);">無符合篩選條件的題目</p>
        </div>
        <table v-else class="data-table">
          <thead>
            <tr>
              <th style="width:28px;"></th>
              <th style="width:44px;">#</th>
              <th style="width:160px;">框架領域</th>
              <th>題目內容</th>
              <th style="width:120px;">題型</th>
              <th style="width:140px;">SASB Topic</th>
              <th style="width:130px;">合規範疇</th>
              <th style="width:72px;">權重</th>
              <th style="width:52px;">必填</th>
              <th style="width:120px;">操作 <span class="font-mono" style="font-size:11px;color:var(--text-secondary);">{{ filteredQuestions.length }}/{{ questions.length }}</span></th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(q, i) in filteredQuestions"
              :key="q.id"
              draggable="true"
              @dragstart="onDragStart(i)"
              @dragover.prevent="onDragOver(i)"
              @drop="onDrop"
              :class="{ 'dragging-row': dragFromIdx === i }"
            >
              <td style="padding:0 4px;text-align:center;">
                <span class="drag-handle" :class="{ 'drag-disabled': isArchived || !!bankFilter.keyword }" title="拖曳排序">⠿</span>
              </td>
              <td class="num">{{ i + 1 }}</td>
              <td>
                <span v-if="q.question_tags?.length" class="tag-chips-inline">
                  <span v-for="tag in q.question_tags" :key="tag.id" class="tag-chip-sm">{{ tag.l2_pillar }} › {{ tag.l3_topic }}</span>
                </span>
                <span v-else style="color:var(--text-secondary);font-size:12px;">—</span>
              </td>
              <td class="q-text">{{ truncate(q.question_text, 60) }}</td>
              <td><span class="badge badge-gray q-type-badge">{{ typeLabel(q.question_type) }}</span></td>
              <td style="font-size:12px;color:var(--text-secondary);">{{ (q as any).sasb_topic?.topic_name || '—' }}</td>
              <td>
                <template v-if="(q as any).compliance_domains && (q as any).compliance_domains.length">
                  <span v-for="cd in (q as any).compliance_domains" :key="cd" class="compliance-chip">{{ cd }}</span>
                </template>
                <span v-else style="color:var(--text-secondary);font-size:12px;">—</span>
              </td>
              <td class="num font-mono">{{ q.weight != null ? q.weight.toFixed(2) : '—' }}</td>
              <td class="num">{{ q.is_required ? '✓' : '' }}</td>
              <td>
                <div class="action-btns">
                  <button class="btn btn-secondary btn-sm" :disabled="isArchived" @click="openEditModal(q)">編輯</button>
                  <button class="btn btn-danger btn-sm" :disabled="isArchived" @click="confirmDelete(q)">刪除</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- 新增/編輯題目 Modal -->
    <div v-if="showQuestionModal" class="modal-overlay" @click.self="closeModal">
      <div class="modal modal-wide">
        <div class="modal-header">
          <span class="modal-title">{{ editingQuestion ? '編輯題目' : '新增題目' }}</span>
          <button class="modal-close" @click="closeModal">×</button>
        </div>

        <div class="form-group">
          <label class="form-label">題目內容 *</label>
          <textarea v-model="qForm.question_text" class="form-textarea" rows="3" placeholder="請輸入題目..." />
        </div>

        <div class="form-group">
          <label class="form-label">標籤</label>
          <TagSelector v-model="qForm.selectedTags" />
        </div>

        <div class="form-row">
          <div class="form-group" style="flex:1;">
            <label class="form-label">題型 *</label>
            <select v-model="qForm.question_type" class="form-select" :disabled="!!editingQuestion">
              <option value="boolean">是/否（boolean）</option>
              <option value="single_choice">單選（single_choice）</option>
              <option value="multiple_choice">多選（multiple_choice）</option>
              <option value="text">文字（text）</option>
              <option value="number">數字（number）</option>
            </select>
          </div>
        </div>

        <!-- 選項輸入（single/multiple） -->
        <div v-if="qForm.question_type === 'single_choice' || qForm.question_type === 'multiple_choice'" class="form-group">
          <label class="form-label">選項（最少 2 個，最多 10 個）</label>
          <div class="options-list">
            <div v-for="(opt, oi) in qForm.options" :key="oi" class="option-row">
              <input v-model="qForm.options[oi]" class="form-input" :placeholder="`選項 ${oi + 1}`" />
              <button
                class="btn btn-danger btn-sm icon-btn"
                :disabled="qForm.options.length <= 2"
                @click="removeOption(oi)"
              >×</button>
            </div>
          </div>
          <button
            v-if="qForm.options.length < 10"
            class="btn btn-secondary btn-sm"
            style="margin-top:8px;"
            @click="addOption"
          >＋ 新增選項</button>
        </div>

        <!-- SASB 對應 -->
        <div class="form-row">
          <div class="form-group" style="flex:1;">
            <label class="form-label">SASB 揭露主題（選填）</label>
            <select v-model="qForm.sasb_topic_id" class="form-select" :disabled="sasbTopicsLoading" @change="onTopicSelect">
              <option value="">— 不指定 —</option>
              <option v-for="t in sasbTopics" :key="t.id" :value="t.id">
                {{ t.esg_category }} · {{ t.topic_name }} ({{ t.topic_code }})
              </option>
            </select>
          </div>
          <div class="form-group" style="flex:1;">
            <label class="form-label">SASB Metric 代碼（選填）</label>
            <input v-model="qForm.sasb_metric_code" class="form-input" placeholder="如 EM-IS-110a.1" maxlength="40" />
          </div>
        </div>

        <div class="form-row">
          <div class="form-group" style="flex:1;">
            <label class="form-label">
              相對權重（選填）
              <span style="font-size:11px;font-weight:400;color:var(--text-secondary);margin-left:4px;">數值愈大權重愈高，建立專案時自動正規化</span>
            </label>
            <input
              v-model.number="qForm.weight"
              type="number"
              min="0"
              step="0.01"
              class="form-input font-mono"
              placeholder="如 0.05"
            />
          </div>
          <div class="form-group" style="flex:0 0 auto;justify-content:flex-end;padding-top:28px;">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;">
              <input type="checkbox" v-model="qForm.is_required" />
              <span>必填</span>
            </label>
          </div>
        </div>

        <!-- 計分設定折疊面板 -->
        <div class="scoring-panel-wrap">
          <button type="button" class="scoring-panel-toggle" @click="showScoringPanel = !showScoringPanel">
            ⚙ 計分設定 <span style="font-size:11px;color:var(--text-secondary);margin-left:4px;">（選填）</span>
            <span style="margin-left:auto;">{{ showScoringPanel ? '▲' : '▼' }}</span>
          </button>
          <div v-if="showScoringPanel" class="scoring-panel-body">
            <!-- 佐證說明：隱藏所有計分欄位 -->
            <div v-if="qForm.scoring_type === 'evidence_only'" class="evidence-note-box">
              📎 此題為佐證說明，不納入評分計算。
            </div>
            <template v-else>
              <div class="form-row">
                <div class="form-group" style="flex:1;">
                  <label class="form-label">計分方向</label>
                  <div class="direction-toggle">
                    <button
                      type="button"
                      class="dir-btn"
                      :class="{ 'dir-btn--active': qForm.scoring_direction === 'positive' }"
                      @click="qForm.scoring_direction = 'positive'"
                    >正向（回答肯定得高分）</button>
                    <button
                      type="button"
                      class="dir-btn"
                      :class="{ 'dir-btn--active': qForm.scoring_direction === 'negative' }"
                      @click="qForm.scoring_direction = 'negative'"
                    >反向（回答否定得高分）</button>
                  </div>
                </div>
              </div>
            </template>

            <div class="form-row" style="margin-top:10px;">
              <div class="form-group" style="flex:1;">
                <label class="form-label">計分方式</label>
                <select v-model="qForm.scoring_type" class="form-select">
                  <option value="">預設（規則判斷）</option>
                  <option value="ordered_asc">自動升序（選項由低到高線性給分）</option>
                  <option value="ordered_desc">自動降序（選項由高到低線性給分）</option>
                  <option value="custom">自訂分值（各選項手動設定）</option>
                  <option value="evidence_only">佐證說明（不計入評分）</option>
                  <option value="llm">LLM 品質評分（文字題 AI 評分）</option>
                </select>
              </div>
            </div>

            <!-- 自訂分值：各選項分值輸入 -->
            <div v-if="qForm.scoring_type === 'custom' && qForm.options.filter(o => o.trim()).length" class="custom-scores-wrap">
              <label class="form-label" style="margin-bottom:8px;">各選項分值（0–100）</label>
              <div
                v-for="opt in qForm.options.filter(o => o.trim())"
                :key="opt"
                class="custom-score-row"
              >
                <span class="custom-score-opt">{{ opt }}</span>
                <input
                  type="number"
                  class="custom-score-input font-mono"
                  min="0" max="100" step="1"
                  :value="qForm.option_scores[opt] ?? ''"
                  @input="qForm.option_scores[opt] = parseFloat(($event.target as HTMLInputElement).value)"
                  placeholder="—"
                />
              </div>
            </div>
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

    <!-- 基本資訊編輯 Modal -->
    <div v-if="showInfoModal" class="modal-overlay" @click.self="showInfoModal=false">
      <div class="modal" style="min-width:440px;">
        <div class="modal-header">
          <span class="modal-title">編輯範本資訊</span>
          <button class="modal-close" @click="showInfoModal=false">×</button>
        </div>
        <div class="form-group">
          <label class="form-label">範本名稱 *</label>
          <input v-model="infoForm.name" class="form-input" required />
        </div>
        <div class="form-group">
          <label class="form-label">計分框架</label>
          <div class="form-input" style="background:var(--bg-secondary);color:var(--text-secondary);cursor:not-allowed;display:flex;align-items:center;gap:8px;">
            <span v-if="template?.scoring_framework"
                  :style="{ background: frameworkColor(template.scoring_framework), color:'#fff', padding:'1px 8px', borderRadius:'4px', fontSize:'11px', fontWeight:600 }">
              {{ template.scoring_framework }}
            </span>
            <span v-else style="color:var(--text-secondary);">未設定（通用）</span>
            <span style="font-size:11px;color:var(--text-secondary);">· 建立後不可修改</span>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">版本號</label>
          <input v-model="infoForm.version" class="form-input font-mono" />
        </div>
        <div class="form-group">
          <label class="form-label">描述（選填）</label>
          <textarea v-model="infoForm.description" class="form-textarea" rows="3" />
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showInfoModal=false">取消</button>
          <button class="btn btn-primary" :disabled="infoSubmitting || !infoForm.name.trim()" @click="saveInfo">
            {{ infoSubmitting ? '儲存中…' : '儲存' }}
          </button>
        </div>
      </div>
    </div>

    <!-- 從題目庫選題 Modal -->
    <BankImportModal
      v-if="showBankModal && template"
      :template-id="template.id"
      @close="showBankModal=false"
      @imported="onBankImported"
    />

    <!-- 刪除確認 Modal -->
    <div v-if="deleteTarget" class="modal-overlay" @click.self="deleteTarget = null">
      <div class="modal" style="min-width:360px;text-align:center;">
        <div class="modal-header"><span class="modal-title">確認刪除題目</span></div>
        <p style="margin:16px 0;color:var(--text-secondary);">
          「{{ truncate(deleteTarget.question_text, 30) }}」<br>此操作無法復原。
        </p>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="deleteTarget = null">取消</button>
          <button class="btn btn-danger" :disabled="isSubmitting" @click="doDelete">確認刪除</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { settingsApi, questionsApi, sasbTopicsApi, type QuestionnaireTemplate, type SAQQuestion, type QuestionType, type QuestionTag, type SasbDisclosureTopic } from '@/api/modules/settings'
import BankImportModal from '@/components/common/BankImportModal.vue'
import QuestionBankFilter from '@/components/common/QuestionBankFilter.vue'
import TagSelector from '@/components/common/TagSelector.vue'
import { applyBankFilter, type BankFilterPayload } from '@/constants/questionBank'

const TYPE_LABELS: Record<string, string> = {
  boolean: '是/否', single_choice: '單選', multiple_choice: '多選', text: '文字', number: '數字',
}

function blankForm() {
  return {
    question_text: '',
    selectedTags: [] as QuestionTag[],
    question_type: 'boolean' as QuestionType,
    options: ['', ''] as string[],
    weight: null as number | null,
    is_required: false,
    sasb_topic_id: '' as string,
    sasb_metric_code: '' as string,
    scoring_direction: 'positive' as 'positive' | 'negative',
    scoring_type: '' as string,
    option_scores: {} as Record<string, number>,
  }
}

export default defineComponent({
  name: 'TemplateDetailView',
  components: { BankImportModal, QuestionBankFilter, TagSelector },
  setup() { return { router: useRouter(), route: useRoute() } },

  data() {
    return {
      isLoading: false,
      isSubmitting: false,
      isReordering: false,
      template: null as QuestionnaireTemplate | null,
      questions: [] as SAQQuestion[],
      showQuestionModal: false,
      editingQuestion: null as SAQQuestion | null,
      deleteTarget: null as SAQQuestion | null,
      qForm: blankForm(),
      sasbTopics: [] as SasbDisclosureTopic[],
      sasbTopicsLoading: false,
      showBankModal: false,
      showInfoModal: false,
      infoForm: { name: '', version: '', description: '', scoring_framework: '' },
      infoSubmitting: false,
      bankFilter: { keyword: '' } as BankFilterPayload,
      showPublishConfirm: false,
      isPublishing: false,
      dragFromIdx: null as number | null,
      dragToIdx: null as number | null,
      showScoringPanel: false,
    }
  },

  computed: {
    isArchived(): boolean {
      return !!this.template?.archived_at
    },
    filteredQuestions(): SAQQuestion[] {
      return applyBankFilter(this.questions, this.bankFilter)
    },
    nextVersion(): number {
      return Number(this.template?.version ?? 1) + 1
    },
  },

  mounted() { this.loadAll() },

  methods: {
    async loadAll() {
      this.isLoading = true
      const id = this.route.params.id as string
      try {
        const [tRes, qRes] = await Promise.all([
          settingsApi.templates.get(id),
          questionsApi.list(id),
        ])
        this.template = tRes.data.data
        this.questions = qRes.data.data
      } catch {
        this.template = null
      } finally { this.isLoading = false }
    },

    async loadTemplate() {
      const id = this.route.params.id as string
      try {
        const { data } = await questionsApi.list(id)
        this.questions = data.data
      } catch { /**/ }
    },

    async onBankImported() {
      this.showBankModal = false
      await this.loadTemplate()
    },

    async loadSasbTopics() {
      if (this.sasbTopics.length) return
      this.sasbTopicsLoading = true
      try {
        const industryId = this.template?.sasb_industry_id ?? undefined
        const { data } = await sasbTopicsApi.list(industryId ? { industry_id: industryId } : undefined)
        this.sasbTopics = data.data
      } finally { this.sasbTopicsLoading = false }
    },

    onTopicSelect() {
      const topic = this.sasbTopics.find(t => t.id === this.qForm.sasb_topic_id)
      if (topic) {
        this.qForm.sasb_metric_code = topic.topic_code + '.'
      }
    },

    openCreateModal() {
      this.editingQuestion = null
      this.qForm = blankForm()
      this.showScoringPanel = false
      this.showQuestionModal = true
      this.loadSasbTopics()
    },

    openEditModal(q: SAQQuestion) {
      this.editingQuestion = q
      this.qForm = {
        question_text: q.question_text,
        selectedTags: q.question_tags ? [...q.question_tags] : [],
        question_type: q.question_type,
        options: q.options?.length ? [...q.options] : ['', ''],
        weight: q.weight,
        is_required: q.is_required,
        sasb_topic_id: (q as any).sasb_topic_id ?? '',
        sasb_metric_code: (q as any).sasb_metric_code ?? '',
        scoring_direction: (q as any).scoring_direction ?? 'positive',
        scoring_type: (q as any).scoring_type ?? '',
        option_scores: (q as any).option_scores ? { ...(q as any).option_scores } : {},
      }
      this.showScoringPanel = !!(this.qForm.scoring_type || this.qForm.scoring_direction === 'negative')
      this.showQuestionModal = true
      this.loadSasbTopics()
    },

    closeModal() {
      this.showQuestionModal = false
      this.editingQuestion = null
    },

    addOption() { this.qForm.options.push('') },
    removeOption(i: number) { this.qForm.options.splice(i, 1) },

    async saveQuestion() {
      const id = this.route.params.id as string
      const needsOptions = ['single_choice', 'multiple_choice'].includes(this.qForm.question_type)
      const scoringType = this.qForm.scoring_type || null
      const optionScores = scoringType === 'custom' && needsOptions
        ? this.qForm.option_scores
        : null
      const payload = {
        question_text: this.qForm.question_text.trim(),
        tag_ids: this.qForm.selectedTags.map(t => t.id),
        question_type: this.qForm.question_type,
        options: needsOptions ? this.qForm.options.filter(o => o.trim()) : null,
        weight: this.qForm.weight,
        is_required: this.qForm.is_required,
        sasb_topic_id: this.qForm.sasb_topic_id || null,
        sasb_metric_code: this.qForm.sasb_metric_code || null,
        scoring_direction: this.qForm.scoring_direction,
        scoring_type: scoringType,
        option_scores: optionScores,
      }

      this.isSubmitting = true
      try {
        if (this.editingQuestion) {
          await questionsApi.update(id, this.editingQuestion.id, payload)
        } else {
          const order = (this.questions.at(-1)?.order ?? 0) + 1
          await questionsApi.create(id, { ...payload, order })
        }
        this.closeModal()
        await this.loadTemplate()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '儲存失敗')
      } finally { this.isSubmitting = false }
    },

    confirmDelete(q: SAQQuestion) { this.deleteTarget = q },

    async doDelete() {
      if (!this.deleteTarget) return
      const id = this.route.params.id as string
      this.isSubmitting = true
      try {
        await questionsApi.remove(id, this.deleteTarget.id)
        this.deleteTarget = null
        await this.loadTemplate()
        this.questions = this.questions.map((q, i) => ({ ...q, order: i + 1 }))
      } finally { this.isSubmitting = false }
    },

    onDragStart(idx: number) {
      if (this.isArchived || this.bankFilter.keyword) return
      this.dragFromIdx = idx
    },
    onDragOver(idx: number) {
      if (this.dragFromIdx === null) return
      this.dragToIdx = idx
    },
    async onDrop() {
      const from = this.dragFromIdx
      const to = this.dragToIdx
      this.dragFromIdx = null
      this.dragToIdx = null
      if (from === null || to === null || from === to) return
      if (this.isArchived || this.bankFilter.keyword) return
      const moved = this.questions.splice(from, 1)[0]
      this.questions.splice(to, 0, moved)
      await this.onReorder()
    },

    async onReorder() {
      const templateId = this.route.params.id as string
      const snapshot = [...this.questions]
      this.isReordering = true
      try {
        await questionsApi.reorder(templateId, this.questions.map(q => q.id))
      } catch {
        this.questions = snapshot
        alert('排序更新失敗，已還原')
      } finally {
        this.isReordering = false
      }
    },

    onBankFilterChange(payload: BankFilterPayload) {
      this.bankFilter = payload
    },

    frameworkColor(fw: string): string {
      const map: Record<string, string> = {
        'ESG': '#1a4d3e',
        'ISO20400': '#2563eb',
        'ISO26000': '#7c3aed',
        'Geo-Risk': '#b45309',
        'Product-Compliance': '#0e7490',
      }
      return map[fw] ?? '#6b7280'
    },

    openInfoModal() {
      if (!this.template) return
      this.infoForm = {
        name: this.template.name,
        version: this.template.version,
        description: this.template.description ?? '',
        scoring_framework: this.template.scoring_framework ?? '',
      }
      this.showInfoModal = true
    },

    async saveInfo() {
      if (!this.template || !this.infoForm.name.trim()) return
      this.infoSubmitting = true
      try {
        await settingsApi.templates.update(this.template.id, {
          name: this.infoForm.name.trim(),
          version: this.infoForm.version.trim(),
          description: this.infoForm.description.trim() || null,
        })
        this.showInfoModal = false
        await this.loadAll()
      } catch {
        alert('儲存失敗，請稍後再試')
      } finally {
        this.infoSubmitting = false
      }
    },

    async doPublish() {
      if (!this.template) return
      this.isPublishing = true
      try {
        await settingsApi.templates.publish(this.template.id)
        this.showPublishConfirm = false
        await this.loadAll()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '發布失敗，請稍後再試')
      } finally {
        this.isPublishing = false
      }
    },

    formatDate(iso: string | null | undefined) {
      if (!iso) return ''
      return iso.slice(0, 10)
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

/* 題目列表 */
.q-text { font-size: 13px; max-width: 340px; }
.q-type-badge { font-size: 11px; }

/* 操作按鈕 */
.action-btns { display: flex; gap: 4px; flex-wrap: nowrap; }
.icon-btn { padding: 4px 8px; min-width: 28px; }

/* 拖曳把手 */
.drag-handle {
  display: inline-block;
  cursor: grab;
  color: var(--text-secondary);
  font-size: 16px;
  padding: 2px 4px;
  border-radius: 3px;
  user-select: none;
}
.drag-handle:hover { background: var(--surface-2); color: var(--text-primary); }
.drag-disabled { cursor: default; opacity: 0.3; }

/* 標籤 chips（列表行內） */
.tag-chips-inline { display: flex; flex-wrap: wrap; gap: 4px; }
.tag-chip-sm {
  display: inline-block;
  background: #f0ede6;
  border: 1px solid #e2ddd6;
  border-radius: 4px;
  padding: 2px 6px;
  font-size: 11px;
  color: var(--text-secondary);
  white-space: nowrap;
}

/* 合規範疇 chip */
.compliance-chip { display: inline-block; font-size: 10px; font-weight: 600; padding: 2px 6px; border-radius: 4px; background: #e0f2fe; color: #0369a1; margin: 1px; }

/* Modal 寬版 */
.modal-wide { min-width: 560px; max-width: 680px; }

/* 表單行 */
.form-row { display: flex; gap: 16px; }

/* 選項列表 */
.options-list { display: flex; flex-direction: column; gap: 6px; }
.option-row { display: flex; gap: 8px; align-items: center; }
.option-row .form-input { flex: 1; }

/* 計分設定面板 */
.scoring-panel-wrap { border: 1px solid var(--border); border-radius: 6px; margin-bottom: 16px; overflow: hidden; }
.scoring-panel-toggle { display: flex; align-items: center; width: 100%; background: var(--surface-2); border: none; padding: 10px 14px; font-size: 13px; font-weight: 600; color: var(--text-primary); cursor: pointer; gap: 6px; }
.scoring-panel-toggle:hover { background: var(--surface-3, #f5f0ea); }
.scoring-panel-body { padding: 14px; background: var(--surface); border-top: 1px solid var(--border); }
.evidence-note-box { background: #fef9c3; border: 1px solid #fde68a; border-radius: 6px; padding: 10px 14px; font-size: 13px; color: #854d0e; }
.direction-toggle { display: flex; gap: 8px; }
.dir-btn { flex: 1; padding: 6px 10px; font-size: 12px; border: 1px solid var(--border); border-radius: 6px; background: var(--surface); color: var(--text-secondary); cursor: pointer; }
.dir-btn--active { background: #ecfdf5; border-color: #86efac; color: #15803d; font-weight: 600; }
.custom-scores-wrap { margin-top: 12px; }
.custom-score-row { display: flex; align-items: center; gap: 10px; padding: 4px 0; }
.custom-score-opt { flex: 1; font-size: 13px; color: var(--text-primary); }
.custom-score-input { width: 64px; padding: 4px 6px; border: 1px solid var(--border); border-radius: 4px; font-size: 13px; text-align: right; }

/* 封存 Banner */
.archive-banner {
  background: #fef9c3;
  border: 1px solid #fde047;
  border-radius: 6px;
  padding: 10px 16px;
  font-size: 14px;
  color: #713f12;
  margin-bottom: 16px;
}
</style>
