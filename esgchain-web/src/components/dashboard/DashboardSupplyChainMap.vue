<template>
  <div class="card sc-map-card">
    <div class="sc-map-header">
      <span class="sc-map-icon">🌐</span>
      <span class="card-title">供應鏈地圖 － 風險熱點</span>
      <span v-if="expiringCount > 0" class="expiring-chip">
        {{ expiringCount }} 家供應商有文件即將到期
      </span>
    </div>

    <div class="sc-map-body" ref="wrap">
      <canvas ref="canvas" class="sc-canvas" @mousemove="onMouseMove" @mouseleave="onMouseLeave" @click="onCanvasClick" />
      <!-- Tooltip -->
      <div v-if="tooltip" class="sc-tooltip" :style="{ left: tooltip.x + 'px', top: tooltip.y + 'px' }">
        <div class="sc-tt-name">{{ tooltip.supplier_name }}</div>
        <div class="sc-tt-row">
          <span class="sc-tt-dot" :style="{ background: tooltip.color }"></span>
          {{ tooltip.levelLabel }}
        </div>
        <div class="sc-tt-row" style="color:var(--text-secondary)">
          衝擊度 {{ (tooltip.impact * 100).toFixed(0) }} · 機率 {{ (tooltip.prob * 100).toFixed(0) }}
        </div>
        <div v-if="tooltip.expiringDoc" class="sc-tt-expiring">
          ⚠ {{ tooltip.expiringDoc }}
        </div>
      </div>
    </div>

    <!-- Legend -->
    <div class="sc-legend">
      <span v-for="l in LEVELS" :key="l.key" class="sc-legend-item">
        <span class="sc-dot" :style="{ background: l.color }"></span>{{ l.label }}
      </span>
      <span class="sc-legend-item sc-legend-expiring">
        <span class="sc-ring-demo"></span>文件即將到期
      </span>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import type { SixDimHeatmapRow } from '@/api/modules/risk'
import type { ExpiringDoc } from '@/api/modules/dashboard'

const LEVELS = [
  { key: 'critical', label: '極端', color: '#b91c1c' },
  { key: 'high',     label: '高度', color: '#d97706' },
  { key: 'low',      label: '低度', color: '#16a34a' },
  { key: 'watch',    label: '監控', color: '#3b82f6' },
] as const

// 依 risk_score + is_critical 判斷等級
function getLevel(row: SixDimHeatmapRow): typeof LEVELS[number] {
  if (row.is_critical) return LEVELS[0]
  if ((row.risk_score ?? 0) >= 0.4) return LEVELS[1]
  if ((row.risk_score ?? 0) >= 0.2) return LEVELS[2]
  return LEVELS[3]
}

// X = Impact = risk_score (0–1 already)
function getImpact(row: SixDimHeatmapRow): number {
  return Math.min(1, Math.max(0, row.risk_score ?? 0.1))
}

// Y = Probability = 反轉 dim_e4 (地緣政治, 40–100 → 1–0)
function getProb(row: SixDimHeatmapRow): number {
  const e4 = row.dim_e4 ?? 70
  return Math.min(1, Math.max(0, (100 - e4) / 60))
}

interface PlotPoint {
  row: SixDimHeatmapRow
  color: string
  levelLabel: string
  impact: number  // 0–1 normalized
  prob: number    // 0–1 normalized
  isExpiring: boolean
  expiringDocLabel: string | null
}

interface TooltipState {
  x: number; y: number
  supplier_name: string
  color: string
  levelLabel: string
  impact: number; prob: number
  expiringDoc: string | null
}

// Padding
const PAD = { left: 56, right: 24, top: 24, bottom: 40 }
const DOT_R = 7

