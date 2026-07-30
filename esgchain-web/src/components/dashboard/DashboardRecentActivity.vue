<template>
  <div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
      <div class="card-title" style="margin:0;">最近供應商動態</div>
      <router-link to="/dashboard/activity" class="view-all-link">查看全部 →</router-link>
    </div>
    <div v-if="activities.length === 0" class="empty-state"><p>近期無動態</p></div>
    <div v-else>
      <div
        v-for="(item, idx) in visible"
        :key="idx"
        class="activity-row"
        @click="$router.push(`/suppliers/${item.supplier_id}`)"
      >
        <span class="activity-icon" :class="`activity-icon--${item.severity}`">
          {{ severityIcon(item.severity) }}
        </span>
        <div class="activity-body">
          <div class="activity-supplier">{{ maskSupplierName(item.supplier_name) }}</div>
          <div class="activity-label">{{ item.event_label }}</div>
        </div>
        <div class="activity-time">{{ relativeTime(item.occurred_at) }}</div>
      </div>
      <div v-if="activities.length > LIMIT" class="activity-more">
        <router-link to="/dashboard/activity">還有 {{ activities.length - LIMIT }} 筆 · 查看全部</router-link>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import type { PropType } from 'vue'
import type { RecentActivity } from '@/api/modules/dashboard'
import { maskSupplierName } from '@/utils/maskName'

const LIMIT = 5

export default defineComponent({
  name: 'DashboardRecentActivity',
  props: {
    activities: {
      type: Array as PropType<RecentActivity[]>,
      default: () => [],
    },
  },
  data() { return { LIMIT } },
  computed: {
    visible(): RecentActivity[] { return this.activities.slice(0, LIMIT) },
  },
  methods: {
    maskSupplierName,
    severityIcon(severity: string): string {
      return { warning: '!', pending: '○', info: '●' }[severity] ?? '●'
    },
    relativeTime(iso: string): string {
      const diff = Date.now() - new Date(iso).getTime()
      const future = diff < 0
      const absMins = Math.floor(Math.abs(diff) / 60000)
      const suffix = future ? '後' : '前'
      if (absMins < 1) return '剛剛'
      if (absMins < 60) return `${absMins} 分鐘${suffix}`
      const absHrs = Math.floor(absMins / 60)
      if (absHrs < 24) return `${absHrs} 小時${suffix}`
      const days = Math.floor(absHrs / 24)
      if (days < 30) return `${days} 天${suffix}`
      const months = Math.floor(days / 30)
      return `${months} 個月${suffix}`
    },
  },
})
</script>

<style scoped>
.activity-row {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 0;
  border-bottom: 1px solid var(--border);
  cursor: pointer;
}
.activity-row:last-child { border-bottom: none; }
.activity-row:hover { background: var(--surface-2); margin: 0 -8px; padding: 10px 8px; border-radius: 4px; }
.activity-icon {
  width: 20px;
  text-align: center;
  font-size: 13px;
  flex-shrink: 0;
  margin-top: 2px;
}
.activity-icon--warning { color: #dc2626; }
.activity-icon--pending { color: #2563eb; }
.activity-icon--info    { color: var(--accent); }
.activity-body { flex: 1; min-width: 0; }
.activity-supplier { font-size: 13px; font-weight: 500; }
.activity-label { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
.activity-time { font-size: 12px; color: var(--text-secondary); flex-shrink: 0; }
.view-all-link { font-size: 12px; color: var(--accent); text-decoration: none; }
.view-all-link:hover { text-decoration: underline; }
.activity-more { text-align: center; padding: 10px 0 4px; font-size: 12px; }
.activity-more a { color: var(--accent); text-decoration: none; }
.activity-more a:hover { text-decoration: underline; }
</style>
