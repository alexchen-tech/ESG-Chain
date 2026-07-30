<template>
  <div class="geo-event-view">
    <div class="page-header">
      <div>
        <h1>地緣事件複查</h1>
        <p class="page-desc">建立地緣政治事件，批次評估受影響供應商的 E4 風險</p>
      </div>
      <button class="btn-primary" @click="showCreate = true">＋ 新增事件</button>
    </div>

    <div v-if="loading" class="loading-state">載入中...</div>
    <div v-else-if="!events.length" class="empty-state">尚無地緣事件記錄</div>
    <div v-else class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>事件名稱</th>
            <th>類型</th>
            <th>嚴重程度</th>
            <th>影響國家</th>
            <th>發生日期</th>
            <th>複查進度</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="ev in events" :key="ev.id" class="data-row">
            <td>{{ ev.name }}</td>
            <td>{{ ev.event_type }}</td>
            <td><span class="severity-badge" :class="`sev-${ev.severity}`">{{ severityLabel(ev.severity) }}</span></td>
            <td class="font-mono">{{ (ev.affected_scope?.country_codes || []).join(', ') || '—' }}</td>
            <td>{{ ev.occurred_at?.slice(0, 10) }}</td>
            <td>
              <span class="progress-text font-mono">
                {{ ev.done_count ?? 0 }} / {{ ev.supplier_reviews_count ?? 0 }}
              </span>
            </td>
            <td>
              <router-link :to="`/risk/geo-events/${ev.id}`" class="btn-link">詳情 →</router-link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 分頁 -->
    <div v-if="events.length" class="pagination">
      <span>第 {{ pagination.current_page }} / {{ pagination.last_page }} 頁（共 {{ pagination.total }} 筆）</span>
      <button class="pg-btn" :disabled="pagination.current_page <= 1" @click="goPage(pagination.current_page - 1)">‹</button>
      <button class="pg-btn" :disabled="pagination.current_page >= pagination.last_page" @click="goPage(pagination.current_page + 1)">›</button>
    </div>

    <!-- 新增 Modal -->
    <div v-if="showCreate" class="modal-overlay" @click.self="showCreate = false">
      <div class="modal-content">
        <h2>新增地緣事件</h2>
        <div class="form-group">
          <label>事件名稱 *</label>
          <input v-model="form.name" type="text" placeholder="例：2025 美中關稅調升" />
        </div>
        <div class="form-group">
          <label>事件類型</label>
          <select v-model="form.event_type">
            <option value="tariff_change">關稅調整</option>
            <option value="sanction">制裁</option>
            <option value="country_risk_update">國家風險更新</option>
            <option value="other">其他</option>
          </select>
        </div>
        <div class="form-group">
          <label>嚴重程度</label>
          <select v-model="form.severity">
            <option value="low">低</option>
            <option value="medium">中</option>
            <option value="high">高</option>
            <option value="critical">極高</option>
          </select>
        </div>
        <div class="form-group">
          <label>影響國家代碼（逗號分隔，如 VN,CN）</label>
          <input v-model="form.country_codes_str" type="text" placeholder="VN,CN,IN" />
        </div>
        <div class="form-group">
          <label>發生日期 *</label>
          <input v-model="form.occurred_at" type="date" />
        </div>
        <div class="modal-actions">
          <button class="btn-secondary" @click="showCreate = false">取消</button>
          <button class="btn-primary" @click="createEvent" :disabled="creating">
            {{ creating ? '建立中...' : '建立事件' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { riskApi } from '@/api/modules/risk'

export default {
  name: 'GeoEventView',
  data() {
    return {
      events: [],
      loading: false,
      showCreate: false,
      creating: false,
      pagination: { current_page: 1, per_page: 20, total: 0, last_page: 1 },
      form: { name: '', event_type: 'tariff_change', severity: 'medium', country_codes_str: '', occurred_at: '' },
    }
  },
  mounted() { this.loadEvents() },
  methods: {
    async loadEvents() {
      this.loading = true
      try {
        const res = await riskApi.geoEvents({ page: this.pagination.current_page, per_page: this.pagination.per_page })
        this.events = res.data.data
        this.pagination = {
          current_page: res.data.current_page,
          per_page: res.data.per_page,
          total: res.data.total,
          last_page: res.data.last_page,
        }
      } catch (e) { console.error(e) } finally { this.loading = false }
    },
    goPage(page) {
      this.pagination.current_page = page
      this.loadEvents()
    },
    async createEvent() {
      if (!this.form.name || !this.form.occurred_at) return
      this.creating = true
      try {
        await riskApi.createGeoEvent({
          name: this.form.name,
          event_type: this.form.event_type,
          severity: this.form.severity,
          occurred_at: this.form.occurred_at,
          affected_scope: {
            country_codes: this.form.country_codes_str.split(',').map(c => c.trim()).filter(Boolean),
          },
        })
        this.showCreate = false
        this.form = { name: '', event_type: 'tariff_change', severity: 'medium', country_codes_str: '', occurred_at: '' }
        await this.loadEvents()
      } catch (e) { console.error(e) } finally { this.creating = false }
    },
    severityLabel(s) {
      return { low: '低', medium: '中', high: '高', critical: '極高' }[s] ?? s
    },
  },
}
</script>

<style scoped>
.geo-event-view { padding: 1.5rem; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem; }
.page-desc { color: var(--text-muted, #888); font-size: 0.875rem; margin-top: 0.25rem; }

.data-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.data-table th { padding: 0.6rem 1rem; background: var(--surface, #f5f5f5); border-bottom: 2px solid var(--border, #ddd); text-align: left; white-space: nowrap; }
.data-row td { padding: 0.6rem 1rem; border-bottom: 1px solid var(--border, #eee); }

.severity-badge { font-size: 0.75rem; padding: 0.15rem 0.5rem; border-radius: 3px; }
.sev-low { background: #dcfce7; color: #15803d; }
.sev-medium { background: #fef9c3; color: #a16207; }
.sev-high { background: #ffedd5; color: #c2410c; }
.sev-critical { background: #fee2e2; color: #b91c1c; }

.progress-text { font-size: 0.875rem; }
.btn-link { color: var(--accent, #1a4d3e); text-decoration: none; font-size: 0.875rem; }

.loading-state, .empty-state { padding: 2rem; text-align: center; color: var(--text-muted, #888); }

.btn-primary { padding: 0.5rem 1.25rem; background: var(--accent, #1a4d3e); color: #fff; border: none; border-radius: 4px; cursor: pointer; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-secondary { padding: 0.5rem 1.25rem; border: 1px solid var(--border, #ddd); border-radius: 4px; cursor: pointer; background: transparent; }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 300; display: flex; align-items: center; justify-content: center; }
.modal-content { background: var(--bg, #fff); border-radius: 8px; padding: 2rem; width: 480px; max-width: 90vw; }
.modal-content h2 { margin-bottom: 1.25rem; }
.form-group { margin-bottom: 1rem; }
.form-group label { display: block; font-size: 0.875rem; margin-bottom: 0.3rem; color: var(--text-muted, #555); }
.form-group input, .form-group select { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--border, #ddd); border-radius: 4px; font-size: 0.875rem; background: var(--bg, #fff); color: var(--text, #111); }
.modal-actions { display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem; }
</style>
