<template>
  <div class="portal-pcf-view">
    <div class="page-header">
      <div>
        <h1 class="page-title">碳排申報</h1>
        <p class="page-subtitle">填寫各物料的產品碳足跡（PCF）數值</p>
      </div>
    </div>

    <div v-if="loading" class="loading-state">載入中…</div>

    <template v-else>
      <!-- 待申報區塊 -->
      <template v-if="pendingRequests.length > 0">
        <div class="section-label">待申報（{{ pendingRequests.length }}）</div>
        <div
          v-for="req in pendingRequests"
          :key="req.id"
          class="pcf-card"
        >
          <div class="pcf-card-header">
            <div class="pcf-card-meta">
              <span class="font-mono period-text">{{ req.period_start }} ～ {{ req.period_end }}</span>
              <span
                class="due-badge"
                :class="dueBadgeClass(req)"
              >
                截止 {{ req.due_date }}
                <span v-if="req.days_left <= 14 && req.days_left >= 0">（剩 {{ req.days_left }} 天）</span>
                <span v-if="req.days_left < 0">（已逾期）</span>
              </span>
            </div>
            <div class="pcf-card-progress">
              <div class="progress-bar-wrap">
                <div
                  class="progress-bar-fill"
                  :style="{ width: progressPct(req) + '%' }"
                  :class="progressPct(req) === 100 ? 'fill-done' : 'fill-partial'"
                ></div>
              </div>
              <span class="font-mono progress-label">{{ req.progress.submitted }}/{{ req.progress.total }}</span>
            </div>
          </div>

          <!-- SAQ 整合入口 -->
          <div v-if="req.saq_round_id" class="saq-link-row">
            <span class="saq-label">企業排放問卷</span>
            <router-link :to="'/supplier/portal'" class="saq-btn">填寫問卷 →</router-link>
          </div>

          <!-- 逐料填寫 -->
          <table class="line-table">
            <thead>
              <tr>
                <th>物料名稱</th>
                <th>HS Code</th>
                <th>碳排數值（kgCO₂e/unit）</th>
                <th>計量單位</th>
                <th>狀態</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="line in req.lines" :key="line.id">
                <td>{{ line.material_name }}</td>
                <td class="font-mono">{{ line.hs_code || '-' }}</td>
                <td>
                  <input
                    v-if="line.status === 'pending'"
                    v-model.number="lineInputs[line.id].declared_value"
                    type="number"
                    min="0"
                    step="0.001"
                    class="value-input font-mono"
                    placeholder="0.000"
                  />
                  <span v-else class="font-mono submitted-value">已提交</span>
                </td>
                <td>
                  <input
                    v-if="line.status === 'pending'"
                    v-model="lineInputs[line.id].quantity_unit"
                    type="text"
                    class="unit-input"
                    placeholder="kg"
                  />
                  <span v-else class="submitted-value">{{ lineInputs[line.id]?.quantity_unit || '-' }}</span>
                </td>
                <td>
                  <span class="line-status" :class="'ls-' + line.status">
                    {{ lineStatusLabel(line.status) }}
                  </span>
                </td>
                <td>
                  <button
                    v-if="line.status === 'pending'"
                    class="btn-save"
                    :disabled="savingLine === line.id"
                    @click="saveLine(req.id, line)"
                  >
                    {{ savingLine === line.id ? '儲存中…' : '儲存' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- 整體提交 -->
          <div class="card-footer">
            <button
              class="btn-submit"
              :disabled="progressPct(req) < 100 || submittingReq === req.id"
              @click="submitRequest(req.id)"
            >
              {{ submittingReq === req.id ? '提交中…' : '整體提交' }}
            </button>
            <span v-if="progressPct(req) < 100" class="submit-hint">所有物料填寫完畢後可提交</span>
          </div>
        </div>
      </template>
      <div v-else class="empty-state">
        <p>目前沒有待申報的碳排請求</p>
      </div>

      <!-- 已完成區塊 -->
      <div v-if="doneRequests.length > 0" class="done-section">
        <button class="collapse-btn" @click="showDone = !showDone">
          已完成（{{ doneRequests.length }}）{{ showDone ? ' ▲' : ' ▼' }}
        </button>
        <div v-if="showDone">
          <div v-for="req in doneRequests" :key="req.id" class="pcf-card pcf-card-done">
            <div class="pcf-card-header">
              <span class="font-mono period-text">{{ req.period_start }} ～ {{ req.period_end }}</span>
              <span class="status-badge status-submitted">{{ req.status === 'verified' ? '已驗證' : '已提交' }}</span>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { portalPcfApi, type PcfRequestDetail, type PcfRequestLine } from '@/api/modules/pcf'

export default defineComponent({
  name: 'PortalPcfView',
  data() {
    return {
      loading: false,
      requests: [] as PcfRequestDetail[],
      showDone: false,
      lineInputs: {} as Record<string, { declared_value: number | null; quantity_unit: string }>,
      savingLine: null as string | null,
      submittingReq: null as string | null,
    }
  },
  computed: {
    pendingRequests(): PcfRequestDetail[] {
      return this.requests.filter((r) => r.status === 'pending' || r.status === 'overdue')
    },
    doneRequests(): PcfRequestDetail[] {
      return this.requests.filter((r) => r.status === 'submitted' || r.status === 'verified')
    },
  },
  mounted() {
    this.loadData()
  },
  methods: {
    async loadData() {
      this.loading = true
      try {
        const res = await portalPcfApi.list()
        this.requests = res.data.data || []
        // 初始化 lineInputs
        this.lineInputs = {}
        for (const req of this.requests) {
          for (const line of req.lines) {
            this.lineInputs[line.id] = { declared_value: null, quantity_unit: 'kg' }
          }
        }
      } catch {
        this.requests = []
      } finally {
        this.loading = false
      }
    },
    progressPct(req: PcfRequestDetail) {
      if (!req.progress.total) return 0
      return Math.round((req.progress.submitted / req.progress.total) * 100)
    },
    dueBadgeClass(req: PcfRequestDetail) {
      if (req.days_left < 0 || req.status === 'overdue') return 'due-overdue'
      if (req.days_left <= 14) return 'due-warn'
      return 'due-normal'
    },
    lineStatusLabel(status: string) {
      const map: Record<string, string> = { pending: '待填寫', submitted: '已提交', verified: '已驗證' }
      return map[status] || status
    },
    async saveLine(reqId: string, line: PcfRequestLine) {
      const input = this.lineInputs[line.id]
      if (input.declared_value === null || input.declared_value < 0) return
      if (!input.quantity_unit) return

      this.savingLine = line.id
      try {
        await portalPcfApi.updateLine(reqId, line.id, {
          declared_value: input.declared_value,
          quantity_unit: input.quantity_unit,
        })
        // 更新本地狀態
        const req = this.requests.find((r) => r.id === reqId)
        if (req) {
          const l = req.lines.find((l) => l.id === line.id)
          if (l) {
            l.status = 'submitted'
            req.progress.submitted++
          }
        }
      } catch {
        // 錯誤略
      } finally {
        this.savingLine = null
      }
    },
    async submitRequest(reqId: string) {
      this.submittingReq = reqId
      try {
        await portalPcfApi.submit(reqId)
        const req = this.requests.find((r) => r.id === reqId)
        if (req) req.status = 'submitted'
      } catch (err: any) {
        const msg = err?.response?.data?.message || '提交失敗'
        alert(msg)
      } finally {
        this.submittingReq = null
      }
    },
  },
})
</script>

<style scoped>
.portal-pcf-view { padding: 24px; }
.page-header { margin-bottom: 24px; }
.page-title { font-size: 20px; font-weight: 600; color: var(--text-primary); margin: 0 0 4px; }
.page-subtitle { font-size: 13px; color: var(--text-secondary); margin: 0; }

.section-label { font-size: 13px; font-weight: 600; color: var(--text-secondary); margin-bottom: 10px; }

.pcf-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  margin-bottom: 16px;
  overflow: hidden;
}
.pcf-card-done { opacity: 0.7; }
.pcf-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 18px;
  border-bottom: 1px solid var(--border);
  background: var(--surface-2);
}
.pcf-card-meta { display: flex; align-items: center; gap: 12px; }
.period-text { font-size: 13px; color: var(--text-primary); }