export default defineComponent({
  name: 'DashboardSupplyChainMap',

  props: {
    rows: { type: Array as PropType<SixDimHeatmapRow[]>, default: () => [] },
    expiringDocs: { type: Array as PropType<ExpiringDoc[]>, default: () => [] },
  },

  data() {
    return {
      LEVELS,
      points: [] as PlotPoint[],
      tooltip: null as TooltipState | null,
      animFrame: 0,
      tick: 0,
      resizeObserver: null as ResizeObserver | null,
    }
  },

  computed: {
    expiringSet(): Map<string, string> {
      const m = new Map<string, string>()
      for (const d of this.expiringDocs) {
        const cur = m.get(d.supplier_id)
        const label = `${d.doc_type}（${d.days_remaining}天後到期）`
        m.set(d.supplier_id, cur ? cur + '、' + label : label)
      }
      return m
    },
    expiringCount(): number {
      return new Set(this.expiringDocs.map(d => d.supplier_id)).size
    },
  },

  watch: {
    rows() { this.buildPoints(); this.draw() },
    expiringDocs() { this.buildPoints(); this.draw() },
  },

  mounted() {
    this.buildPoints()
    this.startLoop()
    this.resizeObserver = new ResizeObserver(() => { this.resizeCanvas(); this.draw() })
    this.resizeObserver.observe(this.$refs.wrap as Element)
  },

  beforeUnmount() {
    cancelAnimationFrame(this.animFrame)
    this.resizeObserver?.disconnect()
  },

  methods: {
    buildPoints() {
      this.points = this.rows.map(row => {
        const impact = getImpact(row)
        const prob = getProb(row)
        const level = getLevel(row)
        const isExpiring = this.expiringSet.has(row.supplier_id)
        return {
          row,
          color: level.color,
          levelLabel: level.label,
          impact, prob,
          isExpiring,
          expiringDocLabel: isExpiring ? (this.expiringSet.get(row.supplier_id) ?? null) : null,
        }
      })
    },

    resizeCanvas() {
      const canvas = this.$refs.canvas as HTMLCanvasElement
      const wrap = this.$refs.wrap as HTMLElement
      if (!canvas || !wrap) return
      const dpr = window.devicePixelRatio || 1
      const W = wrap.clientWidth
      const H = 260
      canvas.style.width = W + 'px'
      canvas.style.height = H + 'px'
      canvas.width = W * dpr
      canvas.height = H * dpr
      const ctx = canvas.getContext('2d')!
      ctx.scale(dpr, dpr)
    },

    startLoop() {
      this.resizeCanvas()
      const loop = () => {
        this.tick++
        this.draw()
        this.animFrame = requestAnimationFrame(loop)
      }
      this.animFrame = requestAnimationFrame(loop)
    },

    draw() {
      const canvas = this.$refs.canvas as HTMLCanvasElement
      if (!canvas) return
      const ctx = canvas.getContext('2d')
      if (!ctx) return
      const dpr = window.devicePixelRatio || 1
      const W = canvas.width / dpr
      const H = canvas.height / dpr
      const plotW = W - PAD.left - PAD.right
      const plotH = H - PAD.top - PAD.bottom

      ctx.clearRect(0, 0, W, H)

      // Background
      ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--surface').trim() || '#fafaf8'
      ctx.fillRect(PAD.left, PAD.top, plotW, plotH)

      // Grid lines
      ctx.strokeStyle = getComputedStyle(document.documentElement).getPropertyValue('--border').trim() || '#e5e2dc'
      ctx.lineWidth = 1
      const DIVS = 5
      for (let i = 0; i <= DIVS; i++) {
        const x = PAD.left + (plotW / DIVS) * i
        ctx.beginPath(); ctx.moveTo(x, PAD.top); ctx.lineTo(x, PAD.top + plotH); ctx.stroke()
        const y = PAD.top + (plotH / DIVS) * i
        ctx.beginPath(); ctx.moveTo(PAD.left, y); ctx.lineTo(PAD.left + plotW, y); ctx.stroke()
      }

      // Axis labels
      ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() || '#888'
      ctx.font = '11px system-ui, sans-serif'
      ctx.textAlign = 'center'
      // X axis label
      ctx.fillText('衝擊度 →', PAD.left + plotW / 2, H - 6)
      // X ticks
      for (let i = 0; i <= DIVS; i++) {
        const x = PAD.left + (plotW / DIVS) * i
        ctx.fillText((i * 20) + '', x, PAD.top + plotH + 14)
      }
      // Y axis label
      ctx.save()
      ctx.translate(12, PAD.top + plotH / 2)
      ctx.rotate(-Math.PI / 2)
      ctx.fillText('機率 →', 0, 0)
      ctx.restore()
      // Y ticks
      ctx.textAlign = 'right'
      for (let i = 0; i <= DIVS; i++) {
        const y = PAD.top + plotH - (plotH / DIVS) * i
        ctx.fillText((i * 20) + '', PAD.left - 6, y + 4)
      }

      // Quadrant labels (faint)
      ctx.globalAlpha = 0.25
      ctx.font = 'bold 11px system-ui, sans-serif'
      ctx.fillStyle = '#b91c1c'
      ctx.textAlign = 'right'
      ctx.fillText('高影響·高機率', PAD.left + plotW - 6, PAD.top + 16)
      ctx.fillStyle = '#3b82f6'
      ctx.textAlign = 'left'
      ctx.fillText('低影響·低機率', PAD.left + 6, PAD.top + plotH - 6)
      ctx.globalAlpha = 1

      // Pulsing ring phase (0→1→0 over 60 ticks)
      const phase = (Math.sin(this.tick * 0.05) + 1) / 2

      // Draw points
      for (const p of this.points) {
        const cx = PAD.left + p.impact * plotW
        const cy = PAD.top + (1 - p.prob) * plotH

        // Expiring ring
        if (p.isExpiring) {
          const ringR = DOT_R + 6 + phase * 4
          ctx.beginPath()
          ctx.arc(cx, cy, ringR, 0, Math.PI * 2)
          ctx.strokeStyle = '#f59e0b'
          ctx.lineWidth = 1.5
          ctx.globalAlpha = 0.4 + phase * 0.4
          ctx.stroke()
          ctx.globalAlpha = 1
        }

        // Dot
        ctx.beginPath()
        ctx.arc(cx, cy, DOT_R, 0, Math.PI * 2)
        ctx.fillStyle = p.color
        ctx.fill()
      }
    },

    hitTest(ex: number, ey: number): PlotPoint | null {
      const canvas = this.$refs.canvas as HTMLCanvasElement
      const rect = canvas.getBoundingClientRect()
      const mx = ex - rect.left
      const my = ey - rect.top
      // Use CSS dimensions (rect already gives CSS px)
      const W = canvas.clientWidth
      const H = canvas.clientHeight
      const plotW = W - PAD.left - PAD.right
      const plotH = H - PAD.top - PAD.bottom
      for (const p of [...this.points].reverse()) {
        const cx = PAD.left + p.impact * plotW
        const cy = PAD.top + (1 - p.prob) * plotH
        const dx = cx - mx
        const dy = cy - my
        if (dx * dx + dy * dy <= (DOT_R + 6) * (DOT_R + 6)) return p
      }
      return null
    },

    onMouseMove(e: MouseEvent) {
      const hit = this.hitTest(e.clientX, e.clientY)
      if (!hit) { this.tooltip = null; return }
      const canvas = this.$refs.canvas as HTMLCanvasElement
      const rect = canvas.getBoundingClientRect()
      const wrap = this.$refs.wrap as HTMLElement
      const wrapRect = wrap.getBoundingClientRect()
      this.tooltip = {
        x: e.clientX - wrapRect.left + 12,
        y: e.clientY - wrapRect.top - 10,
        supplier_name: hit.row.supplier_name,
        color: hit.color,
        levelLabel: hit.levelLabel,
        impact: hit.impact,
        prob: hit.prob,
        expiringDoc: hit.expiringDocLabel,
      }
    },

    onMouseLeave() { this.tooltip = null },

    onCanvasClick(e: MouseEvent) {
      const hit = this.hitTest(e.clientX, e.clientY)
      if (hit) this.$router.push(`/suppliers/${hit.row.supplier_id}`)
    },
  },
})
</script>

