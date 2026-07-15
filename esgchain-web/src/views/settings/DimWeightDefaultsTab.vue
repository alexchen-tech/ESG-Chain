<template>
  <div class="dim-defaults-panel">
    <div class="panel-header">
      <div>
        <h2 class="panel-title">維度預設加權</h2>
        <p class="panel-desc">設定六軸評估維度的系統預設加權比例。新建立的評核系列將自動繼承此設定。</p>
      </div>
    </div>

    <div v-if="loading" class="loading-state">載入中…</div>

    <div v-else class="weight-form">
      <div class="weight-rows">
        <div v-for="dim in DIM_LIST" :key="dim.key" class="weight-row">
          <div class="dim-label">
            <span class="dim-code">{{ dim.key }}</span>
            <span class="dim-name">{{ dim.label }}</span>
          </div>
          <div class="input-wrap">
            <input
              v-model.number="form[dim.key]"
              type="number"
              min="0"
              max="100"
              step="1"
              class="weight-input font-mono"
              @input="onInput"
            />
            <span class="pct-unit">%</span>
          </div>
        </div>
      </div>

      <div class="sum-row" :class="{ 'sum-error': !isSumValid }">
        合計：<span class="font-mono">{{ totalPct }}%</span>
        <span v-if="!isSumValid" class="sum-hint">（須等於 100%）</span>
      </div>

      <div class="action-row">
        <button
          class="btn-save"
          :disabled="saving || !isSumValid"
          @click="save"
        >
          {{ saving ? '儲存中…' : '儲存預設加權' }}
        </button>
        <span v-if="savedOk" class="save-ok">✓ 已儲存</span>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { settingsApi } from '@/api/modules/settings'

const DIM_LIST = [
  { key: 'E1', label: 'ESG 綜合評估' },
  { key: 'E2', label: 'ISO 20400 永續採購' },
  { key: 'E3', label: 'ISO 26000 社會責任' },
  { key: 'E4', label: '地緣風險評估' },
  { key: 'E5', label: 'ISO 28000 供應鏈安全' },
  { key: 'E6', label: '產品合規' },
]

export default defineComponent({
  name: 'DimWeightDefaultsTab',

  data() {
    return {
      DIM_LIST,
      loading: true,
      saving: false,
      savedOk: false,
      form: { E1: 25, E2: 15, E3: 20, E4: 15, E5: 10, E6: 15 } as Record<string, number>,
    }
  },

  computed: {
    totalPct(): number {
      return DIM_LIST.reduce((s, d) => s + (this.form[d.key] || 0), 0)
    },
    isSumValid(): boolean {
      return Math.abs(this.totalPct - 100) < 1
    },
  },

  async mounted() {
    await this.load()
  },

  methods: {
    async load() {
      this.loading = true
      try {
        const res = await settingsApi.getDimWeightDefaults()
        const weights = res.data?.dim_weights ?? {}
        for (const d of DIM_LIST) {
          if (weights[d.key] !== undefined) {
            this.form[d.key] = Math.round(weights[d.key] * 100)
          }
        }
      } catch {
        // fallback to hardcoded defaults already in form
      } finally {
        this.loading = false
      }
    },

    onInput() {
      this.savedOk = false
    },

    async save() {
      if (!this.isSumValid || this.saving) return
      this.saving = true
      this.savedOk = false
      try {
        const dimWeights: Record<string, number> = {}
        for (const d of DIM_LIST) {
          dimWeights[d.key] = (this.form[d.key] || 0) / 100
        }
        await settingsApi.updateDimWeightDefaults(dimWeights)
        this.savedOk = true
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '儲存失敗，請稍後再試')
      } finally {
        this.saving = false
      }
    },
  },
})
</script>

<style scoped>
.dim-defaults-panel {
  max-width: 560px;
}

.panel-header {
  margin-bottom: 28px;
}

.panel-title {
  font-size: 18px;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0 0 6px;
}

.panel-desc {
  font-size: 13px;
  color: var(--text-secondary);
  margin: 0;
}

.loading-state {
  color: var(--text-secondary);
  font-size: 14px;
}

.weight-rows {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 20px;
}

.weight-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  background: var(--surface-2, #f0ede6);
  border: 1px solid var(--border, #e2ddd6);
  border-radius: 6px;
}

.dim-label {
  display: flex;
  align-items: center;
  gap: 10px;
}

.dim-code {
  font-family: var(--font-mono, monospace);
  font-size: 12px;
  font-weight: 700;
  color: var(--accent, #1a4d3e);
  background: color-mix(in srgb, var(--accent, #1a4d3e) 10%, transparent);
  padding: 2px 6px;
  border-radius: 4px;
  min-width: 28px;
  text-align: center;
}

.dim-name {
  font-size: 14px;
  color: var(--text-primary, #1a1714);
}

.input-wrap {
  display: flex;
  align-items: center;
  gap: 6px;
}

.weight-input {
  width: 64px;
  text-align: right;
  padding: 5px 8px;
  border: 1px solid var(--border, #e2ddd6);
  border-radius: 4px;
  font-size: 14px;
  background: var(--surface, #ffffff);
  color: var(--text-primary, #1c1917);
}

.weight-input:focus {
  outline: none;
  border-color: var(--accent, #1a4d3e);
}

.pct-unit {
  font-size: 14px;
  color: var(--text-secondary);
  width: 16px;
}

.sum-row {
  font-size: 14px;
  color: var(--text-secondary);
  padding: 10px 0;
  border-top: 1px solid var(--border);
  margin-bottom: 20px;
}

.sum-row.sum-error {
  color: var(--error, #dc3545);
}

.sum-hint {
  margin-left: 8px;
  font-size: 12px;
}

.action-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.btn-save {
  padding: 8px 20px;
  background: var(--accent, #1a4d3e);
  color: #fff;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  cursor: pointer;
  transition: opacity 0.15s;
}

.btn-save:hover:not(:disabled) {
  opacity: 0.88;
}

.btn-save:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.save-ok {
  font-size: 13px;
  color: var(--accent, #1a4d3e);
}

@media (prefers-color-scheme: dark) {
  .weight-row { background: var(--surface, #2a2825); }
  .weight-input { background: var(--surface, #2a2825); color: var(--text-primary, #f5f4f2); border-color: var(--border, #3a3835); }
  .dim-name { color: var(--text-primary, #f5f4f2); }
}

:root[data-theme="dark"] .weight-row { background: var(--surface, #2a2825); }
:root[data-theme="dark"] .weight-input { background: var(--surface, #2a2825); color: var(--text-primary, #f5f4f2); border-color: var(--border, #3a3835); }
:root[data-theme="dark"] .dim-name { color: var(--text-primary, #f5f4f2); }
:root[data-theme="light"] .weight-row { background: #ffffff; }
:root[data-theme="light"] .weight-input { background: #ffffff; }
</style>
