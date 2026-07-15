<template>
  <div class="card">
    <div class="card-title" style="margin-bottom:16px">商品合規風險彙總</div>
    <div v-if="!risk" class="empty-state"><p>載入中...</p></div>
    <div v-else>
      <div class="risk-grid">
        <div class="risk-block">
          <div class="risk-block__label">CBAM 適用商品</div>
          <div class="risk-block__value font-mono">{{ risk.cbam_products_count }} 件</div>
        </div>

        <div class="risk-block" :class="{ 'risk-block--urgent': risk.compliance_issues_count > 0 }">
          <div class="risk-block__label">合規問題商品</div>
          <div class="risk-block__value font-mono">{{ risk.compliance_issues_count }} 件</div>
        </div>
        <div class="risk-block" :class="{ 'risk-block--urgent': risk.eudr_pending_count > 0 }">
          <div class="risk-block__label">EUDR 待處理</div>
          <div class="risk-block__value font-mono">{{ risk.eudr_pending_count }} 件</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import type { PropType } from 'vue'
import type { ComplianceRisk } from '@/api/modules/dashboard'

export default defineComponent({
  name: 'DashboardComplianceRisk',
  props: {
    risk: {
      type: Object as PropType<ComplianceRisk | null>,
      default: null,
    },
  },
  methods: {
    formatAmount(val: number): string {
      return val.toLocaleString('de-DE', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
    },
  },
})
</script>

<style scoped>
.risk-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 12px;
}
.risk-block {
  background: var(--surface-2);
  border-radius: 6px;
  padding: 14px;
  border: 1px solid var(--border);
}
.risk-block--highlight {
  background: #f0fdf4;
  border-color: #86efac;
}
.risk-block--urgent {
  background: #fff5f5;
  border-color: #fca5a5;
}
.risk-block__label { font-size: 12px; color: var(--text-secondary); margin-bottom: 4px; }
.risk-block__value { font-size: 20px; font-weight: 700; }
.risk-block__sub { font-size: 11px; color: var(--text-secondary); margin-top: 2px; }
.missing-warning {
  font-size: 12px;
  color: #92400e;
  background: #fef3c7;
  border: 1px solid #fcd34d;
  border-radius: 4px;
  padding: 8px 12px;
}
</style>