<style scoped>
.sc-map-card { padding: 16px 20px 12px; }

.sc-map-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}
.sc-map-icon { font-size: 16px; }
.card-title { font-size: 14px; font-weight: 600; color: var(--text-primary); flex: 1; }
.expiring-chip {
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 99px;
  background: #fef3c7;
  color: #b45309;
  font-weight: 500;
}

.sc-map-body {
  position: relative;
  width: 100%;
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 6px;
  overflow: hidden;
}

.sc-canvas {
  display: block;
  cursor: crosshair;
}

.sc-tooltip {
  position: absolute;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 8px 10px;
  pointer-events: none;
  white-space: nowrap;
  box-shadow: 0 4px 12px rgba(0,0,0,.08);
  z-index: 10;
  min-width: 160px;
}
.sc-tt-name { font-size: 13px; font-weight: 600; margin-bottom: 4px; }
.sc-tt-row { display: flex; align-items: center; gap: 5px; font-size: 12px; }
.sc-tt-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.sc-tt-expiring { font-size: 11px; color: #b45309; margin-top: 4px; font-weight: 500; }

.sc-legend {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
  margin-top: 10px;
  align-items: center;
}
.sc-legend-item {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  color: var(--text-secondary);
}
.sc-dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; }
.sc-legend-expiring { color: #b45309; }
.sc-ring-demo {
  display: inline-block;
  width: 9px; height: 9px;
  border-radius: 50%;
  border: 1.5px solid #f59e0b;
}
</style>
