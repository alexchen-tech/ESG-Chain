<template>
  <div class="page-container">
    <div class="breadcrumb">
      <button class="breadcrumb-link" @click="$router.push('/settings')">系統設定</button>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current">市場與合規規則</span>
    </div>

    <div class="page-header">
      <div>
        <h1 class="page-title">市場與合規規則</h1>
        <p class="page-subtitle">目標市場定義、出口文件合規要求、各國風險評等</p>
      </div>
    </div>

    <div class="detail-tabs">
      <button
        v-for="tab in TABS"
        :key="tab.key"
        class="detail-tab"
        :class="{ active: activeTab === tab.key }"
        @click="activeTab = tab.key"
      >{{ tab.label }}</button>
    </div>

    <div class="tab-panel-wrap">
      <!-- ══ 目標市場 ══ -->
      <div v-show="activeTab === 'market'" class="section-card">
        <div class="section-header">
          <div>
            <h2 class="section-title">目標市場</h2>
            <p class="section-desc">定義品牌/客戶專屬的目標市場代碼，供市場合規規則與出口審查使用</p>
          </div>
          <button class="btn btn-primary" @click="openCreateMarketModal">+ 新增市場定義</button>
        </div>

        <div class="table-container">
          <div v-if="marketsLoading" class="loading-mask">載入中...</div>
          <div v-else-if="markets.length === 0" class="empty-state"><p>尚無目標市場定義</p></div>
          <table v-else class="data-table">
            <thead>
              <tr>
                <th style="width:180px;">代碼</th>
                <th style="width:200px;">標籤</th>
                <th>說明</th>
                <th style="width:80px;">系統</th>
                <th style="width:120px;">操作</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="m in markets" :key="m.id">
                <td class="font-mono" style="font-weight:700;color:var(--accent);font-size:13px;">{{ m.code }}</td>
                <td style="font-weight:600;">{{ m.label }}</td>
                <td style="font-size:13px;color:var(--text-secondary);">{{ m.description || '—' }}</td>
                <td>
                  <span v-if="m.is_system" class="badge badge-green" style="font-size:11px;">系統</span>
                  <span v-else style="color:var(--text-secondary);font-size:13px;">—</span>
                </td>
                <td>
                  <div style="display:flex;gap:6px;">
                    <button class="btn btn-secondary btn-sm" @click="openEditMarketModal(m)">編輯</button>
                    <button v-if="!m.is_system" class="btn btn-danger btn-sm" @click="confirmDeleteMarket(m)">刪除</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- 新增/編輯市場 Modal -->
        <div v-if="showMarketModal" class="modal-overlay" @click.self="closeMarketModal">
          <div class="modal" style="min-width:400px;">
            <div class="modal-header">
              <span class="modal-title">{{ marketForm.id ? '編輯市場定義' : '新增市場定義' }}</span>
              <button class="modal-close" @click="closeMarketModal">×</button>
            </div>
            <div v-if="!marketForm.id" class="form-group">
              <label class="form-label">代碼 * <span style="font-size:11px;color:var(--text-secondary);">大寫底線格式，如 US_MARKET</span></label>
              <input v-model="marketForm.code" class="form-input font-mono" placeholder="US_MARKET"
                @input="marketForm.code = (marketForm.code as string).toUpperCase().replace(/[^A-Z_]/g, '')" />
            </div>
            <div v-else class="form-group">
              <label class="form-label">代碼</label>
              <div class="form-input font-mono" style="background:var(--surface-2);color:var(--text-secondary);">{{ marketForm.code }}</div>
            </div>
            <div class="form-group"><label class="form-label">標籤 *</label><input v-model="marketForm.label" class="form-input" placeholder="美國市場" /></div>
            <div class="form-group"><label class="form-label">說明</label><textarea v-model="marketForm.description" class="form-textarea" placeholder="選填說明"></textarea></div>
            <div class="modal-footer">
              <button class="btn btn-secondary" @click="closeMarketModal">取消</button>
              <button class="btn btn-primary" :disabled="isSubmitting" @click="saveMarket">{{ isSubmitting ? '儲存中...' : (marketForm.id ? '儲存' : '建立') }}</button>
            </div>
          </div>
        </div>

        <!-- 刪除市場 Modal -->
        <div v-if="deleteTargetMarket" class="modal-overlay" @click.self="deleteTargetMarket=null">
          <div class="modal" style="max-width:380px;text-align:center;">
            <div class="modal-header"><span class="modal-title">確認刪除</span></div>
            <p style="margin:16px 0;color:var(--text-secondary);">確定要刪除「{{ deleteTargetMarket.label }}（{{ deleteTargetMarket.code }}）」？</p>
            <div class="modal-footer">
              <button class="btn btn-secondary" @click="deleteTargetMarket=null">取消</button>
              <button class="btn btn-danger" :disabled="isSubmitting" @click="deleteMarket">確認刪除</button>
            </div>
          </div>
        </div>
      </div>

      <!-- ══ 市場合規規則 ══ -->
      <div v-show="activeTab === 'rules'">
        <MarketComplianceRulesView />
      </div>

      <!-- ══ 國家風險評等 ══ -->
      <div v-show="activeTab === 'country-risk'">
        <CountryRiskView />
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { marketDefinitionApi, type MarketDefinition } from '@/api/modules/settings'
import MarketComplianceRulesView from './MarketComplianceRulesView.vue'
import CountryRiskView from './CountryRiskView.vue'

