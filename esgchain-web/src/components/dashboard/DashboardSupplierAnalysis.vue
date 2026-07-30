<template>
  <div class="card">
    <div class="card-title" style="margin-bottom:16px">供應商分析</div>
    <div v-if="total === 0" class="empty-state"><p>尚無供應商資料</p></div>
    <div v-else>
      <div class="analysis-total">
        <span class="analysis-total__value font-mono">{{ total }}</span>
        <span class="analysis-total__label">家供應商</span>
      </div>

      <div class="analysis-section">
        <div class="analysis-section__label">依層級</div>
        <div v-for="row in tierRows" :key="row.label" class="dist-row" @click="goTo(`/suppliers?tier=${row.tier}`)">
          <span class="dist-label">{{ row.label }}</span>
          <div class="dist-bar-wrap">
            <div class="dist-bar dist-bar--tier" :style="{ width: pct(row.count) + '%' }"></div>
          </div>
          <span class="font-mono dist-count">{{ row.count }}</span>
          <span class="dist-pct">{{ pct(row.count) }}%</span>
        </div>
      </div>

      <div class="analysis-section">
        <div class="analysis-section__label">依活躍狀態</div>
        <div v-for="row in statusRows" :key="row.status" class="dist-row dist-row--static">
          <span class="badge dist-status-badge" :class="statusBadgeClass(row.status)">{{ statusLabel(row.status) }}</span>
          <div class="dist-bar-wrap">
            <div class="dist-bar" :class="statusBarClass(row.status)" :style="{ width: pct(row.count) + '%' }"></div>
          </div>
          <span class="font-mono dist-count">{{ row.count }}</span>
          <span class="dist-pct">{{ pct(row.count) }}%</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import type { PropType } from 'vue'
import type { Supplier } from '@/api/modules/suppliers'

const STATUS_LABELS: Record<string, string> = {
  active: '啟用', inactive: '停用', suspended: '暫停',
}
const STATUS_BADGE_CLASS: Record<string, string> = {
  active: 'badge-green', inactive: 'badge-gray', suspended: 'badge-red',
}
const STATUS_BAR_CLASS: Record<string, string> = {
  active: 'dist-bar--green', inactive: 'dist-bar--gray', suspended: 'dist-bar--red',
}

export default defineComponent({
  name: 'DashboardSupplierAnalysis',
  props: {
    suppliers: {
      type: Array as PropType<Supplier[]>,
      default: () => [],
    },
  },
  computed: {
    total(): number {
      return this.suppliers.length
    },
    tierRows(): { tier: number; label: string; count: number }[] {
      const counts: Record<number, number> = { 1: 0, 2: 0, 3: 0 }
      for (const s of this.suppliers) {
        if (s.tier && counts[s.tier] !== undefined) counts[s.tier]++
      }
      return [1, 2, 3].map(t => ({ tier: t, label: `Tier ${t}`, count: counts[t] ?? 0 }))
    },
    statusRows(): { status: string; count: number }[] {
      const counts: Record<string, number> = {}
      for (const s of this.suppliers) counts[s.status] = (counts[s.status] || 0) + 1
      return Object.entries(counts)
        .map(([status, count]) => ({ status, count }))
        .sort((a, b) => b.count - a.count)
    },
  },
  methods: {
    pct(count: number): number {
      if (this.total === 0) return 0
      return Math.round((count / this.total) * 100)
    },
    statusLabel(status: string): string {
      return STATUS_LABELS[status] ?? status
    },
    statusBadgeClass(status: string): string {
      return STATUS_BADGE_CLASS[status] ?? 'badge-gray'
    },
    statusBarClass(status: string): string {
      return STATUS_BAR_CLASS[status] ?? 'dist-bar--gray'
    },
    goTo(path: string) {
      this.$router.push(path)
    },
  },
})
</script>

<style scoped>
.analysis-total {
  display: flex;
  align-items: baseline;
  gap: 6px;
  background: var(--accent-soft);
  border: 1px solid var(--accent-soft-border);
  border-radius: 8px;
  padding: 10px 14px;
  margin-bottom: 16px;
}
.analysis-total__value { font-size: 22px; font-weight: 700; color: var(--accent); }
.analysis-total__label { font-size: 13px; color: var(--text-secondary); }

.analysis-section { margin-bottom: 16px; }
.analysis-section:last-child { margin-bottom: 0; }
.analysis-section__label {
  font-size: 12px;
  font-weight: 600;
  color: var(--text-secondary);
  margin-bottom: 8px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.dist-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 8px;
  cursor: pointer;
  border-radius: 4px;
  padding: 2px 4px;
  margin-left: -4px;
  transition: background 0.1s;
}
.dist-row:hover { background: var(--surface-2); }
.dist-row--static { cursor: default; }
.dist-row--static:hover { background: none; }
.dist-row:last-child { margin-bottom: 0; }
.dist-label { width: 48px; font-size: 13px; flex-shrink: 0; }
.dist-status-badge { width: 48px; flex-shrink: 0; text-align: center; justify-content: center; }
.dist-bar-wrap { flex: 1; height: 6px; background: var(--surface-2); border-radius: 3px; overflow: hidden; }
.dist-bar { height: 100%; background: var(--accent); border-radius: 3px; transition: width 0.4s; }
.dist-bar--tier { background: var(--accent); }
.dist-bar--green { background: #22c55e; }
.dist-bar--red { background: #ef4444; }
.dist-bar--gray { background: #a8a29e; }
.dist-count { font-size: 13px; min-width: 26px; text-align: right; flex-shrink: 0; }
.dist-pct { font-size: 12px; color: var(--text-secondary); min-width: 34px; text-align: right; flex-shrink: 0; }
</style>
