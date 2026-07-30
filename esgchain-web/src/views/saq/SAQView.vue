<template>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h1 class="page-title">問卷管理</h1>
        <p class="page-subtitle">SAQ 發送、填寫、審查生命週期</p>
      </div>
    </div>

    <!-- SAQ 列表（以狀態分組） -->
    <div class="filter-bar">
      <select v-model="filterStatus" class="filter-select" @change="loadData">
        <option value="">所有狀態</option>
        <option value="sent">已發送</option>
        <option value="in_progress">填寫中</option>
        <option value="submitted">已提交</option>
        <option value="reviewing">審核中</option>
        <option value="approved">已核准</option>
        <option value="rejected">已退回</option>
      </select>
    </div>

    <div class="table-container">
      <div v-if="isLoading" class="loading-mask">載入中...</div>
      <div v-else-if="saqs.length === 0" class="empty-state">
        <p>尚無 SAQ 記錄</p>
      </div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>#</th>
            <th>供應商</th>
            <th>狀態</th>
            <th>分數</th>
            <th>等級</th>
            <th style="white-space:nowrap;">發送日</th>
            <th style="white-space:nowrap;">提交日</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(saq, i) in saqs" :key="saq.id">
            <td class="num">{{ i + 1 }}</td>
            <td>{{ saq.supplier?.name ? maskSupplierName(saq.supplier.name) : saq.supplier_id.slice(0, 8) }}</td>
            <td><span class="badge" :class="saqBadgeClass(saq.status)">{{ saqStatusLabel(saq.status) }}</span></td>
            <td class="num">{{ saq.score != null ? saq.score.toFixed(1) : '—' }}</td>
            <td>
              <span v-if="saq.grade" class="badge" :class="gradeBadgeClass(saq.grade)">{{ saq.grade }} 級</span>
              <span v-else>—</span>
            </td>
            <td style="font-size: 12px;white-space:nowrap;">{{ formatDate(saq.sent_at) }}</td>
            <td style="font-size: 12px;white-space:nowrap;">{{ formatDate(saq.submitted_at) }}</td>
            <td>
              <div style="display: flex; gap: 6px;">
                <button v-if="saq.status === 'reviewing'" class="btn btn-primary btn-sm" @click="openReviewModal(saq)">審核</button>
                <button class="btn btn-secondary btn-sm" @click="viewDetail(saq)">詳情</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 審核 Modal -->
    <div v-if="showReviewModal" class="modal-overlay" @click.self="showReviewModal = false">
      <div class="modal">
        <div class="modal-header">
          <span class="modal-title">審核 SAQ</span>
          <button class="modal-close" @click="showReviewModal = false">×</button>
        </div>
        <div v-if="reviewTarget">
          <p style="font-size: 14px; margin-bottom: 12px; color: var(--text-secondary);">
            供應商：<strong>{{ maskSupplierName(reviewTarget.supplier?.name) }}</strong>
            ｜分數：<span class="font-mono">{{ reviewTarget.score?.toFixed(1) }}</span>
            ｜等級：<span class="badge" :class="gradeBadgeClass(reviewTarget.grade || '')">{{ reviewTarget.grade }}</span>
          </p>
          <div class="form-group">
            <label class="form-label">審核意見</label>
            <textarea v-model="reviewComment" class="form-textarea" placeholder="選填審核說明..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showReviewModal = false">取消</button>
          <button class="btn btn-danger" :disabled="isSubmitting" @click="doReject">退回</button>
          <button class="btn btn-primary" :disabled="isSubmitting" @click="doApprove">核准</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { saqApi, type SAQ } from '@/api/modules/saq'
import { maskSupplierName } from '@/utils/maskName'

const SAQ_STATUS_LABELS: Record<string, string> = {
  pending: '待發送', sent: '已發送', in_progress: '填寫中',
  submitted: '已提交', reviewing: '審核中', approved: '已核准', rejected: '已退回',
}

export default defineComponent({
  name: 'SAQView',

  data() {
    return {
      isLoading: false,
      isSubmitting: false,
      saqs: [] as SAQ[],
      filterStatus: '',
      showReviewModal: false,
      reviewTarget: null as SAQ | null,
      reviewComment: '',
    }
  },

  mounted() { this.loadData() },

  methods: {
    maskSupplierName,
    async loadData() {
      this.isLoading = true
      try {
        // 目前以 supplier portal 的 myList 為主，內部審查員可改用 project 列表
        const { data } = await saqApi.myList()
        this.saqs = this.filterStatus
          ? data.data.filter((s: SAQ) => s.status === this.filterStatus)
          : data.data
      } catch {
        this.saqs = []
      } finally { this.isLoading = false }
    },
    saqStatusLabel(status: string) { return SAQ_STATUS_LABELS[status] ?? status },
    saqBadgeClass(status: string) {
      return ({ approved: 'badge-green', reviewing: 'badge-blue', submitted: 'badge-blue',
        sent: 'badge-yellow', in_progress: 'badge-yellow', rejected: 'badge-red', pending: 'badge-gray' })[status] ?? 'badge-gray'
    },
    gradeBadgeClass(grade: string) {
      return ({ A: 'badge-green', B: 'badge-blue', C: 'badge-yellow', D: 'badge-red', E: 'badge-red' })[grade] ?? 'badge-gray'
    },
    formatDate(dt: string | null) {
      if (!dt) return '—'
      return new Date(dt).toLocaleDateString('zh-TW')
    },
    openReviewModal(saq: SAQ) { this.reviewTarget = saq; this.reviewComment = ''; this.showReviewModal = true },
    viewDetail(saq: SAQ) { console.log('view detail', saq.id) },
    async doApprove() {
      if (!this.reviewTarget) return
      this.isSubmitting = true
      try {
        await saqApi.approve(this.reviewTarget.id, this.reviewComment)
        this.showReviewModal = false
        this.loadData()
      } finally { this.isSubmitting = false }
    },
    async doReject() {
      if (!this.reviewTarget || !this.reviewComment) { alert('退回時請填寫原因'); return }
      this.isSubmitting = true
      try {
        await saqApi.reject(this.reviewTarget.id, this.reviewComment)
        this.showReviewModal = false
        this.loadData()
      } finally { this.isSubmitting = false }
    },
  },
})
</script>
