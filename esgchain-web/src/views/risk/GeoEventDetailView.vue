<template>
  <div class="geo-event-detail">
    <div class="page-header">
      <router-link to="/risk/geo-events" class="back-link">← 返回地緣事件列表</router-link>
    </div>

    <div v-if="loading" class="loading-state">載入中...</div>
    <template v-else-if="event">
      <div class="event-card">
        <div class="event-header">
          <div>
            <h1>{{ event.name }}</h1>
            <div class="event-meta">
              <span class="severity-badge" :class="`sev-${event.severity}`">{{ severityLabel(event.severity) }}</span>
              <span>{{ event.event_type }}</span>
              <span>影響國家：{{ (event.affected_scope?.country_codes || []).join(', ') || '—' }}</span>
              <span>{{ event.occurred_at?.slice(0, 10) }}</span>
            </div>
          </div>
          <button
            class="btn-primary"
            @click="recalculate"
            :disabled="recalculating || !hasPending"
          >
            <span v-if="recalculating">計算中...</span>
            <span v-else>批次重算 E4</span>
          </button>
        </div>

        <!-- 進度 -->
        <div class="progress-bar-wrap" v-if="reviews.length">
          <div class="progress-info">
            <span>複查進度：{{ doneCount }} / {{ reviews.length }}</span>
            <span v-if="failedCount > 0" class="failed-text">{{ failedCount }} 件失敗</span>
          </div>
          <div class="progress-bar">
            <div class="progress-fill" :style="{ width: (doneCount / reviews.length * 100) + '%' }" />
          </div>
        </div>
      </div>

      <!-- 受影響供應商表格 -->
      <div class="card table-card">
        <div v-if="!reviews.length" class="empty-state">此事件無受影響供應商</div>
        <table v-else class="data-table">
          <thead>
            <tr>
              <th>供應商</th>
              <th>國家</th>
              <th class="col-num">E4 重算前</th>
              <th class="col-num">E4 重算後</th>
              <th class="col-num">差異</th>
              <th>狀態</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in reviews" :key="r.id" class="data-row">
              <td>{{ r.supplier?.name ?? r.supplier_id }}</td>
              <td>{{ r.supplier?.country_code ?? '—' }}</td>
              <td class="col-num font-mono">{{ r.pre_e4_score !== null ? r.pre_e4_score.toFixed(1) : '—' }}</td>
              <td class="col-num font-mono" :class="postClass(r)">
                {{ r.post_e4_score !== null ? r.post_e4_score.toFixed(1) : '—' }}
              </td>
              <td class="col-num font-mono" :class="deltaClass(r)">
                {{ delta(r) }}
              </td>
              <td><span class="status-badge" :class="`st-${r.status}`">{{ statusLabel(r.status) }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<script>
import { riskApi } from '@/api/modules/risk'

export default {
  name: 'GeoEventDetailView',
  data() {
    return {
      event: null,
      reviews: [],
      loading: false,
      recalculating: false,
      pollTimer: null,
    }
  },
  computed: {
    doneCount() { return this.reviews.filter(r => r.status === 'done').length },
    failedCount() { return this.reviews.filter(r => r.status === 'failed').length },
    hasPending() { return this.reviews.some(r => r.status === 'pending') },
    hasRecalculating() { return this.reviews.some(r => r.status === 'recalculating') },
  },
  mounted() { this.loadAll() },
  beforeUnmount() { this.stopPoll() },
  methods: {
    async loadAll() {
      this.loading = true
      try {
        const id = this.$route.params.id
        const [evRes, revRes] = await Promise.all([
          riskApi.getGeoEvent(id),
          riskApi.geoEventReviews(id),
        ])
        this.event = evRes.data
        this.reviews = revRes.data.data
        if (this.hasRecalculating) this.startPoll()
      } catch (e) { console.error(e) } finally { this.loading = false }
    },
    async loadReviews() {
      try {
        const res = await riskApi.geoEventReviews(this.$route.params.id)
        this.reviews = res.data.data
        if (!this.hasRecalculating) this.stopPoll()
      } catch (e) { console.error(e) }
    },
    async recalculate() {
      this.recalculating = true
      try {
        await riskApi.recalculateE4(this.$route.params.id)
        await this.loadReviews()
        this.startPoll()
      } catch (e) { console.error(e) } finally { this.recalculating = false }
    },
    startPoll() {
      if (this.pollTimer) return
      this.pollTimer = setInterval(this.loadReviews, 5000)
    },
    stopPoll() {
      if (this.pollTimer) { clearInterval(this.pollTimer); this.pollTimer = null }
    },
    delta(r) {
      if (r.pre_e4_score === null || r.post_e4_score === null) return '—'
      const d = r.post_e4_score - r.pre_e4_score
      return (d > 0 ? '+' : '') + d.toFixed(1)
    },
    deltaClass(r) {
      if (r.pre_e4_score === null || r.post_e4_score === null) return ''
      const d = r.post_e4_score - r.pre_e4_score
      if (d > 0) return 'delta-good'
      if (d < 0) return 'delta-bad'
      return ''
    },
    postClass(r) {
      if (r.post_e4_score === null) return ''
      return r.post_e4_score < 35 ? 'score-danger' : r.post_e4_score < 70 ? 'score-warn' : 'score-ok'
    },
    severityLabel(s) { return { low: '低', medium: '中', high: '高', critical: '極高' }[s] ?? s },
    statusLabel(s) { return { pending: '待處理', recalculating: '計算中', done: '完成', failed: '失敗' }[s] ?? s },
  },
}
</script>

<style scoped>
.geo-event-detail { padding: 1.5rem; }
.page-header { margin-bottom: 1rem; }
.back-link { color: var(--accent, #1a4d3e); text-decoration: none; font-size: 0.875rem; }

.event-card { background: var(--surface, #f9f9f9); border: 1px solid var(--border, #ddd); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.25rem; }
.event-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem; }
.event-header h1 { font-size: 1.25rem; margin: 0 0 0.4rem 0; }
.event-meta { display: flex; gap: 0.75rem; align-items: center; font-size: 0.875rem; flex-wrap: wrap; }

.severity-badge { font-size: 0.75rem; padding: 0.15rem 0.5rem; border-radius: 3px; }
.sev-low { background: #dcfce7; color: #15803d; }
.sev-medium { background: #fef9c3; color: #a16207; }
.sev-high { background: #ffedd5; color: #c2410c; }
.sev-critical { background: #fee2e2; color: #b91c1c; }

.progress-bar-wrap { margin-top: 0.75rem; }
.progress-info { display: flex; justify-content: space-between; font-size: 0.875rem; margin-bottom: 0.3rem; }
.failed-text { color: #e53e3e; }
.progress-bar { background: var(--border, #ddd); border-radius: 4px; height: 8px; }
.progress-fill { background: var(--accent, #1a4d3e); height: 100%; border-radius: 4px; transition: width 0.5s; }

.card { background: var(--bg, #fff); border: 1px solid var(--border, #ddd); border-radius: 8px; overflow: hidden; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.data-table th { padding: 0.6rem 1rem; background: var(--surface, #f5f5f5); border-bottom: 2px solid var(--border, #ddd); text-align: left; }
.col-num { text-align: right !important; }
.data-row td { padding: 0.6rem 1rem; border-bottom: 1px solid var(--border, #eee); }
.data-row:last-child td { border-bottom: none; }

.delta-good { color: #15803d; }
.delta-bad { color: #b91c1c; }
.score-ok { color: #15803d; }
.score-warn { color: #a16207; }
.score-danger { color: #b91c1c; font-weight: 600; }

.status-badge { font-size: 0.75rem; padding: 0.15rem 0.5rem; border-radius: 3px; }
.st-pending { background: #f3f4f6; color: #6b7280; }
.st-recalculating { background: #bee3f8; color: #2b6cb0; }
.st-done { background: #dcfce7; color: #15803d; }
.st-failed { background: #fee2e2; color: #b91c1c; }

.loading-state, .empty-state { padding: 2rem; text-align: center; color: var(--text-muted, #888); }
.btn-primary { padding: 0.5rem 1.25rem; background: var(--accent, #1a4d3e); color: #fff; border: none; border-radius: 4px; cursor: pointer; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
