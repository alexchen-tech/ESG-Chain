<template>
  <div style="min-height:100vh;background:var(--bg);display:flex;align-items:center;justify-content:center;padding:24px;">
    <div class="profile-card">
      <div class="profile-header">
        <h2 class="profile-title">主檔資料覆核</h2>
        <p class="profile-subtitle">請確認並補充以下資料，完成後即可使用供應商入口</p>
      </div>

      <!-- 系統預填資料（唯讀） -->
      <div class="profile-section">
        <div class="section-label">ERP 系統基本資料（唯讀）</div>
        <div class="readonly-grid">
          <div class="readonly-item">
            <span class="readonly-key">廠商名稱</span>
            <span class="readonly-val">{{ supplier?.name || '—' }}</span>
          </div>
          <div class="readonly-item">
            <span class="readonly-key">VAT Number</span>
            <span class="readonly-val font-mono">{{ supplier?.vat_number || '—' }}</span>
          </div>
          <div class="readonly-item">
            <span class="readonly-key">國家/地區</span>
            <span class="readonly-val">{{ supplier?.country_code || '—' }}</span>
          </div>
        </div>
      </div>

      <!-- 需補充欄位 -->
      <div class="profile-section">
        <div class="section-label">請補充以下資料</div>

        <div class="form-group">
          <label class="form-label">
            永續 / 安衛主管 Email *
            <span style="font-size:11px;color:var(--text-secondary);font-weight:400;">（請勿填入採購業務信箱）</span>
          </label>
          <input
            v-model="form.sustainability_email"
            class="form-input"
            type="email"
            placeholder="sustainability@yourcompany.com"
          />
          <div v-if="isSameAsOldEmail" class="warn-hint">
            ⚠ 此信箱與 ERP 業務信箱相同，請確認這是永續/安衛主管的專屬信箱
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">
            實體製造廠區地址 *
            <span style="font-size:11px;color:var(--text-secondary);font-weight:400;">（請填入實際生產廠區，非總公司地址）</span>
          </label>
          <textarea
            v-model="form.address"
            class="form-textarea"
            rows="3"
            placeholder="如：越南河內市東英縣 XX 工業區 XX 路 XX 號"
          />
        </div>
      </div>

      <div style="display:flex;justify-content:flex-end;margin-top:8px;">
        <button
          class="btn btn-primary"
          :disabled="isSubmitting || !form.sustainability_email || !form.address"
          @click="submit"
          style="padding:10px 32px;font-size:15px;"
        >
          {{ isSubmitting ? '送出中...' : '確認送出，進入供應商入口' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { supplierProfileApi } from '@/api/modules/suppliers'

export default defineComponent({
  name: 'SupplierProfileView',
  setup() {
    const authStore = useAuthStore()
    const router = useRouter()
    const supplier = computed(() => (authStore as any).supplier ?? null)
    return { authStore, router, supplier }
  },

  data() {
    return {
      isSubmitting: false,
      form: { sustainability_email: '', address: '' },
    }
  },

  computed: {
    isSameAsOldEmail(): boolean {
      const oldEmail = (this.supplier as any)?.contacts?.find((c: any) => c.is_primary)?.email
      return !!(oldEmail && this.form.sustainability_email && oldEmail === this.form.sustainability_email)
    },
  },

  async submit() {
    const supplierId = (this.authStore as any).supplier?.id
    if (!supplierId) return
    this.isSubmitting = true
    try {
      await supplierProfileApi.update(supplierId, this.form)
      // 更新 auth store 的 supplier
      if ((this.authStore as any).supplier) {
        (this.authStore as any).supplier.profile_completed = true
      }
      this.router.push('/supplier/portal')
    } catch (e: any) {
      alert(e?.response?.data?.message ?? '送出失敗，請稍後再試')
    } finally {
      this.isSubmitting = false
    }
  },

  methods: {
    async submit() {
      const supplierId = (this.authStore as any).supplier?.id
      if (!supplierId) return
      this.isSubmitting = true
      try {
        await supplierProfileApi.update(supplierId, this.form)
        if ((this.authStore as any).supplier) {
          (this.authStore as any).supplier.profile_completed = true
        }
        this.router.push('/supplier/portal')
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '送出失敗，請稍後再試')
      } finally {
        this.isSubmitting = false
      }
    },
  },
})
</script>

<style scoped>
.profile-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 36px;
  width: 100%;
  max-width: 560px;
}
.profile-header { margin-bottom: 28px; }
.profile-title { font-size: 22px; font-weight: 700; margin-bottom: 6px; }
.profile-subtitle { font-size: 14px; color: var(--text-secondary); }
.profile-section { margin-bottom: 24px; }
.section-label {
  font-size: 11px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.06em; color: var(--text-secondary);
  margin-bottom: 14px; padding-bottom: 6px; border-bottom: 1px solid var(--border);
}
.readonly-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.readonly-item { display: flex; flex-direction: column; gap: 2px; }
.readonly-key { font-size: 11px; color: var(--text-secondary); }
.readonly-val { font-size: 14px; font-weight: 500; }
.warn-hint {
  margin-top: 6px; font-size: 12px; color: #92400e;
  background: #fef9c3; padding: 6px 10px; border-radius: 4px;
}
</style>
