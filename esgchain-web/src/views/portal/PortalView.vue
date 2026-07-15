<template>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h1 class="page-title">供應商入口</h1>
        <p class="page-subtitle">歡迎，{{ authStore.user?.name }}</p>
      </div>
    </div>

    <!-- 快速入口 -->
    <div style="display:flex;gap:12px;margin-bottom:20px;">
      <router-link to="/supplier/compliance" class="quick-card">
        <div class="quick-icon">📄</div>
        <div class="quick-label">合規文件</div>
      </router-link>
      <router-link to="/supplier/portal/procurement" class="quick-card">
        <div class="quick-icon">📦</div>
        <div class="quick-label">採購需求</div>
      </router-link>
      <router-link to="/supplier/portal/pcf" class="quick-card">
        <div class="quick-icon">🌿</div>
        <div class="quick-label">碳排申報</div>
      </router-link>
      <router-link to="/supplier/portal/disclosures" class="quick-card">
        <div class="quick-icon">📊</div>
        <div class="quick-label">永續 KPI 填報</div>
      </router-link>
    </div>

    <!-- 待填問卷（SAQ）-->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-title" style="margin-bottom:16px;">待填問卷（SAQ）</div>
      <div v-if="isLoadingSaq" class="loading-mask">載入中...</div>
      <div v-else-if="questionnaires.length === 0" class="empty-state"><p>目前沒有待填問卷</p></div>
      <table v-else class="data-table">
        <thead>
          <tr><th>問卷</th><th>狀態</th><th>截止日</th><th>操作</th></tr>
        </thead>
        <tbody>
          <tr
            v-for="q in questionnaires"
            :key="q.id"
            :class="{ 'row-returned': q.status === 'review_returned' }"
          >
            <td>
              {{ q.template?.name ?? '問卷' }} #{{ q.round }}
              <span v-if="q.status === 'review_returned'" class="returned-hint">需修改後重新提交</span>
            </td>
            <td><span class="badge" :class="statusBadgeClass(q.status)">{{ statusLabel(q.status) }}</span></td>
            <td style="font-size:12px;font-family:var(--font-mono);white-space:nowrap;">{{ q.deadline ?? '—' }}</td>
            <td>
              <button
                v-if="q.status === 'review_returned'"
                class="btn btn-warning btn-sm"
                @click="router.push(`/supplier/survey/${q.id}`)"
              >查看退回說明</button>
              <button
                v-else-if="q.is_editable"
                class="btn btn-primary btn-sm"
                @click="router.push(`/supplier/survey/${q.id}`)"
              >填寫</button>
              <button
                v-else
                class="btn btn-secondary btn-sm"
                @click="router.push(`/supplier/survey/${q.id}`)"
              >查看</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 待填碳排（PCF）-->
    <div class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
        <div class="card-title">
          待填碳排（PCF）
          <span v-if="pendingPcfLines.length > 0" class="badge badge-yellow" style="margin-left:8px;">
            {{ pendingPcfLines.length }} 筆待填
          </span>
        </div>
        <router-link
          to="/supplier/portal/material-emissions"
          class="btn btn-secondary btn-sm"
        >前往填報</router-link>
      </div>
      <div v-if="isLoadingPcf" class="loading-mask">載入中...</div>
      <div v-else-if="pendingPcfLines.length === 0" class="empty-state"><p>目前沒有待填碳排任務</p></div>
      <table v-else class="data-table">
        <thead>
          <tr><th>物料名稱</th><th>HS Code</th><th>截止日</th><th>狀態</th></tr>
        </thead>
        <tbody>
          <tr v-for="line in pendingPcfLines" :key="line.id">
            <td>{{ line.material_name }}</td>
            <td style="font-family:var(--font-mono);font-size:12px;">{{ line.hs_code ?? '—' }}</td>
            <td style="font-family:var(--font-mono);font-size:12px;white-space:nowrap;">{{ line.due_date ?? '—' }}</td>
            <td>
              <span class="badge badge-yellow">待填</span>
            </td>
          </tr>
        </tbody>
      </table>
    <!-- 活動資料任務區 -->
    <div class="task-section card" style="margin-top:20px;">
      <div class="task-header">
        <div class="card-title">
          活動資料申報
          <span v-if="pendingFacilities.length > 0" class="badge badge-yellow" style="margin-left:8px;">
            {{ pendingFacilities.length }} 設施未申報
          </span>
        </div>
        <router-link to="/supplier/portal/activity-reports" class="btn btn-secondary btn-sm">前往填報</router-link>
      </div>
      <div v-if="isLoadingFacilities" class="loading-mask">載入中...</div>
      <div v-else-if="!facilities.length" class="empty-state"><p>尚無設施需填報活動資料</p></div>
      <table v-else class="data-table">
        <thead>
          <tr><th>設施名稱</th><th>最新申報期間</th><th>申報狀態</th></tr>
        </thead>
        <tbody>
          <tr v-for="f in facilities" :key="f.id">
            <td>{{ f.name }}</td>
            <td class="font-mono">{{ f.latest_report_period || '—' }}</td>
            <td>
              <span :class="facilityStatusClass(f.latest_report_status)" class="badge">
                {{ facilityStatusLabel(f.latest_report_status) }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { questionnaireApi, type Questionnaire } from '@/api/modules/questionnaire'
import { portalPcfRequestApi, type PcfRequestLineSummary } from '@/api/modules/materialEmissions'
import { portalFacilityApi, type SupplierFacility } from '@/api/modules/suppliers'
import { SAQ_STATUS_BADGE } from '@/utils/saqStatus'

const STATUS_LABELS: Record<string, string> = {
  not_started: '未開始', in_progress: '填寫中', submitted: '已提交',
  under_review: '審核中', review_returned: '已退回', completed: '已通過', reviewed: '已複核',
}

export default defineComponent({
  name: 'PortalView',
  setup() { return { authStore: useAuthStore(), router: useRouter() } },

  data() {
    return {
      isLoadingSaq: false,
      isLoadingPcf: false,
      isLoadingFacilities: false,
      questionnaires: [] as Questionnaire[],
      pendingPcfLines: [] as PcfRequestLineSummary[],
      facilities: [] as SupplierFacility[],
    }
  },

  mounted() {
    const supplier = (this.authStore as any).supplier
    if (supplier && supplier.profile_completed === false) {
      this.router.push('/supplier/profile')
      return
    }
    this.loadSaqData()
    this.loadPcfData()
    this.loadFacilitiesData()
  },

  methods: {
    async loadSaqData() {
      this.isLoadingSaq = true
      try {
        const { data } = await questionnaireApi.list({ per_page: 50 })
        this.questionnaires = data.data
      } finally { this.isLoadingSaq = false }
    },

    async loadPcfData() {
      this.isLoadingPcf = true
      try {
        const { data } = await portalPcfRequestApi.pendingLines()
        this.pendingPcfLines = data.data
      } catch {
        this.pendingPcfLines = []
      } finally { this.isLoadingPcf = false }
    },

    statusLabel: (s: string) => STATUS_LABELS[s] ?? s,
    statusBadgeClass: (s: string) => SAQ_STATUS_BADGE[s] ?? 'badge-gray',

    async loadFacilitiesData() {
      this.isLoadingFacilities = true
      try {
        const res = await portalFacilityApi.list()
        this.facilities = res.data.data
      } catch {
        // 供應商可能沒有設施，靜默失敗
      } finally {
        this.isLoadingFacilities = false
      }
    },

    facilityStatusLabel(status: string | null): string {
      return ({ draft: '草稿', submitted: '已提交', verified: '已核實' }[status ?? ''] ?? '未申報')
    },
    facilityStatusClass(status: string | null): string {
      return ({ draft: 'badge-gray', submitted: 'badge-blue', verified: 'badge-green' }[status ?? ''] ?? 'badge-gray')
    },

  },

  computed: {
    pendingFacilities(): SupplierFacility[] {
      return (this as any).facilities.filter((f: SupplierFacility) => !f.latest_report_status || f.latest_report_status === 'draft')
    },
  },
})
</script>

<style scoped>
.quick-card {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 6px; padding: 16px 24px; background: var(--surface);
  border: 1px solid var(--border); border-radius: 8px; text-decoration: none;
  color: var(--text-primary); min-width: 100px; transition: all 0.15s;
}
.quick-card:hover { border-color: var(--accent); background: var(--surface-2); }
.quick-icon { font-size: 22px; }
.quick-label { font-size: 13px; font-weight: 500; }

.row-returned td { background: #fff7ed !important; }
.returned-hint { font-size: 11px; color: #c2410c; background: #ffedd5; border-radius: 4px; padding: 1px 6px; margin-left: 6px; font-weight: 500; }
.btn-warning { background: #f97316; color: #fff; border: none; }
.btn-warning:hover { background: #ea6c0a; }
</style>
