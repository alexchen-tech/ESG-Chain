<template>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h1 class="page-title">永續 KPI 填報</h1>
        <p class="page-subtitle">填報您的永續績效數據，將顯示於採購商的揭露資料時間序列</p>
      </div>
    </div>

    <div v-if="isLoading" class="loading-mask" style="padding:40px;text-align:center;">載入中...</div>

    <template v-else>
      <!-- 年度選擇器 -->
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
        <label style="font-size:13px;color:var(--text-secondary);">填報年度</label>
        <select v-model="selectedYear" class="filter-select" style="width:120px;">
          <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>

      <!-- KPI sections by prefix -->
      <div v-for="(fields, prefix) in fieldsByPrefix" :key="prefix" class="section-block">
        <div class="section-header">{{ prefixLabel(prefix) }}</div>
        <div class="kpi-grid">
          <div v-for="field in fields" :key="field.slug" class="kpi-row">
            <div class="kpi-info">
              <span class="kpi-label">{{ field.label }}</span>
              <span v-if="field.unit" class="kpi-unit">{{ field.unit }}</span>
            </div>

            <!-- Source badge -->
            <div class="kpi-source">
              <span v-if="currentRecord(field)" class="source-badge" :class="'source-' + currentRecord(field).source">
                {{ sourceLabelMap[currentRecord(field).source] }}
              </span>
            </div>

            <!-- saq_sync overwrite warning -->
            <div v-if="currentRecord(field) && currentRecord(field).source === 'saq_sync'" class="saq-warning">
              此數值已由問卷自動同步，修改後將標記為手動填報
            </div>

            <!-- Input -->
            <div class="kpi-input-row">
              <template v-if="field.data_type === 'boolean'">
                <label class="toggle-label">
                  <input type="checkbox"
                    :checked="getBoolValue(field)"
                    @change="setBoolValue(field, ($event.target as HTMLInputElement).checked)" />
                  <span>{{ getBoolValue(field) ? '是' : '否' }}</span>
                </label>
              </template>
              <template v-else-if="field.data_type === 'single_choice'">
                <select
                  class="filter-select"
                  :value="getTextValue(field)"
                  @change="setTextValue(field, ($event.target as HTMLSelectElement).value)"
                >
                  <option value="" disabled>請選擇</option>
                  <option v-for="opt in field.options ?? []" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
              </template>
              <template v-else>
                <input
                  type="number"
                  class="kpi-number-input font-mono"
                  :value="getNumValue(field)"
                  @input="setNumValue(field, parseFloat(($event.target as HTMLInputElement).value))"
                  :placeholder="`輸入數值${field.unit ? ' (' + field.unit + ')' : ''}`"
                  step="any"
                />
              </template>

              <button
                class="btn btn-primary btn-sm"
                :disabled="savingKey === field.slug || !hasChanged(field)"
                @click="save(field)"
              >
                <span v-if="savingKey === field.slug">儲存中...</span>
                <span v-else>儲存</span>
              </button>

              <!-- Inline feedback -->
              <span v-if="savedKey === field.slug" class="save-success">已儲存 ✓</span>
              <span v-if="overwroteKey === field.slug" class="save-overwrite">已覆蓋問卷數值</span>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { portalDisclosureApi, type DisclosureFieldKpi } from '@/api/modules/portalDisclosure'
import { DISCLOSURE_PREFIX_LABELS as PREFIX_LABELS } from '@/constants/disclosureDomains'

const SOURCE_LABELS: Record<string, string> = {
  saq_sync: '來自問卷',
  manual: '手動填報',
  erp_sync: 'ERP 同步',
}

