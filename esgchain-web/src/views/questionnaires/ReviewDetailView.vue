<template>
  <div class="page-container">
    <div class="breadcrumb">
      <button class="breadcrumb-link" @click="router.push('/questionnaires/review')">問卷審核</button>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current">審核詳情</span>
    </div>

    <div v-if="isLoading" class="loading-mask">載入中...</div>
    <template v-else-if="saq">
      <!-- 頁首 -->
      <div class="page-header" style="align-items:flex-start;">
        <div style="flex:1;min-width:0;">
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <h1 class="page-title" style="margin:0;word-break:break-word;">{{ maskSupplierName(saq.supplier?.name) }}</h1>
            <div v-if="saq.project?.template?.scoring_framework" class="framework-tag-wrap">
              <span style="font-size:12px;color:var(--text-secondary);white-space:nowrap;">評核框架</span>
              <span class="framework-chip">{{ saq.project.template.scoring_framework }}</span>
              <div class="framework-help-wrap">
                <button class="framework-help-btn" @click.stop="showFrameworkInfo = !showFrameworkInfo">?</button>
                <div v-if="showFrameworkInfo" class="framework-info-popover" @click.stop>
                  <div class="framework-info-header">
                    <strong>{{ frameworkInfo.name }}</strong>
                    <button class="framework-info-close" @click="showFrameworkInfo = false">×</button>
                  </div>
                  <p class="framework-info-desc">{{ frameworkInfo.desc }}</p>
                  <div class="framework-info-pillars">
                    <div v-for="p in frameworkInfo.pillars" :key="p" class="framework-info-pillar">{{ p }}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <p class="page-subtitle" style="margin-top:6px;">
            <span v-if="saq.template?.name">{{ saq.template.name }} · </span>第 {{ saq.round }} 輪<span v-if="saq.project"> · {{ saq.project?.name }}</span>
          </p>
        </div>
        <div style="display:flex;align-items:center;gap:12px;">
          <span class="badge" :class="statusBadge[saq.status]">{{ statusLabel[saq.status] }}</span>
          <!-- 最終評分（覆核後） -->
          <div v-if="saq.final_score != null" class="score-block score-block--final">
            <span style="font-size:11px;color:var(--text-secondary);margin-right:4px;">最終評分</span>
            <span class="score-num font-mono">{{ saq.final_score.toFixed(1) }}</span>
            <span class="score-grade" :class="gradeClass(saq.final_grade)">{{ saq.final_grade }}</span>
          </div>
          <!-- AI 評分（可折疊查看） -->
          <div v-if="saq.score != null" class="score-block" :style="saq.final_score != null ? 'opacity:0.6;' : ''">
            <span v-if="saq.final_score != null" style="font-size:11px;color:var(--text-secondary);margin-right:4px;">AI</span>
            <span class="score-num font-mono">{{ saq.score.toFixed(1) }}</span>
            <span class="score-grade" :class="gradeClass(saq.grade)">{{ saq.grade }}</span>
          </div>
        </div>
      </div>

      <!-- 操作列 -->
      <div class="page-action-bar" v-if="saq.status === 'under_review' || saq.status === 're_review'">
        <button class="btn btn-secondary btn-sm" :disabled="isActing || isSubmittingReviews || reviewDraftCount === 0" @click="doSubmitReviews">
          {{ isSubmittingReviews ? '提交中...' : `提交覆核分（${reviewDraftCount} 題）` }}
        </button>
        <button v-if="saq.status === 'under_review'" class="btn btn-danger btn-sm" :disabled="isActing" @click="openAction('return')">退回修改</button>
        <button v-if="saq.status === 'under_review'" class="btn btn-primary" :disabled="isActing" @click="openAction('complete')">審核通過</button>
        <button v-if="saq.status === 're_review'" class="btn btn-primary" :disabled="isActing" @click="doFinalize">最終確認</button>
      </div>
      <div class="page-action-bar" v-else-if="saq.status === 'submitted'">
        <button class="btn btn-primary" :disabled="isActing" @click="doStartReview">開始審核</button>
      </div>
      <div class="page-action-bar" v-else-if="saq.status === 'completed'">
        <button class="btn btn-secondary" :disabled="isActing" @click="doMarkReviewed">標記已複核</button>
      </div>

      <div class="review-layout">
        <!-- 左欄：題目 + 回答 -->
        <div class="questions-col">
          <!-- 評分說明列（僅當有 AI 信心度資料時顯示）-->
          <div v-if="hasConfidenceData" class="score-legend-bar">
            <span class="score-legend-item">
              <span class="confidence-badge confidence-high">●</span> 高信心度：規則計分
            </span>
            <span class="score-legend-sep">·</span>
            <span class="score-legend-item">
              <span class="confidence-badge confidence-medium">◐</span> 中信心度：LLM 語意評分（可展開查看理由）
            </span>
            <span class="score-legend-sep">·</span>
            <span class="score-legend-item">
              <span class="confidence-badge confidence-low">⚠</span> 低信心度：備援評分
            </span>
            <span class="score-legend-sep">·</span>
            <span class="score-legend-item score-legend-formula">AI 得分 × 題目權重% = 貢獻分</span>
          </div>

          <div
            v-for="(item, i) in mergedItems"
            :key="item.question.id"
            class="question-card"
            :class="{ 'has-answer': !!item.response }"
          >
            <div class="q-header">
              <span class="q-num font-mono">{{ i + 1 }}</span>
              <span class="q-type-badge">{{ typeLabel(item.question.question_type) }}</span>
              <span v-if="item.question.is_required" class="required-dot" title="必填">*</span>
              <span v-if="item.question.scoring_type === 'evidence_only'" class="q-tag q-tag--evidence">📎 佐證</span>
              <template v-if="frameworkTags(item.question).length">
                <span
                  v-for="tag in frameworkTags(item.question)"
                  :key="tag"
                  class="q-tag q-tag--fw"
                >{{ tag }}</span>
              </template>
              <span
                v-if="scoringFramework === 'ESG' && questionEsgCat(item.question)"
                class="q-esg-cat"
                :class="`q-esg-${questionEsgCat(item.question)?.toLowerCase()}`"
              >{{ questionEsgCat(item.question) }}</span>
            </div>
            <p class="q-text">{{ item.question.question_text }}</p>

            <!-- 回答區 -->
            <div v-if="item.response" class="answer-block">
              <!-- boolean / single_choice -->
              <template v-if="item.response.answer">
                <span class="answer-value">{{ item.response.answer }}</span>
              </template>
              <!-- multiple_choice -->
              <template v-else-if="item.response.answer_options?.length">
                <span
                  v-for="opt in item.response.answer_options"
                  :key="opt"
                  class="answer-chip"
                >{{ opt }}</span>
              </template>
              <span v-else class="no-answer">未填寫</span>

              <!-- 佐證說明 -->
              <p v-if="item.response.evidence_note" class="evidence-note">
                <span class="evidence-label">佐證：</span>{{ item.response.evidence_note }}
              </p>
            </div>
            <div v-else class="answer-block no-answer">未回答</div>

            <!-- 分數列 -->
            <div v-if="item.question.scoring_type === 'evidence_only'" class="evidence-only-hint">
              📎 此題為佐證說明，不計入評分
            </div>
            <div v-else class="score-row">
              <!-- AI 得分（answer_score = raw_score / weight） -->
              <template v-if="item.response?.raw_score != null && item.question.weight">
                <span class="q-score-label">AI 得分</span>
                <span class="q-score-val font-mono"
                  :class="reviewExisting[item.question.id] ? 'q-score-dimmed' : ''">
                  {{ (item.response.raw_score / item.question.weight).toFixed(1) }}
                </span>
                <span class="q-weight-label font-mono">× {{ (item.question.weight * 100).toFixed(1) }}%</span>
                <span class="q-raw-label font-mono">= {{ item.response.raw_score.toFixed(2) }}</span>
                <!-- 信心度徽章 -->
                <span
                  v-if="item.response.score_confidence"
                  class="confidence-badge"
                  :class="'confidence-' + item.response.score_confidence"
                  :title="confidenceLabel(item.response.score_confidence)"
                >{{ confidenceIcon(item.response.score_confidence) }}</span>
              </template>

              <!-- 審核員覆核分（唯讀，非 under_review/re_review 狀態） -->
              <template v-if="saq.status !== 'under_review' && saq.status !== 're_review' && reviewExisting[item.question.id]">
                <span class="q-divider">→</span>
                <span class="q-score-label q-score-label--reviewer">覆核</span>
                <span class="q-score-val q-score-val--reviewer font-mono">
                  {{ reviewExisting[item.question.id].reviewer_score.toFixed(1) }}
                </span>
                <span v-if="reviewExisting[item.question.id].reason" class="q-review-reason">
                  {{ reviewExisting[item.question.id].reason }}
                </span>
              </template>

              <!-- 逐題覆核輸入面板（under_review / re_review 狀態） -->
              <template v-if="saq.status === 'under_review' || saq.status === 're_review'">
                <div class="reviewer-panel">
                  <label class="reviewer-label">覆核分（0–100）</label>
                  <input
                    type="number"
                    class="reviewer-input font-mono"
                    min="0" max="100" step="1"
                    :value="reviewDraft[item.question.id]?.score ?? reviewExisting[item.question.id]?.reviewer_score ?? ''"
                    @input="onReviewInput(item.question.id, $event)"
                    placeholder="—"
                  />
                  <input
                    type="text"
                    class="reviewer-reason"
                    :value="reviewDraft[item.question.id]?.reason ?? reviewExisting[item.question.id]?.reason ?? ''"
                    @input="onReasonInput(item.question.id, $event)"
                    placeholder="覆核理由（選填）"
                  />
                </div>
              </template>

              <!-- LLM 評分理由展開區（score_confidence=medium） -->
              <template v-if="item.response?.score_confidence === 'medium' && item.response?.llm_score_reason">
                <div class="llm-reason-wrap">
                  <button class="llm-reason-toggle" @click="toggleLlmReason(item.question.id)">
                    {{ llmReasonExpanded[item.question.id] ? '▲' : '▼' }} AI 評分理由
                  </button>
                  <div v-if="llmReasonExpanded[item.question.id]" class="llm-reason-body">
                    {{ item.response.llm_score_reason }}
                  </div>
                </div>
              </template>
            </div>
          </div>
        </div>

        <!-- 右欄：審核歷程 -->
        <div class="history-col">
          <div class="card" style="padding:16px;">
            <h3 class="card-section-title">審核歷程</h3>
            <div v-if="!saq.reviewHistories?.length" style="color:var(--text-secondary);font-size:13px;padding:8px 0;">尚無記錄</div>
            <div class="timeline">
              <div
                v-for="(h, idx) in sortedHistories"
                :key="h.id"
                class="tl-item"
                :class="{ 'tl-stale': h.action === 'scoring_complete' && idx > 0 && sortedHistories[idx-1]?.action === 'scoring_complete' }"
              >
                <div class="tl-dot" :class="historyDotClass(h.action)"></div>
                <div class="tl-body">
                  <div class="tl-header">
                    <span class="tl-label" :class="historyClass(h.action)">{{ actionLabel(h.action) }}</span>
                  </div>
                  <div class="tl-meta">
                    <span>{{ h.actor?.name || '—' }}</span>
                    <span>{{ formatTime(h.created_at) }}</span>
                  </div>
                  <p v-if="h.comment" class="tl-comment">{{ h.comment }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- 供應商資訊 -->
          <!-- 分數摘要卡片 -->
          <div v-if="saq.score != null" class="card" style="padding:16px;margin-top:12px;">
            <div class="score-card-header">
              <h3 class="card-section-title" style="margin-bottom:0;">分數摘要</h3>
              <button class="detail-toggle" @click="showScoreDetail = !showScoreDetail">
                細部說明 <span class="detail-chevron" :class="{ open: showScoreDetail }">›</span>
              </button>
            </div>

            <!-- 最終評分 vs AI 評分 -->
            <div v-if="saq.final_score != null" class="score-summary-row score-summary-final" style="margin-top:12px;">
              <span class="score-summary-label">最終評分</span>
              <span class="score-summary-val font-mono">{{ saq.final_score.toFixed(1) }}</span>
              <span class="score-summary-grade" :class="gradeClass(saq.final_grade)">{{ saq.final_grade }}</span>
            </div>
            <div class="score-summary-row" :style="(saq.final_score != null ? 'opacity:0.65;' : '') + (saq.final_score == null ? 'margin-top:12px;' : '')">
              <span class="score-summary-label">{{ saq.final_score != null ? 'AI 評分' : '總分' }}</span>
              <span class="score-summary-val font-mono">{{ saq.score.toFixed(1) }}</span>
              <span class="score-summary-grade" :class="gradeClass(saq.grade)">{{ saq.grade }}</span>
            </div>

            <!-- 框架 pillar 分數（category_scores 有值時優先顯示） -->
            <div v-if="pillarScoreEntries.length > 0" style="margin-top:10px;border-top:1px solid var(--border);padding-top:10px;">
              <div v-for="(entry, idx) in pillarScoreEntries" :key="entry.label" class="esg-row">
                <span class="esg-cat" :style="{ background: pillarColor(idx), color: '#fff', minWidth: '60px', fontSize: '11px', padding: '2px 5px', borderRadius: '4px', textAlign: 'center' }">{{ entry.label }}</span>
                <div class="esg-bar-wrap">
                  <div class="esg-bar" :style="{ width: entry.score + '%', background: pillarColor(idx) }"></div>
                </div>
                <span class="esg-val font-mono">{{ entry.score.toFixed(1) }}</span>
              </div>
            </div>
            <!-- ESG 框架 fallback：顯示 E/S/G 三維（無 category_scores 時，僅限 ESG 框架） -->
            <div v-else-if="scoringFramework === 'ESG' && (saq.score_e != null || saq.score_s != null || saq.score_g != null)" style="margin-top:10px;border-top:1px solid var(--border);padding-top:10px;">
              <div class="esg-row">
                <span class="esg-cat esg-e">E</span>
                <div class="esg-bar-wrap">
                  <div class="esg-bar" :style="{ width: (saq.score_e ?? 0) + '%' }"></div>
                </div>
                <span class="esg-val font-mono">{{ saq.score_e != null ? saq.score_e.toFixed(1) : '—' }}</span>
              </div>
              <div class="esg-row">
                <span class="esg-cat esg-s">S</span>
                <div class="esg-bar-wrap">
                  <div class="esg-bar esg-bar-s" :style="{ width: (saq.score_s ?? 0) + '%' }"></div>
                </div>
                <span class="esg-val font-mono">{{ saq.score_s != null ? saq.score_s.toFixed(1) : '—' }}</span>
              </div>
              <div class="esg-row">
                <span class="esg-cat esg-g">G</span>
                <div class="esg-bar-wrap">
                  <div class="esg-bar esg-bar-g" :style="{ width: (saq.score_g ?? 0) + '%' }"></div>
                </div>
                <span class="esg-val font-mono">{{ saq.score_g != null ? saq.score_g.toFixed(1) : '—' }}</span>
              </div>
            </div>

            <!-- 細部說明展開區 -->
            <div v-if="showScoreDetail" class="score-detail-wrap">
              <div v-for="group in esgBreakdown" :key="group.cat" class="score-detail-group">
                <!-- 分類標題 + 加權平均小計 -->
                <div class="score-detail-cat-header">
                  <span class="score-detail-cat" :style="{ color: group.color }">{{ group.label }}</span>
                  <span v-if="group.catScore != null" class="score-detail-cat-avg font-mono" :style="{ color: group.color }">
                    加權平均 {{ group.catScore.toFixed(1) }}
                  </span>
                  <span v-else-if="group.cat === '—'" class="score-detail-cat-note">不計入框架分類</span>
                </div>
                <div
                  v-for="item in group.items"
                  :key="item.question.id"
                  class="score-detail-row"
                >
                  <span class="score-detail-q">{{ item.question.question_text }}</span>
                  <span class="score-detail-nums font-mono">
                    <span :class="(item.response?.raw_score ?? 0) > 0 ? 'score-detail-pos' : 'score-detail-zero'">
                      {{ item.question.weight > 0 ? ((item.response?.raw_score ?? 0) / item.question.weight).toFixed(0) : '0' }}
                    </span>
                    <span class="score-detail-sep">×</span>
                    <span>{{ (item.question.weight * 100).toFixed(1) }}%</span>
                    <span class="score-detail-sep">=</span>
                    <span class="score-detail-contrib">{{ (item.response?.raw_score ?? 0).toFixed(2) }}</span>
                  </span>
                </div>
              </div>
              <!-- 總分公式行（框架 pillar 等權） -->
              <div v-if="pillarScoreEntries.length > 1" class="score-detail-formula">
                <span class="score-detail-formula-label">總分計算</span>
                <span class="font-mono" style="font-size:11px;">
                  <template v-for="(entry, idx) in pillarScoreEntries" :key="entry.label">
                    <span v-if="idx > 0"> + </span>
                    <span :style="{ color: pillarColor(idx), fontWeight: 600 }">{{ entry.score.toFixed(1) }}</span>
                  </template>
                  ÷ {{ pillarScoreEntries.length }}
                  = <strong>{{ saq.score.toFixed(1) }}</strong>
                </span>
              </div>
              <!-- ESG 框架 fallback 公式 -->
              <div v-else-if="scoringFramework === 'ESG' && saq.score_e != null && saq.score_s != null && saq.score_g != null && pillarScoreEntries.length === 0" class="score-detail-formula">
                <span class="score-detail-formula-label">總分計算</span>
                <span class="font-mono">
                  <span class="score-detail-e">{{ saq.score_e.toFixed(1) }}</span>×40%
                  + <span class="score-detail-s">{{ saq.score_s.toFixed(1) }}</span>×35%
                  + <span class="score-detail-g">{{ saq.score_g.toFixed(1) }}</span>×25%
                  = <strong>{{ saq.score.toFixed(1) }}</strong>
                </span>
              </div>
              <div v-if="esgBreakdown.length === 0" style="font-size:12px;color:var(--text-secondary);padding:8px 0;">
                尚無計分資料
              </div>
            </div>
          </div>

          <div class="card" style="padding:16px;margin-top:12px;">
            <h3 class="card-section-title">供應商資訊</h3>
            <div class="info-row"><span>代碼</span><span class="font-mono">{{ saq.supplier?.code }}</span></div>
            <div class="info-row"><span>國家</span><span>{{ saq.supplier?.country_code || '—' }}</span></div>
            <div class="info-row"><span>提交時間</span><span>{{ formatTime(saq.submitted_at) || '—' }}</span></div>
            <div class="info-row"><span>截止日</span><span>{{ formatDate(saq.deadline) || '—' }}</span></div>
            <div class="info-row"><span>審核員</span><span>{{ saq.reviewer?.name || '—' }}</span></div>
            <div class="info-row"><span>開始審核</span><span>{{ formatTime(saq.review_started_at) || '—' }}</span></div>
          </div>
        </div>
      </div>
    </template>
    <div v-else class="empty-state" style="padding:60px;text-align:center;">
      <p>問卷不存在或無權限查看</p>
    </div>

    <!-- 審核 Modal -->
    <div v-if="showActionModal" class="modal-overlay" @click.self="showActionModal=false">
      <div class="modal" style="min-width:420px;">
        <div class="modal-header">
          <span class="modal-title">{{ actionType === 'complete' ? '審核通過' : '退回修改' }}</span>
          <button class="modal-close" @click="showActionModal=false">×</button>
        </div>
        <div class="form-group" style="margin:16px 0;">
          <label class="form-label">
            {{ actionType === 'return' ? '退回原因（必填）' : '審核意見（選填）' }}
          </label>
          <textarea
            v-model="actionComment"
            class="form-textarea"
            rows="4"
            :placeholder="actionType === 'return' ? '請說明退回原因...' : '選填審核說明...'"
          />
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showActionModal=false">取消</button>
          <button
            class="btn"
            :class="actionType === 'complete' ? 'btn-primary' : 'btn-danger'"
            :disabled="isActing || (actionType === 'return' && !actionComment.trim())"
            @click="doAction"
          >
            {{ isActing ? '處理中...' : (actionType === 'complete' ? '確認通過' : '確認退回') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { questionnaireApi } from '@/api/modules/questionnaire'
import { saqApi } from '@/api/modules/saq'
import { SAQ_STATUS_LABEL, SAQ_STATUS_BADGE } from '@/utils/saqStatus'
import { maskSupplierName } from '@/utils/maskName'

export default defineComponent({
  name: 'ReviewDetailView',
  setup() { return { router: useRouter(), route: useRoute() } },

  data() {
    return {
      isLoading: false,
      isActing: false,
      isSubmittingReviews: false,
      saq: null as any,
      showActionModal: false,
      actionType: '' as 'complete' | 'return',
      actionComment: '',
      // 逐題覆核
      reviewDraft: {} as Record<string, { score: number | null; reason: string }>,
      reviewExisting: {} as Record<string, any>,
      llmReasonExpanded: {} as Record<string, boolean>,
      showScoreDetail: false,
      showFrameworkInfo: false,
    }
  },

  computed: {
    mergedItems(): Array<{ question: any; response: any }> {
      const questions: any[] = this.saq?.project?.project_questions ?? []
      const responses: any[] = this.saq?.responses ?? []
      const responseMap = Object.fromEntries(responses.map((r: any) => [r.project_question_id, r]))
      return [...questions]
        .sort((a, b) => a.order - b.order)
        .map(q => ({ question: q, response: responseMap[q.id] ?? null }))
    },
    hasConfidenceData(): boolean {
      return this.mergedItems.some((item: any) => item.response?.score_confidence != null)
    },
    sortedHistories(): any[] {
      return [...(this.saq?.review_histories ?? [])].sort(
        (a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
      )
    },
    statusLabel(): Record<string, string> { return SAQ_STATUS_LABEL },
    statusBadge(): Record<string, string> { return SAQ_STATUS_BADGE },

    // 框架 pillar 顏色（循環）
    pillarColors(): string[] {
      return ['#1a4d3e', '#2563eb', '#7c3aed', '#d97706', '#dc2626', '#0891b2', '#16a34a']
    },

    // 從 category_scores 建立 [{label, score}] 陣列，若為空則從 esgBreakdown 本地計算
    pillarScoreEntries(): { label: string; score: number }[] {
      const cs = this.saq?.category_scores
      if (cs && typeof cs === 'object' && Object.keys(cs).length > 0) {
        return Object.entries(cs)
          .filter(([, v]) => typeof v === 'number')
          .map(([label, score]) => ({ label, score: score as number }))
      }
      // Fallback：從 esgBreakdown 本地重算 pillar 加權平均
      return (this as any).esgBreakdown
        .filter((g: any) => g.cat !== '—' && g.catScore != null)
        .map((g: any) => ({ label: g.label, score: g.catScore as number }))
    },

    projectDomainPrefix(): string | null {
      const map: Record<string, string> = {
        esg: 'esg.', iso20400: 'iso20400.', iso26000: 'iso26k.',
        'product-compliance': 'prod_comp.', product_compliance: 'prod_comp.',
        'geo-risk': 'geo_risk.', geo_risk: 'geo_risk.',
      }
      const fw = (this.saq?.project?.template?.scoring_framework ?? '').toLowerCase()
      return map[fw] ?? null
    },

    scoringFramework(): string {
      return this.saq?.project?.template?.scoring_framework ?? ''
    },

    frameworkInfo(): { name: string; desc: string; pillars: string[] } {
      const fw = this.scoringFramework
      const map: Record<string, { name: string; desc: string; pillars: string[] }> = {
        'ESG': {
          name: 'ESG 永續評核框架',
          desc: '從環境（E）、社會（S）、公司治理（G）三大維度評估供應商的永續管理成熟度，加權比例預設 E 40% · S 35% · G 25%。',
          pillars: ['環境管理', '供應鏈環境', '勞工人權', '職場安全', '社區與消費者', '公司治理'],
        },
        'ISO20400': {
          name: 'ISO 20400 永續採購框架',
          desc: '依據 ISO 20400 永續採購國際標準，評估供應商在採購政策、績效評估與風險管理三大採購核心領域的落實程度。',
          pillars: ['採購政策', '績效評估', '風險管理'],
        },
        'ISO26000': {
          name: 'ISO 26000 社會責任框架',
          desc: '依據 ISO 26000 社會責任指引，涵蓋組織治理、人權、勞工、環境、公平營運、消費者及社區七大核心主題。',
          pillars: ['組織治理', '人權', '勞工', '環境', '公平營運', '消費者', '社區'],
        },
        'Geo-Risk': {
          name: '地緣政治風險框架',
          desc: '評估供應商所在地區的地緣政治穩定性與物流韌性，涵蓋地緣政治風險與物流/天災兩大維度。',
          pillars: ['地緣政治風險', '物流/天災'],
        },
        'Product-Compliance': {
          name: '產品合規框架',
          desc: '針對出口產品的法規合規要求，評估供應商在 CBAM、EUDR、化學法規及溯源認證四大領域的準備程度。',
          pillars: ['CBAM', 'EUDR', '化學法規', '溯源與認證'],
        },
      }
      return map[fw] ?? { name: fw, desc: '自訂評核框架', pillars: [] }
    },

    // slug → framework L2 pillar name（與 scoring_service.py 的 SLUG_PREFIX_TO_PILLAR 同步）
    esgBreakdown(): { cat: string; label: string; color: string; catScore: number | null; items: any[] }[] {
      const SLUG_TO_PILLAR: [RegExp, string][] = [
        [/^esg\.env\./, '環境管理'],
        [/^esg\.sc_env\./, '供應鏈環境'],
        [/^esg\.labor\./, '勞工人權'],
        [/^esg\.ohs\./, '職場安全'],
        [/^esg\.comm\./, '社區與消費者'],
        [/^esg\.gov\./, '公司治理'],
        [/^iso20400\.policy\./, '採購政策'],
        [/^iso20400\.perf\./, '績效評估'],
        [/^iso20400\.risk\./, '風險管理'],
        [/^iso26k\.gov\./, '組織治理'],
        [/^iso26k\.hr\./, '人權'],
        [/^iso26k\.labor\./, '勞工'],
        [/^iso26k\.env\./, '環境'],
        [/^iso26k\.fair\./, '公平營運'],
        [/^iso26k\.consumer\./, '消費者'],
        [/^iso26k\.comm\./, '社區'],
        [/^geo_risk\.geopo\./, '地緣政治風險'],
        [/^geo_risk\.logistics\./, '物流/天災'],
        [/^prod_comp\.cbam\./, 'CBAM'],
        [/^prod_comp\.eudr\./, 'EUDR'],
        [/^prod_comp\.chem\./, '化學法規'],
        [/^prod_comp\.trace\./, '溯源與認證'],
      ]
      const slugToPillar = (slug: string): string | null => {
        for (const [re, pillar] of SLUG_TO_PILLAR) {
          if (re.test(slug)) return pillar
        }
        return null
      }

      // framework filter（從範本 scoring_framework 取得）
      const frameworkPrefixMap: Record<string, string> = {
        esg: 'esg.', iso20400: 'iso20400.', iso26000: 'iso26k.',
        'product-compliance': 'prod_comp.', product_compliance: 'prod_comp.',
        'geo-risk': 'geo_risk.', geo_risk: 'geo_risk.',
      }
      const scoringFramework = (this as any).scoringFramework.toLowerCase()
      const frameworkPrefix = frameworkPrefixMap[scoringFramework] ?? null

      // 有 category_scores 時：用 pillar 名稱作為分組 key
      const categoryScores: Record<string, number> = this.saq?.category_scores ?? {}
      const hasCategoryScores = Object.keys(categoryScores).length > 0

      const buckets: Record<string, any[]> = {}
      for (const item of this.mergedItems) {
        if (item.question.scoring_type === 'evidence_only') continue
        if (!item.response) continue
        const bankTags: string[] = (item.question.bank_question?.question_tags ?? []).map((t: any) => t.slug)
        const fallbackTags: string[] = item.question.tags ?? []
        const allSlugs: string[] = bankTags.length > 0 ? bankTags : fallbackTags
        const slugs = frameworkPrefix ? allSlugs.filter((s: string) => s.startsWith(frameworkPrefix)) : allSlugs
        let pillar: string | null = null
        for (const slug of slugs) {
          pillar = slugToPillar(slug)
          if (pillar) break
        }
        const key = pillar ?? '—'
        if (!buckets[key]) buckets[key] = []
        buckets[key].push(item)
      }

      // pillar 顏色（固定 palette）
      const PILLAR_COLORS: Record<string, string> = {
        '環境管理': '#16a34a', '供應鏈環境': '#15803d', '勞工人權': '#2563eb',
        '職場安全': '#1d4ed8', '社區與消費者': '#7c3aed', '公司治理': '#9333ea',
        '採購政策': '#1a4d3e', '績效評估': '#2563eb', '風險管理': '#d97706',
        '組織治理': '#9333ea', '人權': '#2563eb', '勞工': '#1d4ed8',
        '環境': '#16a34a', '公平營運': '#d97706', '消費者': '#7c3aed', '社區': '#0891b2',
        '地緣政治風險': '#dc2626', '物流/天災': '#d97706',
        'CBAM': '#1a4d3e', 'EUDR': '#2563eb', '化學法規': '#16a34a', '溯源與認證': '#7c3aed',
        '—': '#78716c',
      }

      const computeLocalCatScore = (items: any[]): number | null => {
        const scored = items.filter((i: any) => i.question.weight > 0 && i.response?.raw_score != null)
        if (!scored.length) return null
        const totalWeight = scored.reduce((s: number, i: any) => s + i.question.weight, 0)
        const totalRaw = scored.reduce((s: number, i: any) => s + (i.response.raw_score ?? 0), 0)
        return totalWeight > 0 ? Math.round(totalRaw / totalWeight * 10) / 10 : null
      }

      return Object.entries(buckets).map(([pillarKey, items]) => ({
        cat: pillarKey,
        label: pillarKey === '—' ? '不計入框架分類' : pillarKey,
        color: PILLAR_COLORS[pillarKey] ?? '#78716c',
        catScore: hasCategoryScores ? (categoryScores[pillarKey] ?? null) : computeLocalCatScore(items),
        items,
      })).filter(g => g.items.length > 0).sort((a, b) => {
        if (a.cat === '—') return 1
        if (b.cat === '—') return -1
        return 0
      })
    },
    reviewDraftCount(): number {
      return Object.values(this.reviewDraft).filter(d => d.score !== null && d.score !== undefined).length
    },
  },

  mounted() { this.loadSaq() },

  methods: {
    maskSupplierName,
    pillarColor(idx: number): string {
      return (this as any).pillarColors[idx % (this as any).pillarColors.length]
    },

    async loadSaq() {
      this.isLoading = true
      try {
        const { data } = await questionnaireApi.get(this.route.params.id as string)
        this.saq = (data as any).data
        if (['under_review', 're_review', 'finalized', 'completed'].includes(this.saq?.status)) {
          await this.loadReviews()
        }
      } finally {
        this.isLoading = false
      }
    },

    async loadReviews() {
      try {
        const { data } = await saqApi.getResponseReviews(this.saq.id)
        const reviews: any[] = (data as any).data ?? []
        this.reviewExisting = Object.fromEntries(reviews.map((r: any) => [r.project_question_id, r]))
      } catch {}
    },

    onReviewInput(questionId: string, event: any) {
      const val = event.target.value
      if (!this.reviewDraft[questionId]) {
        this.reviewDraft[questionId] = { score: null, reason: '' }
      }
      this.reviewDraft[questionId].score = val === '' ? null : parseFloat(val)
    },

    onReasonInput(questionId: string, event: any) {
      if (!this.reviewDraft[questionId]) {
        this.reviewDraft[questionId] = { score: null, reason: '' }
      }
      this.reviewDraft[questionId].reason = event.target.value
    },

    async doSubmitReviews() {
      const reviews = Object.entries(this.reviewDraft)
        .filter(([, d]) => d.score !== null)
        .map(([pqId, d]) => ({
          project_question_id: pqId,
          reviewer_score: d.score as number,
          reason: d.reason || undefined,
        }))
      if (!reviews.length) return
      this.isSubmittingReviews = true
      try {
        await saqApi.submitResponseReviews(this.saq.id, reviews)
        this.reviewDraft = {}
        await this.loadSaq()
      } finally {
        this.isSubmittingReviews = false }
    },

    async doFinalize() {
      this.isActing = true
      try {
        await saqApi.finalize(this.saq.id)
        await this.loadSaq()
      } finally { this.isActing = false }
    },

    confidenceIcon(c: string | null): string {
      if (c === 'high') return '●'
      if (c === 'medium') return '◐'
      return '⚠'
    },

    confidenceLabel(c: string | null): string {
      if (c === 'high') return '高信心度（規則計分）'
      if (c === 'medium') return '中信心度（LLM 評分）'
      return '低信心度（備援評分）'
    },

    toggleLlmReason(questionId: string) {
      this.llmReasonExpanded[questionId] = !this.llmReasonExpanded[questionId]
    },

    parseTags(tags: any): string[] {
      if (!tags) return []
      const arr: any[] = Array.isArray(tags) ? tags : (() => { try { return JSON.parse(tags) } catch { return [] } })()
      const labels = arr.map((t: any) => typeof t === 'string' ? t : (t?.name || t?.l1_domain || t?.slug || ''))
      return [...new Set(labels.filter(Boolean))]
    },

    frameworkTags(question: any): string[] {
      const prefix = (this as any).projectDomainPrefix as string | null
      // slug → pillar label 映射
      const SLUG_TO_PILLAR: [RegExp, string][] = [
        [/^esg\.env\./, '環境管理'], [/^esg\.sc_env\./, '供應鏈環境'],
        [/^esg\.labor\./, '勞工人權'], [/^esg\.ohs\./, '職場安全'],
        [/^esg\.comm\./, '社區與消費者'], [/^esg\.gov\./, '公司治理'],
        [/^iso20400\.policy\./, '採購政策'], [/^iso20400\.perf\./, '績效評估'], [/^iso20400\.risk\./, '風險管理'],
        [/^iso26k\.gov\./, '組織治理'], [/^iso26k\.hr\./, '人權'], [/^iso26k\.labor\./, '勞工'],
        [/^iso26k\.env\./, '環境'], [/^iso26k\.fair\./, '公平營運'], [/^iso26k\.consumer\./, '消費者'], [/^iso26k\.comm\./, '社區'],
        [/^geo_risk\.geopo\./, '地緣政治'], [/^geo_risk\.logistics\./, '物流/天災'],
        [/^prod_comp\.cbam\./, 'CBAM'], [/^prod_comp\.eudr\./, 'EUDR'],
        [/^prod_comp\.chem\./, '化學法規'], [/^prod_comp\.trace\./, '溯源認證'],
      ]
      const bankSlugs: string[] = (question.bank_question?.question_tags ?? []).map((t: any) => t.slug as string)
      const tagSlugs: string[] = (question.tags ?? []).map((t: any) => typeof t === 'string' ? t : (t?.slug ?? ''))
      const allSlugs: string[] = bankSlugs.length > 0 ? bankSlugs : tagSlugs
      const labels: string[] = []
      for (const slug of allSlugs) {
        let matched = false
        for (const [re, label] of SLUG_TO_PILLAR) {
          if (re.test(slug)) { if (!labels.includes(label)) labels.push(label); matched = true; break }
        }
        if (!matched && slug && !labels.includes(slug)) labels.push(slug)
      }
      return labels
    },

    typeLabel(type: string): string {
      const map: Record<string, string> = {
        boolean: '是/否', single_choice: '單選', multiple_choice: '多選',
        text: '文字', number: '數字',
      }
      return map[type] ?? type
    },

    gradeClass(grade: string | null): string {
      if (!grade) return ''
      const map: Record<string, string> = { A: 'grade-a', B: 'grade-b', C: 'grade-c', D: 'grade-d', E: 'grade-e' }
      return map[grade] ?? ''
    },

    historyClass(action: string): string {
      if (action === 'complete_review') return 'action-complete'
      if (action === 'return_review') return 'action-return'
      if (action === 'submit') return 'action-submit'
      if (action === 'scoring_complete') return 'action-scoring'
      if (action === 'start_review') return 'action-review'
      return 'action-default'
    },

    questionEsgCat(question: any): string | null {
      const slugToCat = (slug: string) => {
        if (/^esg\.env\.|^esg\.sc_env\.|^iso26k\.env\.|^prod_comp\.chem\./.test(slug)) return 'E'
        if (/^esg\.labor\.|^esg\.ohs\.|^esg\.comm\.|^iso20400\.risk\.|^iso26k\.(labor|hr|consumer|comm)\./.test(slug)) return 'S'
        if (/^esg\.gov\.|^iso20400\.(policy|perf)\.|^iso26k\.(fair|gov)\.|^geo_risk\.|^prod_comp\./.test(slug)) return 'G'
        return null
      }
      const bankSlugs: string[] = (question.bank_question?.question_tags ?? []).map((t: any) => t.slug)
      const prefix = this.projectDomainPrefix
      const slugs = prefix ? bankSlugs.filter((s: string) => s.startsWith(prefix)) : bankSlugs
      for (const slug of slugs) {
        const cat = slugToCat(slug)
        if (cat) return cat
      }
      return null
    },

    historyDotClass(action: string): string {
      if (action === 'complete_review') return 'dot-complete'
      if (action === 'return_review') return 'dot-return'
      if (action === 'submit') return 'dot-submit'
      if (action === 'scoring_complete') return 'dot-scoring'
      if (action === 'start_review') return 'dot-review'
      return 'dot-default'
    },

    actionLabel(action: string): string {
      const map: Record<string, string> = {
        send: '發送問卷', submit: '供應商提交', start_review: '開始審核',
        complete_review: '審核通過', return_review: '退回修改', mark_reviewed: '標記複核',
        dispute: '提出申訴', re_review: '開始重審', finalize: '最終確認', reviewer_override: '覆核分更新',
        scoring_complete: 'AI 計分完成',
      }
      return map[action] ?? action
    },

    formatTime(dt: string | null): string {
      if (!dt) return ''
      return new Date(dt).toLocaleString('zh-TW', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
    },

    formatDate(dt: string | null): string {
      if (!dt) return ''
      return new Date(dt).toLocaleDateString('zh-TW')
    },

    openAction(type: 'complete' | 'return') {
      this.actionType = type
      this.actionComment = ''
      this.showActionModal = true
    },

    async doStartReview() {
      this.isActing = true
      try {
        await questionnaireApi.startReview(this.saq.id)
        await this.loadSaq()
      } finally { this.isActing = false }
    },

    async doMarkReviewed() {
      this.isActing = true
      try {
        await questionnaireApi.markReviewed(this.saq.id)
        await this.loadSaq()
      } finally { this.isActing = false }
    },

    async doAction() {
      if (this.actionType === 'return' && !this.actionComment.trim()) return
      this.isActing = true
      try {
        if (this.actionType === 'complete') {
          await questionnaireApi.completeReview(this.saq.id, this.actionComment || undefined)
        } else {
          await questionnaireApi.returnReview(this.saq.id, this.actionComment)
        }
        this.showActionModal = false
        await this.loadSaq()
      } finally { this.isActing = false }
    },
  },
})
</script>

<style scoped>
.card-section-title {
  font-size: 13px; font-weight: 700; color: var(--text-secondary);
  letter-spacing: .03em; text-transform: uppercase;
  margin: 0 0 12px; padding-left: 8px;
  border-left: 3px solid var(--accent, #1a4d3e);
}
.review-layout { display: flex; gap: 20px; margin-top: 16px; align-items: flex-start; }
.questions-col { flex: 2; min-width: 0; display: flex; flex-direction: column; gap: 12px; }
.history-col { flex: 1; min-width: 0; position: sticky; top: 80px; }

.question-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 16px;
}
.question-card.has-answer { border-left: 3px solid var(--accent); }

.q-header { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.q-num { font-size: 12px; color: var(--text-secondary); min-width: 20px; }
.q-type-badge { font-size: 11px; padding: 1px 7px; border-radius: 99px; background: var(--surface-2); color: var(--text-secondary); border: 1px solid var(--border); }
.required-dot { color: #dc2626; font-size: 14px; font-weight: 700; }
.q-text { font-size: 14px; color: var(--text-primary); line-height: 1.5; margin: 0 0 12px; }

.answer-block { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 10px 12px; font-size: 14px; }
.answer-value { font-weight: 600; color: var(--accent); }
.answer-chip { display: inline-block; background: var(--accent); color: #fff; font-size: 12px; padding: 2px 8px; border-radius: 99px; margin: 2px; }
.no-answer { color: var(--text-secondary); font-style: italic; }
.evidence-note { font-size: 12px; color: var(--text-secondary); margin: 8px 0 0; line-height: 1.4; }
.evidence-label { font-weight: 600; color: #92400e; }
.score-row { display: flex; align-items: center; gap: 12px; margin-top: 8px; flex-wrap: wrap; }
.raw-score { font-size: 11px; color: var(--text-secondary); }
.reviewer-panel { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.reviewer-label { font-size: 11px; color: var(--text-secondary); white-space: nowrap; }
.reviewer-input { width: 64px; font-size: 13px; padding: 3px 6px; border: 1px solid var(--border); border-radius: 4px; background: var(--surface); color: var(--text-primary); text-align: right; }
.reviewer-input:focus { outline: none; border-color: var(--accent); }
.reviewer-reason { flex: 1; min-width: 120px; font-size: 12px; padding: 3px 6px; border: 1px solid var(--border); border-radius: 4px; background: var(--surface); color: var(--text-primary); }
.reviewer-badge { font-size: 11px; color: #15803d; background: #dcfce7; padding: 2px 8px; border-radius: 99px; }
.score-block--final { background: #ecfdf5; border-color: #86efac; }

.score-block { display: flex; align-items: center; gap: 8px; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 8px 14px; }
.score-num { font-size: 24px; font-weight: 700; color: var(--text-primary); }
.score-grade { font-size: 18px; font-weight: 700; }
.grade-a { color: #16a34a; }
.grade-b { color: #2563eb; }
.grade-c { color: #d97706; }
.grade-d { color: #dc2626; }
.grade-e { color: #7c3aed; }

/* Timeline */
.timeline { position: relative; padding-left: 0; }
.tl-item { display: flex; gap: 10px; position: relative; padding-bottom: 16px; }
.tl-item:last-child { padding-bottom: 0; }
.tl-item::before {
  content: '';
  position: absolute;
  left: 5px;
  top: 18px;
  bottom: 0;
  width: 1px;
  background: var(--border);
}
.tl-item:last-child::before { display: none; }
.tl-dot {
  width: 11px; height: 11px; border-radius: 50%; flex-shrink: 0;
  margin-top: 4px; border: 2px solid;
}
.dot-complete { background: #dcfce7; border-color: #16a34a; }
.dot-return   { background: #fef3c7; border-color: #d97706; }
.dot-submit   { background: #dbeafe; border-color: #2563eb; }
.dot-scoring  { background: #f3e8ff; border-color: #9333ea; }
.dot-review   { background: #fff7ed; border-color: #ea580c; }
.dot-default  { background: var(--surface-2); border-color: var(--text-secondary); }
.tl-body { flex: 1; min-width: 0; }
.tl-header { display: flex; align-items: center; gap: 6px; margin-bottom: 2px; }
.tl-label { font-size: 12px; font-weight: 600; }
.tl-meta { display: flex; gap: 6px; font-size: 12px; color: #57534e; margin-bottom: 2px; }  /* stone-600 5.3:1 */
.tl-meta span:first-child::after { content: '·'; margin-left: 6px; }
.tl-comment { font-size: 12px; color: #44403c; line-height: 1.5; margin: 4px 0 0; border-left: 2px solid var(--border); padding-left: 8px; }  /* stone-700 */
.tl-stale { opacity: 0.45; }
/* action label colours — WCAG AA on #f5f3ee warm paper */
.action-complete { color: #166534; }  /* green-800  4.8:1 */
.action-return   { color: #92400e; }  /* amber-800  5.1:1 */
.action-submit   { color: #1e40af; }  /* blue-800   5.5:1 */
.action-scoring  { color: #6b21a8; }  /* purple-800 5.0:1 */
.action-review   { color: #9a3412; }  /* orange-800 5.2:1 */
.action-default  { color: #44403c; }  /* stone-700  7.2:1 */

.info-row { display: flex; justify-content: space-between; font-size: 13px; padding: 5px 0; border-bottom: 1px solid var(--border); color: var(--text-secondary); }
.info-row:last-child { border-bottom: none; }
.info-row span:last-child { color: var(--text-primary); font-weight: 500; }

.score-summary-row { display: flex; align-items: center; gap: 8px; padding: 5px 0; }
.score-summary-final { background: #ecfdf5; border-radius: 6px; padding: 6px 8px; margin-bottom: 4px; }
.score-summary-label { font-size: 12px; color: var(--text-secondary); flex: 1; }
.score-summary-val { font-size: 18px; font-weight: 700; color: var(--text-primary); }
.score-summary-grade { font-size: 14px; font-weight: 700; }

.esg-row { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.esg-row:last-child { margin-bottom: 0; }
.esg-cat { font-size: 11px; font-weight: 700; width: 16px; text-align: center; }
.esg-e { color: #16a34a; }
.esg-s { color: #2563eb; }
.esg-g { color: #7c3aed; }
.esg-bar-wrap { flex: 1; height: 6px; background: var(--surface-2); border-radius: 99px; overflow: hidden; }
.esg-bar { height: 100%; background: #16a34a; border-radius: 99px; transition: width 0.4s; }
.esg-bar-s { background: #2563eb; }
.esg-bar-g { background: #7c3aed; }
.esg-val { font-size: 12px; font-weight: 600; color: var(--text-primary); width: 34px; text-align: right; }

/* 分數摘要 header + 細部說明 */
.score-card-header { display: flex; align-items: center; justify-content: space-between; }
.detail-toggle { background: none; border: none; font-size: 12px; color: var(--accent); cursor: pointer; padding: 2px 0; display: flex; align-items: center; gap: 3px; font-weight: 500; }
.detail-toggle:hover { opacity: 0.75; }
.detail-chevron { display: inline-block; transition: transform .2s; font-size: 14px; line-height: 1; }
.detail-chevron.open { transform: rotate(90deg); }
.score-detail-wrap { margin-top: 12px; border-top: 1px solid var(--border); padding-top: 10px; display: flex; flex-direction: column; gap: 12px; }
.score-detail-group {}
.score-detail-cat-header { display: flex; align-items: baseline; gap: 8px; margin-bottom: 4px; }
.score-detail-cat { font-size: 11px; font-weight: 700; letter-spacing: .04em; }
.score-detail-cat-avg { font-size: 11px; font-weight: 600; }
.score-detail-cat-note { font-size: 10px; color: #a8a29e; }
.score-detail-formula { margin-top: 4px; padding-top: 8px; border-top: 1px solid var(--border); display: flex; align-items: baseline; gap: 8px; font-size: 11px; }
.score-detail-formula-label { color: var(--text-secondary); white-space: nowrap; }
.score-detail-formula strong { font-weight: 700; color: var(--text-primary); }
.score-detail-e { color: #16a34a; font-weight: 600; }
.score-detail-s { color: #2563eb; font-weight: 600; }
.score-detail-g { color: #7c3aed; font-weight: 600; }
.score-detail-row { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; padding: 3px 0; border-bottom: 1px solid var(--surface-2); }
.score-detail-row:last-child { border-bottom: none; }
.score-detail-q { font-size: 11px; color: #44403c; flex: 1; line-height: 1.4; }
.score-detail-nums { display: flex; align-items: baseline; gap: 3px; white-space: nowrap; flex-shrink: 0; font-size: 11px; color: var(--text-secondary); }
.score-detail-sep { color: var(--border); }
.score-detail-pos { color: var(--text-primary); font-weight: 600; }
.score-detail-zero { color: #dc2626; font-weight: 600; }
.score-detail-contrib { font-weight: 700; color: var(--text-primary); min-width: 32px; text-align: right; }

.q-tag { font-size: 11px; padding: 1px 7px; border-radius: 99px; background: var(--surface-2); color: var(--text-secondary); border: 1px solid var(--border); white-space: nowrap; }
.q-tag--evidence { background: #fef9c3; color: #854d0e; border-color: #fde68a; }
.q-tag--fw { background: #f0fdf4; color: #166534; border-color: #bbf7d0; font-weight: 500; }
.framework-tag-wrap { display: flex; align-items: center; gap: 6px; }
.framework-chip { font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 6px; background: var(--accent, #1a4d3e); color: #fff; letter-spacing: .02em; }
.framework-help-wrap { position: relative; }
.framework-help-btn { width: 18px; height: 18px; border-radius: 50%; border: 1.5px solid var(--text-secondary); background: none; color: var(--text-secondary); font-size: 11px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; line-height: 1; padding: 0; }
.framework-help-btn:hover { border-color: var(--accent); color: var(--accent); }
.framework-info-popover { position: absolute; top: calc(100% + 8px); left: 0; z-index: 200; background: #fff; border: 1px solid var(--border); border-radius: 10px; padding: 14px 16px; width: 280px; box-shadow: 0 4px 20px rgba(0,0,0,.12); }
.framework-info-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.framework-info-close { background: none; border: none; font-size: 16px; cursor: pointer; color: var(--text-secondary); padding: 0; line-height: 1; }
.framework-info-desc { font-size: 13px; color: var(--text-secondary); margin: 0 0 10px; line-height: 1.6; }
.framework-info-pillars { display: flex; flex-wrap: wrap; gap: 5px; }
.framework-info-pillar { font-size: 11px; padding: 2px 8px; border-radius: 4px; background: var(--surface-2); color: var(--text-primary); border: 1px solid var(--border); }
.q-esg-cat { font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 4px; white-space: nowrap; letter-spacing: .03em; }
.q-esg-e { background: #dcfce7; color: #166534; }
.q-esg-s { background: #dbeafe; color: #1e40af; }
.q-esg-g { background: #ede9fe; color: #6b21a8; }

.evidence-only-hint { margin-top: 8px; font-size: 12px; color: var(--text-secondary); background: var(--surface-2); border-radius: 6px; padding: 6px 10px; font-style: italic; }

.score-legend-bar {
  display: flex; align-items: center; flex-wrap: wrap; gap: 6px;
  font-size: 11px; color: var(--text-secondary);
  background: var(--surface-2); border-radius: 8px;
  padding: 7px 12px; margin-bottom: 12px;
}
.score-legend-item { display: flex; align-items: center; gap: 4px; white-space: nowrap; }
.score-legend-sep { color: var(--border); }
.score-legend-formula { font-family: var(--font-mono, monospace); color: var(--text-tertiary, var(--text-secondary)); letter-spacing: .01em; }

.confidence-badge { font-size: 12px; cursor: default; }
.confidence-high { color: #16a34a; }
.confidence-medium { color: #d97706; }
.confidence-low { color: #dc2626; }

.llm-reason-wrap { width: 100%; margin-top: 4px; }
.llm-reason-toggle { background: none; border: none; font-size: 11px; color: var(--text-secondary); cursor: pointer; padding: 2px 0; }
.llm-reason-toggle:hover { color: var(--text-primary); }
.llm-reason-body { font-size: 12px; color: var(--text-secondary); background: var(--surface-2); border-radius: 4px; padding: 6px 8px; margin-top: 4px; line-height: 1.5; }

.q-score-label { font-size: 11px; color: var(--text-secondary); }
.q-score-val { font-size: 14px; font-weight: 700; color: var(--text-primary); }
.q-score-dimmed { opacity: 0.5; }
.q-weight-label { font-size: 11px; color: var(--text-secondary); }
.q-raw-label { font-size: 11px; color: var(--text-secondary); }
.q-divider { font-size: 12px; color: var(--text-secondary); margin: 0 2px; }
.q-score-label--reviewer { color: #15803d; }
.q-score-val--reviewer { color: #15803d; }
.q-review-reason { font-size: 11px; color: var(--text-secondary); flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>
