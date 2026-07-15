<template>
  <div class="radar-wrap">
    <div v-if="!hasAnyData" class="radar-empty">
      <span>尚無三軸風險資料</span>
    </div>
    <svg v-else viewBox="-120 -120 240 240" class="radar-svg" @click.stop>
      <!-- 背景同心多邊形 -->
      <g class="radar-grid">
        <polygon v-for="r in [20, 40, 60, 80, 100]" :key="r"
          :points="trianglePoints(r)"
          fill="none" stroke="var(--color-border, #e5e7eb)" stroke-width="0.5" />
      </g>

      <!-- 三軸線 -->
      <line v-for="(axis, i) in axes" :key="'axis-' + i"
        x1="0" y1="0"
        :x2="axisEnd(i).x" :y2="axisEnd(i).y"
        stroke="var(--color-border, #ccc)" stroke-width="1" />

      <!-- 填色區域 -->
      <polygon
        :points="dataPoints"
        :fill="fillColor"
        fill-opacity="0.25"
        :stroke="fillColor"
        stroke-width="1.5"
        class="radar-area" />

      <!-- 虛線標記（缺資料軸） -->
      <template v-for="(axis, i) in axes" :key="'dashed-' + i">
        <line v-if="axis.score === null"
          x1="0" y1="0"
          :x2="axisEnd(i, 100).x" :y2="axisEnd(i, 100).y"
          stroke="var(--color-border, #ccc)" stroke-width="1"
          stroke-dasharray="4 3" />
      </template>

      <!-- 軸標籤 -->
      <text v-for="(axis, i) in axes" :key="'label-' + i"
        :x="labelPos(i).x" :y="labelPos(i).y"
        text-anchor="middle" dominant-baseline="middle"
        font-size="10" fill="var(--color-text, #374151)"
        class="radar-label"
        style="cursor:pointer"
        @click="$emit('axis-click', axis.key)">
        {{ axis.label }}
        <tspan v-if="axis.score !== null" :x="labelPos(i).x" :dy="13" font-size="9"
          :fill="levelColor(axis.level)">
          {{ axis.levelLabel }}
        </tspan>
        <tspan v-else :x="labelPos(i).x" :dy="13" font-size="8" fill="#9ca3af">未填報</tspan>
      </text>
    </svg>
  </div>
</template>

<script>
const AXIS_ANGLES = [-90, 30, 150] // 頂、右下、左下（度）

function deg2rad(deg) { return (deg * Math.PI) / 180 }

const LEVEL_COLOR = {
  extreme: '#dc2626',
  high:    '#ea580c',
  medium:  '#d97706',
  low:     '#65a30d',
  very_low: '#16a34a',
}
const LEVEL_LABEL = {
  extreme: '極高風險',
  high:    '高風險',
  medium:  '中風險',
  low:     '低風險',
  very_low: '極低風險',
}

export default {
  name: 'SupplierRiskRadarChart',
  emits: ['axis-click'],

  props: {
    // { score: 0~100 | null, level: string | null, source_saq_id: string | null }
    axis1: { type: Object, default: () => ({ score: null, level: null }) },
    axis2: { type: Object, default: () => ({ score: null, level: null }) },
    axis3: { type: Object, default: () => ({ score: null, level: null }) },
  },

  computed: {
    axes() {
      return [
        { key: 'axis1', label: 'ESG 揭露', score: this.axis1?.score ?? null, level: this.axis1?.level ?? null },
        { key: 'axis2', label: '治理成熟度', score: this.axis2?.score ?? null, level: this.axis2?.level ?? null },
        { key: 'axis3', label: '地緣產業', score: this.axis3?.score ?? null, level: this.axis3?.level ?? null },
      ]
    },

    hasAnyData() {
      return this.axes.some(a => a.score !== null)
    },

    dataPoints() {
      return this.axes.map((axis, i) => {
        const r = axis.score ?? 0
        const pt = this.polarToCart(i, r)
        return `${pt.x},${pt.y}`
      }).join(' ')
    },

    fillColor() {
      const levels = this.axes.map(a => a.level).filter(Boolean)
      if (levels.includes('extreme')) return LEVEL_COLOR.extreme
      if (levels.includes('high'))    return LEVEL_COLOR.high
      if (levels.includes('medium'))  return LEVEL_COLOR.medium
      if (levels.includes('low'))     return LEVEL_COLOR.low
      return LEVEL_COLOR.very_low
    },
  },

  methods: {
    polarToCart(axisIndex, r) {
      const angle = deg2rad(AXIS_ANGLES[axisIndex])
      return { x: r * Math.cos(angle), y: r * Math.sin(angle) }
    },

    axisEnd(i, r = 105) {
      return this.polarToCart(i, r)
    },

    labelPos(i) {
      const pt = this.polarToCart(i, 118)
      return pt
    },

    trianglePoints(r) {
      return AXIS_ANGLES.map(a => {
        const pt = { x: r * Math.cos(deg2rad(a)), y: r * Math.sin(deg2rad(a)) }
        return `${pt.x},${pt.y}`
      }).join(' ')
    },

    levelColor(level) {
      return LEVEL_COLOR[level] ?? '#6b7280'
    },

    levelLabel(level) {
      return LEVEL_LABEL[level] ?? ''
    },
  },
}
</script>

<style scoped>
.radar-wrap {
  width: 100%;
  max-width: 280px;
  margin: 0 auto;
}
.radar-svg { width: 100%; height: auto; }
.radar-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 140px;
  color: #9ca3af;
  font-size: 0.875rem;
}
.radar-label { user-select: none; }
</style>
