<template>
  <div class="tag-selector">
    <div class="tag-chips" v-if="modelValue.length > 0">
      <span
        v-for="tag in modelValue"
        :key="tag.id"
        class="tag-chip"
      >
        <span class="tag-chip-label">{{ tag.l1_domain }} › {{ tag.l2_pillar }} › {{ tag.label_zh || tag.l3_topic }}</span>
        <button class="tag-chip-remove" @click="removeTag(tag.id)" type="button">✕</button>
      </span>
    </div>
    <div v-else class="tag-empty-hint">尚未選擇任何分類標籤</div>

    <div class="tag-adder" v-if="!showCascade">
      <button class="btn btn-secondary btn-sm" @click="showCascade = true" type="button">+ 新增標籤</button>
    </div>

    <div class="tag-cascade" v-if="showCascade">
      <div class="cascade-row">
        <select v-model="selectedL1" @change="onL1Change" class="form-select cascade-select">
          <option value="">選擇 L1 領域</option>
          <option v-for="domain in tree" :key="domain.l1_domain" :value="domain.l1_domain">
            {{ domain.l1_domain }}
          </option>
        </select>

        <select v-model="selectedL2" @change="onL2Change" class="form-select cascade-select" :disabled="!selectedL1">
          <option value="">選擇 L2 支柱</option>
          <option v-for="pillar in currentPillars" :key="pillar.l2_pillar" :value="pillar.l2_pillar">
            {{ pillar.l2_pillar }}
          </option>
        </select>

        <select v-model="selectedTagId" class="form-select cascade-select" :disabled="!selectedL2">
          <option value="">選擇 L3 主題</option>
          <option v-for="topic in currentTopics" :key="topic.id" :value="topic.id">
            {{ topic.label_zh || topic.l3_topic }}
          </option>
        </select>
      </div>

      <div class="cascade-actions">
        <button
          class="btn btn-primary btn-sm"
          :disabled="!selectedTagId"
          @click="addTag"
          type="button"
        >加入</button>
        <button class="btn btn-secondary btn-sm" @click="cancelCascade" type="button">取消</button>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import { tagLibraryApi, type QuestionTag, type TagTreeDomain, type TagTreePillar } from '@/api/modules/settings'

export default defineComponent({
  name: 'TagSelector',
  props: {
    modelValue: {
      type: Array as PropType<QuestionTag[]>,
      default: () => [],
    },
  },
  emits: ['update:modelValue'],
  data() {
    return {
      tree: [] as TagTreeDomain[],
      showCascade: false,
      selectedL1: '',
      selectedL2: '',
      selectedTagId: '',
    }
  },
  computed: {
    currentPillars(): TagTreePillar[] {
      const domain = this.tree.find(d => d.l1_domain === this.selectedL1)
      return domain?.pillars ?? []
    },
    currentTopics(): QuestionTag[] {
      const pillar = this.currentPillars.find(p => p.l2_pillar === this.selectedL2)
      const alreadySelectedIds = this.modelValue.map(t => t.id)
      return (pillar?.topics ?? []).filter(t =>
        t.scoring_engine_key != null && !alreadySelectedIds.includes(t.id)
      )
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
      } catch {
        // 載入失敗不中斷
      }
    },
    onL1Change() {
      this.selectedL2 = ''
      this.selectedTagId = ''
    },
    onL2Change() {
      this.selectedTagId = ''
    },
    addTag() {
      if (!this.selectedTagId) return
      const topic = this.currentTopics.find(t => t.id === this.selectedTagId)
      if (!topic) return
      this.$emit('update:modelValue', [...this.modelValue, topic])
      this.cancelCascade()
    },
    removeTag(tagId: string) {
      this.$emit('update:modelValue', this.modelValue.filter(t => t.id !== tagId))
    },
    cancelCascade() {
      this.showCascade = false
      this.selectedL1 = ''
      this.selectedL2 = ''
      this.selectedTagId = ''
    },
  },
})
</script>

<style scoped>
.tag-selector { display: flex; flex-direction: column; gap: 8px; }

.tag-chips { display: flex; flex-wrap: wrap; gap: 6px; }

.tag-chip {
  display: inline-flex; align-items: center; gap: 4px;
  background: var(--surface-2); border: 1px solid var(--border);
  border-radius: 4px; padding: 3px 8px; font-size: 12px; color: var(--text-primary);
}

.tag-chip-label { line-height: 1.4; }

.tag-chip-remove {
  background: none; border: none; cursor: pointer; padding: 0 2px;
  color: var(--text-secondary); font-size: 11px; line-height: 1;
}
.tag-chip-remove:hover { color: var(--text-primary); }

.tag-empty-hint { font-size: 13px; color: var(--text-secondary); }

.tag-cascade { display: flex; flex-direction: column; gap: 8px; border: 1px solid var(--border); border-radius: 6px; padding: 12px; background: var(--surface-2); }

.cascade-row { display: flex; gap: 8px; flex-wrap: wrap; }

.cascade-select { flex: 1; min-width: 140px; }

.cascade-actions { display: flex; gap: 8px; }
</style>
