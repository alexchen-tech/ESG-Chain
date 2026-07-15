<template>
  <div class="action-cards">
    <div
      v-for="card in cards"
      :key="card.key"
      class="action-card"
      :class="{ 'action-card--urgent': card.urgent }"
      @click="$router.push(card.link)"
    >
      <div class="action-card__label">{{ card.label }}</div>
      <div class="action-card__value font-mono" :class="{ 'action-card__value--urgent': card.urgent }">
        {{ card.value }}
      </div>
      <div class="action-card__meta">{{ card.urgent ? '需要處理 →' : '查看詳情 →' }}</div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import type { PropType } from 'vue'
import type { ActionCard } from '@/api/modules/dashboard'

export default defineComponent({
  name: 'DashboardActionCards',
  props: {
    cards: {
      type: Array as PropType<ActionCard[]>,
      default: () => [],
    },
  },
})
</script>

<style scoped>
.action-cards {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}
.action-card {
  flex: 1;
  min-width: 160px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 20px;
  cursor: pointer;
  transition: box-shadow 0.2s, border-color 0.2s;
}
.action-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.08); }
.action-card--urgent { border-color: #fca5a5; background: #fff5f5; }
.action-card__label { font-size: 13px; color: var(--text-secondary); margin-bottom: 8px; }
.action-card__value { font-size: 32px; font-weight: 700; color: var(--text-primary); line-height: 1; }
.action-card__value--urgent { color: #dc2626; }
.action-card__meta { font-size: 12px; color: var(--text-secondary); margin-top: 8px; }
</style>
