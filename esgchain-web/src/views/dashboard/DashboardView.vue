<template>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h1 class="page-title">儀表板</h1>
        <p class="page-subtitle">歡迎回來，{{ authStore.user?.name }}（{{ roleLabel }}）</p>
      </div>
    </div>

    <!-- 今日行動卡片 -->
    <DashboardActionCards :cards="summary?.cards ?? []" style="margin-bottom:16px" />

    <!-- 供應鏈地圖（sustain / comply / admin 才顯示） -->
    <DashboardSupplyChainMap
      v-if="showMap"
      :rows="heatmapRows"
      :expiring-docs="expiringDocs"
      style="margin-bottom:16px"
    />

    <!-- 角色特定下排 Widget -->
    <div class="widget-grid">
      <!-- 最近動態（所有角色） -->
      <DashboardRecentActivity :activities="activities" />

      <!-- 到期預警（所有角色） -->
      <DashboardExpiringDocs :docs="expiringDocs" />

      <!-- sustain: ESG 分數 -->
      <DashboardEsgScores v-if="role === 'sustain'" :scores="esgScores" />

      <!-- comply: 商品合規風險 -->
      <DashboardComplianceRisk v-if="role === 'comply' || role === 'admin'" :risk="complianceRisk" />

      <!-- sustain / comply / admin: 供應商碳盤查覆蓋度（依群組） -->
      <DashboardGhgCoverageByGroup
        v-if="['sustain', 'comply', 'admin'].includes(role)"
        :data="ghgCoverageByGroup"
        :period-year="ghgCoveragePeriodYear"
        @update:period-year="onGhgCoverageYearChange"
      />

      <!-- admin/buyer/sustain/comply: 供應商分析（層級＋活躍狀態彙總） -->
      <DashboardSupplierAnalysis v-if="['admin', 'buyer', 'sustain', 'comply'].includes(role)" :suppliers="suppliers" />
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'
import { suppliersApi, type Supplier, supplierGhgCoverageApi, type SupplierGhgCoverageByGroup } from '@/api/modules/suppliers'
import { dashboardApi, type DashboardSummary, type RecentActivity, type ExpiringDoc, type ComplianceRisk } from '@/api/modules/dashboard'
import { riskApi, type SixDimHeatmapRow } from '@/api/modules/risk'
import type { EsgScores } from '@/components/dashboard/DashboardEsgScores.vue'
import DashboardActionCards from '@/components/dashboard/DashboardActionCards.vue'
import DashboardRecentActivity from '@/components/dashboard/DashboardRecentActivity.vue'
import DashboardExpiringDocs from '@/components/dashboard/DashboardExpiringDocs.vue'
import DashboardComplianceRisk from '@/components/dashboard/DashboardComplianceRisk.vue'
import DashboardEsgScores from '@/components/dashboard/DashboardEsgScores.vue'
import DashboardSupplierAnalysis from '@/components/dashboard/DashboardSupplierAnalysis.vue'
import DashboardSupplyChainMap from '@/components/dashboard/DashboardSupplyChainMap.vue'
import DashboardGhgCoverageByGroup from '@/components/dashboard/DashboardGhgCoverageByGroup.vue'

const ROLE_LABELS: Record<string, string> = {
  admin: '系統管理員', buyer: '採購商', sustain: '永續長',
  comply: '法遵', analyst: '分析師',
}

export default defineComponent({
  name: 'DashboardView',
  components: {
    DashboardActionCards,
    DashboardRecentActivity,
    DashboardExpiringDocs,
    DashboardComplianceRisk,
    DashboardEsgScores,
    DashboardSupplierAnalysis,
    DashboardSupplyChainMap,
    DashboardGhgCoverageByGroup,
  },

  setup() {
    return { authStore: useAuthStore(), router: useRouter() }
  },

  data() {
    return {
      isLoading: false,
      suppliers: [] as Supplier[],
      totalSuppliers: 0,
      summary: null as DashboardSummary | null,
      activities: [] as RecentActivity[],
      expiringDocs: [] as ExpiringDoc[],
      complianceRisk: null as ComplianceRisk | null,
      esgScores: null as EsgScores | null,
      heatmapRows: [] as SixDimHeatmapRow[],
      ghgCoverageByGroup: null as SupplierGhgCoverageByGroup | null,
      ghgCoveragePeriodYear: null as number | null,
    }
  },

  computed: {
    role(): string {
      return this.authStore.user?.role ?? 'buyer'
    },
    roleLabel(): string {
      return ROLE_LABELS[this.role] ?? this.role
    },
    showMap(): boolean {
      return ['sustain', 'comply', 'admin'].includes(this.role)
    },
  },

  mounted() {
    this.loadData()
  },

  methods: {
    async loadData() {
      this.isLoading = true
      try {
        const baseRequests = [
          dashboardApi.summary(),
          dashboardApi.recentActivity(),
          dashboardApi.expiringDocs(),
        ] as const

        const [summaryRes, activityRes, expiringRes] = await Promise.allSettled(baseRequests)

        if (summaryRes.status === 'fulfilled') this.summary = summaryRes.value.data.data
        if (activityRes.status === 'fulfilled') this.activities = activityRes.value.data.data
        if (expiringRes.status === 'fulfilled') this.expiringDocs = expiringRes.value.data.data

        // 供應鏈地圖（sustain/comply/admin）
        if (this.showMap) {
          const mapRes = await riskApi.sixDimHeatmap().catch(() => null)
          if (mapRes) this.heatmapRows = mapRes.data.data
        }

        // 角色特定請求
        if (this.role === 'sustain') {
          const esgRes = await dashboardApi.esgScores().catch(() => null)
          if (esgRes) this.esgScores = esgRes.data.data as any
        }

        if (this.role === 'comply' || this.role === 'admin') {
          const riskRes = await dashboardApi.complianceRisk().catch(() => null)
          if (riskRes) this.complianceRisk = riskRes.data.data
        }

        // sustain/comply/admin: 供應商碳盤查覆蓋度（依群組），年度預設抓最新一年
        if (['sustain', 'comply', 'admin'].includes(this.role)) {
          const trendRes = await supplierGhgCoverageApi.trend().catch(() => null)
          if (trendRes && trendRes.data.data.length > 0) {
            this.ghgCoveragePeriodYear = trendRes.data.data[trendRes.data.data.length - 1].period_year
          }
          await this.loadGhgCoverageByGroup()
        }

        if (['admin', 'buyer', 'sustain', 'comply'].includes(this.role)) {
          const suppliersRes = await suppliersApi.list({ per_page: 200 }).catch(() => null)
          if (suppliersRes) {
            this.suppliers = suppliersRes.data.data
            this.totalSuppliers = suppliersRes.data.pagination.total
          }
        }

      } catch (e) {
        console.error(e)
      } finally {
        this.isLoading = false
      }
    },

    async loadGhgCoverageByGroup() {
      const res = await supplierGhgCoverageApi.byGroup(this.ghgCoveragePeriodYear ?? undefined).catch(() => null)
      if (res) this.ghgCoverageByGroup = res.data.data
    },
    onGhgCoverageYearChange(year: number | null) {
      this.ghgCoveragePeriodYear = year
      this.loadGhgCoverageByGroup()
    },
  },
})
</script>

<style scoped>
.widget-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
</style>
