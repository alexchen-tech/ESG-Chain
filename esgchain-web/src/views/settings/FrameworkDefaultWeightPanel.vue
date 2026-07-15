<template>
  <div class="section-card">
    <div class="section-header">
      <div>
        <h2 class="section-title">設定框架加權</h2>
        <p class="section-desc">設定各評核框架的預設 Pillar 加權比例，新建 Series 計分設定將以此為初始值。</p>
      </div>
    </div>

    <div v-if="isLoadingWeights" class="loading-state">載入中...</div>

    <div v-else class="framework-tabs">
      <div class="tab-list">
        <button
          v-for="fw in frameworks"
          :key="fw.key"
          class="tab-btn"
          :class="{ active: activeFramework === fw.key }"
          @click="activeFramework = fw.key"
        >
          {{ fw.label }}
        </button>
      </div>

      <div v-for="fw in frameworks" v-show="activeFramework === fw.key" :key="fw.key" class="tab-panel">
        <div v-if="weightForms[fw.key]" class="pillar-table-wrap">
          <table class="pillar-table">
            <thead>
              <tr>
                <th>Pillar</th>
                <th style="width:160px;">加權比例</th>
                <th style="width:200px;">佔比</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="node in fw.nodes" :key="node.id">
                <td style="font-weight:500;">{{ node.label_zh }}</td>
                <td>
                  <div class="weight-input-wrap">
                    <input
                      type="number"
                      class="weight-input font-mono"
                      min="0" max="1" step="0.01"
                      :value="weightForms[fw.key][node.id]"
                      @input="onWeightInput(fw.key, node.id, $event)"
                    />
                    <span class="weight-pct font-mono">{{ pctDisplay(weightForms[fw.key][node.id]) }}%</span>
                  </div>
                </td>
                <td>
                  <div class="progress-bar-track">
                    <div class="progress-bar-fill" :style="{ width: pctDisplay(weightForms[fw.key][node.id]) + '%' }"></div>
                  </div>
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <td style="font-weight:600;">合計</td>
                <td colspan="2">
                  <span :class="['weight-total font-mono', weightSumClass(fw.key)]">
                    {{ weightSum(fw.key).toFixed(2) }}
                  </span>
                  <span v-if="Math.abs(weightSum(fw.key) - 1) > 0.01" class="weight-error">合計須等於 1.0</span>
                </td>
              </tr>
            </tfoot>
          </table>

          <div class="save-row">
            <button
              class="btn btn-primary"
              :disabled="Math.abs(weightSum(fw.key) - 1) > 0.01 || savingFramework === fw.key"
              @click="saveFrameworkWeights(fw.key)"
            >
              <span v-if="savingFramework === fw.key">儲存中...</span>
              <span v-else>儲存</span>
            </button>
            <span v-if="savedFramework === fw.key" class="save-success">✓ 已儲存，新 Series 將使用此預設值。</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { tagLibraryApi } from '@/api/modules/settings'

// 靜態 E-code 對照表（l1_domain → Tab 標籤）
const E_CODE_LABELS = {
  'ESG':                'E1 · ESG',
  'ISO20400':           'E2 · ISO 20400',
  'ISO26000':           'E3 · ISO 26000',
  'Geo-Risk':           'E4 · Geo-Risk',
  'ISO28000':           'E5 · ISO 28000',
  'Product-Compliance': 'E6 · 產品合規',
}

// Tab 顯示順序
const FRAMEWORK_ORDER = ['ESG', 'ISO20400', 'ISO26000', 'Geo-Risk', 'ISO28000', 'Product-Compliance']

