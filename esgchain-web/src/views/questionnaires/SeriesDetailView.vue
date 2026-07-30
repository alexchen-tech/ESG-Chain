<template>
  <div class="page-container">
    <!-- 麵包屑 -->
    <div class="breadcrumb">
      <button class="breadcrumb-link" @click="router.push('/questionnaires/series')">評核系列</button>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current">{{ series?.name ?? '載入中...' }}</span>
    </div>

    <div v-if="isLoading" class="loading-mask">載入中...</div>
    <template v-else-if="series">
      <div class="series-header">
        <div class="series-header-main">
          <div class="series-title-row">
            <h1 class="page-title" style="margin:0;">{{ series.name }}</h1>
            <span :class="['badge', series.status === 'active' ? 'badge-green' : 'badge-gray']">
              {{ series.status === 'active' ? '進行中' : '已封存' }}
            </span>
          </div>
          <p v-if="series.description" class="page-subtitle" style="margin-top:4px;">{{ series.description }}</p>
          <div class="series-meta-chips">
            <span class="meta-chip">框架：{{ series.template?.scoring_framework ?? '通用' }}</span>
          </div>
        </div>
      </div>

      <!-- Tab -->
      <div class="tabs" style="margin-bottom:20px;">
        <button v-for="tab in TABS" :key="tab.key" :class="['tab', activeTab === tab.key && 'tab--active']" @click="activeTab = tab.key; onTabChange(tab.key)">
          {{ tab.label }}
        </button>
      </div>

      <!-- 概覽 Tab -->
      <div v-if="activeTab === 'overview'">
        <!-- 統計摘要 -->
        <div v-if="projects.length" class="stat-row">
          <div class="stat-card">
            <span class="stat-label">問卷專案</span>
            <span class="stat-value font-mono">{{ projects.length }}</span>
          </div>
          <div class="stat-card">
            <span class="stat-label">累計 SAQ</span>
            <span class="stat-value font-mono">{{ projects.reduce((s, p) => s + (p.saqs_count ?? 0), 0) }}</span>
          </div>
          <div class="stat-card">
            <span class="stat-label">最近建立</span>
            <span class="stat-value font-mono">{{ projects[0]?.created_at?.slice(0,10) ?? '—' }}</span>
          </div>
        </div>

        <div style="display:flex;justify-content:flex-end;margin-bottom:12px;">
          <button v-if="series?.status === 'active'" class="btn btn-primary btn-sm" @click="openNewWaveModal">＋ 啟動新波次</button>
        </div>
        <div v-if="!projects.length" class="empty-state">
          <p>此系列尚無問卷專案</p>
        </div>
        <table v-else class="data-table">
          <thead>
            <tr>
              <th>專案名稱</th>
              <th>框架</th>
              <th>SAQ 數</th>
              <th>平均分數</th>
              <th style="white-space:nowrap;">截止日期</th>
              <th>狀態</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in projects" :key="p.id" class="table-row clickable" @click="router.push(`/questionnaires/projects/${p.id}`)">
              <td style="font-weight:500;">{{ p.name }}</td>
              <td>{{ p.domain ?? '通用' }}</td>
              <td class="font-mono">{{ p.saqs_count ?? 0 }}</td>
              <td class="font-mono">
                <span v-if="p.avg_score != null" style="display:flex;align-items:center;gap:6px;">
                  {{ p.avg_score.toFixed(1) }}
                  <span class="badge badge-blue" style="font-size:10px;">{{ scoreToGrade(p.avg_score) }}</span>
                </span>
                <span v-else style="color:var(--text-secondary);">—</span>
              </td>
              <td class="font-mono" style="font-size:13px;white-space:nowrap;">{{ fmtDate(p.due_date) }}</td>
              <td>
                <span :class="['badge', PROJECT_STATUS_BADGE[p.status] ?? 'badge-gray']">
                  {{ PROJECT_STATUS_LABEL[p.status] ?? p.status }}
                </span>
              </td>
              <td @click.stop>
                <button class="btn btn-ghost-sm" @click="router.push(`/questionnaires/projects/${p.id}`)">查看 →</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Weight 設定 Tab -->
      <div v-if="activeTab === 'weights'">
        <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px;">
          Weight 設定將套用至此系列的新波次快照。已建立的專案不受影響；如需調整現有專案權重，請進入個別專案的「⚖ 題目權重」。
        </p>
        <div v-if="!weightQuestions.length" class="empty-state">
          <p>請先在此系列下建立問卷專案，才能設定 Weight。</p>
        </div>
        <template v-else>
          <table class="data-table">
            <thead>
              <tr><th>#</th><th>題目</th><th style="width:120px;">Weight</th></tr>
            </thead>
            <tbody>
              <tr v-for="(wq, idx) in weightQuestions" :key="wq.source_template_question_id">
                <td class="font-mono" style="color:var(--text-secondary);">{{ idx + 1 }}</td>
                <td style="font-size:13px;">{{ wq.question_text }}</td>
                <td>
                  <input v-model.number="wq.weight" type="number" step="0.01" min="0" class="form-input font-mono" style="width:90px;padding:4px 8px;" />
                </td>
              </tr>
            </tbody>
          </table>
          <div style="margin-top:16px;display:flex;gap:8px;justify-content:flex-end;">
            <button class="btn btn-primary" :disabled="isSavingWeights" @click="saveWeights">
              {{ isSavingWeights ? '儲存中...' : '儲存 Weight' }}
            </button>
          </div>
        </template>
      </div>

      <!-- 供應商比較 Tab -->
      <div v-if="activeTab === 'comparison'">
        <div class="comparison-filter-panel">
          <div class="comparison-filter-header">
            <span class="comparison-filter-title">
              篩選供應商
              <span v-if="latestProjectName" style="font-weight:400;color:var(--text-secondary);font-size:11px;margin-left:6px;">
                （{{ latestProjectName }} 的參與廠商）
              </span>
            </span>
            <div style="display:flex;gap:8px;align-items:center;">
              <span v-if="isLoadingComparison" style="font-size:12px;color:var(--text-secondary);">查詢中...</span>
              <button v-if="selectedSupplierIds.length" class="btn btn-secondary btn-sm" @click="selectedSupplierIds=[]">清除</button>
              <button class="btn btn-secondary btn-sm" @click="toggleAllSuppliers">
                {{ selectedSupplierIds.length === availableSuppliers.length ? '取消全選' : '全選' }}
              </button>
            </div>
          </div>
          <div v-if="suppliersLoading" style="padding:12px;color:var(--text-secondary);font-size:13px;">載入供應商中...</div>
          <div v-else-if="!availableSuppliers.length" style="padding:12px;color:var(--text-secondary);font-size:13px;">最新波次尚無參與廠商</div>
          <div v-else class="supplier-checkbox-grid">
            <label
              v-for="s in availableSuppliers"
              :key="s.id"
              :class="['supplier-chip', selectedSupplierIds.includes(s.id) && 'supplier-chip--selected']"
            >
              <input type="checkbox" :value="s.id" v-model="selectedSupplierIds" style="display:none;" @change="debouncedLoadComparison" />
              {{ maskSupplierName(s.name) }}
            </label>
          </div>
        </div>

        <div v-if="comparisonData">
          <!-- 折線圖 -->
          <div v-if="comparisonData.projects.length > 1" class="score-chart" style="margin-bottom:24px;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
              <span style="font-size:13px;font-weight:600;">總分趨勢</span>
              <span v-if="comparisonData.scoring_model_inconsistent" class="model-warning-chip" title="此系列中部分波次使用了不同的計分模型，虛線段分數不直接可比">⚠ 計分模型不一致</span>
            </div>
            <div class="chart-wrapper">
              <!-- Y 軸標籤 -->
              <div class="chart-y-axis">
                <span>100</span><span>75</span><span>50</span><span>25</span><span>0</span>
              </div>
              <div class="chart-area">
                <!-- 背景格線 -->
                <svg class="chart-svg" viewBox="0 0 1000 200" preserveAspectRatio="none">
                  <!-- 格線 -->
                  <line v-for="y in [0,50,100,150,200]" :key="y" x1="0" :y1="y" x2="1000" :y2="y" stroke="var(--border)" stroke-width="0.5" />
                  <!-- 折線（分段，不同 scoring_model 相鄰波次用虛線） -->
                  <g v-for="(sup, si) in comparisonData.suppliers" :key="sup.supplier_id">
                    <!-- 分段線段 -->
                    <template v-for="seg in buildSegments(sup, si)" :key="seg.key">
                      <line
                        :x1="seg.x1" :y1="seg.y1" :x2="seg.x2" :y2="seg.y2"
                        :stroke="seg.color"
                        stroke-width="2.5"
                        :stroke-dasharray="seg.dashed ? '8,5' : ''"
                        stroke-linecap="round"
                      />
                      <!-- ⚠ 中點警示 -->
                      <text
                        v-if="seg.dashed && si === 0"
                        :x="(seg.x1 + seg.x2) / 2"
                        :y="(seg.y1 + seg.y2) / 2 - 8"
                        text-anchor="middle"
                        font-size="14"
                        fill="#d97706"
                      >⚠<title>此段波次使用不同計分模型，分數不直接可比</title></text>
                    </template>
                    <!-- 資料點 -->
                    <circle
                      v-for="(proj, pi) in comparisonData.projects" :key="proj.id"
                      :cx="chartX(pi, comparisonData.projects.length)"
                      :cy="chartY(sup.scores_by_project[proj.id]?.total_score)"
                      r="5"
                      :fill="CHART_COLORS[si % CHART_COLORS.length]"
                      stroke="#fff"
                      stroke-width="1.5"
                    >
                      <title>{{ maskSupplierName(sup.supplier_name) }}: {{ sup.scores_by_project[proj.id]?.total_score?.toFixed(1) ?? '—' }}</title>
                    </circle>
                  </g>
                </svg>
                <!-- X 軸標籤 -->
                <div class="chart-x-labels">
                  <span v-for="p in comparisonData.projects" :key="p.id">{{ p.name.slice(0,12) }}</span>
                </div>
              </div>
            </div>
            <!-- 圖例 -->
            <div style="display:flex;gap:16px;margin-top:10px;flex-wrap:wrap;">
              <div v-for="(sup, si) in comparisonData.suppliers" :key="sup.supplier_id" style="display:flex;align-items:center;gap:6px;font-size:12px;">
                <span :style="{ width:'20px', height:'3px', background: CHART_COLORS[si % CHART_COLORS.length], display:'inline-block', borderRadius:'2px' }"></span>
                {{ sup.supplier_name ? maskSupplierName(sup.supplier_name) : sup.supplier_id.slice(0, 8) }}
              </div>
            </div>
          </div>

          <!-- 逐題跨波次趨勢矩陣（最近三波） -->
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;flex-wrap:wrap;">
            <span style="font-size:13px;font-weight:600;">逐題分數趨勢</span>
            <span style="font-size:11px;color:var(--text-secondary);">顯示最近 {{ trendProjects.length }} 波次</span>
            <span class="trend-legend"><span class="trend-legend-dot trend-legend-up"></span>進步</span>
            <span class="trend-legend"><span class="trend-legend-dot trend-legend-down"></span>退步</span>
            <span v-if="allScoresNull" class="trend-no-data-note">逐題分數由 AI 計分後顯示，目前尚無資料</span>
          </div>
          <div style="overflow-x:auto;">
            <table class="data-table trend-table">
              <thead>
                <tr>
                  <th class="trend-q-col">題目</th>
                  <th
                    v-for="sup in comparisonData.suppliers"
                    :key="sup.supplier_id"
                    class="trend-sup-col"
                  >
                    <div class="trend-sup-name" :title="sup.supplier_name ? maskSupplierName(sup.supplier_name) : sup.supplier_id">
                      {{ sup.supplier_name ? maskSupplierName(sup.supplier_name) : sup.supplier_id.slice(0,10) }}
                    </div>
                    <div class="trend-wave-labels">
                      <span v-for="p in trendProjects" :key="p.id" :title="p.name">
                        {{ waveLabel(p.name) }}
                      </span>
                    </div>
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(trend, ti) in allQuestionTrends" :key="trend.source_template_question_id ?? trend.question_text" :class="ti % 2 === 0 ? 'trend-row-even' : 'trend-row-odd'">
                  <td class="trend-q-col trend-q-text" :title="trend.question_text">
                    <span class="trend-q-index">{{ ti + 1 }}.</span> {{ trend.question_text }}
                  </td>
                  <td v-for="sup in comparisonData.suppliers" :key="sup.supplier_id" class="trend-cell">
                    <span
                      v-for="(proj, pi) in trendProjects"
                      :key="proj.id"
                      class="trend-score font-mono"
                      :class="[trendScoreClass(sup, trend.source_template_question_id, trendProjectOffset + pi), getQuestionScore(sup, trend.source_template_question_id, proj.id) == null ? 'trend-score--null' : 'trend-score--has-value']"
                      :title="`${proj.name}: ${getQuestionScore(sup, trend.source_template_question_id, proj.id) ?? '無資料'}`"
                    >{{ getQuestionScore(sup, trend.source_template_question_id, proj.id) != null
                        ? Number(getQuestionScore(sup, trend.source_template_question_id, proj.id)).toFixed(1)
                        : '—' }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div v-else-if="!isLoadingComparison" class="empty-state">選擇供應商後點擊「查詢」</div>
      </div>

      <!-- 計分設定 Tab -->
      <div v-if="activeTab === 'scoring'" style="max-width:640px;">
        <div v-if="!scoringConfig" style="padding:32px;text-align:center;color:var(--text-secondary);">載入中...</div>
        <template v-else>
          <div style="margin-bottom:20px;">
            <div style="font-size:13px;color:var(--text-secondary);margin-bottom:4px;">評核框架</div>
            <span class="badge badge-gray">{{ series?.template?.scoring_framework ?? '—' }}</span>
            <span style="font-size:12px;color:var(--text-secondary);margin-left:8px;">繼承自範本，唯讀</span>
          </div>

          <!-- 構面加權 -->
          <div v-if="availablePillars.length" style="margin-bottom:24px;">
            <div class="weights-section-title">構面加權</div>
            <table class="data-table" style="margin-bottom:8px;">
              <thead>
                <tr>
                  <th style="min-width:140px;">構面</th>
                  <th style="width:100px;">比例 %</th>
                  <th>視覺</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="p in availablePillars" :key="p.slug">
                  <td style="font-size:13px;">{{ p.label }}</td>
                  <td>
                    <input
                      v-model.number="scoringForm.pillar_weights[p.slug]"
                      type="number" min="0" max="100"
                      class="form-input font-mono"
                      style="width:72px;padding:4px 8px;font-size:13px;"
                    />
                  </td>
                  <td>
                    <div style="height:8px;background:var(--surface-2);border-radius:4px;width:200px;overflow:hidden;">
                      <div :style="{width: Math.min(scoringForm.pillar_weights[p.slug]||0, 100)+'%', height:'100%', background:'var(--accent)', borderRadius:'4px', transition:'width .2s'}"></div>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
            <div style="display:flex;align-items:center;gap:12px;">
              <span :style="{fontSize:'13px', fontWeight:'600', color: (pillarWeightSum>=99&&pillarWeightSum<=101)?'#166534':'#991b1b'}">
                合計：{{ pillarWeightSum }}%{{ (pillarWeightSum>=99&&pillarWeightSum<=101) ? ' ✓' : '（須等於 100%）' }}
              </span>
              <button class="btn btn-secondary btn-sm" @click="resetToEqualWeight">重設為等權</button>
            </div>
          </div>

          <!-- 六維度加權 -->
          <div style="margin-bottom:24px;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
              <span class="weights-section-title" style="margin-bottom:0;">六維度加權</span>
              <span
                :style="{
                  fontSize:'11px', padding:'2px 8px', borderRadius:'10px', fontWeight:'600',
                  background: scoringConfig?.dim_weights_source === 'custom' ? 'color-mix(in srgb,#1a4d3e 15%,transparent)' : 'var(--surface-2)',
                  color: scoringConfig?.dim_weights_source === 'custom' ? '#1a4d3e' : 'var(--text-secondary)',
                }"
              >{{ scoringConfig?.dim_weights_source === 'custom' ? '自訂' : '系統預設' }}</span>
            </div>
            <table class="data-table" style="margin-bottom:8px;">
              <thead>
                <tr>
                  <th style="width:40px;">軸</th>
                  <th>維度</th>
                  <th style="width:100px;">比例 %</th>
                  <th>視覺</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="dim in DIM_LIST" :key="dim.key">
                  <td><span class="font-mono" style="font-size:12px;font-weight:700;color:var(--accent);">{{ dim.key }}</span></td>
                  <td style="font-size:13px;">{{ dim.label }}</td>
                  <td>
                    <input
                      v-model.number="scoringForm.dim_weights[dim.key]"
                      type="number" min="0" max="100"
                      class="form-input font-mono"
                      style="width:72px;padding:4px 8px;font-size:13px;"
                    />
                  </td>
                  <td>
                    <div style="height:8px;background:var(--surface-2);border-radius:4px;width:160px;overflow:hidden;">
                      <div :style="{width: Math.min(scoringForm.dim_weights[dim.key]||0, 100)+'%', height:'100%', background:'var(--accent)', borderRadius:'4px', transition:'width .2s'}"></div>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
            <div style="display:flex;align-items:center;gap:12px;">
              <span :style="{fontSize:'13px', fontWeight:'600', color: (dimWeightSum>=99&&dimWeightSum<=101)?'#166534':'#991b1b'}">
                合計：{{ dimWeightSum }}%{{ (dimWeightSum>=99&&dimWeightSum<=101) ? ' ✓' : '（須等於 100%）' }}
              </span>
              <button class="btn btn-secondary btn-sm" :disabled="isLoadingSystemDefaults" @click="resetDimToSystemDefaults">
                {{ isLoadingSystemDefaults ? '載入中…' : '恢復系統預設' }}
              </button>
            </div>
          </div>

          <!-- E4 α 滑桿 -->
          <div style="margin-bottom:24px;padding:16px;background:var(--surface-2,#f0ede6);border:1px solid var(--border);border-radius:8px;">
            <div class="weights-section-title">E4 地緣風險 — 客觀比例設定（α）</div>
            <div v-if="!hasSubScoresData" style="font-size:13px;color:#b45309;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:8px 12px;margin-bottom:12px;">
              ⚠ 尚無國家風險 sub_scores 資料，純客觀路徑與混合路徑將 fallback 至 geo_risk 欄位。
            </div>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
              <span style="font-size:12px;color:var(--text-secondary);min-width:80px;">客觀（國家評等）</span>
              <input
                v-model.number="scoringForm.e4_objective_ratio_pct"
                type="range"
                min="0" max="100" step="5"
                style="flex:1;"
                @input="onE4RatioChange"
              />
              <span style="font-size:12px;color:var(--text-secondary);min-width:80px;text-align:right;">主觀（SAQ 自填）</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:13px;">
              <span class="font-mono" style="color:var(--accent);font-weight:700;">{{ scoringForm.e4_objective_ratio_pct }}%</span>
              <span style="color:var(--text-secondary);">主觀佔比 {{ 100 - scoringForm.e4_objective_ratio_pct }}%</span>
            </div>
          </div>

          <!-- 等級閾值 -->
          <div style="margin-bottom:24px;">
            <div class="weights-section-title">評核等級閾值</div>
            <div style="font-size:12px;color:var(--text-secondary);margin-bottom:10px;">得分達到或超過該數值即評定為對應等級</div>
            <div style="display:flex;gap:12px;align-items:flex-end;">
              <div v-for="grade in ['a','b','c','d']" :key="grade" class="form-group" style="flex:1;margin:0;">
                <label class="form-label">{{ grade.toUpperCase() }} 級</label>
                <input v-model.number="scoringForm[`grade_${grade}` as any]" type="number" min="0" max="100" class="form-input font-mono" />
              </div>
            </div>
          </div>

          <!-- 儲存 -->
          <div style="display:flex;align-items:center;gap:12px;">
            <button
              class="btn btn-primary"
              :disabled="isSavingScoring || (availablePillars.length > 0 && (pillarWeightSum < 99 || pillarWeightSum > 101)) || (dimWeightSum < 99 || dimWeightSum > 101)"
              @click="saveScoringConfig"
            >
              {{ isSavingScoring ? '儲存中...' : '儲存計分設定' }}
            </button>
            <span v-if="scoringSaved" style="font-size:13px;color:#166534;">
              ✓ 設定已儲存。已完成的問卷分數不會自動重算。
            </span>
          </div>
        </template>
      </div>
    </template>
  </div>

  <!-- 啟動新波次 Modal -->
  <div v-if="showNewWaveModal" class="modal-overlay" @click.self="showNewWaveModal = false">
    <div class="modal" style="min-width:460px;">
      <div class="modal-header">
        <span class="modal-title">啟動新波次</span>
        <button class="modal-close" @click="showNewWaveModal = false">×</button>
      </div>
      <div style="display:flex;flex-direction:column;gap:14px;padding:4px 0 8px;">
        <div style="display:flex;flex-direction:column;gap:4px;">
          <label style="font-size:13px;font-weight:500;">波次名稱 *</label>
          <input v-model="newWaveForm.name" class="form-input" maxlength="120" :placeholder="suggestedWaveName" />
        </div>
        <div style="display:flex;flex-direction:column;gap:4px;">
          <label style="font-size:13px;font-weight:500;">截止日期</label>
          <input v-model="newWaveForm.due_date" type="date" class="form-input" />
        </div>
        <div style="font-size:12px;color:var(--text-secondary);background:var(--surface-2);border-radius:6px;padding:10px 12px;">
          沿用範本：<strong>{{ lastProjectTemplateName }}</strong><br>
          建立後自動歸入此系列，題目 weight 繼承範本設定。
        </div>
      </div>
      <div class="modal-footer" style="justify-content:flex-end;">
        <button class="btn btn-secondary" @click="showNewWaveModal = false">取消</button>
        <button class="btn btn-primary" :disabled="!newWaveForm.name.trim() || isCreatingWave" @click="doCreateWave">
          {{ isCreatingWave ? '建立中...' : '建立波次' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import {
  assessmentSeriesApi,
  saqProjectApi,
  type AssessmentSeries,
  type AssessmentSeriesWeight,
  type SeriesComparisonResponse,
  type SeriesSupplierComparison,
  type SeriesQuestionTrend,
} from '@/api/modules/saq'
import { saqApi } from '@/api/modules/saq'
import { suppliersApi } from '@/api/modules/suppliers'
import { settingsApi } from '@/api/modules/settings'
import { maskSupplierName } from '@/utils/maskName'

const TABS = [
  { key: 'overview', label: '概覽' },
  { key: 'weights', label: '加權設定' },
  { key: 'comparison', label: '供應商比較' },
  { key: 'scoring', label: '計分設定' },
]

const FRAMEWORK_PILLARS: Record<string, Array<{ slug: string; label: string }>> = {
  ESG: [
    { slug: 'esg.env',     label: '環境管理' },
    { slug: 'esg.sc_env',  label: '供應鏈環境' },
    { slug: 'esg.labor',   label: '勞工人權' },
    { slug: 'esg.ohs',     label: '職場安全' },
    { slug: 'esg.comm',    label: '社區與消費者' },
    { slug: 'esg.gov',     label: '公司治理' },
  ],
  ISO20400: [
    { slug: 'iso20400.policy', label: '採購政策' },
    { slug: 'iso20400.perf',   label: '績效評估' },
    { slug: 'iso20400.risk',   label: '風險管理' },
  ],
  ISO26000: [
    { slug: 'iso26k.gov',      label: '組織治理' },
    { slug: 'iso26k.hr',       label: '人權' },
    { slug: 'iso26k.labor',    label: '勞工' },
    { slug: 'iso26k.env',      label: '環境' },
    { slug: 'iso26k.fair',     label: '公平營運' },
    { slug: 'iso26k.consumer', label: '消費者' },
    { slug: 'iso26k.comm',     label: '社區' },
  ],
  'Geo-Risk': [
    { slug: 'geo_risk.geopo',      label: '地緣政治風險' },
    { slug: 'geo_risk.logistics',  label: '物流/天災' },
  ],
}

const DIM_LIST = [
  { key: 'E1', label: 'ESG 綜合評估' },
  { key: 'E2', label: 'ISO 20400 永續採購' },
  { key: 'E3', label: 'ISO 26000 社會責任' },
  { key: 'E4', label: '地緣風險評估' },
  { key: 'E5', label: 'ISO 28000 供應鏈安全' },
  { key: 'E6', label: '產品合規' },
]

const CHART_COLORS = ['#1a4d3e', '#f59e0b', '#3b82f6', '#ef4444', '#8b5cf6']

const PROJECT_STATUS_LABEL: Record<string, string> = {
  draft: '草稿',
  active: '進行中',
  closed: '已結案',
}

const PROJECT_STATUS_BADGE: Record<string, string> = {
  draft: 'badge-gray',
  active: 'badge-green',
  closed: 'badge-blue',
}

interface WeightQuestion {
  source_template_question_id: string
  question_text: string
  weight: number
}

export default defineComponent({
  name: 'SeriesDetailView',
  setup() { return { router: useRouter(), route: useRoute(), TABS, CHART_COLORS, PROJECT_STATUS_LABEL, PROJECT_STATUS_BADGE } },

  data() {
    return {
      isLoading: false,
      series: null as AssessmentSeries | null,
      activeTab: 'overview',
      projects: [] as any[],
      weightQuestions: [] as WeightQuestion[],
      isSavingWeights: false,
      availableSuppliers: [] as Array<{ id: string; name: string }>,
      suppliersLoading: false,
      selectedSupplierIds: [] as string[],
      isLoadingComparison: false,
      comparisonData: null as SeriesComparisonResponse | null,
      comparisonTimer: null as ReturnType<typeof setTimeout> | null,
      // 新波次
      showNewWaveModal: false,
      isCreatingWave: false,
      newWaveForm: { name: '', due_date: '' },
      // 計分設定
      scoringConfig: null as {
        pillar_weights: Record<string, number> | null
        grade_thresholds: Record<string, number> | null
        available_pillars: Array<{ slug: string; label: string }>
        dim_weights: Record<string, number> | null
        dim_weights_source: 'default' | 'custom'
      } | null,
      scoringForm: {
        pillar_weights: {} as Record<string, number>,
        dim_weights: { E1: 25, E2: 15, E3: 20, E4: 15, E5: 10, E6: 15 } as Record<string, number>,
        e4_objective_ratio_pct: 40,
        grade_a: 80, grade_b: 60, grade_c: 40, grade_d: 20,
      },
      isSavingScoring: false,
      scoringSaved: false,
      isLoadingSystemDefaults: false,
      hasSubScoresData: true,
      DIM_LIST,
    }
  },

  computed: {
    allQuestionTrends(): SeriesQuestionTrend[] {
      if (!this.comparisonData) return []
      const map = new Map<string, SeriesQuestionTrend>()
      for (const sup of this.comparisonData.suppliers) {
        for (const t of sup.question_trends) {
          if (!map.has(t.source_template_question_id)) {
            map.set(t.source_template_question_id, t)
          }
        }
      }
      return Array.from(map.values())
    },
    // 最近三波次（時間舊→新）
    trendProjects(): any[] {
      if (!this.comparisonData) return []
      return this.comparisonData.projects.slice(-3)
    },
    trendProjectOffset(): number {
      if (!this.comparisonData) return 0
      return Math.max(0, this.comparisonData.projects.length - 3)
    },
    allScoresNull(): boolean {
      if (!this.comparisonData) return false
      return this.comparisonData.suppliers.every(sup =>
        sup.question_trends.every(t =>
          Object.values(t.scores).every(v => v == null)
        )
      )
    },
    latestProjectName(): string {
      return this.projects[0]?.name ?? ''
    },
    suggestedWaveName(): string {
      const now = new Date()
      const y = now.getFullYear()
      const q = Math.ceil((now.getMonth() + 1) / 3)
      return `${y} Q${q} ${this.series?.domain ?? 'ESG'} 評核`
    },
    lastProjectTemplateName(): string {
      const last = this.projects[0]
      return last?.template?.name ?? last?.template_id?.slice(0, 8) ?? '—'
    },
    availablePillars(): Array<{ slug: string; label: string }> {
      const fw = this.series?.template?.scoring_framework ?? ''
      return FRAMEWORK_PILLARS[fw] ?? []
    },
    pillarWeightSum(): number {
      return Math.round(Object.values(this.scoringForm.pillar_weights).reduce((s, v) => s + (v || 0), 0))
    },
    dimWeightSum(): number {
      return Math.round(DIM_LIST.reduce((s, d) => s + (this.scoringForm.dim_weights[d.key] || 0), 0))
    },
  },

  mounted() { this.loadAll() },

  methods: {
    maskSupplierName,
    async loadAll() {
      this.isLoading = true
      const id = this.route.params.id as string
      try {
        const [sRes, pRes] = await Promise.all([
          assessmentSeriesApi.get(id),
          assessmentSeriesApi.getProjects(id),
        ])
        this.series = sRes.data.data
        this.projects = pRes.data.data
        this.buildAvailableSuppliers()
      } finally { this.isLoading = false }
    },

    buildAvailableSuppliers() { /* 改由 loadAvailableSuppliers 處理 */ },

    async loadAvailableSuppliers() {
      // 取最新波次（projects 排序 DESC，[0] 最新）的參與廠商
      const latestProject = this.projects[0]
      if (!latestProject) return
      this.suppliersLoading = true
      try {
        const { data } = await saqProjectApi.saqs(latestProject.id)
        const seen = new Set<string>()
        this.availableSuppliers = (data.data ?? [])
          .filter((s: any) => s.supplier && !seen.has(s.supplier_id) && seen.add(s.supplier_id))
          .map((s: any) => ({ id: s.supplier_id, name: s.supplier?.name ?? s.supplier_id.slice(0, 8) }))
      } catch { /* ignore */ } finally {
        this.suppliersLoading = false
      }
    },

    async onTabChange(tab: string) {
      if (tab === 'weights') await this.loadWeights()
      if (tab === 'comparison') await this.loadAvailableSuppliers()
      if (tab === 'scoring') await this.loadScoringConfig()
    },

    async loadWeights() {
      const seriesId = this.route.params.id as string
      const firstProject = this.projects[0]
      if (!firstProject) return
      try {
        const [wRes, pqRes] = await Promise.all([
          assessmentSeriesApi.getWeights(seriesId),
          saqProjectApi.questions(firstProject.id),
        ])
        const existingWeights = wRes.data.data as AssessmentSeriesWeight[]
        const weightMap = new Map(existingWeights.map(w => [w.source_template_question_id, w.weight]))

        this.weightQuestions = pqRes.data.data
          .map((q: any) => ({
            source_template_question_id: q.source_template_question_id ?? null,
            question_text: q.question_text,
            weight: (q.source_template_question_id && weightMap.get(q.source_template_question_id)) ?? q.weight ?? 1,
          }))
      } catch { /**/ }
    },

    async saveWeights() {
      this.isSavingWeights = true
      const id = this.route.params.id as string
      try {
        const saveable = this.weightQuestions.filter(wq => wq.source_template_question_id)
        if (!saveable.length) { alert('此系列的題目尚未連結範本，無法儲存 Weight。'); return }
        await assessmentSeriesApi.setWeights(id, saveable.map(wq => ({
          source_template_question_id: wq.source_template_question_id!,
          weight: wq.weight,
        })))
        alert('Weight 已儲存')
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '儲存失敗')
      } finally { this.isSavingWeights = false }
    },

    debouncedLoadComparison() {
      if (this.comparisonTimer) clearTimeout(this.comparisonTimer)
      if (!this.selectedSupplierIds.length) { this.comparisonData = null; return }
      this.comparisonTimer = setTimeout(() => this.loadComparison(), 400)
    },

    toggleAllSuppliers() {
      if (this.selectedSupplierIds.length === this.availableSuppliers.length) {
        this.selectedSupplierIds = []
        this.comparisonData = null
      } else {
        this.selectedSupplierIds = this.availableSuppliers.map(s => s.id)
        this.loadComparison()
      }
    },

    async loadComparison() {
      if (!this.selectedSupplierIds.length) return
      this.isLoadingComparison = true
      const id = this.route.params.id as string
      try {
        const { data } = await assessmentSeriesApi.getComparison(id, this.selectedSupplierIds)
        this.comparisonData = data.data
      } finally { this.isLoadingComparison = false }
    },

    getSupplierTrendScore(sup: SeriesSupplierComparison, questionId: string): string {
      for (const t of sup.question_trends) {
        if (t.source_template_question_id === questionId) {
          const latestProjectId = this.comparisonData?.projects.at(-1)?.id
          if (latestProjectId) {
            const score = t.scores[latestProjectId]
            return score != null ? score.toFixed(2) : '—'
          }
        }
      }
      return '—'
    },

    getQuestionScore(sup: SeriesSupplierComparison, questionId: string | null, projectId: string): number | null {
      const trend = sup.question_trends.find(t => t.source_template_question_id === questionId)
      if (!trend) return null
      const score = trend.scores[projectId]
      return score != null ? score : null
    },

    trendScoreClass(sup: SeriesSupplierComparison, questionId: string | null, absoluteIndex: number): string {
      if (!this.comparisonData || absoluteIndex === 0) return ''
      const projects = this.comparisonData.projects
      if (absoluteIndex >= projects.length) return ''
      const curr = this.getQuestionScore(sup, questionId, projects[absoluteIndex].id)
      const prev = this.getQuestionScore(sup, questionId, projects[absoluteIndex - 1].id)
      if (curr == null || prev == null) return ''
      if (curr > prev) return 'trend-up'
      if (curr < prev) return 'trend-down'
      return ''
    },

    openNewWaveModal() {
      const last = this.projects[0]
      this.newWaveForm = {
        name: this.suggestedWaveName,
        due_date: '',
      }
      this.showNewWaveModal = true
    },

    async doCreateWave() {
      if (!this.series || !this.newWaveForm.name.trim()) return
      const lastProject = this.projects[0]
      if (!lastProject?.template_id) {
        alert('找不到範本，請至問卷專案頁手動建立')
        return
      }
      this.isCreatingWave = true
      try {
        const payload: any = {
          name: this.newWaveForm.name.trim(),
          template_id: lastProject.template_id,
          series_id: this.series.id,
        }
        if (this.newWaveForm.due_date) payload.due_date = this.newWaveForm.due_date
        await saqProjectApi.create(payload)
        this.showNewWaveModal = false
        const id = this.route.params.id as string
        const pRes = await assessmentSeriesApi.getProjects(id)
        this.projects = pRes.data.data
        alert('新波次已建立！')
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '建立失敗')
      } finally {
        this.isCreatingWave = false
      }
    },

    chartX(index: number, total: number): number {
      if (total <= 1) return 500
      return (index / (total - 1)) * 900 + 50
    },

    chartY(score: number | null | undefined): number {
      if (score == null) return 100
      return 200 - (score / 100) * 190
    },

    waveLabel(name: string): string {
      // 嘗試抽出 Q1/Q2/H1 等標記，否則取最後6字
      const match = name.match(/Q[1-4]|H[12]|[0-9]{4}/)
      return match ? match[0] : name.slice(-5)
    },

    fmtDate(val: string | null | undefined): string {
      if (!val) return '—'
      return val.slice(0, 10)
    },

    scoreToGrade(score: number | null): string | null {
      if (score == null) return null
      if (score >= 90) return 'A'
      if (score >= 75) return 'B'
      if (score >= 60) return 'C'
      if (score >= 45) return 'D'
      return 'E'
    },

    buildPolylinePoints(sup: SeriesSupplierComparison): string {
      if (!this.comparisonData) return ''
      const pts = this.comparisonData.projects
        .filter(proj => sup.scores_by_project[proj.id]?.total_score != null)
        .map((proj, _, arr) => {
          const allProjects = this.comparisonData!.projects
          const i = allProjects.findIndex(p => p.id === proj.id)
          const x = this.chartX(i, allProjects.length)
          const y = this.chartY(sup.scores_by_project[proj.id]?.total_score)
          return `${x},${y}`
        })
      return pts.join(' ')
    },

    buildSegments(sup: SeriesSupplierComparison, supIndex: number): Array<{
      key: string; x1: number; y1: number; x2: number; y2: number; color: string; dashed: boolean
    }> {
      if (!this.comparisonData) return []
      const projects = this.comparisonData.projects
      const total = projects.length
      const color = (this as any).CHART_COLORS[supIndex % (this as any).CHART_COLORS.length]
      const segments: any[] = []

      for (let i = 0; i < projects.length - 1; i++) {
        const p1 = projects[i]
        const p2 = projects[i + 1]
        const s1 = sup.scores_by_project[p1.id]?.total_score
        const s2 = sup.scores_by_project[p2.id]?.total_score
        if (s1 == null || s2 == null) continue

        const dashed = !!this.comparisonData.scoring_model_inconsistent
          && (p1 as any).scoring_model_id != null
          && (p2 as any).scoring_model_id != null
          && (p1 as any).scoring_model_id !== (p2 as any).scoring_model_id

        segments.push({
          key: `${sup.supplier_id}-${i}`,
          x1: this.chartX(i, total),
          y1: this.chartY(s1),
          x2: this.chartX(i + 1, total),
          y2: this.chartY(s2),
          color,
          dashed,
        })
      }
      return segments
    },

    async loadScoringConfig() {
      const id = this.route.params.id as string
      try {
        const { data } = await assessmentSeriesApi.getScoringConfig(id)
        this.scoringConfig = data.data
        const pillars = this.availablePillars
        const existing = data.data.pillar_weights
        const weights: Record<string, number> = {}
        for (const p of pillars) {
          weights[p.slug] = existing ? Math.round((existing[p.slug] ?? (1 / pillars.length)) * 100) : Math.round(100 / pillars.length)
        }
        this.scoringForm.pillar_weights = weights
        const t = data.data.grade_thresholds
        this.scoringForm.grade_a = t?.A ?? 80
        this.scoringForm.grade_b = t?.B ?? 60
        this.scoringForm.grade_c = t?.C ?? 40
        this.scoringForm.grade_d = t?.D ?? 20
        // 六維度加權
        const dw = data.data.dim_weights
        const dimForm: Record<string, number> = {}
        for (const d of DIM_LIST) {
          dimForm[d.key] = dw ? Math.round((dw[d.key] ?? 0) * 100) : 0
        }
        this.scoringForm.dim_weights = dimForm
        // E4 α
        const e4Ratio = data.data.e4_objective_ratio_effective ?? 0.40
        this.scoringForm.e4_objective_ratio_pct = Math.round(e4Ratio * 100)
        // 檢查是否有 sub_scores 資料
        this.checkSubScoresData()
      } catch { /**/ }
    },

    async checkSubScoresData() {
      try {
        const res = await import('@/api/http').then(m => m.default.get('/api/v1/settings/country-risk-ratings'))
        const ratings = res.data?.data ?? []
        this.hasSubScoresData = ratings.some((r: any) => r.sub_scores != null)
      } catch { this.hasSubScoresData = true }
    },

    onE4RatioChange() {
      // snap to nearest 5
      const pct = this.scoringForm.e4_objective_ratio_pct
      this.scoringForm.e4_objective_ratio_pct = Math.round(pct / 5) * 5
    },

    async resetDimToSystemDefaults() {
      this.isLoadingSystemDefaults = true
      try {
        const res = await settingsApi.getDimWeightDefaults()
        const defaults = res.data?.dim_weights ?? {}
        const dimForm: Record<string, number> = {}
        for (const d of DIM_LIST) {
          dimForm[d.key] = Math.round((defaults[d.key] ?? 0) * 100)
        }
        this.scoringForm.dim_weights = dimForm
      } catch { /**/ } finally {
        this.isLoadingSystemDefaults = false
      }
    },

    resetToEqualWeight() {
      const pillars = this.availablePillars
      const eq = Math.round(100 / pillars.length)
      const weights: Record<string, number> = {}
      for (const p of pillars) weights[p.slug] = eq
      this.scoringForm.pillar_weights = weights
    },

    async saveScoringConfig() {
      if (this.pillarWeightSum < 99 || this.pillarWeightSum > 101) return
      if (this.dimWeightSum < 99 || this.dimWeightSum > 101) return
      const { grade_a, grade_b, grade_c, grade_d } = this.scoringForm
      if (!(grade_a > grade_b && grade_b > grade_c && grade_c > grade_d && grade_d > 0)) {
        alert('閾值須遞減且 D > 0（A > B > C > D > 0）')
        return
      }
      this.isSavingScoring = true
      try {
        const pillarWeights: Record<string, number> = {}
        for (const [slug, pct] of Object.entries(this.scoringForm.pillar_weights)) {
          pillarWeights[slug] = pct / 100
        }
        const dimWeights: Record<string, number> = {}
        for (const d of DIM_LIST) {
          dimWeights[d.key] = (this.scoringForm.dim_weights[d.key] || 0) / 100
        }
        await assessmentSeriesApi.updateScoringConfig(this.route.params.id as string, {
          pillar_weights: pillarWeights,
          grade_thresholds: { A: grade_a, B: grade_b, C: grade_c, D: grade_d },
          dim_weights: dimWeights,
          e4_objective_ratio: this.scoringForm.e4_objective_ratio_pct / 100,
        })
        if (this.scoringConfig) this.scoringConfig.dim_weights_source = 'custom'
        this.scoringSaved = true
        setTimeout(() => { this.scoringSaved = false }, 4000)
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '儲存失敗')
      } finally {
        this.isSavingScoring = false
      }
    },
  },
})
</script>

