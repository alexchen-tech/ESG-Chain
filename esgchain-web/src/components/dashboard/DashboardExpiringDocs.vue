<template>
  <div class="card">
    <div class="card-title" style="margin-bottom:16px">合規文件到期預警（7 天內）</div>
    <div v-if="docs.length === 0" class="empty-state"><p>未來 7 天無到期文件</p></div>
    <div v-else class="timeline">
      <div
        v-for="(doc, idx) in docs"
        :key="idx"
        class="timeline-item"
        :class="{ 'timeline-item--critical': doc.days_remaining <= 3 }"
      >
        <div class="timeline-dot" :class="{ 'timeline-dot--critical': doc.days_remaining <= 3 }"></div>
        <div class="timeline-body">
          <div class="timeline-supplier">{{ doc.supplier_name }}</div>
          <div class="timeline-doc">{{ doc.doc_type }}</div>
        </div>
        <div class="timeline-days font-mono" :class="{ 'timeline-days--critical': doc.days_remaining <= 3 }">
          {{ doc.days_remaining }} 天後到期
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import type { PropType } from 'vue'
import type { ExpiringDoc } from '@/api/modules/dashboard'

export default defineComponent({
  name: 'DashboardExpiringDocs',
  props: {
    docs: {
      type: Array as PropType<ExpiringDoc[]>,
      default: () => [],
    },
  },
})
</script>

<style scoped>
.timeline { position: relative; padding-left: 16px; }
.timeline::before {
  content: '';
  position: absolute;
  left: 6px;
  top: 8px;
  bottom: 8px;
  width: 2px;
  background: var(--border);
}
.timeline-item {
  display: flex;
  align-items: center;
  gap: 12px;
  position: relative;
  padding: 8px 0;
}
.timeline-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--accent);
  flex-shrink: 0;
  position: absolute;
  left: -19px;
}
.timeline-dot--critical { background: #dc2626; }
.timeline-item--critical { background: #fff5f5; margin: 0 -8px; padding: 8px; border-radius: 4px; }
.timeline-body { flex: 1; }
.timeline-supplier { font-size: 13px; font-weight: 500; }
.timeline-doc { font-size: 12px; color: var(--text-secondary); }
.timeline-days { font-size: 12px; color: var(--text-secondary); }
.timeline-days--critical { color: #dc2626; font-weight: 600; }
</style>
