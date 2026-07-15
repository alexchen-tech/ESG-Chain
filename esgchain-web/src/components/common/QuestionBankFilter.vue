<template>
  <div class="qbf-bar">
    <input
      v-model="keyword"
      class="qbf-input"
      placeholder="搜尋題目文字..."
      @input="onKeywordInput"
    />
    <select v-model="selectedL1" class="qbf-select" @change="onL1Change">
      <option value="">所有框架領域</option>
      <option v-for="domain in tree" :key="domain.l1_domain" :value="domain.l1_domain">
        {{ domain.l1_domain }}
      </option>
    </select>
    <select v-model="selectedL2" class="qbf-select" :disabled="!selectedL1" @change="onL2Change">
      <option value="">{{ selectedL1 ? '所有支柱' : '— 請先選框架領域 —' }}</option>
      <option v-for="pillar in currentPillars" :key="pillar.l2_pillar" :value="pillar.l2_pillar">
        {{ pillar.l2_pillar }}
      </option>
    </select>
    <select v-model="selectedL3" class="qbf-select" :disabled="!selectedL2" @change="emitChange">
      <option value="">所有主題</option>
      <option v-for="topic in currentTopics" :key="topic.slug" :value="topic.slug">
        {{ topic.label_zh || topic.l3_topic }}
      </option>
    </select>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { tagLibraryApi, type TagTreeDomain, type TagTreePillar, type TagTreeTopic } from '@/api/modules/settings'
import type { BankFilterPayload } from '@/constants/questionBank'

export default defineComponent({
  name: 'QuestionBankFilter',
  emits: ['change'],
  data() {
    return {
      tree: [] as TagTreeDomain[],
      keyword: '',
      selectedL1: '',
      selectedL2: '',
      selectedL3: '',
      debounceTimer: null as ReturnType<typeof setTimeout> | null,
    }
  },
  computed: {
    currentPillars(): TagTreePillar[] {
      return this.tree.find(d => d.l1_domain === this.selectedL1)?.pillars ?? []
    },
    currentTopics(): TagTreeTopic[] {
      return this.currentPillars.find(p => p.l2_pillar === this.selectedL2)?.topics ?? []
    },
  },
  mounted() {
    this.loadTree()
  },
  methods: {
    async loadTree() {
      try {
        const res = await tagLibraryApi.tree()
        this.tree = res.data.data
      } catch { /**/ }
    },
    onKeywordInput() {
      if (this.debounceTimer) clearTimeout(this.debounceTimer)
      this.debounceTimer = setTimeout(() => this.emitChange(), 300)
    },
    onL1Change() {
      this.selectedL2 = ''
      this.selectedL3 = ''
      this.emitChange()
    },
    onL2Change() {
      this.selectedL3 = ''
      this.emitChange()
    },
    emitChange() {
      const payload: BankFilterPayload = { keyword: this.keyword }
      if (this.selectedL1) payload.l1 = this.selectedL1
      if (this.selectedL2) payload.l2 = this.selectedL2
      if (this.selectedL3) payload.l3 = this.selectedL3
      this.$emit('change', payload)
    },
  },
})
</script>

<style scoped>
.qbf-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.qbf-input {
  width: 220px;
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 7px 12px;
  font-size: 14px;
  background: var(--surface);
  color: var(--text-primary);
}
.qbf-input:focus { outline: 2px solid var(--accent); border-color: transparent; }
.qbf-input::placeholder { color: var(--text-secondary); }

.qbf-select {
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 7px 10px;
  font-size: 14px;
  background: var(--surface);
  color: var(--text-primary);
  cursor: pointer;
  min-width: 130px;
}
.qbf-select:focus { outline: 2px solid var(--accent); border-color: transparent; }
.qbf-select:disabled { opacity: 0.5; cursor: not-allowed; background: var(--surface-2); }
</style>