<style scoped>
.weights-section-title {
  font-size: 13px; font-weight: 700; color: var(--text-primary);
  letter-spacing: .03em; margin-bottom: 10px;
  padding-left: 8px; border-left: 3px solid var(--accent, #1a4d3e);
}
/* ── Series Header ─────────────────────────────── */
.series-header {
  margin-bottom: 20px;
  padding-bottom: 20px;
  border-bottom: 1px solid var(--border);
}
.series-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.series-meta-chips {
  display: flex;
  gap: 8px;
  margin-top: 8px;
  flex-wrap: wrap;
}
.meta-chip {
  font-size: 12px;
  color: var(--text-secondary);
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 99px;
  padding: 2px 10px;
}

/* ── Tabs ──────────────────────────────────────── */
.tabs {
  display: flex;
  gap: 0;
  border-bottom: 2px solid var(--border);
  margin-bottom: 20px;
}
.tab {
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  padding: 8px 18px;
  font-size: 13px;
  font-weight: 500;
  color: var(--text-secondary);
  cursor: pointer;
  transition: color 0.15s, border-color 0.15s;
}
.tab:hover { color: var(--text-primary); }
.tab--active {
  color: var(--accent);
  border-bottom-color: var(--accent);
  font-weight: 600;
}

/* ── Stat Row ──────────────────────────────────── */
.stat-row {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}
.stat-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 14px 22px;
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 130px;
}
.stat-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); }
.stat-value { font-size: 22px; font-weight: 700; color: var(--text-primary); line-height: 1.2; margin-top: 2px; }

