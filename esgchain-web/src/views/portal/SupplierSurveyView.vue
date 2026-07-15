<template>
  <div class="page-container">
    <!-- 載入中 -->
    <div v-if="isLoading" class="loading-mask">載入問卷中...</div>

    <!-- 問卷不可編輯 Banner -->
    <div v-else-if="questionnaire && !questionnaire.is_editable" class="locked-banner">
      <span>⚠ 問卷目前處於「{{ statusLabel(questionnaire.status) }}」狀態，暫時無法編輯。</span>
    </div>

    <template v-if="questionnaire && !isLoading">
      <!-- 退回通知 Banner -->
      <div v-if="questionnaire.status === 'review_returned' && returnComment" class="return-banner">
        <div class="return-banner-icon">↩</div>
        <div class="return-banner-body">
          <div class="return-banner-title">問卷已被退回，請依據說明修改後重新提交</div>
          <div class="return-banner-comment">{{ returnComment }}</div>
        </div>
      </div>

      <div class="page-header">
        <div>
          <h1 class="page-title">ESG 問卷填寫</h1>
          <p class="page-subtitle">
            <span class="badge" :class="statusBadgeClass(questionnaire.status)">{{ statusLabel(questionnaire.status) }}</span>
            <span v-if="questionnaire.deadline" style="margin-left:8px;font-size:12px;color:var(--text-secondary);">
              截止日：{{ questionnaire.deadline }}
            </span>
          </p>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
          <span v-if="lastSavedText" style="font-size:12px;color:var(--text-secondary);">{{ lastSavedText }}</span>
          <!-- 申訴按鈕（completed 且窗口期內） -->
          <button
            v-if="canDispute"
            class="btn btn-secondary"
            @click="showDisputeModal = true"
          >申訴（尚可申訴 {{ disputeRemainingDays }} 天）</button>
          <button
            v-if="questionnaire.is_editable"
            class="btn btn-primary"
            :disabled="isSubmitting"
            @click="confirmSubmit"
          >
            {{ isSubmitting ? '提交中...' : '提交問卷' }}
          </button>
        </div>
      </div>

      <!-- 依 E/S/G 分類展示題目 -->
      <div v-for="cat in ['E','S','G']" :key="cat" class="category-section">
        <h2 class="category-title">
          {{ catLabel(cat) }}
          <span class="category-badge">{{ questionsByCategory(cat).length }} 題</span>
        </h2>

        <div v-if="questionsByCategory(cat).length === 0" style="color:var(--text-secondary);font-size:13px;padding:8px 0;">此類別無題目</div>

        <div
          v-for="q in questionsByCategory(cat)"
          :key="q.id"
          class="question-block"
          :class="{ 'question-locked': !questionnaire.is_editable }"
        >
          <div class="question-text">
            <span v-if="q.is_required" class="required-star">*</span>
            {{ q.question_text }}
            <span v-if="q.is_sasb_required" class="sasb-required-badge">SASB 必調</span>
          </div>

          <!-- single_choice -->
          <div v-if="q.question_type === 'single_choice'" class="options-group">
            <label v-for="opt in q.options" :key="opt" class="option-label">
              <input
                type="radio"
                :name="q.id"
                :value="opt"
                v-model="answers[q.id]"
                :disabled="!questionnaire.is_editable"
                @change="onAnswerChange"
              />
              {{ opt }}
            </label>
          </div>

          <!-- multiple_choice -->
          <div v-else-if="q.question_type === 'multiple_choice'" class="options-group">
            <label v-for="opt in q.options" :key="opt" class="option-label">
              <input
                type="checkbox"
                :value="opt"
                :checked="(multiAnswers[q.id] || []).includes(opt)"
                :disabled="!questionnaire.is_editable"
                @change="onMultiChange(q.id, opt, $event)"
              />
              {{ opt }}
            </label>
          </div>

          <!-- boolean -->
          <div v-else-if="q.question_type === 'boolean'" class="toggle-group">
            <button
              class="toggle-btn"
              :class="{ active: answers[q.id] === '是' }"
              :disabled="!questionnaire.is_editable"
              @click="questionnaire.is_editable && setAnswer(q.id, '是')"
            >是</button>
            <button
              class="toggle-btn"
              :class="{ active: answers[q.id] === '否' }"
              :disabled="!questionnaire.is_editable"
              @click="questionnaire.is_editable && setAnswer(q.id, '否')"
            >否</button>
            <span v-if="prefillHints[q.id] && prefillHints[q.id].data_type === 'boolean'"
                  style="margin-left:10px;font-size:12px;color:var(--text-secondary);">
              已從歷史記錄帶入，請確認（{{ prefillHints[q.id].last_period_year }}）
            </span>
          </div>

          <!-- number -->
          <div v-else-if="q.question_type === 'number'">
            <input
              type="number"
              class="form-input"
              style="max-width:200px;"
              v-model="answers[q.id]"
              :disabled="!questionnaire.is_editable"
              @input="onAnswerChange"
            />
            <div v-if="prefillHints[q.id] && prefillHints[q.id].data_type === 'numeric' && prefillHints[q.id].last_numeric != null"
                 style="font-size:12px;color:var(--text-secondary);margin-top:4px;">
              上次填報：{{ prefillHints[q.id].last_numeric.toLocaleString() }}
              {{ prefillHints[q.id].unit }}（{{ prefillHints[q.id].last_period_year }}）
            </div>
          </div>

          <!-- text (default) -->
          <div v-else>
            <textarea
              class="form-textarea"
              v-model="answers[q.id]"
              :disabled="!questionnaire.is_editable"
              @input="onAnswerChange"
            ></textarea>
          </div>
        </div>
      </div>

      <!-- 底部提交 -->
      <div v-if="questionnaire.is_editable" style="text-align:center;padding:24px 0;">
        <button class="btn btn-primary" style="min-width:180px;" :disabled="isSubmitting" @click="confirmSubmit">
          {{ isSubmitting ? '提交中...' : '提交問卷' }}
        </button>
      </div>
    </template>

    <!-- 提交確認 -->
    <div v-if="showConfirm" class="modal-overlay" @click.self="showConfirm=false">
      <div class="modal" style="min-width:400px;">
        <div class="modal-header"><span class="modal-title">確認提交</span></div>

        <!-- 必填未答警告 -->
        <div v-if="unansweredRequired.length > 0" class="required-warning">
          <div class="required-warning-title">⚠ 以下必填題目尚未作答，無法提交：</div>
          <ul class="required-warning-list">
            <li v-for="q in unansweredRequired" :key="q.id">{{ q.order }}. {{ q.question_text }}</li>
          </ul>
        </div>
        <template v-else>
          <p style="margin:16px 0;color:var(--text-secondary);text-align:center;">提交後問卷將進入審核流程，無法再編輯。確定要提交嗎？</p>
        </template>

        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showConfirm=false">取消</button>
          <button class="btn btn-primary" :disabled="isSubmitting || unansweredRequired.length > 0" @click="submitQuestionnaire">確認提交</button>
        </div>
      </div>
    </div>

    <!-- 申訴審核中說明 -->
    <div
      v-if="questionnaire && ['disputed','re_review'].includes(questionnaire.status)"
      class="dispute-banner"
    >
      <div class="dispute-banner-icon">📋</div>
      <div>
        <div class="dispute-banner-title">申訴審核中</div>
        <div class="dispute-banner-body">您的申訴已送出，審核員正在重新審核，結果確定後將通知您。</div>
      </div>
    </div>

    <!-- 申訴 Modal -->
    <div v-if="showDisputeModal" class="modal-overlay" @click.self="showDisputeModal = false">
      <div class="modal" style="min-width:400px;">
        <div class="modal-header">
          <span class="modal-title">提出申訴</span>
          <button class="modal-close" @click="showDisputeModal = false">×</button>
        </div>
        <div style="padding:16px 0;">
          <p style="font-size:14px;color:var(--text-secondary);margin-bottom:12px;">
            請說明申訴原因，審核員將重新審核您的問卷。申訴期限：審核完成後 7 天內。
          </p>
          <label class="form-label">申訴原因（必填）</label>
          <textarea
            v-model="disputeReason"
            class="form-textarea"
            rows="4"
            placeholder="請說明您認為評分有誤的原因..."
          />
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showDisputeModal = false">取消</button>
          <button
            class="btn btn-primary"
            :disabled="isDisputing || !disputeReason.trim()"
            @click="doDispute"
          >{{ isDisputing ? '提交中...' : '確認提出申訴' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { questionnaireApi, type Questionnaire } from '@/api/modules/questionnaire'
import { SAQ_STATUS_BADGE } from '@/utils/saqStatus'

const CATEGORY_LABELS: Record<string, string> = { E: 'E — 環境', S: 'S — 社會', G: 'G — 治理' }
const STATUS_LABELS: Record<string, string> = {
  sent: '待填寫', in_progress: '填寫中', submitted: '已提交',
  under_review: '審核中', review_returned: '已退回', completed: '已通過', reviewed: '已複核',
  disputed: '申訴審核中', re_review: '申訴審核中', finalized: '最終評分確認',
}

function debounce(fn: Function, ms: number) {
  let timer: any
  return (...args: any[]) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), ms) }
}

