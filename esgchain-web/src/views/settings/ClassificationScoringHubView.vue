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
    <div v-show="activeTab === 'sasb-industries'" class="section-card">
      <div class="section-header">
        <div>
          <h2 class="section-title">SASB 產業分類</h2>
          <p class="section-desc">SASB sector / industry 對照表，供 SAQ 問卷產業推薦與必調題目設定使用</p>
        </div>
      </div>
      <div class="filter-bar" style="margin-bottom:16px;">
        <input v-model="sasbSearch" class="filter-input" placeholder="搜尋 sector 或 industry..." style="width:280px;" />
        <span style="font-size:13px;color:var(--text-secondary);">共 {{ filteredSasbIndustries.length }} 個產業</span>
      </div>
      <div v-if="sasbLoading" class="loading-mask">載入中...</div>
      <div v-else>
        <div v-for="(industries, sector) in groupedSasb" :key="sector" class="sector-block">
          <div class="sector-header" @click="toggleSector(sector as string)">
            <span class="sector-chevron">{{ expandedSectors[sector] ? '▼' : '▶' }}</span>
            <span class="sector-name">{{ sector }}</span>
            <span class="sector-count badge badge-gray">{{ industries.length }}</span>
          </div>
          <div v-if="expandedSectors[sector]" class="sector-industries">
            <div v-for="ind in industries" :key="ind.id" class="industry-row">
              <span class="industry-code font-mono">{{ ind.code }}</span>
              <span class="industry-name">{{ ind.industry }}</span>
            </div>
          </div>
        </div>
        <div v-if="Object.keys(groupedSasb).length === 0" class="empty-state"><p>無符合條件的產業分類</p></div>
      </div>
    </div>
    <div v-show="activeTab === 'carbon-price'">
      <CarbonPriceSettingsView />
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import TagLibraryView from './TagLibraryView.vue'
import FrameworkDefaultWeightPanel from './FrameworkDefaultWeightPanel.vue'
import SasbRequiredTopicPanel from './SasbRequiredTopicPanel.vue'
import DimWeightDefaultsTab from './DimWeightDefaultsTab.vue'
import CarbonPriceSettingsView from './CarbonPriceSettingsView.vue'
import { settingsApi, type SasbIndustry } from '@/api/modules/settings'

const TABS = [
  { key: 'dim-weights', label: '維度預設加權' },
  { key: 'framework-weights', label: '設定框架加權' },
  { key: 'tag-library', label: '問卷題目標籤庫' },
  { key: 'sasb-topics', label: 'SASB 必調題目' },
  { key: 'sasb-industries', label: 'SASB 產業分類' },
  { key: 'carbon-price', label: '碳價設定' },
]

export default defineComponent({
  name: 'ClassificationScoringHubView',

  components: {
    TagLibraryView,
    FrameworkDefaultWeightPanel,
    SasbRequiredTopicPanel,
    DimWeightDefaultsTab,
    CarbonPriceSettingsView,
  },

  data() {
    return {
      TABS,
      activeTab: 'dim-weights',

      // SASB 產業分類
      sasbIndustries: [] as SasbIndustry[],
      sasbLoading: false,
      sasbSearch: '',
      expandedSectors: {} as Record<string, boolean>,
    }
  },

  computed: {
    filteredSasbIndustries(): SasbIndustry[] {
      const q = this.sasbSearch.toLowerCase()
      if (!q) return this.sasbIndustries
      return this.sasbIndustries.filter(i => i.sector.toLowerCase().includes(q) || i.industry.toLowerCase().includes(q) || i.code.toLowerCase().includes(q))
    },
    groupedSasb(): Record<string, SasbIndustry[]> {
      return this.filteredSasbIndustries.reduce((acc, ind) => {
        if (!acc[ind.sector]) acc[ind.sector] = []
        acc[ind.sector].push(ind)
        return acc
      }, {} as Record<string, SasbIndustry[]>)
    },
  },

  mounted() {
    const tab = this.$route.query.tab as string | undefined
    if (tab && TABS.some(t => t.key === tab)) {
      this.activeTab = tab
    }
    this.loadSasb()
  },

  methods: {
    async loadSasb() {
      this.sasbLoading = true
      try { const { data } = await settingsApi.sasb.list(); this.sasbIndustries = data.data } finally { this.sasbLoading = false }
    },
    toggleSector(sector: string) { this.expandedSectors = { ...this.expandedSectors, [sector]: !this.expandedSectors[sector] } },
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

/* SASB 產業分類 */
.sector-block { border: 1px solid var(--border); border-radius: 8px; margin-bottom: 8px; overflow: hidden; }
.sector-header { display: flex; align-items: center; gap: 10px; padding: 12px 16px; cursor: pointer; background: var(--surface); user-select: none; }
.sector-header:hover { background: var(--surface-2); }
.sector-chevron { font-size: 11px; color: var(--text-secondary); width: 12px; }
.sector-name { font-size: 14px; font-weight: 600; flex: 1; }
.sector-count { font-size: 11px; }
.sector-industries { border-top: 1px solid var(--border); }
.industry-row { display: flex; align-items: center; gap: 12px; padding: 8px 16px; border-bottom: 1px solid var(--border); }
.industry-row:last-child { border-bottom: none; }
.industry-code { font-size: 12px; color: var(--accent); min-width: 60px; }
.industry-name { font-size: 13px; }
</style>
