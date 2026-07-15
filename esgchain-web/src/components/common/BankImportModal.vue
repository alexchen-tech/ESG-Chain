<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal modal-wide">
      <div class="modal-header">
        <span class="modal-title">從題目庫選題</span>
        <button class="modal-close" @click="$emit('close')">×</button>
      </div>

      <!-- 過濾 -->
      <div style="margin-bottom:12px;display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
        <QuestionBankFilter @change="onFilterChange" />
        <select v-model="complianceDomainFilter" class="form-select" style="width:140px;font-size:13px;" @change="filterBank">
          <option value="">全部範疇</option>
          <option v-for="d in COMPLIANCE_DOMAINS" :key="d" :value="d">{{ d }}</option>
        </select>
      </div>

      <!-- 題目列表 -->
      <div class="bank-list">
        <div v-if="isLoading" class="loading-mask">載入中...</div>
        <div v-else-if="filtered.length === 0 && allItems.length === 0" class="empty-state" style="padding:24px;text-align:center;">
          <p>題庫目前沒有題目</p>
          <button class="btn btn-secondary btn-sm" style="margin-top:8px;" @click="$router.push('/settings/question-bank'); $emit('close')">
            前往題目庫新增
          </button>
        </div>
        <div v-else-if="filtered.length === 0" class="empty-inline" style="padding:16px;text-align:center;">無符合條件的題目</div>
        <label
          v-else
          v-for="q in filtered"
          :key="q.id"
          class="bank-item"
          :class="{ selected: selectedIds.includes(q.id) }"
        >
          <input type="checkbox" :value="q.id" v-model="selectedIds" style="flex-shrink:0;" />
          <template v-for="domain in getL1Domains(q)" :key="domain">
            <span class="badge badge-domain" style="margin-left:8px;flex-shrink:0;">{{ domain }}</span>
          </template>
          <span class="bank-item-text">{{ q.question_text }}</span>
          <template v-if="(q as any).compliance_domains && (q as any).compliance_domains.length">
            <span v-for="cd in (q as any).compliance_domains" :key="cd" class="badge badge-compliance" style="margin-left:4px;flex-shrink:0;">{{ cd }}</span>
          </template>
          <div class="bank-item-meta">
            <span class="badge badge-gray" style="font-size:10px;">{{ typeLabel(q.question_type) }}</span>
            <span v-if="q.usage_count > 0" class="usage-badge usage-active">{{ q.usage_count }}</span>
          </div>
        </label>
      </div>

      <div class="modal-footer" style="justify-content:space-between;">
        <span style="font-size:13px;color:var(--text-secondary);">
          {{ selectedIds.length > 0 ? `已選 ${selectedIds.length} 道` : '請勾選要加入的題目' }}
        </span>
        <div style="display:flex;gap:8px;">
          <button class="btn btn-secondary" @click="$emit('close')">取消</button>
          <button class="btn btn-primary" :disabled="!selectedIds.length || isImporting" @click="doImport">
            {{ isImporting ? '匯入中...' : `加入範本（${selectedIds.length}）` }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { useRouter } from 'vue-router'
import { questionBankApi, importFromBankApi, type QuestionBankItem } from '@/api/modules/settings'
import QuestionBankFilter from '@/components/common/QuestionBankFilter.vue'
import { applyBankFilter, type BankFilterPayload } from '@/constants/questionBank'

const TYPE_LABELS: Record<string, string> = {
  boolean: '是/否', single_choice: '單選', multiple_choice: '多選', text: '文字', number: '數字',
}
const COMPLIANCE_DOMAINS = ['UFLPA', 'EUDR', 'CMRT', 'SDS', 'CE'] as const

export default defineComponent({
  name: 'BankImportModal',
  components: { QuestionBankFilter },
  props: {
    templateId: { type: String, required: true },
  },
  emits: ['close', 'imported'],
  setup() { return { router: useRouter(), COMPLIANCE_DOMAINS } },

  data() {
    return {
      isLoading: false, isImporting: false,
      allItems: [] as QuestionBankItem[],
      filtered: [] as QuestionBankItem[],
      selectedIds: [] as string[],
      activeFilter: { keyword: '' } as BankFilterPayload,
      complianceDomainFilter: '',
    }
  },

  mounted() { this.loadBank() },

  methods: {
    async loadBank() {
      this.isLoading = true
      try {
        const { data } = await questionBankApi.list()
        this.allItems = data.data
        this.filterBank()
      } finally { this.isLoading = false }
    },

    onFilterChange(payload: BankFilterPayload) {
      this.activeFilter = payload
      this.filterBank()
    },

    filterBank() {
      let items = applyBankFilter(this.allItems, this.activeFilter)
      if (this.complianceDomainFilter) {
        items = items.filter(q => (q as any).compliance_domains?.includes(this.complianceDomainFilter))
      }
      this.filtered = items
    },

    async doImport() {
      if (!this.selectedIds.length) return
      this.isImporting = true
      try {
        await importFromBankApi.import(this.templateId, this.selectedIds)
        this.$emit('imported')
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '匯入失敗')
      } finally { this.isImporting = false }
    },

    typeLabel: (t: string) => TYPE_LABELS[t] ?? t,

    getL1Domains(q: QuestionBankItem): string[] {
      if (!q.question_tags?.length) return []
      return [...new Set(q.question_tags.map(tag => tag.l1_domain).filter(Boolean))]
    },
  },
})
</script>

<style scoped>
.modal-wide { min-width: 600px; max-width: 740px; }

.bank-list {
  max-height: 320px; overflow-y: auto;
  border: 1px solid var(--border); border-radius: 6px;
  margin-bottom: 12px;
}

.bank-item {
  display: flex; align-items: center; gap: 0;
  padding: 10px 12px; cursor: pointer;
  border-bottom: 1px solid var(--border); transition: background 0.1s;
}
.bank-item:last-child { border-bottom: none; }
.bank-item:hover { background: var(--surface-2); }
.bank-item.selected { background: #f0fdf4; }

.bank-item-text { flex: 1; font-size: 13px; margin: 0 10px; }
.bank-item-meta { display: flex; gap: 6px; align-items: center; flex-shrink: 0; }

.badge-domain { background: #ecfdf5; color: #065f46; font-size: 11px; }
.badge-compliance { background: #e0f2fe; color: #0369a1; font-size: 10px; font-weight: 600; }

.usage-badge { font-size: 11px; padding: 1px 7px; border-radius: 99px; font-weight: 600; }
.usage-active { background: #dcfce7; color: #166534; }
</style>