export default defineComponent({
  name: 'SupplierSurveyView',
  setup() { return { route: useRoute(), router: useRouter() } },

  data() {
    return {
      isLoading: false,
      isSubmitting: false,
      questionnaire: null as Questionnaire | null,
      answers: {} as Record<string, any>,
      multiAnswers: {} as Record<string, string[]>,
      lastSavedText: '',
      showConfirm: false,
      debouncedSave: null as any,
      prefillHints: {} as Record<string, any>,
      // 申訴
      showDisputeModal: false,
      disputeReason: '',
      isDisputing: false,
    }
  },

  computed: {
    questions(): any[] {
      return this.questionnaire?.project?.project_questions ?? this.questionnaire?.template?.questions ?? []
    },
    unansweredRequired(): any[] {
      return this.questions.filter((q: any) => {
        if (!q.is_required) return false
        const isMulti = q.question_type === 'multi_choice'
        if (isMulti) {
          const arr = this.multiAnswers[q.id]
          return !arr || arr.length === 0
        }
        const val = this.answers[q.id]
        return val === undefined || val === null || String(val).trim() === ''
      })
    },
    disputeRemainingDays(): number {
      const reviewedAt = (this.questionnaire as any)?.reviewed_at
      if (!reviewedAt) return 0
      const diff = Math.ceil(7 - (Date.now() - new Date(reviewedAt).getTime()) / 86400000)
      return Math.max(0, diff)
    },
    canDispute(): boolean {
      return (this.questionnaire as any)?.status === 'completed' && this.disputeRemainingDays > 0
    },
    returnComment(): string {
      const histories: any[] = (this.questionnaire as any)?.review_histories ?? []
      const returned = histories
        .filter((h: any) => h.action === 'return_review')
        .sort((a: any, b: any) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())
      return returned[0]?.comment ?? ''
    },
  },

  mounted() {
    this.debouncedSave = debounce(this.autoSave, 1500)
    this.loadQuestionnaire()
  },

  methods: {
    async loadQuestionnaire() {
      this.isLoading = true
      try {
        const id = this.route.params.id as string
        const { data } = await questionnaireApi.get(id)
        this.questionnaire = data.data

        // 初始化 answers 從既有 responses
        if ((this.questionnaire as any).responses) {
          for (const r of (this.questionnaire as any).responses) {
            if (r.answer_options) {
              this.multiAnswers[r.question_id] = r.answer_options
            } else {
              this.answers[r.question_id] = r.answer
            }
          }
        }

        // 載入 disclosure 預填提示
        await this.loadPrefillHints(id)
      } finally { this.isLoading = false }
    },

    async loadPrefillHints(saqId: string) {
      try {
        const res = await fetch(`/api/v1/saqs/${saqId}/prefill-hints`, {
          headers: { Authorization: `Bearer ${localStorage.getItem('token')}` },
        })
        const json = await res.json()
        this.prefillHints = json.data ?? {}

        // 自動帶入 boolean / single_choice（只在尚未填過的題）
        for (const [pqId, hint] of Object.entries(this.prefillHints) as any[]) {
          if (this.answers[pqId] !== undefined && this.answers[pqId] !== null && this.answers[pqId] !== '') continue
          if (hint.data_type === 'boolean' && hint.last_boolean !== null) {
            this.answers[pqId] = hint.last_boolean ? '是' : '否'
          } else if (hint.data_type === 'single_choice' && hint.last_text) {
            this.answers[pqId] = hint.last_text
          }
          // numeric：不自動帶入，只顯示參考值
        }
      } catch { /* 失敗不影響填報 */ }
    },
    questionsByCategory(cat: string): any[] {
      return this.questions.filter((q: any) => q.category === cat).sort((a: any, b: any) => a.order - b.order)
    },
    catLabel: (c: string) => CATEGORY_LABELS[c] ?? c,
    statusLabel: (s: string) => STATUS_LABELS[s] ?? s,
    statusBadgeClass: (s: string) => SAQ_STATUS_BADGE[s] ?? 'badge-gray',
    setAnswer(qId: string, value: string) {
      this.answers[qId] = value
      this.onAnswerChange()
    },
    onMultiChange(qId: string, opt: string, e: Event) {
      if (!this.multiAnswers[qId]) this.multiAnswers[qId] = []
      const checked = (e.target as HTMLInputElement).checked
      if (checked) { this.multiAnswers[qId].push(opt) }
      else { this.multiAnswers[qId] = this.multiAnswers[qId].filter(x => x !== opt) }
      this.onAnswerChange()
    },
    onAnswerChange() {
      if (this.questionnaire?.is_editable) this.debouncedSave()
    },
    async autoSave() {
      if (!this.questionnaire?.is_editable) return
      try {
        const combined: Record<string, any> = { ...this.answers }
        for (const [k, v] of Object.entries(this.multiAnswers)) { combined[k] = v }
        await questionnaireApi.update(this.questionnaire.id, combined)
        this.lastSavedText = `已自動儲存 ${new Date().toLocaleTimeString('zh-TW')}`
      } catch { this.lastSavedText = '儲存失敗' }
    },
    async doDispute() {
      if (!this.disputeReason.trim() || !this.questionnaire) return
      this.isDisputing = true
      try {
        await questionnaireApi.dispute(this.questionnaire.id, this.disputeReason)
        this.showDisputeModal = false
        this.disputeReason = ''
        await this.loadQuestionnaire()
      } finally { this.isDisputing = false }
    },
    confirmSubmit() { this.showConfirm = true },
    async submitQuestionnaire() {
      if (!this.questionnaire) return
      this.isSubmitting = true
      try {
        await this.autoSave()
        await questionnaireApi.submit(this.questionnaire.id)
        this.showConfirm = false
        await this.loadQuestionnaire()
      } finally { this.isSubmitting = false }
    },
  },
})
</script>