export default {
  name: 'FrameworkDefaultWeightPanel',

  data() {
    return {
      isLoadingWeights: false,
      activeFramework: 'ESG',
      frameworks: [],    // [{ key, label, nodes: [{id, slug, label_zh, default_weight}] }]
      weightForms: {},   // { l1_domain: { nodeId: weight } }
      savingFramework: null,
      savedFramework: null,
    }
  },

  mounted() {
    this.loadWeights()
  },

  methods: {
    async loadWeights() {
      this.isLoadingWeights = true
      try {
        const res = await tagLibraryApi.getL2Nodes()
        const grouped = res.data.data || {}

        // 依 FRAMEWORK_ORDER 排序 tab
        const fws = FRAMEWORK_ORDER
          .filter(k => grouped[k]?.length)
          .map(k => ({
            key: k,
            label: E_CODE_LABELS[k] || k,
            nodes: grouped[k],
          }))

        // 補上 API 回傳但不在預設順序內的框架
        for (const k of Object.keys(grouped)) {
          if (!fws.find(f => f.key === k)) {
            fws.push({ key: k, label: k, nodes: grouped[k] })
          }
        }

        this.frameworks = fws

        // 初始化 weightForms
        const forms = {}
        for (const fw of fws) {
          forms[fw.key] = {}
          for (const node of fw.nodes) {
            forms[fw.key][node.id] = node.default_weight ?? (1 / fw.nodes.length)
          }
        }
        this.weightForms = forms
      } finally {
        this.isLoadingWeights = false
      }
    },

    onWeightInput(framework, nodeId, event) {
      const val = parseFloat(event.target.value)
      if (!isNaN(val)) {
        this.weightForms[framework][nodeId] = val
      }
    },

    pctDisplay(weight) {
      return ((weight || 0) * 100).toFixed(1)
    },

    weightSum(framework) {
      const form = this.weightForms[framework] || {}
      return Object.values(form).reduce((s, v) => s + (v || 0), 0)
    },

    weightSumClass(framework) {
      const sum = this.weightSum(framework)
      return Math.abs(sum - 1) <= 0.01 ? 'weight-sum-ok' : 'weight-sum-error'
    },

    async saveFrameworkWeights(framework) {
      const fw = this.frameworks.find(f => f.key === framework)
      if (!fw) return

      this.savingFramework = framework
      this.savedFramework = null
      try {
        // 逐筆更新每個 L2 節點的 default_weight
        await Promise.all(
          fw.nodes.map(node =>
            tagLibraryApi.updateL2NodeWeight(node.id, this.weightForms[framework][node.id])
          )
        )
        this.savedFramework = framework
        setTimeout(() => { this.savedFramework = null }, 4000)
      } catch (e) {
        alert(e?.response?.data?.message || '儲存失敗')
      } finally {
        this.savingFramework = null
      }
    },
  },
}
</script>

<style scoped>
.section-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 24px;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0 0 4px;
}

.section-desc {
  font-size: 13px;
  color: var(--text-secondary);
  margin: 0;
}

.loading-state {
  text-align: center;
  padding: 32px;
  color: var(--text-secondary);
}

.framework-tabs { }

.tab-list {
  display: flex;
  gap: 4px;
  border-bottom: 1px solid var(--border);
  margin-bottom: 20px;
}

.tab-btn {
  padding: 8px 16px;
  border: none;
  background: none;
  cursor: pointer;
  font-size: 13px;
  color: var(--text-secondary);
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  border-radius: 4px 4px 0 0;
  transition: all 0.15s;
}

.tab-btn:hover { color: var(--text-primary); background: var(--surface-hover, #f5f4f2); }
.tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); font-weight: 500; }

.tab-panel { }

.pillar-table-wrap { overflow-x: auto; }

.pillar-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.pillar-table th {
  text-align: left;
  padding: 8px 12px;
  border-bottom: 1px solid var(--border);
  font-weight: 500;
  color: var(--text-secondary);
  background: var(--surface-alt, #faf9f7);
}

.pillar-table td {
  padding: 10px 12px;
  border-bottom: 1px solid var(--border-light, #eeece8);
  vertical-align: middle;
}

.pillar-table tfoot td {
  border-top: 2px solid var(--border);
  border-bottom: none;
  padding-top: 10px;
  font-weight: 600;
}

.weight-input-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}

.weight-input {
  width: 72px;
  padding: 4px 8px;
  border: 1px solid var(--border);
  border-radius: 4px;
  font-size: 13px;
  background: var(--surface);
}

.weight-pct {
  font-size: 12px;
  color: var(--text-secondary);
  min-width: 44px;
}

.progress-bar-track {
  height: 6px;
  background: var(--border-light, #eeece8);
  border-radius: 3px;
  overflow: hidden;
}

.progress-bar-fill {
  height: 100%;
  background: var(--accent);
  border-radius: 3px;
  transition: width 0.2s;
}

.weight-total { font-size: 14px; }
.weight-sum-ok { color: var(--accent); }
.weight-sum-error { color: #dc2626; }
.weight-error { margin-left: 8px; font-size: 12px; color: #dc2626; }

.save-row {
  margin-top: 16px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.save-success {
  font-size: 13px;
  color: var(--accent);
}
</style>
