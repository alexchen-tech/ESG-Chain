<template>
  <div class="card">
    <div class="card-title" style="margin-bottom:16px">供應商 Tier 分布</div>
    <div v-if="total === 0" class="empty-state"><p>尚無供應商資料</p></div>
    <div v-else>
      <div v-for="tier in tiers" :key="tier.label" class="tier-row">
        <span class="tier-label">{{ tier.label }}</span>
        <div class="tier-bar-wrap">
          <div class="tier-bar" :style="{ width: (tier.count / total * 100) + '%' }"></div>
        </div>
        <span class="font-mono tier-count">{{ tier.count }}</span>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import type { PropType } from 'vue'

export interface TierDistribution {
  tier1: number
  tier2: number
  tier3: number
}

export default defineComponent({
  name: 'DashboardSupplierTier',
  props: {
    distribution: {
      type: Object as PropType<TierDistribution | null>,
      default: null,
    },
  },
  computed: {
    tiers(): { label: string; count: number }[] {
      if (!this.distribution) return []
      return [
        { label: 'Tier 1', count: this.distribution.tier1 },
        { label: 'Tier 2', count: this.distribution.tier2 },
        { label: 'Tier 3', count: this.distribution.tier3 },
      ]
    },
    total(): number {
      if (!this.distribution) return 0
      return this.distribution.tier1 + this.distribution.tier2 + this.distribution.tier3
    },
  },
})
</script>

<style scoped>
.tier-row { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; }
.tier-label { width: 48px; font-size: 13px; }
.tier-bar-wrap { flex: 1; height: 6px; background: var(--surface-2); border-radius: 3px; overflow: hidden; }
.tier-bar { height: 100%; background: var(--accent); border-radius: 3px; transition: width 0.4s; }
.tier-count { font-size: 13px; min-width: 30px; text-align: right; }
</style>
