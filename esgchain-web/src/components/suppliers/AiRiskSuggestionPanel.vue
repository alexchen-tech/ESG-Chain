<template>
  <div class="ai-suggestion-panel">
    <div class="ai-panel-header">
      <span class="ai-panel-icon">🤖</span>
      <span class="ai-panel-title">風險評估建議</span>
      <button
        class="ai-regen-btn"
        :disabled="loading"
        @click="generate(true)"
      >{{ loading ? '產生中...' : '重新產生' }}</button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="ai-panel-loading">
      <span class="ai-spin">⟳</span> AI 分析中，請稍候…
    </div>

    <!-- 無 RA 資料 -->
    <div v-else-if="!hasRa" class="ai-panel-empty">
      尚無風險評估資料，無法產生建議
    </div>

    <!-- 錯誤 -->
    <div v-else-if="error" class="ai-panel-error">
      {{ error }}
    </div>

    <!-- 建議內容 -->
    <template v-else-if="suggestion">
      <div class="ai-summary">{{ suggestion.summary }}</div>
      <div class="ai-recs">
        <div v-for="rec in suggestion.recommendations" :key="rec.axis" class="ai-rec-item">
          <div class="ai-rec-label">{{ rec.label }}</div>
          <div class="ai-rec-action">{{ rec.action }}</div>
        </div>
      </div>
      <div class="ai-panel-footer">
        <span class="ai-generated-at">{{ formattedDate }}</span>
        <span class="ai-disclaimer">AI 產生，僅供參考</span>
      </div>
    </template>

    <!-- 初始未觸發（不應出現，hasRa=true 時 mounted 自動觸發） -->
    <div v-else class="ai-panel-empty">點擊「重新產生」取得建議</div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import http from '@/api/http'

export default defineComponent({
  name: 'AiRiskSuggestionPanel',

  props: {
    supplierId: { type: String, required: true },
    hasRa:      { type: Boolean, default: false },
  },

  data() {
    return {
      loading:     false,
      error:       null as string | null,
      suggestion:  null as any,
      generatedAt: null as string | null,
    }
  },

  computed: {
    formattedDate(): string {
      if (!this.generatedAt) return ''
      const d = new Date(this.generatedAt)
      return `${d.getFullYear()}/${d.getMonth()+1}/${d.getDate()} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')} 產生`
    },
  },

  mounted() {
    if (this.hasRa) this.generate(false)
  },

  methods: {
    async generate(force: boolean) {
      this.loading = true
      this.error = null
      try {
        const res = await http.post(
          `/api/v1/suppliers/${this.supplierId}/risk-suggestion`,
          { force }
        )
        this.suggestion  = res.data.suggestion
        this.generatedAt = res.data.generated_at
      } catch (e: any) {
        const msg = e?.response?.data?.message || e?.message || '產生失敗，請稍後再試'
        this.error = msg
      } finally {
        this.loading = false
      }
    },
  },
})
</script>

<style scoped>
.ai-suggestion-panel {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-width: 260px;
}

.ai-panel-header {
  display: flex;
  align-items: center;
  gap: 6px;
}
.ai-panel-icon { font-size: 15px; }
.ai-panel-title {
  font-size: 13px;
  font-weight: 700;
  color: var(--text-primary);
  flex: 1;
}
.ai-regen-btn {
  font-size: 11.5px;
  padding: 3px 10px;
  border: 1px solid var(--border);
  border-radius: 5px;
  background: transparent;
  color: var(--accent);
  cursor: pointer;
  font-weight: 500;
  transition: background 0.15s;
}
.ai-regen-btn:hover:not(:disabled) { background: rgba(26,77,62,0.06); }
.ai-regen-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.ai-panel-loading {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12.5px;
  color: var(--text-secondary);
  padding: 8px 0;
}
.ai-spin { animation: spin 1.2s linear infinite; display: inline-block; }
@keyframes spin { to { transform: rotate(360deg); } }

.ai-panel-empty,
.ai-panel-error {
  font-size: 12.5px;
  color: var(--text-secondary);
  padding: 8px 0;
}
.ai-panel-error { color: #b91c1c; }

.ai-summary {
  font-size: 13px;
  color: var(--text-primary);
  line-height: 1.6;
  padding: 8px 10px;
  background: rgba(26,77,62,0.05);
  border-left: 3px solid var(--accent);
  border-radius: 0 5px 5px 0;
}

.ai-recs {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.ai-rec-item {
  padding: 8px 10px;
  border: 1px solid var(--border);
  border-radius: 7px;
}
.ai-rec-label {
  font-size: 11.5px;
  font-weight: 700;
  color: var(--accent);
  margin-bottom: 4px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.ai-rec-action {
  font-size: 12.5px;
  color: var(--text-primary);
  line-height: 1.55;
}

.ai-panel-footer {
  display: flex;
  align-items: center;
  gap: 8px;
  padding-top: 4px;
  border-top: 1px solid var(--border);
}
.ai-generated-at {
  font-size: 11px;
  color: var(--text-secondary);
  flex: 1;
}
.ai-disclaimer {
  font-size: 11px;
  color: var(--text-secondary);
  background: #f5f3ee;
  padding: 2px 7px;
  border-radius: 4px;
}
@media (prefers-color-scheme: dark) {
  .ai-disclaimer { background: rgba(255,255,255,0.07); }
}
</style>