.due-badge { font-size: 12px; font-weight: 600; padding: 2px 8px; border-radius: 10px; }
.due-normal { background: #f0fdf4; color: #166534; }
.due-warn { background: #fff7ed; color: #c2410c; }
.due-overdue { background: #fee2e2; color: #991b1b; }

.pcf-card-progress { display: flex; align-items: center; gap: 8px; }
.progress-bar-wrap { width: 80px; height: 6px; background: var(--border); border-radius: 3px; }
.progress-bar-fill { height: 100%; border-radius: 3px; transition: width 0.3s; }
.fill-done { background: #16a34a; }
.fill-partial { background: var(--accent); }
.progress-label { font-size: 12px; color: var(--text-secondary); }

.saq-link-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 18px;
  background: #f0f9ff;
  border-bottom: 1px solid #bae6fd;
}
.saq-label { font-size: 12px; color: #0369a1; }
.saq-btn { font-size: 12px; color: #0369a1; font-weight: 600; text-decoration: none; }

.line-table { width: 100%; border-collapse: collapse; }
.line-table th {
  padding: 8px 14px;
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-secondary);
  background: var(--surface-2);
  border-bottom: 1px solid var(--border);
}
.line-table td { padding: 10px 14px; font-size: 13px; color: var(--text-primary); border-bottom: 1px solid var(--border); }
.line-table tr:last-child td { border-bottom: none; }

.value-input {
  width: 120px;
  padding: 5px 8px;
  border: 1px solid var(--border);
  border-radius: 5px;
  font-size: 13px;
  background: var(--surface);
  color: var(--text-primary);
}
.unit-input {
  width: 64px;
  padding: 5px 8px;
  border: 1px solid var(--border);
  border-radius: 5px;
  font-size: 13px;
}
.submitted-value { color: var(--text-secondary); font-size: 13px; }

.line-status { display: inline-block; padding: 2px 7px; border-radius: 10px; font-size: 11px; font-weight: 600; }
.ls-pending { background: #fef9c3; color: #854d0e; }
.ls-submitted { background: #dcfce7; color: #166534; }
.ls-verified { background: #d1fae5; color: #065f46; }

.btn-save {
  padding: 4px 12px;
  background: var(--accent);
  color: #fff;
  border: none;
  border-radius: 5px;
  font-size: 12px;
  cursor: pointer;
  font-weight: 500;
}
.btn-save:disabled { opacity: 0.5; cursor: not-allowed; }

.card-footer {
  padding: 14px 18px;
  display: flex;
  align-items: center;
  gap: 10px;
  border-top: 1px solid var(--border);
  background: var(--surface-2);
}
.btn-submit {
  padding: 8px 20px;
  background: #16a34a;
  color: #fff;
  border: none;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}
.btn-submit:disabled { opacity: 0.45; cursor: not-allowed; }
.submit-hint { font-size: 12px; color: var(--text-secondary); }

.done-section { margin-top: 24px; }
.collapse-btn {
  background: none;
  border: none;
  font-size: 13px;
  font-weight: 600;
  color: var(--text-secondary);
  cursor: pointer;
  padding: 0;
  margin-bottom: 10px;
}

.status-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
.status-submitted { background: #dcfce7; color: #166534; }

.loading-state, .empty-state { padding: 40px; text-align: center; color: var(--text-secondary); }
.font-mono { font-family: 'Fira Code', monospace; }
</style>
