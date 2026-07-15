<template>
  <div class="page-container">
    <div class="breadcrumb">
      <button class="breadcrumb-link" @click="$router.push('/settings')">系統設定</button>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current">分類與計分管理</span>
    </div>

    <div class="page-header">
      <div>
        <h1 class="page-title">分類與計分管理</h1>
        <p class="page-subtitle">標籤庫、框架預設加權、SASB 必調題目設定</p>
      </div>
    </div>

    <div class="hub-tabs">
      <button
        v-for="tab in TABS"
        :key="tab.key"
        class="hub-tab-btn"
        :class="{ active: activeTab === tab.key }"
        @click="activeTab = tab.key"
      >
        {{ tab.label }}
      </button>
    </div>

    <div v-show="activeTab === 'dim-weights'">
      <DimWeightDefaultsTab />
    </div>
    <div v-show="activeTab === 'tag-library'">
      <TagLibraryView embedded />
    </div>
    <div v-show="activeTab === 'framework-weights'">
      <FrameworkDefaultWeightPanel />
    </div>
    <div v-show="activeTab === 'sasb-topics'">
      <SasbRequiredTopicPanel />
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import TagLibraryView from './TagLibraryView.vue'
import FrameworkDefaultWeightPanel from './FrameworkDefaultWeightPanel.vue'
import SasbRequiredTopicPanel from './SasbRequiredTopicPanel.vue'
import DimWeightDefaultsTab from './DimWeightDefaultsTab.vue'

const TABS = [
  { key: 'dim-weights', label: '維度預設加權' },
  { key: 'framework-weights', label: '設定框架加權' },
  { key: 'tag-library', label: '問卷題目標籤庫' },
  { key: 'sasb-topics', label: 'SASB 必調題目' },
]

export default defineComponent({
  name: 'ClassificationScoringHubView',

  components: {
    TagLibraryView,
    FrameworkDefaultWeightPanel,
    SasbRequiredTopicPanel,
    DimWeightDefaultsTab,
  },

  data() {
    return {
      TABS,
      activeTab: 'dim-weights',
    }
  },
})
</script>

<style scoped>
.hub-tabs {
  display: flex;
  gap: 4px;
  border-bottom: 1px solid var(--border);
  margin-bottom: 24px;
}

.hub-tab-btn {
  padding: 10px 18px;
  border: none;
  background: none;
  cursor: pointer;
  font-size: 14px;
  color: var(--text-secondary);
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  border-radius: 4px 4px 0 0;
  transition: all 0.15s;
}

.hub-tab-btn:hover { color: var(--text-primary); background: var(--surface-hover, #f5f4f2); }
.hub-tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); font-weight: 600; }
</style>