const TABS = [
  { key: 'market', label: '目標市場' },
  { key: 'rules', label: '市場合規規則' },
  { key: 'country-risk', label: '國家風險評等' },
]

export default defineComponent({
  name: 'MarketRulesHubView',

  components: { MarketComplianceRulesView, CountryRiskView },

  data() {
    return {
      TABS,
      activeTab: 'market',
      isSubmitting: false,

      // 目標市場
      markets: [] as MarketDefinition[],
      marketsLoading: false,
      showMarketModal: false,
      marketForm: { id: '', code: '', label: '', description: '' },
      deleteTargetMarket: null as MarketDefinition | null,
    }
  },

  mounted() {
    const tab = this.$route.query.tab as string | undefined
    if (tab && TABS.some(t => t.key === tab)) {
      this.activeTab = tab
    }
    this.loadMarkets()
  },

  methods: {
    // ── 目標市場 ──
    async loadMarkets() {
      this.marketsLoading = true
      try { const { data } = await marketDefinitionApi.list(); this.markets = data.data } finally { this.marketsLoading = false }
    },
    openCreateMarketModal() { this.marketForm = { id: '', code: '', label: '', description: '' }; this.showMarketModal = true },
    openEditMarketModal(m: MarketDefinition) { this.marketForm = { id: m.id, code: m.code, label: m.label, description: m.description ?? '' }; this.showMarketModal = true },
    closeMarketModal() { this.showMarketModal = false },
    async saveMarket() {
      if (!this.marketForm.label || (!this.marketForm.id && !this.marketForm.code)) return
      this.isSubmitting = true
      try {
        this.marketForm.id
          ? await marketDefinitionApi.update(this.marketForm.id, { label: this.marketForm.label, description: this.marketForm.description || undefined })
          : await marketDefinitionApi.create({ code: this.marketForm.code, label: this.marketForm.label, description: this.marketForm.description || undefined })
        this.showMarketModal = false; await this.loadMarkets()
      } catch (e: any) { alert(e?.response?.data?.message ?? '儲存失敗') } finally { this.isSubmitting = false }
    },
    confirmDeleteMarket(m: MarketDefinition) { this.deleteTargetMarket = m },
    async deleteMarket() {
      if (!this.deleteTargetMarket) return
      this.isSubmitting = true
      try { await marketDefinitionApi.destroy(this.deleteTargetMarket.id); this.deleteTargetMarket = null; await this.loadMarkets() }
      catch (e: any) { alert(e?.response?.data?.message ?? '刪除失敗') } finally { this.isSubmitting = false }
    },
  },
})
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
</style>