<style scoped>
.locked-banner { background:#fef9c3; border:1px solid #eab308; border-radius:6px; padding:10px 16px; margin-bottom:16px; color:#854d0e; font-size:14px; }

.required-warning { background:#fef2f2; border:1px solid #fca5a5; border-left:4px solid #ef4444; border-radius:6px; padding:14px 16px; margin:16px 0; }
.required-warning-title { font-size:14px; font-weight:600; color:#b91c1c; margin-bottom:8px; }
.required-warning-list { margin:0; padding-left:18px; color:#991b1b; font-size:13px; line-height:1.7; }

.return-banner {
  display: flex;
  gap: 14px;
  align-items: flex-start;
  background: #fff7ed;
  border: 1px solid #fdba74;
  border-left: 4px solid #f97316;
  border-radius: 8px;
  padding: 14px 18px;
  margin-bottom: 16px;
}
.return-banner-icon { font-size: 20px; color: #f97316; flex-shrink: 0; line-height: 1.2; }
.return-banner-title { font-size: 14px; font-weight: 600; color: #c2410c; margin-bottom: 4px; }
.return-banner-comment { font-size: 13px; color: #7c2d12; line-height: 1.5; white-space: pre-line; }
.category-section { margin-bottom:24px; }
.category-title { font-family:var(--font-title); font-size:16px; font-weight:600; color:var(--accent); margin-bottom:12px; display:flex; align-items:center; gap:8px; }
.category-badge { font-family:var(--font-mono); font-size:11px; background:var(--surface-2); color:var(--text-secondary); padding:2px 8px; border-radius:99px; }
.question-block { background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:16px; margin-bottom:12px; }
.question-locked { opacity:0.7; pointer-events:none; }
.question-text { font-size:14px; font-weight:500; margin-bottom:12px; line-height:1.5; }
.required-star { color:#dc2626; margin-right:4px; }
.sasb-required-badge { display:inline-block; margin-left:6px; padding:1px 6px; font-size:11px; font-weight:500; color:#92400e; background:#fffbeb; border:1px solid #fcd34d; border-radius:3px; vertical-align:middle; }
.options-group { display:flex; flex-direction:column; gap:8px; }
.option-label { display:flex; align-items:center; gap:8px; font-size:14px; cursor:pointer; }
.toggle-group { display:flex; gap:8px; }
.toggle-btn { padding:6px 20px; border:1px solid var(--border); border-radius:6px; background:var(--surface); cursor:pointer; font-size:14px; }
.toggle-btn.active { background:var(--accent); color:#fff; border-color:var(--accent); }
.toggle-btn:disabled { opacity:0.5; cursor:default; }
.dispute-banner { display:flex; gap:14px; align-items:flex-start; background:#eff6ff; border:1px solid #93c5fd; border-left:4px solid #3b82f6; border-radius:8px; padding:14px 18px; margin-top:16px; }
.dispute-banner-icon { font-size:20px; flex-shrink:0; }
.dispute-banner-title { font-size:14px; font-weight:600; color:#1d4ed8; margin-bottom:4px; }
.dispute-banner-body { font-size:13px; color:#1e40af; line-height:1.5; }
</style>

