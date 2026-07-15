<template>
  <Teleport to="body">
    <div class="cm-backdrop" @click.self="$emit('close')">
      <div class="cm-modal">
        <!-- Header -->
        <div class="cm-header">
          <span class="cm-title">供應商比較</span>
          <span class="cm-subtitle">{{ suppliers.length }} 家廠商</span>
          <span v-if="maxGapDim" class="cm-gap-badge">
            差距最大：<strong>{{ maxGapDim.key }} {{ maxGapDim.label }}</strong>
            <span class="font-mono cm-gap-num">{{ maxGapDim.gap }} 分</span>
          </span>
          <button class="cm-close" @click="$emit('close')">×</button>
        </div>

        <!-- 比較表 -->
        <div class="cm-scroll">
          <table class="cm-table">
            <thead>
              <tr>
                <th class="cm-label-col">維度</th>
                <th
                  v-for="s in suppliers"
                  :key="s.id"
                  class="cm-supplier-col"
                >
                  <div class="cm-supplier-head">
                    <span class="cm-sup-name">{{ s.name }}</span>
                    <span class="cm-sup-meta">{{ s.country_code }} · Tier {{ s.tier }}</span>
                    <span class="cm-sup-stage badge" :class="stageClass(s.onboarding_stage)">{{ s.onboarding_stage || '—' }}</span>
                  </div>
                  <button class="cm-remove" @click="removeSupplier(s.id)" title="從比較移除">×</button>
                </th>
              </tr>
            </thead>

            <tbody>
              <!-- 載入中 -->
              <tr v-if="loading">
                <td :colspan="suppliers.length + 1" class="cm-loading-cell">載入六維評分中…</td>
              </tr>

              <template v-else>
                <!-- SAQ 六維 -->
                <tr class="cm-section-row">
                  <td :colspan="suppliers.length + 1" class="cm-section-label">SAQ 六軸評分</td>
                </tr>
                <tr v-for="dim in DIMS" :key="dim.key">
                  <td class="cm-label-col cm-row-label">
                    <span class="cm-dim-key">{{ dim.key }}</span>
                    <span class="cm-dim-name">{{ dim.label }}</span>
                  </td>
                  <td
                    v-for="s in suppliers"
                    :key="s.id"
                    class="cm-data-col"
                    :class="dimHighlight(s.id, dim.field)"
                  >
                    <template v-if="dimScore(s.id, dim.field) != null">
                      <div class="cm-bar-wrap">
                        <div class="cm-bar-track">
                          <div
                            class="cm-bar"
                            :class="barClass(dimScore(s.id, dim.field))"
                            :style="{ width: dimScore(s.id, dim.field) + '%' }"
                          ></div>
                        </div>
                        <span class="cm-bar-val font-mono">{{ dimScore(s.id, dim.field).toFixed(1) }}</span>
                      </div>
                    </template>
                    <span v-else class="cm-no-data">—</span>
                  </td>
                </tr>

                <!-- Open CAP -->
                <tr class="cm-section-row">
                  <td :colspan="suppliers.length + 1" class="cm-section-label">矯正行動</td>
                </tr>
                <tr>
                  <td class="cm-label-col cm-row-label">Open CAP</td>
                  <td
                    v-for="s in suppliers"
                    :key="s.id"
                    class="cm-data-col"
                    :class="{ 'cm-worst': (s.open_cap_count ?? 0) > 0 && isMaxCap(s) }"
                  >
                    <span v-if="(s.open_cap_count ?? 0) > 0" class="cm-cap-count font-mono">{{ s.open_cap_count }}</span>
                    <span v-else class="cm-no-cap font-mono">0</span>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Footer -->
        <div class="cm-footer">
          <button class="btn btn-secondary btn-sm" @click="$emit('close')">關閉</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { useCompareStore } from '@/stores/compareStore'
import { riskApi } from '@/api/modules/risk'