export default defineComponent({
  name: 'PortalDisclosureView',

  data() {
    const currentYear = new Date().getFullYear()
    return {
      isLoading: false,
      fields: [] as DisclosureFieldKpi[],
      selectedYear: currentYear,
      yearOptions: Array.from({ length: currentYear - 2019 }, (_, i) => currentYear - i),
      // draft values: slug -> value (number | boolean)
      draftValues: {} as Record<string, number | boolean | null>,
      savingKey: null as string | null,
      savedKey: null as string | null,
      overwroteKey: null as string | null,
      sourceLabelMap: SOURCE_LABELS,
    }
  },

  computed: {
    fieldsByPrefix(): Record<string, DisclosureFieldKpi[]> {
      const map: Record<string, DisclosureFieldKpi[]> = {}
      for (const f of this.fields) {
        const prefix = f.slug.split('.')[0]
        if (!map[prefix]) map[prefix] = []
        map[prefix].push(f)
      }
      return map
    },
  },

  mounted() {
    this.loadDisclosures()
  },

  watch: {
    selectedYear() {
      this.draftValues = {}
      this.savedKey = null
      this.overwroteKey = null
    },
  },

  methods: {
    async loadDisclosures() {
      this.isLoading = true
      try {
        const { data } = await portalDisclosureApi.list()
        this.fields = data.data
        // Pre-fill drafts from current year records
        for (const f of this.fields) {
          const rec = this.currentRecord(f)
          if (rec !== null) {
            this.draftValues[f.slug] = f.data_type === 'boolean' ? rec.boolean_value : rec.numeric_value
          }
        }
      } finally {
        this.isLoading = false
      }
    },

    currentRecord(field: DisclosureFieldKpi) {
      return field.records.find(r => r.period_year === this.selectedYear) ?? null
    },

    getBoolValue(field: DisclosureFieldKpi): boolean {
      if (field.slug in this.draftValues) return !!this.draftValues[field.slug]
      const rec = this.currentRecord(field)
      return rec ? !!rec.boolean_value : false
    },

    setBoolValue(field: DisclosureFieldKpi, val: boolean) {
      this.draftValues[field.slug] = val
    },

    getNumValue(field: DisclosureFieldKpi): number | string {
      if (field.slug in this.draftValues) {
        const v = this.draftValues[field.slug]
        return v == null ? '' : (v as number)
      }
      const rec = this.currentRecord(field)
      return rec && rec.numeric_value != null ? rec.numeric_value : ''
    },

    setNumValue(field: DisclosureFieldKpi, val: number) {
      this.draftValues[field.slug] = isNaN(val) ? null : val
    },

    getTextValue(field: DisclosureFieldKpi): string {
      if (field.slug in this.draftValues) return (this.draftValues[field.slug] as string) ?? ''
      const rec = this.currentRecord(field)
      return rec?.text_value ?? ''
    },

    setTextValue(field: DisclosureFieldKpi, val: string) {
      this.draftValues[field.slug] = val
    },

    hasChanged(field: DisclosureFieldKpi): boolean {
      if (!(field.slug in this.draftValues)) {
        // boolean always editable even without draft
        return field.data_type === 'boolean'
      }
      return true
    },

    async save(field: DisclosureFieldKpi) {
      this.savingKey = field.slug
      this.savedKey = null
      this.overwroteKey = null
      try {
        let value: number | boolean | string
        if (field.data_type === 'boolean') {
          value = this.getBoolValue(field)
        } else if (field.data_type === 'single_choice') {
          value = this.getTextValue(field)
        } else {
          value = this.draftValues[field.slug] as number
        }
        const { data } = await portalDisclosureApi.save({
          field_slug: field.slug,
          period_year: this.selectedYear,
          value,
        })
        // Update local records
        const idx = field.records.findIndex(r => r.period_year === this.selectedYear)
        const newRec = {
          period_year: this.selectedYear,
          numeric_value: field.data_type === 'numeric' ? (value as number) : null,
          boolean_value: field.data_type === 'boolean' ? (value as boolean) : null,
          text_value: field.data_type === 'single_choice' ? (value as string) : null,
          source: 'manual' as const,
          source_saq_id: null,
          updated_at: new Date().toISOString(),
        }
        if (idx >= 0) field.records.splice(idx, 1, newRec)
        else field.records.unshift(newRec)

        this.savedKey = field.slug
        if (data.overwritten_saq_sync) this.overwroteKey = field.slug
        setTimeout(() => { if (this.savedKey === field.slug) this.savedKey = null }, 3000)
        setTimeout(() => { if (this.overwroteKey === field.slug) this.overwroteKey = null }, 5000)
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '儲存失敗')
      } finally {
        this.savingKey = null
      }
    },

    prefixLabel(prefix: string): string {
      return PREFIX_LABELS[prefix] ?? prefix
    },
  },
})
</script>

<style scoped>
.section-block {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  margin-bottom: 16px;
  overflow: hidden;
}

.section-header {
  padding: 10px 16px;
  font-size: 13px;
  font-weight: 600;
  color: var(--text-secondary);
  background: var(--surface-2);
  border-bottom: 1px solid var(--border);
  letter-spacing: .03em;
}

.kpi-grid {
  padding: 0;
}

.kpi-row {
  padding: 12px 16px;
  border-bottom: 1px solid var(--border);
  display: grid;
  grid-template-columns: 1fr auto;
  grid-template-rows: auto auto auto;
  gap: 4px 8px;
  align-items: start;
}

.kpi-row:last-child {
  border-bottom: none;
}

.kpi-info {
  display: flex;
  align-items: center;
  gap: 6px;
  grid-column: 1;
}

.kpi-label {
  font-size: 13px;
  color: var(--text-primary);
}

.kpi-unit {
  font-size: 11px;
  color: var(--text-secondary);
  background: var(--surface-2);
  padding: 1px 6px;
  border-radius: 4px;
}

.kpi-source {
  grid-column: 2;
  grid-row: 1;
  text-align: right;
}

.source-badge {
  font-size: 11px;
  padding: 2px 7px;
  border-radius: 4px;
  font-weight: 500;
}

.source-saq_sync { background: #dbeafe; color: #1d4ed8; }
.source-manual   { background: #dcfce7; color: #15803d; }
.source-erp_sync { background: #f3e8ff; color: #6d28d9; }

.saq-warning {
  grid-column: 1 / -1;
  font-size: 11px;
  color: #b45309;
  background: #fef9c3;
  padding: 4px 8px;
  border-radius: 4px;
}

.kpi-input-row {
  grid-column: 1 / -1;
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 4px;
}

.kpi-number-input {
  width: 180px;
  padding: 6px 10px;
  border: 1px solid var(--border);
  border-radius: 6px;
  font-size: 13px;
  background: var(--surface);
  color: var(--text-primary);
}

.kpi-number-input:focus {
  outline: none;
  border-color: var(--accent);
}

.toggle-label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  font-size: 13px;
}

.save-success {
  font-size: 12px;
  color: #15803d;
  font-weight: 500;
}

.save-overwrite {
  font-size: 12px;
  color: #b45309;
  font-weight: 500;
}
</style>