/* ── Table ─────────────────────────────────────── */
.clickable { cursor: pointer; }
.clickable:hover td { background: var(--surface-2); }

/* ── Comparison Filter ─────────────────────────── */
.comparison-filter-panel {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 16px 20px;
  margin-bottom: 20px;
}
.comparison-filter-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}
.comparison-filter-title { font-size: 13px; font-weight: 600; color: var(--text-primary); }
.comparison-filter-hint { font-size: 12px; color: var(--text-secondary); }

.supplier-checkbox-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.supplier-chip {
  display: inline-flex;
  align-items: center;
  padding: 4px 12px;
  border-radius: 99px;
  border: 1px solid var(--border);
  background: var(--surface-2);
  font-size: 12px;
  color: var(--text-secondary);
  cursor: pointer;
  transition: all 0.12s;
  user-select: none;
}
.supplier-chip:hover {
  border-color: var(--accent);
  color: var(--accent);
}
.supplier-chip--selected {
  background: var(--accent);
  border-color: var(--accent);
  color: #fff;
  font-weight: 500;
}

/* ── Chart ─────────────────────────────────────── */
.chart-wrapper {
  display: flex;
  gap: 6px;
  align-items: stretch;
  background: var(--surface);
  border-radius: 8px;
  padding: 12px 12px 0;
}
.chart-y-axis {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  font-size: 10px;
  color: var(--text-secondary);
  padding-bottom: 20px;
  width: 24px;
  text-align: right;
  flex-shrink: 0;
}
.chart-area {
  flex: 1;
  display: flex;
  flex-direction: column;
}
.chart-svg {
  width: 100%;
  height: 160px;
  display: block;
}
.chart-x-labels {
  display: flex;
  justify-content: space-between;
  margin-top: 4px;
  padding-bottom: 8px;
}
.chart-x-labels span {
  font-size: 11px;
  color: var(--text-secondary);
  text-align: center;
  flex: 1;
}