const DIMS = [
  { key: 'E1', field: 'E1', label: '環境管理' },
  { key: 'E2', field: 'E2', label: '氣候與碳排' },
  { key: 'E3', field: 'E3', label: '社會責任' },
  { key: 'E4', field: 'E4', label: '地緣風險' },
  { key: 'E5', field: 'E5', label: '公司治理' },
  { key: 'E6', field: 'E6', label: '供應鏈透明度' },
]

export default defineComponent({
  name: 'CompareModal',
  emits: ['close'],

  setup() {
    return { compareStore: useCompareStore(), DIMS }
  },

  data() {
    return {
      loading: false,
      // supplierId → { e1…e6: number|null }
      sixDimMap: {} as Record<string, Record<string, number | null>>,
    }
  },

  computed: {
    suppliers() { return this.compareStore.suppliers },

    maxCapCount(): number {
      return Math.max(...this.suppliers.map(s => (s as any).open_cap_count ?? 0))
    },

    maxGapDim(): { key: string; label: string; gap: number } | null {
      if (this.suppliers.length < 2 || this.loading) return null
      let maxGap = 0
      let result: { key: string; label: string; gap: number } | null = null
      for (const dim of DIMS) {
        const vals = this.suppliers
          .map(s => this.dimScore(s.id, dim.field))
          .filter(v => v != null) as number[]
        if (vals.length < 2) continue
        const gap = Math.max(...vals) - Math.min(...vals)
        if (gap > maxGap) { maxGap = gap; result = { key: dim.key, label: dim.label, gap: Math.round(gap * 10) / 10 } }
      }
      return result
    },
  },

  watch: {
    suppliers: {
      immediate: true,
      handler(val) {
        if (val.length === 0) { this.$emit('close'); return }
        this.fetchMissing()
      },
    },
  },

  methods: {
    async fetchMissing() {
      const missing = this.suppliers.filter(s => !this.sixDimMap[s.id])
      if (!missing.length) return
      this.loading = true
      await Promise.all(missing.map(async s => {
        try {
          const res = await riskApi.riskSummary(s.id)
          this.sixDimMap[s.id] = res.data?.data?.six_dims ?? {}
        } catch {
          this.sixDimMap[s.id] = {}
        }
      }))
      this.loading = false
    },

    dimScore(supplierId: string, field: string): number | null {
      return this.sixDimMap[supplierId]?.[field] ?? null
    },

    dimHighlight(supplierId: string, field: string): string {
      const vals = this.suppliers
        .map(s => this.dimScore(s.id, field))
        .filter(v => v != null) as number[]
      if (vals.length < 2) return ''
      const mine = this.dimScore(supplierId, field)
      if (mine == null) return ''
      if (mine === Math.max(...vals)) return 'cm-best'
      if (mine === Math.min(...vals)) return 'cm-worst'
      return ''
    },

    barClass(score: number | null): string {
      if (score == null) return 'bar-unknown'
      if (score >= 75) return 'bar-ok'
      if (score >= 50) return 'bar-warn'
      return 'bar-danger'
    },

    removeSupplier(id: string) { this.compareStore.remove(id) },

    stageClass(stage: string | null): string {
      const map: Record<string, string> = { active: 'badge-green', suspended: 'badge-yellow', terminated: 'badge-red' }
      return stage ? (map[stage] ?? '') : ''
    },

    isMaxCap(s: any): boolean {
      return (s.open_cap_count ?? 0) === this.maxCapCount && this.maxCapCount > 0
    },
  },
})
</script>

<style scoped>
.cm-backdrop {
  position: fixed; inset: 0;
  background: rgba(0,0,0,.45);
  z-index: 1000;
  display: flex; align-items: center; justify-content: center;
  padding: 20px;
}

.cm-modal {
  background: #fff;
  border-radius: 12px;
  width: 100%;
  max-width: 1000px;
  max-height: 88vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 60px rgba(0,0,0,.2);
  overflow: hidden;
}

