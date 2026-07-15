<template>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h1 class="page-title">採購需求</h1>
        <p class="page-subtitle">客戶指定物料及所需合規文件（產品資訊匿名顯示）</p>
      </div>
    </div>

    <!-- KPI 摘要 -->
    <div v-if="!isLoading" class="kpi-grid">
      <div class="kpi-card kpi-card--red">
        <div class="kpi-value kpi-value--red font-mono">{{ missingCount }}</div>
        <div class="kpi-label">待補件文件</div>
      </div>
      <div class="kpi-card kpi-card--green">
        <div class="kpi-value kpi-value--green font-mono">{{ validCount }}</div>
        <div class="kpi-label">已提交有效</div>
      </div>
      <div class="kpi-card kpi-card--orange">
        <div class="kpi-value kpi-value--orange font-mono">{{ warnCount }}</div>
        <div class="kpi-label">即將到期 / 其他</div>
      </div>
    </div>

    <div v-if="isLoading" class="loading-mask" style="padding:64px;text-align:center;">載入中...</div>

    <div v-else-if="requirements.length === 0" class="card empty-state" style="padding:64px;text-align:center;">
      <div style="font-size:40px;margin-bottom:12px;">📦</div>
      <p style="font-weight:500;margin-bottom:4px;">目前尚無客戶採購需求</p>
      <p style="color:var(--text-secondary);font-size:13px;">當採購商在 BOM 明細中指定您為供應商時，需求將顯示於此</p>
    </div>

    <div v-else>
      <div
        v-for="product in requirements"
        :key="product.product_id"
        class="product-req-card card"
      >
        <div class="product-req-header">
          <div class="product-req-title">客戶產品 #{{ product.product_index }}</div>
          <div class="product-req-regs">
            <span
              v-for="reg in product.applicable_regulations"
              :key="reg"
              class="reg-badge"
              :class="`reg-badge--${reg}`"
            >{{ reg }}</span>
          </div>
        </div>

        <div v-if="!product.bom_lines.length" class="req-empty">此產品無 BOM 明細指定</div>

        <div v-else class="bom-lines">
          <div v-for="line in product.bom_lines" :key="line.bom_line_id" class="bom-line-row">
            <div class="bom-line-info">
              <div class="bom-material-name">
                {{ line.material_name }}
                <span class="bom-type-chip" :class="line.bom_line_type === 'service' ? 'bom-type-service' : 'bom-type-material'">
                  {{ line.bom_line_type === 'service' ? '服務' : '原物料' }}
                </span>
                <span v-if="line.role === 'alternate'" class="role-chip">替代供應商</span>
              </div>
              <div v-if="line.material_group" class="bom-material-group">
                <span class="group-chip">{{ line.material_group }}</span>
              </div>
            </div>

            <div class="bom-doc-requirements">
              <div v-if="!line.submitted_docs.length" class="no-req">無文件需求</div>
              <div v-else class="doc-list">
                <div
                  v-for="d in line.submitted_docs"
                  :key="d.doc_type"
                  class="doc-item"
                  :class="docItemClass(d.status)"
                >
                  <span class="doc-type-label">{{ docTypeShort(d.doc_type) }}</span>
                  <span class="doc-status-badge" :class="docStatusClass(d.status)">
                    {{ docStatusLabel(d.status) }}
                  </span>
                  <span v-if="d.expires_at && d.status === 'valid'" class="doc-expire">
                    到期 {{ d.expires_at.slice(0, 10) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { portalProcurementApi, type BomRequirementProduct } from '@/api/modules/compliance'

const DOC_TYPE_SHORT: Record<string, string> = {
  UFLPA_DECLARATION: 'UFLPA', EUDR_DDS: 'EUDR', CMRT: 'CMRT',
  SDS: 'SDS', CE_DOC: 'CE', ORIGIN_CERT: '原產地', OTHER: '其他',
}

export default defineComponent({
  name: 'PortalProcurementView',
  data() {
    return {
      isLoading: false,
      requirements: [] as BomRequirementProduct[],
    }
  },

  computed: {
    allDocs(): { doc_type: string; status: string; expires_at: string | null }[] {
      return this.requirements.flatMap(p => p.bom_lines.flatMap(l => l.submitted_docs))
    },
    missingCount(): number { return this.allDocs.filter(d => d.status === 'missing' || d.status === 'expired').length },
    validCount(): number { return this.allDocs.filter(d => d.status === 'valid').length },
    warnCount(): number { return this.allDocs.filter(d => d.status === 'expiring_soon' || d.status === 'pending').length },
  },

  mounted() { this.loadData() },

  methods: {
    async loadData() {
      this.isLoading = true
      try {
        const { data } = await portalProcurementApi.getRequirements()
        this.requirements = data.data
      } catch {
        this.requirements = []
      } finally { this.isLoading = false }
    },

    docTypeShort: (t: string) => DOC_TYPE_SHORT[t] ?? t,

    docStatusLabel(status: string): string {
      const map: Record<string, string> = { valid: '已提交', missing: '待補件', expired: '已過期', expiring_soon: '即將到期', pending: '待審核' }
      return map[status] ?? status
    },

    docStatusClass(status: string): string {
      const map: Record<string, string> = { valid: 'badge--green', missing: 'badge--red', expired: 'badge--red', expiring_soon: 'badge--orange', pending: 'badge--gray' }
      return map[status] ?? 'badge--gray'
    },

    docItemClass(status: string): string {
      if (status === 'missing' || status === 'expired') return 'doc-item--missing'
      if (status === 'valid') return 'doc-item--valid'
      return 'doc-item--warn'
    },
  },
})
</script>

<style scoped>
/* KPI */
.kpi-grid { display: flex; gap: 12px; margin-bottom: 20px; }
.kpi-card {
  background: var(--surface); border: 1px solid var(--border); border-radius: 10px;
  padding: 14px 24px; text-align: center; min-width: 90px;
}
.kpi-card--green { border-top: 3px solid #16a34a; }
.kpi-card--orange { border-top: 3px solid #d97706; }
.kpi-card--red { border-top: 3px solid #dc2626; }
.kpi-value { font-size: 30px; font-weight: 700; line-height: 1; }
.kpi-value--green { color: #16a34a; }
.kpi-value--orange { color: #d97706; }
.kpi-value--red { color: #dc2626; }
.kpi-label { font-size: 11px; color: var(--text-secondary); margin-top: 4px; font-weight: 500; }
.font-mono { font-family: 'Fira Code', monospace; }

/* 產品卡 */
.product-req-card { margin-bottom: 12px; padding: 0; overflow: hidden; }
.product-req-header {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 20px; background: var(--surface-2); border-bottom: 1px solid var(--border);
}
.product-req-title { font-size: 14px; font-weight: 700; color: var(--text-primary); }
.product-req-regs { display: flex; gap: 5px; }
.reg-badge {
  font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px;
}
.reg-badge--UFLPA { background: #fee2e2; color: #b91c1c; }
.reg-badge--EUDR  { background: #fef3c7; color: #92400e; }
.reg-badge--CMRT  { background: #dbeafe; color: #1d4ed8; }
.reg-badge--REACH { background: #ede9fe; color: #5b21b6; }
.reg-badge--CE    { background: #dcfce7; color: #15803d; }

.req-empty { padding: 20px; color: var(--text-secondary); font-size: 13px; }

.bom-lines { padding: 8px 0; }
.bom-line-row {
  display: flex; gap: 16px; align-items: flex-start;
  padding: 10px 20px; border-bottom: 1px solid var(--border);
}
.bom-line-row:last-child { border-bottom: none; }

.bom-line-info { width: 200px; flex-shrink: 0; }
.bom-material-name { font-size: 13px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px; }
.bom-material-group { }
.group-chip { font-size: 11px; font-weight: 500; padding: 2px 8px; border-radius: 4px; background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }

.bom-doc-requirements { flex: 1; }
.no-req { font-size: 12px; color: var(--text-secondary); }
.doc-list { display: flex; flex-direction: column; gap: 6px; }

.doc-item {
  display: flex; align-items: center; gap: 8px;
  padding: 6px 10px; border-radius: 6px; border: 1px solid transparent;
}
.doc-item--valid   { background: #f0fdf4; border-color: #bbf7d0; }
.doc-item--missing { background: #fef2f2; border-color: #fecaca; }
.doc-item--warn    { background: #fffbeb; border-color: #fde68a; }

.doc-type-label { font-size: 12px; font-weight: 600; color: var(--text-primary); min-width: 70px; }
.doc-expire { font-size: 11px; color: var(--text-secondary); margin-left: auto; }

.doc-status-badge {
  font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 99px;
}
.badge--green  { background: #dcfce7; color: #15803d; }
.badge--red    { background: #fee2e2; color: #b91c1c; }
.badge--orange { background: #fef3c7; color: #b45309; }
.badge--gray   { background: var(--surface-2); color: var(--text-secondary); }

.bom-type-chip { font-size: 10px; font-weight: 600; padding: 1px 5px; border-radius: 3px; margin-left: 6px; }
.bom-type-material { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
.bom-type-service  { background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; }
.role-chip { font-size: 10px; font-weight: 600; padding: 1px 5px; border-radius: 3px; margin-left: 4px; background: #f0ede6; color: #78716c; border: 1px solid #e2ddd6; }
</style>
