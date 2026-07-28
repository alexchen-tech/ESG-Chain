<template>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h1 class="page-title">活動資料申報</h1>
        <p class="page-subtitle">依設施填報本期能源與用水活動資料，供計算範疇一/二溫室氣體排放</p>
      </div>
    </div>

    <div v-if="isLoading" class="loading-mask">載入中...</div>
    <div v-else-if="!facilities.length" class="empty-state"><p>尚無設施需填報活動資料</p></div>

    <div v-else class="card" v-for="f in facilities" :key="f.id" style="margin-bottom:16px;">
      <div style="display:flex;align-items:center;justify-content:space-between;">
        <div>
          <div class="card-title">{{ f.name }}</div>
          <div style="font-size:12px;color:var(--text-secondary);margin-top:2px;">
            最新申報期間：{{ f.latest_report_period || '—' }}
            <span :class="facilityStatusClass(f.latest_report_status)" class="badge" style="margin-left:8px;">
              {{ facilityStatusLabel(f.latest_report_status) }}
            </span>
          </div>
        </div>
        <button class="btn btn-secondary btn-sm" @click="toggleForm(f.id)">
          {{ openFacilityId === f.id ? '收起' : '填寫本期資料' }}
        </button>
      </div>

      <div v-if="openFacilityId === f.id" class="activity-form">
        <div class="form-grid">
          <div class="form-group">
            <label>申報期間</label>
            <select v-model="form.report_period" class="form-select">
              <option value="" disabled>請選擇</option>
              <option v-for="p in periodOptions" :key="p" :value="p">{{ p }}</option>
            </select>
          </div>
          <div class="form-group">
            <label>用電量（kWh）</label>
            <input v-model.number="form.electricity_kwh" type="number" min="0" step="0.01" class="form-input" />
          </div>
          <div class="form-group">
            <label>天然氣（GJ）</label>
            <input v-model.number="form.natural_gas_gj" type="number" min="0" step="0.01" class="form-input" />
          </div>
          <div class="form-group">
            <label>燃料油（公升）</label>
            <input v-model.number="form.fuel_oil_l" type="number" min="0" step="0.01" class="form-input" />
          </div>
          <div class="form-group">
            <label>外購熱能（GJ）</label>
            <input v-model.number="form.heat_gj" type="number" min="0" step="0.01" class="form-input" />
          </div>
          <div class="form-group">
            <label>用水量（立方公尺）</label>
            <input v-model.number="form.water_m3" type="number" min="0" step="0.01" class="form-input" />
          </div>
        </div>
        <div class="form-group">
          <label>備註</label>
          <textarea v-model="form.notes" class="form-textarea" rows="2" placeholder="選填，如資料來源、估算方式說明"></textarea>
        </div>
        <p v-if="errorMsg" class="error-msg">{{ errorMsg }}</p>
        <div style="display:flex;gap:8px;">
          <button class="btn btn-primary btn-sm" :disabled="isSubmitting" @click="submit(f.id)">
            {{ isSubmitting ? '送出中...' : '提交本期資料' }}
          </button>
          <button class="btn btn-secondary btn-sm" :disabled="isSubmitting" @click="toggleForm(f.id)">取消</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { portalFacilityApi, type SupplierFacility, type ActivityDataReport } from '@/api/modules/suppliers'

function emptyForm(): Partial<ActivityDataReport> {
  return {
    report_period: '',
    electricity_kwh: null,
    natural_gas_gj: null,
    fuel_oil_l: null,
    heat_gj: null,
    water_m3: null,
    notes: '',
  }
}

export default defineComponent({
  name: 'PortalActivityReportsView',
  data() {
    return {
      isLoading: false,
      isSubmitting: false,
      errorMsg: '',
      facilities: [] as SupplierFacility[],
      openFacilityId: '',
      form: emptyForm(),
    }
  },
  mounted() {
    this.loadFacilities()
  },
  computed: {
    periodOptions(): string[] {
      const year = new Date().getFullYear()
      const opts: string[] = []
      for (let y = year; y >= year - 2; y--) {
        for (const q of ['Q4', 'Q3', 'Q2', 'Q1']) opts.push(`${y}-${q}`)
      }
      return opts
    },
  },
  methods: {
    async loadFacilities() {
      this.isLoading = true
      try {
        const { data } = await portalFacilityApi.list()
        this.facilities = data.data
      } finally {
        this.isLoading = false
      }
    },
    toggleForm(facilityId: string) {
      if (this.openFacilityId === facilityId) {
        this.openFacilityId = ''
        return
      }
      this.openFacilityId = facilityId
      this.errorMsg = ''
      this.form = emptyForm()
    },
    async submit(facilityId: string) {
      this.errorMsg = ''
      if (!this.form.report_period) {
        this.errorMsg = '請填寫申報期間'
        return
      }
      this.isSubmitting = true
      try {
        const { data } = await portalFacilityApi.createReport(facilityId, this.form)
        await portalFacilityApi.submitReport(facilityId, data.data.id)
        this.openFacilityId = ''
        await this.loadFacilities()
      } catch (e: any) {
        this.errorMsg = e?.response?.data?.message ?? '提交失敗，請確認資料後重試'
      } finally {
        this.isSubmitting = false
      }
    },
    facilityStatusLabel(status: string | null): string {
      return ({ draft: '草稿', submitted: '已提交', verified: '已核實' }[status ?? ''] ?? '未申報')
    },
    facilityStatusClass(status: string | null): string {
      return ({ draft: 'badge-gray', submitted: 'badge-blue', verified: 'badge-green' }[status ?? ''] ?? 'badge-gray')
    },
  },
})
</script>

<style scoped>
.activity-form {
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px solid var(--border);
}
.form-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  margin-bottom: 12px;
}
.error-msg { color: #dc2626; font-size: 13px; margin-bottom: 8px; }
</style>
