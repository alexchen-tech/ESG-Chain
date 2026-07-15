<template>
  <div class="page-container">
    <div class="breadcrumb">
      <span class="breadcrumb-parent">風險稽核</span>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current">風險矩陣</span>
    </div>
    <div class="page-header">
      <div>
        <h1 class="page-title">風險矩陣</h1>
        <p class="page-subtitle">5×5 probability × impact 矩陣，含 GP 地緣政治維度。點擊格子查看供應商列表。</p>
      </div>
      <button class="btn-calc-info" @click="showCalcModal = true">計算方式說明</button>
    </div>

    <!-- 計算方式說明 Modal -->
    <Teleport to="body">
      <div v-if="showCalcModal" class="modal-overlay" @click.self="showCalcModal = false">
        <div class="modal-box">
          <div class="modal-header">
            <span class="modal-title">IMPACT 計算說明</span>
            <button class="modal-close" @click="showCalcModal = false">✕</button>
          </div>
          <div class="modal-body">
            <div class="impact-ref__grid">
              <div class="impact-ref__col">
                <div class="impact-ref__dim-label">S 社會 &amp; E 環境</div>
                <code class="impact-ref__formula">s_impact = clamp(max_labor_risk + tier_weight, 1, 5)</code>
                <code class="impact-ref__formula">e_impact = clamp(max_env_risk   + tier_weight, 1, 5)</code>
                <div class="impact-ref__note">tier_weight：Tier 1 → +2　Tier 2 → +1　Tier 3+ → 0</div>
                <div class="impact-ref__note">max_* 取供應商登記地與所有 active 廠址國家的最高風險值</div>
              </div>
              <div class="impact-ref__col">
                <div class="impact-ref__dim-label">G 治理</div>
                <code class="impact-ref__formula">g_impact = clamp(tier_weight + 2, 1, 5)</code>
                <div class="impact-ref__note">與國家風險無關，僅由採購層級決定</div>
              </div>
              <div class="impact-ref__col">
                <div class="impact-ref__dim-label">GP 地緣政治</div>
                <code class="impact-ref__formula">gp_impact = clamp(max_geo_risk, 1, 5)</code>
                <div class="impact-ref__note">不加 tier_weight；地緣風險獨立於採購層級</div>
                <div class="impact-ref__note">max_geo_risk 同樣取所有涉及國家的最高值</div>
              </div>
              <div class="impact-ref__col">
                <div class="impact-ref__dim-label">Probability（所有維度）</div>
                <code class="impact-ref__formula">probability = max(1, ceil((100 − score_dim) / 20))</code>
                <div class="impact-ref__note">SAQ 分數越低 → Probability 越高（最高 5）</div>
              </div>
            </div>
            <div class="impact-ref__footer">
              國家風險評等（labor / env / geo，1–5）可在
              <router-link to="/settings/country-risk" class="impact-ref__link" @click="showCalcModal = false">設定 › 國家風險評等</router-link>
              調整
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <div v-if="isLoading" class="loading-mask">載入矩陣資料...</div>
    <div v-else class="matrix-body">
    <div class="matrix-layout">

      <!-- 左側：矩陣區塊 -->
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
                  @click="cell.supplier_count > 0 && goToSuppliers(cell)"
                >
                  <!-- 空格：僅顯示 score -->
                  <template v-if="cell.supplier_count === 0">
                    <span class="cell-empty-score">{{ cell.cell_score }}</span>
                  </template>

                  <!-- 有資料的格子：3 層 -->
                  <template v-else>
                    <!-- 層 1：供應商數 -->
                    <span class="cell-count">{{ cell.supplier_count }}</span>

                    <!-- 層 2：Open CAP 警示 -->
                    <span v-if="cell.open_cap_count > 0" class="cell-cap-row">
                      <span class="cell-cap-icon">!</span>{{ cell.open_cap_count }} CAP
                    </span>

                    <!-- 層 3：SAQ 等級點陣 -->
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

      <!-- 圖例列 -->
      <div class="legend-row">
        <!-- 風險等級橫條 -->
        <div class="legend-band">
          <span v-for="l in LEVELS" :key="l.value" class="legend-band-item" :class="l.value">
            {{ l.label }}
          </span>
        </div>
        <!-- SAQ / CAP 輔助圖例 -->
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
    </div><!-- /matrix-section -->

      <!-- 右側 Panel -->
      <transition name="panel">
        <div v-if="activeCell" class="side-panel">
          <!-- Panel Header -->
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

          <!-- Loading -->
          <div v-if="panelLoading" class="panel-loading">載入中...</div>

          <!-- Card 列表 -->
          <div v-else class="panel-list">
            <div
              v-for="s in panelSuppliers"
              :key="s.id"
              class="sc-card"
              @click="router.push(`/suppliers/${s.id}`)"
            >
              <!-- 卡片 Header：名稱 + 狀態 + 比較按鈕 -->
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

              <!-- 四維度 + SAQ + 日期 同一行 -->
              <div class="sc-body">
                <!-- 維度分數 -->
                <div v-if="s.risk_assessment" class="sc-dims">
                  <div v-for="dim in ['e','s','g','gp']" :key="dim" class="sc-dim">
                    <span class="sc-dim-label">{{ dim.toUpperCase() }}</span>
                    <span class="sc-dim-score" :class="scoreClass(s.risk_assessment[`${dim}_probability`] * s.risk_assessment[`${dim}_impact`])">
                      {{ s.risk_assessment[`${dim}_probability`] * s.risk_assessment[`${dim}_impact`] }}
                    </span>
                    <span class="sc-dim-formula">{{ s.risk_assessment[`${dim}_probability`] }}×{{ s.risk_assessment[`${dim}_impact`] }}</span>
                  </div>
                </div>
                <div v-else class="sc-no-ra">尚無風險評估</div>
              </div>

              <!-- 卡片 Footer：SAQ 等級 + 評估來源 + 日期 -->
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

          <!-- Footer：查看全部 + 比較 -->
          <div class="panel-footer">
            <span class="panel-footer-count">共 {{ panelTotal }} 家</span>
            <div style="display:flex;gap:8px;">
              <button
                v-if="compareStore.suppliers.length >= 2"
                class="btn btn-accent btn-sm"
                @click="showCompareModal = true"
              >比較 {{ compareStore.suppliers.length }} 家 →</button>
              <button class="btn btn-secondary btn-sm" @click="goToFull">查看全部 →</button>
            </div>
          </div>
        </div>
      </transition>

    </div><!-- /matrix-layout -->
    </div><!-- /matrix-body -->

    <CompareModal v-if="showCompareModal" @close="showCompareModal = false" />
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { useRouter } from 'vue-router'
import { riskApi, type RiskMatrixCell, type RiskDimension } from '@/api/modules/risk'
import { useCompareStore } from '@/stores/compareStore'
import CompareModal from '@/components/CompareModal.vue'