.cm-header {
  padding: 14px 20px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  gap: 10px;
  background: #fafaf9;
  flex-shrink: 0;
}
.cm-title { font-size: 15px; font-weight: 700; }
.cm-subtitle { font-size: 12px; color: var(--text-secondary); }
.cm-gap-badge {
  font-size: 12px; color: var(--text-secondary);
  padding: 2px 10px 2px 8px;
  background: var(--surface, #f5f4f2);
  border-radius: 4px;
  display: flex; align-items: center; gap: 5px;
}
.cm-gap-badge strong { color: var(--text-primary); font-weight: 600; }
.cm-gap-num { color: #e53e3e; }
.cm-close {
  margin-left: auto;
  background: none; border: none;
  font-size: 22px; cursor: pointer;
  color: var(--text-secondary); line-height: 1;
  padding: 2px 6px; border-radius: 4px;
}
.cm-close:hover { background: var(--border); color: var(--text-primary); }

.cm-scroll { flex: 1; overflow: auto; }

.cm-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 600px;
}

.cm-label-col {
  width: 140px;
  min-width: 120px;
  padding: 10px 14px;
  font-size: 12px;
  color: var(--text-secondary);
  background: #fafaf9;
  border-right: 1px solid var(--border);
  vertical-align: middle;
  position: sticky; left: 0; z-index: 1;
}
.cm-supplier-col {
  min-width: 180px;
  padding: 12px 14px;
  border-bottom: 1px solid var(--border);
  border-right: 1px solid var(--border);
  vertical-align: top;
  position: relative;
}
.cm-supplier-head { display: flex; flex-direction: column; gap: 3px; padding-right: 28px; }
.cm-sup-name { font-size: 14px; font-weight: 700; }
.cm-sup-meta { font-size: 11.5px; color: var(--text-secondary); }
.cm-sup-stage { font-size: 11px; align-self: flex-start; }
.cm-remove {
  position: absolute; top: 10px; right: 10px;
  background: none; border: none;
  font-size: 16px; cursor: pointer;
  color: var(--text-secondary); padding: 2px 5px;
  border-radius: 4px; line-height: 1;
}
.cm-remove:hover { background: #fee2e2; color: #991b1b; }

.cm-section-row td { background: var(--surface, #f8f7f6); }
.cm-section-label {
  padding: 7px 14px;
  font-size: 11px;
  font-weight: 700;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: .05em;
  border-top: 1px solid var(--border);
}

.cm-data-col {
  padding: 9px 14px;
  border-bottom: 1px solid var(--border);
  border-right: 1px solid var(--border);
  vertical-align: middle;
  transition: background 0.1s;
}
.cm-row-label {
  display: flex; align-items: center; gap: 6px;
  font-size: 12.5px; font-weight: 500; color: var(--text-primary);
}
.cm-dim-key {
  font-size: 11px; font-weight: 700;
  color: var(--accent, #1a4d3e);
  background: rgba(26,77,62,0.08);
  padding: 1px 5px; border-radius: 3px;
  flex-shrink: 0;
}
.cm-dim-name { font-size: 12px; color: var(--text-secondary); }

/* 最佳/最差高亮 */
.cm-best  { background: #dcfce7 !important; }
.cm-worst { background: #fee2e2 !important; }

/* 長條 */
.cm-bar-wrap { display: flex; align-items: center; gap: 8px; }
.cm-bar-track { flex: 1; height: 14px; background: var(--surface, #f0ede8); border-radius: 4px; overflow: hidden; }
.cm-bar { height: 100%; border-radius: 4px; min-width: 3px; transition: width 0.3s ease; }
.bar-ok     { background: #48bb78; }
.bar-warn   { background: #ed8936; }
.bar-danger { background: #e53e3e; }
.bar-unknown { background: #cbd5e0; }
.cm-bar-val { font-size: 12px; color: var(--text-primary); min-width: 34px; text-align: right; }

.cm-no-data { font-size: 12px; color: var(--text-secondary); }
.cm-loading-cell { padding: 24px; text-align: center; font-size: 13px; color: var(--text-secondary); }

.cm-cap-count { font-size: 12px; color: #991b1b; }
.cm-no-cap    { font-size: 12px; color: var(--text-secondary); }

.cm-footer {
  padding: 12px 20px;
  border-top: 1px solid var(--border);
  display: flex; justify-content: flex-end;
  background: #fafaf9;
  flex-shrink: 0;
}
</style>