/* ── Trend Matrix ──────────────────────────────── */
.trend-table thead th {
  text-transform: none;
  letter-spacing: 0;
  font-size: 12px;
}
.trend-q-col {
  position: sticky;
  left: 0;
  background: var(--surface);
  z-index: 2;
  min-width: 200px;
  max-width: 240px;
  border-right: 1px solid var(--border);
}
.trend-row-even .trend-q-col { background: var(--surface); }
.trend-row-odd  .trend-q-col { background: var(--surface-2, #f9f8f6); }
.trend-row-odd  { background: var(--surface-2, #f9f8f6); }
.trend-q-text {
  font-size: 13px;
  color: var(--text-primary);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  line-height: 1.4;
}
.trend-q-index {
  color: var(--text-secondary);
  font-size: 11px;
  margin-right: 3px;
  flex-shrink: 0;
}
.trend-sup-col {
  min-width: 110px;
  text-align: center;
  font-weight: 600;
  font-size: 12px;
  color: var(--text-primary);
}
.trend-sup-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 130px;
  margin: 0 auto;
  font-size: 12px;
  line-height: 1.3;
}
.trend-table .trend-cell {
  white-space: nowrap;
  padding: 5px 10px;
  text-align: center;
}
.trend-score {
  display: inline-block;
  font-size: 12px;
  padding: 3px 6px;
  border-radius: 4px;
  margin: 0 2px;
  min-width: 36px;
  text-align: center;
  font-variant-numeric: tabular-nums;
}
.trend-score--null {
  background: transparent;
  color: var(--text-secondary);
  opacity: 0.45;
  font-size: 11px;
}
.trend-score--has-value {
  background: var(--surface-2);
  color: var(--text-primary);
  font-weight: 500;
}
.trend-score.trend-up   { background: #dcfce7; color: #166534; font-weight: 600; }
.trend-score.trend-down { background: #fee2e2; color: #991b1b; font-weight: 600; }

.trend-wave-labels {
  display: flex;
  gap: 4px;
  margin-top: 4px;
  justify-content: center;
  flex-wrap: nowrap;
}
.trend-wave-labels span {
  font-size: 10px;
  color: var(--text-secondary);
  min-width: 36px;
  text-align: center;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-weight: 400;
  background: var(--surface-2);
  border-radius: 3px;
  padding: 1px 3px;
}

/* 趨勢圖例 */
.trend-legend {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: var(--text-secondary);
}
.trend-legend-dot {
  width: 8px;
  height: 8px;
  border-radius: 2px;
  display: inline-block;
}
.trend-legend-up   { background: #bbf7d0; }
.trend-legend-down { background: #fecaca; }
.model-warning-chip { font-size: 11px; background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; padding: 2px 8px; border-radius: 99px; cursor: default; }

/* 無資料提示 */
.trend-no-data-note {
  font-size: 11px;
  color: var(--text-secondary);
  background: var(--surface-2);
  border-radius: 4px;
  padding: 2px 8px;
  border: 1px dashed var(--border);
  margin-left: 4px;
}
</style>
