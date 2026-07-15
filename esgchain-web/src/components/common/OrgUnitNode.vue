<template>
  <div class="ou-node">
    <div class="ou-row" :class="{ 'ou-inactive': !unit.is_active }">
      <!-- 縮排 + 折疊按鈕 -->
      <div class="ou-indent" :style="{ width: (unit.depth - 1) * 20 + 'px' }" />
      <button
        v-if="unit.children_list && unit.children_list.length"
        class="ou-toggle"
        @click="expanded = !expanded"
      >{{ expanded ? '▼' : '▶' }}</button>
      <span v-else class="ou-toggle-placeholder" />

      <!-- 節點資訊 -->
      <span class="ou-type-badge" :class="`type-${unit.type}`">{{ TYPE_LABELS[unit.type] }}</span>
      <span class="ou-name">{{ unit.name }}</span>
      <span class="ou-code font-mono">{{ unit.code }}</span>
      <span class="ou-country">{{ unit.country_code }}</span>
      <span v-if="!unit.is_active" class="ou-inactive-badge">停用</span>

      <!-- 操作 -->
      <div class="ou-actions">
        <button class="btn btn-secondary btn-sm" @click="$emit('edit', unit)">編輯</button>
        <button
          class="btn btn-secondary btn-sm"
          @click="$emit('add-child', unit)"
          :disabled="unit.depth >= 4"
          :title="unit.depth >= 4 ? '已達最大層級' : '新增子單位'"
        >+子單位</button>
        <button
          class="btn btn-danger btn-sm"
          :disabled="hasChildren"
          :title="hasChildren ? '請先移除子單位' : '刪除'"
          @click="!hasChildren && $emit('delete', unit)"
        >刪除</button>
      </div>
    </div>

    <!-- 遞迴子節點 -->
    <template v-if="expanded && unit.children_list && unit.children_list.length">
      <OrgUnitNode
        v-for="child in unit.children_list"
        :key="child.id"
        :unit="child"
        @edit="$emit('edit', $event)"
        @delete="$emit('delete', $event)"
        @add-child="$emit('add-child', $event)"
      />
    </template>
  </div>
</template>

<script lang="ts">
import { defineComponent, computed, ref, type PropType } from 'vue'
import type { OrgUnit } from '@/api/modules/settings'

const TYPE_LABELS: Record<string, string> = {
  headquarters:  '母公司',
  subsidiary:    '子公司',
  business_unit: '事業部',
  department:    '部門',
  branch:        '分支',
}

export default defineComponent({
  name: 'OrgUnitNode',

  props: {
    unit: { type: Object as PropType<OrgUnit>, required: true },
  },

  emits: ['edit', 'delete', 'add-child'],

  setup(props) {
    const expanded = ref(true)
    const hasChildren = computed(() => !!(props.unit.children_list?.length))

    return { expanded, hasChildren, TYPE_LABELS }
  },
})
</script>

<style scoped>
.ou-node { display: flex; flex-direction: column; }

.ou-row {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-bottom: 1px solid var(--border);
  transition: background 0.1s;
}
.ou-row:hover { background: var(--surface-2); }
.ou-inactive { opacity: 0.5; }

.ou-indent { flex-shrink: 0; }

.ou-toggle {
  width: 20px; height: 20px;
  background: none; border: none; cursor: pointer;
  color: var(--text-secondary); font-size: 10px;
  flex-shrink: 0; display: flex; align-items: center; justify-content: center;
}
.ou-toggle-placeholder { width: 20px; flex-shrink: 0; }

.ou-type-badge {
  font-size: 11px; padding: 2px 8px; border-radius: 99px;
  font-weight: 600; white-space: nowrap; flex-shrink: 0;
}
.type-headquarters  { background: #dbeafe; color: #1e40af; }
.type-subsidiary    { background: #ede9fe; color: #5b21b6; }
.type-business_unit { background: #dcfce7; color: #166534; }
.type-department    { background: #fef9c3; color: #854d0e; }
.type-branch        { background: #f1f5f9; color: #475569; }

.ou-name  { font-size: 14px; font-weight: 500; flex: 1; }
.ou-code  { font-size: 12px; color: var(--text-secondary); min-width: 60px; }
.ou-country { font-size: 12px; color: var(--text-secondary); min-width: 30px; }
.ou-inactive-badge { font-size: 11px; color: #ef4444; }

.ou-actions { display: flex; gap: 6px; margin-left: auto; flex-shrink: 0; }
</style>
