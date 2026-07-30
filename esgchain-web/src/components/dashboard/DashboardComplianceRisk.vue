<template>
  <div class="card">
    <div class="card-title" style="margin-bottom:16px">商品合規風險彙總</div>
    <div v-if="!risk" class="empty-state"><p>載入中...</p></div>
    <div v-else>
      <!-- CBAM：以歐元風險金額為主視覺，數量/缺值為輔 -->
      <div class="risk-group">
        <div class="risk-group__label">CBAM 碳關稅</div>
        <div class="risk-hero" :class="{ 'risk-hero--zero': risk.cbam_risk_amount_eur === 0 }" @click="goTo('/trade-goods?cbam=yes')">
          <div class="risk-hero__value font-mono">€{{ formatAmount(risk.cbam_risk_amount_eur) }}</div>
          <div class="risk-hero__label">預估碳關稅風險金額</div>
        </div>
        <div class="risk-grid">
          <div class="risk-block risk-block--link" @click="goTo('/trade-goods?cbam=yes')">
            <div class="risk-block__label">適用商品</div>
            <div class="risk-block__value font-mono">{{ risk.cbam_products_count }} 件</div>
          </div>
          <div class="risk-block risk-block--link" :class="{ 'risk-block--urgent': risk.cbam_missing_emissions > 0 }" @click="goTo('/trade-goods?cbam=yes')">
            <div class="risk-block__label">缺碳排資料</div>
            <div class="risk-block__value font-mono">{{ risk.cbam_missing_emissions }} 件</div>
          </div>
        </div>
      </div>

      <!-- 其他合規：文件到期、EUDR -->
      <div class="risk-group">
        <div class="risk-group__label">其他合規</div>
        <div class="risk-grid">
          <div class="risk-block risk-block--link" :class="{ 'risk-block--urgent': risk.compliance_issues_count > 0 }" @click="goTo('/compliance')">
            <div class="risk-block__label">合規文件已過期供應商</div>
            <div class="risk-block__value font-mono">{{ risk.compliance_issues_count }} 家</div>
          </div>
          <div class="risk-block risk-block--link" :class="{ 'risk-block--urgent': risk.eudr_pending_count > 0 }" @click="goTo('/trade-goods?eudr=yes')">
            <div class="risk-block__label">EUDR 待處理</div>
            <div class="risk-block__value font-mono">{{ risk.eudr_pending_count }} 件</div>
          </div>
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
    goTo(path: string) {
      this.$router.push(path)
    },
  },
})
</script>

<style scoped>
.risk-group { margin-bottom: 16px; }
.risk-group:last-child { margin-bottom: 0; }
.risk-group__label {
  font-size: 12px;
  font-weight: 600;
  color: var(--text-secondary);
  margin-bottom: 8px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.risk-hero {
  background: var(--accent-soft);
  border: 1px solid var(--accent-soft-border);
  border-radius: 8px;
  padding: 14px 16px;
  margin-bottom: 10px;
  cursor: pointer;
  transition: opacity 0.1s;
}
.risk-hero:hover { opacity: 0.85; }
.risk-hero--zero { background: var(--surface-2); border-color: var(--border); }
.risk-hero__value { font-size: 24px; font-weight: 700; color: var(--accent); }
.risk-hero--zero .risk-hero__value { color: var(--text-primary); }
.risk-hero__label { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }

.risk-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.risk-block {
  background: var(--surface-2);
  border-radius: 6px;
  padding: 14px;
  border: 1px solid var(--border);
}
.risk-block--link { cursor: pointer; transition: background 0.1s, border-color 0.1s; }
.risk-block--link:hover { background: var(--surface); border-color: #c9c4bc; }
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
