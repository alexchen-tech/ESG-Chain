<template>
  <div class="rm-root">
    <!-- 計算方式說明 Modal -->
    <Teleport to="body">
      <div v-if="showCalcModal" class="modal-overlay" @click.self="showCalcModal = false">
        <div class="modal-box">
          <div class="modal-header">
            <span class="modal-title">5×5 矩陣計算說明</span>
            <button class="modal-close" @click="showCalcModal = false">✕</button>
          </div>
          <div class="modal-body">
            <div class="impact-ref__grid">
              <div class="impact-ref__col">
                <div class="impact-ref__dim-label">Probability（P）</div>
                <code class="impact-ref__formula">P = max(1, ceil((100 − dim_score) / 20))</code>
                <div class="impact-ref__note">dim_score：E1–E5 各維度合規分（0–100，越高越安全）</div>
                <div class="impact-ref__note">分數越低 → P 越高（最高 5）；100分 → P=1</div>
              </div>
              <div class="impact-ref__col">
                <div class="impact-ref__dim-label">Impact（I）</div>
                <code class="impact-ref__formula">I = 四因子加權（1–5）</code>
                <div class="impact-ref__note">Tier 0.30 ＋ 採購額 0.30 ＋ 單一來源 0.20 ＋ 材料關鍵性 0.20</div>
                <div class="impact-ref__note">業務衝擊（Tier＋採購額）＋ 後果嚴重度（單一來源＋材料關鍵性）</div>
                <div class="impact-ref__note">缺資料因子以中性 3 計；分數越高衝擊越大</div>
              </div>
              <div class="impact-ref__col">
                <div class="impact-ref__dim-label">Cell Score &amp; 風險等級</div>
                <code class="impact-ref__formula">cell_score = P × I（1–25）</code>
                <div class="impact-ref__note">Very Low 1–4　Low 5–9　Medium 10–14</div>
                <div class="impact-ref__note">High 15–19　Extreme 20–25</div>
              </div>
              <div class="impact-ref__col">
                <div class="impact-ref__dim-label">COMPOSITE 綜合最差</div>
                <code class="impact-ref__formula">dim_score = min(E1, E2, E3, E4, E5)</code>
                <div class="impact-ref__note">取五個維度中最低分，呈現最差情況的風險位置</div>
                <div class="impact-ref__note">E6（法規準備）另以四態標記呈現，不納入 P 計算</div>
              </div>
            </div>
            <div class="impact-ref__footer">
              各維度警戒閾值：E1≤40 / E2≤45 / E3≤40 / E4≤35 / E5≤40 / E6≤50
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <div v-if="isLoading" class="loading-mask">載入矩陣資料...</div>
    <div v-else class="matrix-body">
      <div class="matrix-layout">

        <!-- 左側：矩陣 -->
        <div class="matrix-section">

          <!-- 摘要列 -->
          <div class="summary-bar">
            <span class="summary-item extreme">Extreme：{{ summary.extreme_count }}</span>
            <span class="summary-item high">High：{{ summary.high_count }}</span>
            <span class="summary-item medium">Medium：{{ summary.medium_count }}</span>
            <span class="summary-item low">Low：{{ summary.low_count }}</span>
            <span class="summary-item very-low">Very Low：{{ summary.very_low_count }}</span>
            <span class="summary-item total">共 {{ summary.total }} 家</span>
          </div>

          <!-- 維度 Tab -->
          <div class="dim-tabs">
            <button
              v-for="d in DIMENSIONS"
              :key="d.value"
              class="dim-tab"
              :class="{ active: activeDim === d.value }"
              @click="switchDim(d.value)"
            >{{ d.label }}</button>
          </div>

          <!-- 5×5 Grid -->
          <div class="matrix-wrapper">
            <div class="y-axis-label">Probability ↑</div>
            <div class="matrix-container">
              <div class="matrix-row">
                <div class="y-labels">
                  <span v-for="p in [5,4,3,2,1]" :key="p" class="axis-label">{{ p }}</span>
                </div>
                <div class="matrix-inner">
                  <div class="matrix-grid">
                    <div
                      v-for="cell in orderedCells"
                      :key="`${cell.probability}-${cell.impact}`"
                      class="matrix-cell"
                      :class="[cell.risk_level, { 'cell-active': cell.supplier_count > 0 }]"
                      @click="cell.supplier_count > 0 && openCell(cell)"
                    >
                      <template v-if="cell.supplier_count === 0">
                        <span class="cell-empty-score">{{ cell.cell_score }}</span>
                      </template>
                      <template v-else>
                        <span class="cell-count">{{ cell.supplier_count }}</span>
                        <span v-if="cell.open_cap_count > 0" class="cell-cap-row">
                          <span class="cell-cap-icon">!</span>{{ cell.open_cap_count }} CAP
                        </span>
                        <div class="cell-grade-row">
                          <template v-for="(cnt, grade) in cell.saq_grade_dist" :key="grade">
                            <span
                              v-for="n in Math.min(cnt, maxPipsPerGrade(cell, grade))"
                              :key="`${grade}-${n}`"
                              class="grade-pip"
                              :class="`pip-${grade.toLowerCase()}`"
                              :title="`SAQ ${grade} 等級`"
                            ></span>
                          </template>
                          <span
                            v-for="n in Math.min(cell.no_saq_count, maxNoSaqPips(cell))"
                            :key="`nosaq-${n}`"
                            class="grade-pip pip-none"
                            title="尚無 SAQ"
                          ></span>
                        </div>
                      </template>
                    </div>
                  </div>
                  <div class="x-labels">
                    <span v-for="i in [1,2,3,4,5]" :key="i" class="axis-label">{{ i }}</span>
                  </div>
                </div>
              </div>
            </div>
            <div class="x-axis-label">Impact →</div>
          </div>

          <!-- 圖例 -->
          <div class="legend-row">
            <div class="legend-band">
              <span v-for="l in LEVELS" :key="l.value" class="legend-band-item" :class="l.value">{{ l.label }}</span>
            </div>
            <div class="pip-legend">
              <span class="pip-legend-label">SAQ：</span>
              <span v-for="g in ['A','B','C','D','E']" :key="g" class="pip-legend-item">
                <span class="grade-pip" :class="`pip-${g.toLowerCase()}`"></span>{{ g }}
              </span>
              <span class="pip-legend-item">
                <span class="grade-pip pip-none"></span>無
              </span>
              <span class="pip-legend-sep">·</span>
              <span class="pip-legend-item">
                <span class="cell-cap-icon" style="font-size:9px;padding:0 3px;border-radius:2px;">!</span>CAP
              </span>
            </div>
          </div>
        </div>

        <!-- 右側 Panel -->
        <transition name="panel">
          <div v-if="activeCell" class="side-panel">
            <div class="panel-header">
              <div class="panel-header-main">
                <span class="badge" :class="activeCell.risk_level">{{ LEVEL_LABELS[activeCell.risk_level] }}</span>
                <span class="panel-title">{{ activeDimLabel }}</span>
              </div>
              <div class="panel-header-sub">
                P={{ activeCell.probability }} × I={{ activeCell.impact }}
                = <strong>{{ activeCell.cell_score }}</strong>
                · {{ activeCell.supplier_count }} 家供應商
              </div>
              <button class="panel-close" @click="activeCell = null">×</button>
            </div>

            <div v-if="panelLoading" class="panel-loading">載入中...</div>
            <div v-else class="panel-list">
              <div
                v-for="s in panelSuppliers"
                :key="s.id"
                class="sc-card"
                @click="$router.push(`/suppliers/${s.id}`)"
              >
                <div class="sc-head">
                  <div class="sc-name-wrap">
                    <span class="sc-name">{{ s.name }}</span>
                    <span class="sc-meta">{{ s.country_code }} · Tier {{ s.tier }} · <span class="font-mono">{{ s.code || '—' }}</span></span>
                  </div>
                  <div class="sc-head-right">
                    <span class="badge" :class="s.status === 'active' ? 'badge-green' : 'badge-yellow'">{{ s.status }}</span>
                    <button
                      class="sc-compare-btn"
                      :class="{ 'sc-compare-added': compareStore.isAdded(s.id) }"
                      :disabled="!compareStore.canAdd && !compareStore.isAdded(s.id)"
                      :title="!compareStore.canAdd && !compareStore.isAdded(s.id) ? '已達上限 4 家' : (compareStore.isAdded(s.id) ? '點擊移除' : '加入比較')"
                      @click.stop="compareStore.isAdded(s.id) ? compareStore.remove(s.id) : compareStore.add(s)"
                    >{{ compareStore.isAdded(s.id) ? '✓' : '+' }}</button>
                  </div>
                </div>
                <div class="sc-body">
                  <div v-if="s.risk_assessment" class="sc-dims">
                    <div v-for="d in SIX_DIMS" :key="d.key" class="sc-dim" :class="{ 'sc-dim-active': s.worst_dim_key === d.key }">
                      <span class="sc-dim-label">{{ d.label }}</span>
                      <span class="sc-dim-score" :class="dimScoreClass(s.risk_assessment[d.key])">
                        {{ s.risk_assessment[d.key] != null ? s.risk_assessment[d.key] : '—' }}
                      </span>
                    </div>
                  </div>
                  <div v-else class="sc-no-ra">尚無風險評估</div>
                </div>
                <div class="sc-foot">
                  <div class="sc-foot-l">
                    <template v-if="s.saq_score != null">
                      <span class="sc-grade" :class="`grade-${s.saq_grade?.toLowerCase()}`">{{ s.saq_grade }}</span>
                      <span class="sc-score font-mono">{{ Number(s.saq_score).toFixed(1) }}</span>
                    </template>
                    <span v-else class="sc-no-saq">無 SAQ</span>
                    <span v-if="s.open_cap_count > 0" class="sc-cap-tag">⚠ {{ s.open_cap_count }} CAP</span>
                  </div>
                  <div v-if="s.risk_assessment" class="sc-foot-r">
                    <span class="sc-chip" :class="s.risk_assessment.notes?.includes('自動從 SAQ') ? 'chip-auto' : 'chip-manual'">
                      {{ s.risk_assessment.notes?.includes('自動從 SAQ') ? '自動' : '手動' }}
                    </span>
                    <span class="sc-date">{{ fmtDate(s.risk_assessment.assessed_at) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <div class="panel-footer">
              <span class="panel-footer-count">共 {{ panelTotal }} 家</span>
              <div style="display:flex;gap:8px;">
                <button
                  v-if="compareStore.suppliers.length >= 2"
                  class="btn btn-accent btn-sm"
                  @click="$emit('open-compare')"
                >比較 {{ compareStore.suppliers.length }} 家 →</button>
                <button class="btn btn-secondary btn-sm" @click="goToFull">查看全部 →</button>
              </div>
            </div>
          </div>
        </transition>

      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { useRouter } from 'vue-router'
import { riskApi, type RiskMatrixCell, type RiskDimension, type RiskMatrixSummary } from '@/api/modules/risk'
import { useCompareStore } from '@/stores/compareStore'

const SIX_DIMS = [
  { key: 'dim_e1', label: 'E1' },
  { key: 'dim_e2', label: 'E2' },
  { key: 'dim_e3', label: 'E3' },
  { key: 'dim_e4', label: 'E4' },
  { key: 'dim_e5', label: 'E5' },
  { key: 'dim_e6', label: 'E6' },
]

const DIMENSIONS = [
  { value: 'COMPOSITE' as RiskDimension, label: '綜合最差' },
  { value: 'E1'        as RiskDimension, label: 'E1 環境管理' },
  { value: 'E2'        as RiskDimension, label: 'E2 氣候與碳排' },
  { value: 'E3'        as RiskDimension, label: 'E3 社會責任' },
  { value: 'E4'        as RiskDimension, label: 'E4 地緣風險' },
  { value: 'E5'        as RiskDimension, label: 'E5 公司治理' },
]

const LEVELS = [
  { value: 'very_low', label: 'Very Low (1–4)' },
  { value: 'low',      label: 'Low (5–9)' },
  { value: 'medium',   label: 'Medium (10–14)' },
  { value: 'high',     label: 'High (15–19)' },
  { value: 'extreme',  label: 'Extreme (20–25)' },
]

const LEVEL_LABELS: Record<string, string> = {
  very_low: 'Very Low', low: 'Low', medium: 'Medium', high: 'High', extreme: 'Extreme',
}

const DEFAULT_SUMMARY: RiskMatrixSummary = {
  extreme_count: 0, high_count: 0, medium_count: 0, low_count: 0, very_low_count: 0, total: 0,
}

const MAX_PIPS = 6

function scoreToLevel(score: number): RiskMatrixCell['risk_level'] {
  if (score >= 20) return 'extreme'
  if (score >= 15) return 'high'
  if (score >= 10) return 'medium'
  if (score >= 5)  return 'low'
  return 'very_low'
}

export default defineComponent({
  name: 'RiskMatrix5x5',
  emits: ['open-compare'],

  setup() {
    return { router: useRouter(), compareStore: useCompareStore() }
  },

  data() {
    return {
      DIMENSIONS,
      SIX_DIMS,
      LEVELS,
      LEVEL_LABELS,
      isLoading: false,
      activeDim: 'COMPOSITE' as RiskDimension,
      matrix: [] as RiskMatrixCell[],
      summary: { ...DEFAULT_SUMMARY } as RiskMatrixSummary,
      activeCell: null as RiskMatrixCell | null,
      panelLoading: false,
      panelSuppliers: [] as any[],
      panelTotal: 0,
      showCalcModal: false,
    }
  },

  computed: {
    activeDimLabel(): string {
      return DIMENSIONS.find(d => d.value === this.activeDim)?.label ?? this.activeDim
    },
    orderedCells(): RiskMatrixCell[] {
      const result: RiskMatrixCell[] = []
      for (let p = 5; p >= 1; p--) {
        for (let i = 1; i <= 5; i++) {
          const found = this.matrix.find(c => c.probability === p && c.impact === i)
          const score = p * i
          result.push(found ?? {
            probability: p, impact: i, cell_score: score,
            risk_level: scoreToLevel(score), supplier_count: 0,
            open_cap_count: 0,
            saq_grade_dist: { A: 0, B: 0, C: 0, D: 0, E: 0 },
            no_saq_count: 0,
          })
        }
      }
      return result
    },
  },

  mounted() { this.loadMatrix() },

  methods: {
    async switchDim(dim: RiskDimension) {
      this.activeDim = dim
      this.activeCell = null
      await this.loadMatrix()
    },

    async loadMatrix() {
      this.isLoading = true
      try {
        const { data } = await riskApi.matrix(this.activeDim)
        this.matrix = data.data.matrix
        this.summary = data.data.summary
      } catch {
        this.matrix = []
        this.summary = { ...DEFAULT_SUMMARY }
      } finally {
        this.isLoading = false
      }
    },

    async openCell(cell: RiskMatrixCell) {
      this.activeCell = cell
      this.panelLoading = true
      this.panelSuppliers = []
      try {
        const { data } = await riskApi.matrixSuppliers(this.activeDim, cell.probability, cell.impact)
        this.panelSuppliers = (data as any).data
        this.panelTotal = (data as any).pagination?.total ?? this.panelSuppliers.length
      } catch {
        this.panelSuppliers = []
      } finally {
        this.panelLoading = false
      }
    },

    goToFull() {
      if (!this.activeCell) return
      this.router.push({
        path: '/suppliers',
        query: {
          risk_dim: this.activeDim,
          risk_probability: String(this.activeCell.probability),
          risk_impact: String(this.activeCell.impact),
        },
      })
    },

    scoreClass(score: number): string {
      if (score >= 20) return 'score-extreme'
      if (score >= 15) return 'score-high'
      if (score >= 10) return 'score-medium'
      if (score >= 5)  return 'score-low'
      return 'score-very-low'
    },

    dimScoreClass(score: number | null): string {
      if (score == null) return 'score-very-low'
      if (score >= 80) return 'score-low'      // 高分 = 安全 = 綠
      if (score >= 60) return 'score-medium'
      if (score >= 40) return 'score-high'
      return 'score-extreme'                   // 低分 = 危險 = 紅
    },

    fmtDate(s: string): string {
      if (!s) return '—'
      return new Date(s).toLocaleDateString('zh-TW', { year: 'numeric', month: '2-digit', day: '2-digit' })
    },

    maxPipsPerGrade(cell: RiskMatrixCell, grade: string): number {
      const total = cell.supplier_count
      if (total === 0) return 0
      const cnt = (cell.saq_grade_dist[grade] ?? 0) as number
      return Math.round((cnt / total) * MAX_PIPS)
    },

    maxNoSaqPips(cell: RiskMatrixCell): number {
      const total = cell.supplier_count
      if (total === 0) return 0
      return Math.round((cell.no_saq_count / total) * MAX_PIPS)
    },
  },
})
</script>

<style scoped>
.rm-root { display: flex; flex-direction: column; gap: 0; }

.rm-toolbar {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 12px;
}

.btn-calc-info {
  padding: 7px 14px;
  font-size: 13px;
  font-weight: 500;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: var(--surface);
  color: var(--text-primary);
  cursor: pointer;
  white-space: nowrap;
}
.btn-calc-info:hover { background: var(--surface-2); }

/* 摘要列 */
.summary-bar {
  display: flex; gap: 6px; margin-bottom: 18px;
  flex-wrap: wrap; align-items: center;
  padding: 8px 12px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
}
.summary-item { font-size: 12.5px; font-weight: 600; padding: 3px 11px; border-radius: 99px; letter-spacing: .01em; white-space: nowrap; }
.summary-item.extreme  { background: #fee2e2; color: #991b1b; }
.summary-item.high     { background: #ffedd5; color: #9a3412; }
.summary-item.medium   { background: #fef9c3; color: #854d0e; }
.summary-item.low      { background: #dcfce7; color: #15803d; }
.summary-item.very-low { background: #d1fae5; color: #064e3b; }
.summary-item.total    { background: transparent; color: var(--text-secondary); font-weight: 400; font-size: 12.5px; margin-left: 4px; border-left: 1px solid var(--border); padding-left: 12px; border-radius: 0; }

/* 維度 Tab */
.dim-tabs { display: flex; gap: 8px; margin-top: 12px; margin-bottom: 16px; }
.dim-tab { padding: 7px 20px; border: 1px solid var(--border); border-radius: 7px; background: var(--surface); color: var(--text-secondary); cursor: pointer; font-size: 13.5px; font-weight: 500; transition: all 0.15s; white-space: nowrap; }
.dim-tab:hover { border-color: #9dc9bd; color: var(--accent); background: #f4faf8; }
.dim-tab.active { background: var(--accent); color: #fff; border-color: var(--accent); font-weight: 700; box-shadow: 0 2px 6px rgba(26,77,62,.25); }

/* 矩陣 */
.matrix-body {}
.matrix-layout { display: flex; gap: 24px; align-items: flex-start; }
.matrix-section { flex: 0 0 auto; }
.matrix-wrapper { display: flex; align-items: flex-start; gap: 8px; margin-bottom: 16px; }
.y-axis-label { writing-mode: vertical-rl; transform: rotate(180deg); font-size: 12px; font-weight: 500; color: var(--text-secondary); align-self: center; }
.x-axis-label { font-size: 12px; font-weight: 500; color: var(--text-secondary); text-align: center; margin-top: 8px; }
.matrix-container { display: flex; flex-direction: column; }
.matrix-row { display: flex; gap: 4px; }
.matrix-inner { display: flex; flex-direction: column; gap: 4px; }
.y-labels { display: flex; flex-direction: column; gap: 4px; }
.y-labels .axis-label { height: 88px; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 600; color: var(--text-secondary); width: 26px; }
.x-labels { display: flex; gap: 4px; margin-top: 4px; }
.x-labels .axis-label { width: 88px; text-align: center; font-size: 13px; font-weight: 600; color: var(--text-secondary); }
.matrix-grid { display: grid; grid-template-columns: repeat(5, 88px); grid-template-rows: repeat(5, 88px); gap: 4px; }

/* 格子 */
.matrix-cell { width: 88px; height: 88px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; border-radius: 7px; padding: 6px 4px; position: relative; transition: transform 0.12s, box-shadow 0.12s; }
.matrix-cell.cell-active { cursor: pointer; }
.matrix-cell.cell-active:hover { transform: scale(1.07); box-shadow: 0 4px 14px rgba(0,0,0,.18); z-index: 2; }

.very_low { background: var(--risk-very-low-bg); color: var(--risk-very-low-color); }
.low      { background: var(--risk-low-bg);      color: var(--risk-low-color); }
.medium   { background: var(--risk-medium-bg);   color: var(--risk-medium-color); }
.high     { background: var(--risk-high-bg);      color: var(--risk-high-color); }
.extreme  { background: var(--risk-extreme-bg);   color: var(--risk-extreme-color); }

.cell-count { font-family: var(--font-mono); font-size: 26px; font-weight: 800; line-height: 1; letter-spacing: -.02em; }
.cell-empty-score { font-size: 14px; font-weight: 600; opacity: .5; }
.cell-cap-row { display: flex; align-items: center; gap: 3px; font-size: 11px; font-weight: 700; line-height: 1; }
.cell-cap-icon { background: rgba(0,0,0,.2); color: inherit; font-size: 9px; font-weight: 900; padding: 1px 4px; border-radius: 3px; line-height: 1.4; }
.cell-grade-row { display: flex; gap: 2px; flex-wrap: wrap; justify-content: center; min-height: 9px; }
.grade-pip { width: 9px; height: 9px; border-radius: 2px; flex-shrink: 0; }
.pip-a { background: #16a34a; }
.pip-b { background: #22c55e; }
.pip-c { background: #eab308; }
.pip-d { background: #f97316; }
.pip-e { background: #ef4444; }
.pip-none { background: rgba(0,0,0,.18); }

/* 圖例 */
.legend-row { display: flex; flex-direction: column; gap: 8px; margin-top: 10px; }
.legend-band { display: flex; border-radius: 6px; overflow: hidden; border: 1px solid rgba(0,0,0,0.06); height: 28px; }
.legend-band-item { flex: 1; display: flex; align-items: center; justify-content: center; font-size: 11.5px; font-weight: 600; letter-spacing: 0.01em; white-space: nowrap; cursor: default; }
.legend-band-item.very_low { background: var(--risk-very-low-bg); color: var(--risk-very-low-color); }
.legend-band-item.low      { background: var(--risk-low-bg);      color: var(--risk-low-color); }
.legend-band-item.medium   { background: var(--risk-medium-bg);   color: var(--risk-medium-color); }
.legend-band-item.high     { background: var(--risk-high-bg);     color: var(--risk-high-color); }
.legend-band-item.extreme  { background: var(--risk-extreme-bg);  color: var(--risk-extreme-color); }
.pip-legend { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.pip-legend-label { font-size: 11.5px; color: var(--text-secondary); font-weight: 600; }
.pip-legend-item { display: flex; align-items: center; gap: 4px; font-size: 11.5px; color: var(--text-secondary); }
.pip-legend-sep { color: var(--text-secondary); opacity: .3; font-size: 14px; }

/* Side Panel */
.side-panel { flex: 0 0 400px; width: 400px; background: var(--surface); border: 1px solid var(--border); border-radius: 10px; display: flex; flex-direction: column; max-height: 680px; overflow: hidden; position: sticky; top: 20px; box-shadow: 0 4px 16px rgba(0,0,0,.07); }
.panel-header { padding: 14px 16px 12px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; background: #fafaf9; }
.panel-header-main { display: flex; align-items: center; gap: 8px; flex: 1; min-width: 0; }
.panel-title { font-size: 14px; font-weight: 700; color: var(--text-primary); white-space: nowrap; }
.panel-header-sub { font-size: 12px; color: var(--text-secondary); font-family: var(--font-mono); margin-left: auto; white-space: nowrap; flex-shrink: 0; }
.panel-close { background: none; border: none; width: 26px; height: 26px; font-size: 16px; cursor: pointer; color: var(--text-secondary); line-height: 1; border-radius: 5px; display: flex; align-items: center; justify-content: center; transition: background 0.12s; flex-shrink: 0; }
.panel-close:hover { background: var(--border); color: var(--text-primary); }
.panel-loading { padding: 40px; text-align: center; font-size: 13px; color: var(--text-secondary); }
.panel-list { flex: 1; overflow-y: auto; padding: 10px; display: flex; flex-direction: column; gap: 8px; }
.panel-footer { padding: 10px 14px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #fafaf9; }
.panel-footer-count { font-size: 12.5px; color: var(--text-secondary); }

/* Supplier Card */
.sc-card { border: 1px solid #ece9e3; border-radius: 9px; padding: 12px 13px; cursor: pointer; background: #fff; transition: border-color 0.15s, box-shadow 0.15s; display: flex; flex-direction: column; gap: 9px; }
.sc-card:hover { border-color: var(--accent); box-shadow: 0 2px 8px rgba(26,77,62,.09); }
.sc-head { display: flex; align-items: flex-start; gap: 8px; }
.sc-name-wrap { flex: 1; min-width: 0; }
.sc-name { font-size: 13.5px; font-weight: 700; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-primary); line-height: 1.3; }
.sc-meta { font-size: 11.5px; color: var(--text-secondary); margin-top: 2px; display: block; }
.sc-head-right { display: flex; align-items: center; gap: 5px; flex-shrink: 0; }
.sc-compare-btn { width: 26px; height: 22px; font-size: 13px; font-weight: 700; border: 1px solid var(--border); border-radius: 5px; background: transparent; color: var(--text-secondary); cursor: pointer; transition: all 0.12s; display: flex; align-items: center; justify-content: center; flex-shrink: 0; line-height: 1; }
.sc-compare-btn:hover:not(:disabled) { border-color: var(--accent); color: var(--accent); background: #f0faf7; }
.sc-compare-btn.sc-compare-added { border-color: var(--accent); color: var(--accent); background: #f0faf7; }
.sc-compare-btn:disabled { opacity: .35; cursor: not-allowed; }
.sc-body { display: flex; flex-direction: column; gap: 0; }
.sc-no-ra { font-size: 12px; color: var(--text-secondary); }
.sc-dims { display: grid; grid-template-columns: repeat(6, 1fr); gap: 4px; }
.sc-dim-active { border-color: #d97706 !important; background: #fffbeb !important; }
.sc-dim { display: flex; flex-direction: column; align-items: center; gap: 1px; padding: 8px 4px 7px; border-radius: 7px; background: #f7f5f1; border: 1px solid #ece9e3; }
.sc-dim-label { font-size: 10px; font-weight: 800; color: #b5ada4; letter-spacing: .08em; text-transform: uppercase; }
.sc-dim-score { font-size: 20px; font-weight: 800; font-variant-numeric: tabular-nums; line-height: 1.1; }
.sc-dim-formula { font-size: 10.5px; color: var(--text-secondary); font-family: var(--font-mono); opacity: .75; }
.score-extreme  { color: #991b1b; }
.score-high     { color: #c2410c; }
.score-medium   { color: #92400e; }
.score-low      { color: #15803d; }
.score-very-low { color: #6b7280; }
.sc-foot { display: flex; justify-content: space-between; align-items: center; gap: 6px; flex-wrap: wrap; padding-top: 7px; border-top: 1px solid #f0ede6; }
.sc-foot-l { display: flex; align-items: center; gap: 6px; }
.sc-foot-r { display: flex; align-items: center; gap: 5px; }
.sc-grade { font-size: 12.5px; font-weight: 800; padding: 2px 7px; border-radius: 4px; }
.grade-a { background: #dcfce7; color: #14532d; }
.grade-b { background: #bbf7d0; color: #14532d; }
.grade-c { background: #fef9c3; color: #713f12; }
.grade-d { background: #fed7aa; color: #7c2d12; }
.grade-e { background: #fecaca; color: #7f1d1d; }
.sc-score { font-size: 13px; font-weight: 600; color: var(--text-primary); font-family: var(--font-mono); }
.sc-no-saq { font-size: 11.5px; color: var(--text-secondary); }
.sc-cap-tag { font-size: 11px; font-weight: 700; color: #991b1b; background: #fee2e2; padding: 1px 6px; border-radius: 99px; }
.sc-chip { font-size: 10.5px; font-weight: 600; padding: 1px 6px; border-radius: 99px; letter-spacing: .02em; }
.chip-auto   { background: #ede9fe; color: #5b21b6; }
.chip-manual { background: #f1f0ee; color: #78716c; }
.sc-date { font-size: 11px; color: var(--text-secondary); font-variant-numeric: tabular-nums; }

/* Panel 動畫 */
.panel-enter-active, .panel-leave-active { transition: opacity 0.18s, transform 0.18s; }
.panel-enter-from, .panel-leave-to { opacity: 0; transform: translateX(16px); }

/* Modal */
.modal-overlay { position: fixed; inset: 0; z-index: 1000; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; }
.modal-box { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; width: min(720px, 92vw); max-height: 85vh; overflow-y: auto; box-shadow: 0 8px 32px rgba(0,0,0,0.18); }
.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid var(--border); }
.modal-title { font-size: 14px; font-weight: 700; color: var(--text-primary); }
.modal-close { background: none; border: none; cursor: pointer; font-size: 15px; color: var(--text-secondary); padding: 2px 6px; border-radius: 4px; }
.modal-close:hover { background: var(--surface-2); }
.modal-body { padding: 20px; }
.impact-ref__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px 24px; }
.impact-ref__col { display: flex; flex-direction: column; gap: 4px; }
.impact-ref__dim-label { font-size: 12px; font-weight: 600; color: var(--text-primary); margin-bottom: 2px; }
.impact-ref__formula { font-size: 11.5px; font-family: var(--font-mono); background: #f4f3f1; color: #1a4d3e; padding: 3px 8px; border-radius: 4px; display: block; white-space: nowrap; overflow-x: auto; }
@media (prefers-color-scheme: dark) { .impact-ref__formula { background: #2a2825; color: #7ecba0; } }
.impact-ref__note { font-size: 11px; color: var(--text-secondary); line-height: 1.5; }
.impact-ref__footer { margin-top: 12px; padding-top: 10px; border-top: 1px solid var(--border); font-size: 12px; color: var(--text-secondary); }
.impact-ref__link { color: var(--accent); text-decoration: none; font-weight: 500; }
.impact-ref__link:hover { text-decoration: underline; }
</style>