const DIMENSIONS = [
  { value: 'E' as RiskDimension, label: 'E 環境' },
  { value: 'S' as RiskDimension, label: 'S 社會' },
  { value: 'G' as RiskDimension, label: 'G 治理' },
  { value: 'GP' as RiskDimension, label: 'GP 地緣政治' },
]

const LEVELS = [
  { value: 'very_low', label: 'Very Low (1–4)' },
  { value: 'low', label: 'Low (5–9)' },
  { value: 'medium', label: 'Medium (10–14)' },
  { value: 'high', label: 'High (15–19)' },
  { value: 'extreme', label: 'Extreme (20–25)' },
]

const DEFAULT_SUMMARY = { extreme_count: 0, high_count: 0, medium_count: 0, low_count: 0, very_low_count: 0, total: 0 }

const LEVEL_LABELS: Record<string, string> = {
  very_low: 'Very Low', low: 'Low', medium: 'Medium', high: 'High', extreme: 'Extreme',
}

const MAX_PIPS = 6  // 每格最多顯示幾個 pip

function scoreToLevel(score: number): string {
  if (score >= 20) return 'extreme'
  if (score >= 15) return 'high'
  if (score >= 10) return 'medium'
  if (score >= 5)  return 'low'
  return 'very_low'
}

export default defineComponent({
  name: 'RiskMatrixView',
  components: { CompareModal },
  setup() {
    return { router: useRouter(), compareStore: useCompareStore() }
  },

  data() {
    return {
      DIMENSIONS,
      LEVELS,
      LEVEL_LABELS,
      isLoading: false,
      activeDim: 'E' as RiskDimension,
      matrix: [] as any[],
      summary: { ...DEFAULT_SUMMARY },
      activeCell: null as any,
      panelLoading: false,
      panelSuppliers: [] as any[],
      panelTotal: 0,
      showCompareModal: false,
      showCalcModal: false,
    }
  },

  computed: {
    orderedCells(): any[] {
      const result: any[] = []
      for (let p = 5; p >= 1; p--) {
        for (let i = 1; i <= 5; i++) {
          const found = this.matrix.find((c: any) => c.probability === p && c.impact === i)
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
        this.matrix = (data as any).data.matrix
        this.summary = (data as any).data.summary
      } catch {
        this.matrix = []
        this.summary = { ...DEFAULT_SUMMARY }
      } finally {
        this.isLoading = false
      }
    },

    async goToSuppliers(cell: any) {
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

    fmtDate(s: string): string {
      if (!s) return '—'
      return new Date(s).toLocaleDateString('zh-TW', { year: 'numeric', month: '2-digit', day: '2-digit' })
    },

    // pip 顯示邏輯：各等級按比例分配 MAX_PIPS 個坑位
    maxPipsPerGrade(cell: any, grade: string): number {
      const total = cell.supplier_count
      if (total === 0) return 0
      const cnt = cell.saq_grade_dist[grade] ?? 0
      return Math.round((cnt / total) * MAX_PIPS)
    },
    maxNoSaqPips(cell: any): number {
      const total = cell.supplier_count
      if (total === 0) return 0
      return Math.round((cell.no_saq_count / total) * MAX_PIPS)
    },
  },
})
</script>

<style scoped>
/* 維度 Tab */
.dim-tabs { display: flex; gap: 8px; margin-top: 12px; margin-bottom: 16px; }
.dim-tab {
  padding: 7px 20px;
  border: 1px solid var(--border);
  border-radius: 7px;
  background: var(--surface);
  color: var(--text-secondary);
  cursor: pointer;
  font-size: 13.5px;
  font-weight: 500;
  transition: all 0.15s;
  white-space: nowrap;
}
.dim-tab:hover { border-color: #9dc9bd; color: var(--accent); background: #f4faf8; }
.dim-tab.active {
  background: var(--accent); color: #fff;
  border-color: var(--accent);
  font-weight: 700;
  box-shadow: 0 2px 6px rgba(26,77,62,.25);
}

/* 摘要列 */
.summary-bar {
  display: flex; gap: 6px;
  margin-bottom: 18px;
  flex-wrap: wrap; align-items: center;
  padding: 8px 12px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
}
.summary-item {
  font-size: 12.5px; font-weight: 600;
  padding: 3px 11px; border-radius: 99px;
  letter-spacing: .01em;
  white-space: nowrap;
}
.summary-item.extreme  { background: #fee2e2; color: #991b1b; }
.summary-item.high     { background: #ffedd5; color: #9a3412; }
.summary-item.medium   { background: #fef9c3; color: #854d0e; }
.summary-item.low      { background: #dcfce7; color: #15803d; }
.summary-item.very-low { background: #d1fae5; color: #064e3b; }
.summary-item.total    {
  background: transparent;
  color: var(--text-secondary);
  font-weight: 400; font-size: 12.5px;
  margin-left: 4px;
  padding-left: 0;
  border-left: 1px solid var(--border);
  padding-left: 12px;
  border-radius: 0;
}

/* 矩陣包裝 */
.matrix-body {}
.matrix-wrapper { display: flex; align-items: flex-start; gap: 8px; margin-bottom: 16px; }
.y-axis-label { writing-mode: vertical-rl; transform: rotate(180deg); font-size: 12px; font-weight: 500; color: var(--text-secondary); align-self: center; }
.x-axis-label { font-size: 12px; font-weight: 500; color: var(--text-secondary); text-align: center; margin-top: 8px; }

.matrix-container { display: flex; flex-direction: column; }
.matrix-row { display: flex; gap: 4px; }
.matrix-inner { display: flex; flex-direction: column; gap: 4px; }
.y-labels { display: flex; flex-direction: column; gap: 4px; }
.y-labels .axis-label {
  height: 88px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 600;
  color: var(--text-secondary);
  width: 26px;
}
.x-labels { display: flex; gap: 4px; margin-top: 4px; }
.x-labels .axis-label {
  width: 88px;
  text-align: center;
  font-size: 13px;
  font-weight: 600;
  color: var(--text-secondary);
}

/* 矩陣格子 */
.matrix-grid { display: grid; grid-template-columns: repeat(5, 88px); grid-template-rows: repeat(5, 88px); gap: 4px; }

.matrix-cell {
  width: 88px; height: 88px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 3px;
  border-radius: 7px;
  padding: 6px 4px;
  position: relative;
  transition: transform 0.12s, box-shadow 0.12s;
}
.matrix-cell.cell-active { cursor: pointer; }
.matrix-cell.cell-active:hover {
  transform: scale(1.07);
  box-shadow: 0 4px 14px rgba(0,0,0,.18);
  z-index: 2;
}

/* 顏色 */
.very_low { background: var(--risk-very-low-bg); color: var(--risk-very-low-color); }
.low      { background: var(--risk-low-bg);      color: var(--risk-low-color); }
.medium   { background: var(--risk-medium-bg);   color: var(--risk-medium-color); }
.high     { background: var(--risk-high-bg);     color: var(--risk-high-color); }
.extreme  { background: var(--risk-extreme-bg);  color: var(--risk-extreme-color); }

/* 層 1：供應商數 */
.cell-count {
  font-family: var(--font-mono);
  font-size: 26px;
  font-weight: 800;
  line-height: 1;
  letter-spacing: -.02em;
}
/* 空格 score：提高對比，讓數字可辨識 */
.cell-empty-score {
  font-size: 14px;
  font-weight: 600;
  opacity: .5;
}

/* 層 2：CAP 警示 */
.cell-cap-row {
  display: flex;
  align-items: center;
  gap: 3px;
  font-size: 11px;
  font-weight: 700;
  line-height: 1;
}
.cell-cap-icon {
  background: rgba(0,0,0,.2);
  color: inherit;
  font-size: 9px;
  font-weight: 900;
  padding: 1px 4px;
  border-radius: 3px;
  line-height: 1.4;
}

/* 層 3：SAQ grade 點陣 */
.cell-grade-row {
  display: flex;
  gap: 2px;
  flex-wrap: wrap;
  justify-content: center;
  min-height: 9px;
}
.grade-pip {
  width: 9px;
  height: 9px;
  border-radius: 2px;
  flex-shrink: 0;
}
.pip-a    { background: #16a34a; }
.pip-b    { background: #22c55e; }
.pip-c    { background: #eab308; }
.pip-d    { background: #f97316; }
.pip-e    { background: #ef4444; }
.pip-none { background: rgba(0,0,0,.18); }

/* 圖例列 */
.legend-row {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 10px;
}

/* 橫式色條 */
.legend-band {
  display: flex;
  border-radius: 6px;
  overflow: hidden;
  border: 1px solid rgba(0,0,0,0.06);
  height: 28px;
}
.legend-band-item {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11.5px;
  font-weight: 600;
  letter-spacing: 0.01em;
  white-space: nowrap;
  cursor: default;
}
.legend-band-item.very_low { background: var(--risk-very-low-bg); color: var(--risk-very-low-color); }
.legend-band-item.low      { background: var(--risk-low-bg);      color: var(--risk-low-color); }
.legend-band-item.medium   { background: var(--risk-medium-bg);   color: var(--risk-medium-color); }
.legend-band-item.high     { background: var(--risk-high-bg);     color: var(--risk-high-color); }
.legend-band-item.extreme  { background: var(--risk-extreme-bg);  color: var(--risk-extreme-color); }

/* 輔助圖例 */
.pip-legend { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.pip-legend-label { font-size: 11.5px; color: var(--text-secondary); font-weight: 600; }
.pip-legend-item { display: flex; align-items: center; gap: 4px; font-size: 11.5px; color: var(--text-secondary); }
.pip-legend-sep { color: var(--text-secondary); opacity: .3; font-size: 14px; }
.pip-cap-item .cell-cap-icon { background: #d1d5db; color: #374151; }

/* ── Layout with side panel ── */
.matrix-layout { display: flex; gap: 24px; align-items: flex-start; }

/* ── Side Panel ── */
/* ── Side Panel ── */
.side-panel {
  flex: 0 0 400px;
  width: 400px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  display: flex;
  flex-direction: column;
  max-height: 680px;
  overflow: hidden;
  position: sticky;
  top: 20px;
  box-shadow: 0 4px 16px rgba(0,0,0,.07);
}

.panel-header {
  padding: 14px 16px 12px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  gap: 10px;
  background: #fafaf9;
}
.panel-header-main { display: flex; align-items: center; gap: 8px; flex: 1; min-width: 0; }
.panel-title { font-size: 14px; font-weight: 700; color: var(--text-primary); white-space: nowrap; }
.panel-header-sub {
  font-size: 12px;
  color: var(--text-secondary);
  font-family: var(--font-mono);
  margin-left: auto;
  white-space: nowrap;
  flex-shrink: 0;
}
.panel-close {
  background: none; border: none;
  width: 26px; height: 26px;
  font-size: 16px; cursor: pointer;
  color: var(--text-secondary); line-height: 1;
  border-radius: 5px;
  display: flex; align-items: center; justify-content: center;
  transition: background 0.12s; flex-shrink: 0;
}
.panel-close:hover { background: var(--border); color: var(--text-primary); }

.panel-loading { padding: 40px; text-align: center; font-size: 13px; color: var(--text-secondary); }
.panel-list { flex: 1; overflow-y: auto; padding: 10px; display: flex; flex-direction: column; gap: 8px; }

.panel-footer {
  padding: 10px 14px;
  border-top: 1px solid var(--border);
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #fafaf9;
}
.panel-footer-count { font-size: 12.5px; color: var(--text-secondary); }

/* ── Supplier Card ── */
.sc-card {
  border: 1px solid #ece9e3;
  border-radius: 9px;
  padding: 12px 13px;
  cursor: pointer;
  background: #fff;
  transition: border-color 0.15s, box-shadow 0.15s;
  display: flex;
  flex-direction: column;
  gap: 9px;
}
.sc-card:hover {
  border-color: var(--accent);
  box-shadow: 0 2px 8px rgba(26,77,62,.09);
}

/* Header 行：名稱 + 狀態 badge + 比較按鈕 */
.sc-head { display: flex; align-items: flex-start; gap: 8px; }
.sc-name-wrap { flex: 1; min-width: 0; }
.sc-name {
  font-size: 13.5px; font-weight: 700;
  display: block;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  color: var(--text-primary);
  line-height: 1.3;
}
.sc-meta { font-size: 11.5px; color: var(--text-secondary); margin-top: 2px; display: block; }
.sc-head-right {
  display: flex; align-items: center; gap: 5px; flex-shrink: 0;
}

/* 比較按鈕：緊湊 icon 形式 */
.sc-compare-btn {
  width: 26px; height: 22px;
  font-size: 13px; font-weight: 700;
  border: 1px solid var(--border);
  border-radius: 5px;
  background: transparent;
  color: var(--text-secondary);
  cursor: pointer;
  transition: all 0.12s;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  line-height: 1;
}
.sc-compare-btn:hover:not(:disabled) { border-color: var(--accent); color: var(--accent); background: #f0faf7; }
.sc-compare-btn.sc-compare-added { border-color: var(--accent); color: var(--accent); background: #f0faf7; }
.sc-compare-btn:disabled { opacity: .35; cursor: not-allowed; }

/* Body：維度 grid */
.sc-body { display: flex; flex-direction: column; gap: 0; }
.sc-no-ra { font-size: 12px; color: var(--text-secondary); }

/* 四維度 grid */
.sc-dims { display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px; }
.sc-dim {
  display: flex; flex-direction: column; align-items: center; gap: 1px;
  padding: 8px 4px 7px;
  border-radius: 7px;
  background: #f7f5f1;
  border: 1px solid #ece9e3;
}
.sc-dim-label {
  font-size: 10px; font-weight: 800;
  color: #b5ada4;
  letter-spacing: .08em; text-transform: uppercase;
}
.sc-dim-score {
  font-size: 20px; font-weight: 800;
  font-variant-numeric: tabular-nums;
  line-height: 1.1;
}
.sc-dim-formula {
  font-size: 10.5px; color: var(--text-secondary);
  font-family: var(--font-mono); opacity: .75;
}

.score-extreme  { color: #991b1b; }
.score-high     { color: #c2410c; }
.score-medium   { color: #92400e; }
.score-low      { color: #15803d; }
.score-very-low { color: #6b7280; }

/* Footer 行：SAQ + CAP + 來源 + 日期 */
.sc-foot {
  display: flex; justify-content: space-between;
  align-items: center; gap: 6px; flex-wrap: wrap;
  padding-top: 7px;
  border-top: 1px solid #f0ede6;
}
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

.sc-notes { font-size: 12px; color: var(--text-secondary); padding: 6px 9px; background: #f8f7f6; border-radius: 4px; border-left: 2px solid var(--border); line-height: 1.6; }

/* Panel 動畫 */
.panel-enter-active, .panel-leave-active { transition: opacity 0.18s, transform 0.18s; }
.panel-enter-from, .panel-leave-to { opacity: 0; transform: translateX(16px); }

/* 計算方式說明 按鈕 */
.btn-calc-info {
  align-self: flex-start;
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

/* Modal */
.modal-overlay {
  position: fixed; inset: 0; z-index: 1000;
  background: rgba(0,0,0,0.4);
  display: flex; align-items: center; justify-content: center;
}
.modal-box {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  width: min(720px, 92vw);
  max-height: 85vh;
  overflow-y: auto;
  box-shadow: 0 8px 32px rgba(0,0,0,0.18);
}
.modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border);
}
.modal-title { font-size: 14px; font-weight: 700; color: var(--text-primary); }
.modal-close {
  background: none; border: none; cursor: pointer;
  font-size: 15px; color: var(--text-secondary); padding: 2px 6px;
  border-radius: 4px;
}
.modal-close:hover { background: var(--surface-2); }
.modal-body { padding: 20px; }

/* Impact 計算說明 */
.impact-ref__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 14px 24px;
}
.impact-ref__col { display: flex; flex-direction: column; gap: 4px; }
.impact-ref__dim-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 2px;
}
.impact-ref__formula {
  font-size: 11.5px;
  font-family: var(--font-mono);
  background: #f4f3f1;
  color: #1a4d3e;
  padding: 3px 8px;
  border-radius: 4px;
  display: block;
  white-space: nowrap;
  overflow-x: auto;
}
@media (prefers-color-scheme: dark) {
  .impact-ref__formula { background: #2a2825; color: #7ecba0; }
}
.impact-ref__note {
  font-size: 11px;
  color: var(--text-secondary);
  line-height: 1.5;
}
.impact-ref__footer {
  margin-top: 12px;
  padding-top: 10px;
  border-top: 1px solid var(--border);
  font-size: 12px;
  color: var(--text-secondary);
}
.impact-ref__link { color: var(--accent); text-decoration: none; font-weight: 500; }
.impact-ref__link:hover { text-decoration: underline; }
</style>
