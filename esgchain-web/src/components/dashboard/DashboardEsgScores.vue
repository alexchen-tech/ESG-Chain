<template>
  <div class="card">
    <div class="card-title" style="margin-bottom:16px">ESG 分數分布（當季）</div>
    <div v-if="!hasData" class="empty-state"><p>尚無評分資料</p></div>
    <div v-else class="scores">
      <div v-for="dim in dimensions" :key="dim.key" class="score-row">
        <span class="score-label">{{ dim.label }}</span>
        <div class="score-bar-wrap">
          <div class="score-bar" :style="{ width: dim.value + '%' }"></div>
        </div>
        <span class="score-value font-mono">{{ dim.value.toFixed(1) }}</span>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import type { PropType } from 'vue'

export interface EsgScores {
  e_score: number | null
  s_score: number | null
  g_score: number | null
}

export default defineComponent({
  name: 'DashboardEsgScores',
  props: {
    scores: {
      type: Object as PropType<EsgScores | null>,
      default: null,
    },
  },
  computed: {
    hasData(): boolean {
      return !!this.scores && (this.scores.e_score !== null || this.scores.s_score !== null || this.scores.g_score !== null)
    },
    dimensions(): { key: string; label: string; value: number }[] {
      if (!this.scores) return []
      return [
        { key: 'E', label: 'E 環境', value: this.scores.e_score ?? 0 },
        { key: 'S', label: 'S 社會', value: this.scores.s_score ?? 0 },
        { key: 'G', label: 'G 治理', value: this.scores.g_score ?? 0 },
      ]
    },
  },
})
</script>

<style scoped>
.score-row { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.score-label { width: 48px; font-size: 13px; font-weight: 500; }
.score-bar-wrap { flex: 1; height: 8px; background: var(--surface-2); border-radius: 4px; overflow: hidden; }
.score-bar { height: 100%; background: var(--accent); border-radius: 4px; transition: width 0.5s; }
.score-value { font-size: 13px; min-width: 36px; text-align: right; }
</style>
