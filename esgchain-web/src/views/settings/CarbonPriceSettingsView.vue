<template>
  <div class="section-card">
    <div class="section-header">
      <div>
        <h2 class="section-title">系統碳價假設</h2>
        <p class="section-desc">設定 CBAM 申報風險金額計算所使用的內部碳成本定價</p>
      </div>
    </div>

    <div style="max-width:560px">
      <div v-if="isLoading" class="loading-mask">載入中...</div>
      <div v-else>
        <div class="info-block">
          <div class="info-row">
            <span class="info-label">目前碳價</span>
            <span class="info-value font-mono">€ {{ current.carbon_price_eur.toFixed(2) }} / tCO₂e</span>
          </div>
          <div class="info-row">
            <span class="info-label">最後更新</span>
            <span class="info-value">{{ current.updated_at ? formatDate(current.updated_at) : '—' }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">更新者</span>
            <span class="info-value">{{ current.updated_by ?? '—' }}</span>
          </div>
          <div v-if="current.is_default" class="default-hint">
            此為系統預設值（€65.00，參考 2024 EU ETS 均價），建議依貴司內部碳定價政策調整。
          </div>
        </div>

        <hr style="margin:24px 0;border:none;border-top:1px solid var(--border)" />

        <div class="form-group">
          <label class="form-label">調整碳價（€ / tCO₂e）</label>
          <div style="display:flex;gap:8px;align-items:center">
            <input
              v-model.number="newPrice"
              type="number"
              step="0.01"
              min="0.01"
              class="form-input font-mono"
              style="width:160px"
              placeholder="例：65.00"
            />
            <button
              class="btn btn-primary"
              :disabled="isSaving || !isValid"
              @click="save"
            >
              {{ isSaving ? '儲存中...' : '儲存' }}
            </button>
          </div>
          <div v-if="newPrice !== null && !isValid" class="form-error">碳價必須大於 0</div>
          <div v-if="saved" class="form-success">碳價假設已更新</div>
        </div>

        <div class="methodology-note">
          <strong>碳成本內部定價法說明</strong><br />
          本系統以此碳價計算 CBAM 商品的預估申報金額：<br />
          <span class="font-mono">申報金額 = 嵌入碳排 (tCO₂e) × 碳價 (€/tCO₂e)</span><br />
          建議參考歐盟 ETS 市場價格或貴司內部碳定價政策定期更新。
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { carbonPriceApi, type CarbonPriceSetting } from '@/api/modules/carbonPrice'

export default defineComponent({
  name: 'CarbonPriceSettingsView',

  data() {
    return {
      isLoading: false,
      isSaving: false,
      saved: false,
      current: {
        carbon_price_eur: 65.0,
        is_default: true,
        updated_at: null,
        updated_by: null,
      } as CarbonPriceSetting,
      newPrice: null as number | null,
    }
  },

  computed: {
    isValid(): boolean {
      return this.newPrice !== null && this.newPrice > 0
    },
  },

  mounted() {
    this.load()
  },

  methods: {
    async load() {
      this.isLoading = true
      try {
        const res = await carbonPriceApi.get()
        this.current = res.data.data
        this.newPrice = this.current.carbon_price_eur
      } catch (e) {
        console.error(e)
      } finally {
        this.isLoading = false
      }
    },

    async save() {
      if (!this.isValid || this.newPrice === null) return
      this.isSaving = true
      this.saved = false
      try {
        await carbonPriceApi.update(this.newPrice)
        this.saved = true
        await this.load()
      } catch (e) {
        console.error(e)
      } finally {
        this.isSaving = false
      }
    },

    formatDate(iso: string): string {
      return new Date(iso).toLocaleString('zh-TW', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
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

.info-block { display: flex; flex-direction: column; gap: 10px; }
.info-row { display: flex; gap: 16px; align-items: baseline; }
.info-label { width: 80px; font-size: 13px; color: var(--text-secondary); flex-shrink: 0; }
.info-value { font-size: 14px; font-weight: 500; }
.default-hint {
  font-size: 12px;
  color: #92400e;
  background: #fef3c7;
  border: 1px solid #fcd34d;
  border-radius: 4px;
  padding: 8px 12px;
  margin-top: 4px;
}
.form-group { margin-bottom: 20px; }
.form-label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 8px; }
.form-input { border: 1px solid var(--border); border-radius: 6px; padding: 8px 12px; font-size: 14px; outline: none; }
.form-input:focus { border-color: var(--accent); }
.form-error { font-size: 12px; color: #dc2626; margin-top: 4px; }
.form-success { font-size: 12px; color: #16a34a; margin-top: 4px; }
.methodology-note {
  font-size: 12px;
  color: var(--text-secondary);
  background: var(--surface-2);
  border-radius: 6px;
  padding: 12px;
  line-height: 1.7;
}
</style>
