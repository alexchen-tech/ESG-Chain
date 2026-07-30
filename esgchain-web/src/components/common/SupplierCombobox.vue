<template>
  <div class="supplier-combobox" ref="root">
    <div class="combobox-controls">
      <div class="combobox-input-wrap">
        <input
          ref="input"
          v-model="keyword"
          class="form-input combobox-input"
          :placeholder="placeholder"
          @focus="onFocus"
          @input="onInput"
          @keydown.esc="close"
          @keydown.enter.prevent="selectFirst"
          autocomplete="off"
        />
        <button v-if="keyword || selectedSupplier" class="combobox-clear" @click.stop="clear" title="清除">×</button>
      </div>
      <select v-model="tierFilter" class="combobox-tier" style="width:88px;" @change="search">
        <option value="">全部 Tier</option>
        <option value="1">Tier 1</option>
        <option value="2">Tier 2</option>
        <option value="3">Tier 3</option>
      </select>
    </div>

    <!-- 已選狀態 -->
    <div v-if="selectedSupplier && !isOpen" class="combobox-selected">
      <span class="combobox-selected-name">{{ maskSupplierName(selectedSupplier.name) }}</span>
      <span class="combobox-selected-code font-mono">{{ selectedSupplier.code }}</span>
      <span class="tier-badge">T{{ selectedSupplier.tier }}</span>
    </div>

    <!-- 下拉清單 -->
    <div v-if="isOpen" class="combobox-dropdown">
      <div v-if="loading" class="combobox-loading">搜尋中…</div>
      <div v-else-if="results.length === 0" class="combobox-empty">找不到符合的認證供應商</div>
      <div
        v-for="s in results"
        :key="s.id"
        class="combobox-option"
        @mousedown.prevent="select(s)"
      >
        <div class="combobox-option-main">
          <span class="combobox-option-name">{{ maskSupplierName(s.name) }}</span>
          <span class="combobox-option-code font-mono">{{ s.code }}</span>
          <span class="tier-badge">T{{ s.tier }}</span>
        </div>
        <div v-if="complianceTags.length" class="combobox-option-tags">
          <span v-for="tag in complianceTags" :key="tag" class="compliance-req-tag">{{ tag }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import http from '@/api/http'
import { maskSupplierName } from '@/utils/maskName'

interface SupplierOption {
  id: string
  name: string
  code: string | null
  tier: number
  status: string
}

export default defineComponent({
  name: 'SupplierCombobox',
  props: {
    modelValue: { type: String, default: '' },
    materialGroupId: { type: String, default: null },
    placeholder: { type: String, default: '搜尋認證供應商名稱或代碼…' },
  },
  emits: ['update:modelValue'],

  data() {
    return {
      keyword: '',
      tierFilter: '' as string,
      results: [] as SupplierOption[],
      selectedSupplier: null as SupplierOption | null,
      isOpen: false,
      loading: false,
      debounceTimer: null as ReturnType<typeof setTimeout> | null,
      complianceTags: [] as string[],
    }
  },

  watch: {
    modelValue(val: string) {
      if (!val) { this.selectedSupplier = null; this.keyword = '' }
    },
    materialGroupId: {
      immediate: true,
      async handler(groupId: string | null) {
        this.complianceTags = []
        if (!groupId) return
        try {
          const res = await http.get<{ success: boolean; data: any }>(`/api/v1/compliance/material-groups`)
          const group = (res.data.data as any[]).find((g: any) => g.id === groupId)
          this.complianceTags = group?.required_doc_types ?? []
        } catch { /**/ }
      },
    },
  },

  mounted() {
    document.addEventListener('click', this.onOutsideClick)
  },
  beforeUnmount() {
    document.removeEventListener('click', this.onOutsideClick)
  },

  methods: {
    maskSupplierName,
    onFocus() {
      this.isOpen = true
      if (!this.results.length) this.search()
    },
    onInput() {
      if (this.debounceTimer) clearTimeout(this.debounceTimer)
      this.debounceTimer = setTimeout(() => this.search(), 300)
    },
    async search() {
      this.loading = true
      this.isOpen = true
      try {
        const params: Record<string, string> = { onboarding_stage: 'certified', per_page: '20' }
        if (this.keyword.trim()) params.q = this.keyword.trim()
        if (this.tierFilter) params.tier = this.tierFilter
        const res = await http.get<{ success: boolean; data: SupplierOption[] }>('/api/v1/suppliers', { params })
        this.results = res.data.data ?? []
      } catch {
        this.results = []
      } finally {
        this.loading = false
      }
    },
    select(s: SupplierOption) {
      this.selectedSupplier = s
      this.keyword = ''
      this.isOpen = false
      this.$emit('update:modelValue', s.id)
    },
    selectFirst() {
      if (this.results.length) this.select(this.results[0])
    },
    clear() {
      this.selectedSupplier = null
      this.keyword = ''
      this.results = []
      this.isOpen = false
      this.$emit('update:modelValue', '')
    },
    close() {
      this.isOpen = false
    },
    onOutsideClick(e: MouseEvent) {
      if (!(this.$refs.root as HTMLElement)?.contains(e.target as Node)) {
        this.isOpen = false
      }
    },
  },
})
</script>

<style scoped>
.supplier-combobox { position: relative; }
.combobox-controls { display: flex; gap: 6px; align-items: stretch; }
.combobox-input-wrap { position: relative; flex: 1; }
.combobox-input {
  width: 100%; padding: 6px 28px 6px 10px;
  height: 32px; box-sizing: border-box;
  border: 1px solid var(--border); border-radius: 6px;
  font-size: 12.5px; background: var(--surface); color: var(--text-primary); outline: none;
}
.combobox-input:focus { border-color: var(--accent); }
.combobox-clear {
  position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
  background: none; border: none; cursor: pointer; font-size: 14px;
  color: var(--text-secondary); line-height: 1;
}
.combobox-clear:hover { color: var(--text-primary); }
.combobox-tier {
  flex-shrink: 0;
  height: 32px; padding: 0 8px;
  border: 1px solid var(--border); border-radius: 6px;
  font-size: 12px; background: var(--surface); color: var(--text-secondary);
  cursor: pointer; outline: none;
}

.combobox-selected {
  display: flex; align-items: center; gap: 6px;
  padding: 4px 8px; margin-top: 4px;
  background: var(--surface-2); border-radius: 4px;
  font-size: 13px;
}
.combobox-selected-name { font-weight: 500; }
.combobox-selected-code { color: var(--text-secondary); font-size: 12px; }

.combobox-dropdown {
  position: absolute; z-index: 200; top: calc(100% + 4px); left: 0; right: 0;
  background: var(--surface); border: 1px solid var(--border); border-radius: 6px;
  box-shadow: 0 4px 12px rgba(0,0,0,.08); max-height: 280px; overflow-y: auto;
}
.combobox-loading, .combobox-empty {
  padding: 12px 14px; font-size: 13px; color: var(--text-secondary); text-align: center;
}
.combobox-option {
  padding: 8px 14px; cursor: pointer; border-bottom: 1px solid var(--border);
}
.combobox-option:last-child { border-bottom: none; }
.combobox-option:hover { background: var(--surface-2); }
.combobox-option-main { display: flex; align-items: center; gap: 6px; }
.combobox-option-name { font-size: 13px; font-weight: 500; }
.combobox-option-code { font-size: 11px; color: var(--text-secondary); }
.combobox-option-tags { margin-top: 4px; display: flex; gap: 4px; flex-wrap: wrap; }

.tier-badge {
  font-size: 10px; font-weight: 600; padding: 1px 5px;
  background: var(--surface-2); border: 1px solid var(--border);
  border-radius: 3px; color: var(--text-secondary);
}
.compliance-req-tag {
  font-size: 10px; padding: 1px 5px; border-radius: 3px;
  background: #fef3c7; border: 1px solid #d97706; color: #92400e;
}
</style>
