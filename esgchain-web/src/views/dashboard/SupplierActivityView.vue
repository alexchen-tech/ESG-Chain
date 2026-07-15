<template>
  <div class="page-container">
    <div class="breadcrumb">
      <router-link to="/dashboard" class="breadcrumb-link">儀表板</router-link>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current">供應商動態明細</span>
    </div>

    <div class="page-header">
      <div>
        <h1 class="page-title">供應商動態明細</h1>
        <p class="page-subtitle">供應商近期所有異動紀錄</p>
      </div>
    </div>

    <!-- 篩選列 -->
    <div class="filter-bar">
      <select v-model="filterDays" class="filter-select" @change="load">
        <option :value="7">最近 7 天</option>
        <option :value="14">最近 14 天</option>
        <option :value="30">最近 30 天</option>
        <option :value="60">最近 60 天</option>
        <option :value="90">最近 90 天</option>
      </select>
      <select v-model="filterType" class="filter-select" @change="applyFilter">
        <option value="">所有類型</option>
        <option value="doc_uploaded">文件上傳</option>
        <option value="saq_submitted">SAQ 提交</option>
        <option value="status_change">狀態變更</option>
        <option value="cap_updated">CAP 更新</option>
        <option value="doc_expiring">文件到期</option>
      </select>
      <select v-model="filterSeverity" class="filter-select" @change="applyFilter">
        <option value="">所有優先級</option>
        <option value="warning">需要處理</option>
        <option value="pending">待辦</option>
        <option value="info">資訊</option>
      </select>
      <span class="filter-count">共 {{ filtered.length }} 筆</span>
    </div>

    <!-- 表格 -->
    <div class="table-container">
      <div v-if="isLoading" class="loading-mask">載入中...</div>
      <div v-else-if="filtered.length === 0" class="empty-state"><p>無符合條件的異動記錄</p></div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th style="width:36px;">#</th>
            <th style="width:80px;">類型</th>
            <th>供應商</th>
            <th>異動說明</th>
            <th style="width:80px;">優先</th>
            <th style="width:130px;">發生時間</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(item, idx) in paged"
            :key="idx"
            class="clickable-row"
            @click="$router.push(`/suppliers/${item.supplier_id}`)"
          >
            <td class="num" style="font-size:12px;color:var(--text-secondary);">
              {{ (page - 1) * perPage + idx + 1 }}
            </td>
            <td>
              <span class="type-badge" :class="`type-badge--${item.event_type}`">
                {{ typeLabel(item.event_type) }}
              </span>
            </td>
            <td style="font-weight:500;font-size:13px;">{{ item.supplier_name }}</td>
            <td style="font-size:13px;color:var(--text-secondary);">{{ item.event_label }}</td>
            <td>
              <span class="sev-dot" :class="`sev-dot--${item.severity}`">
                {{ severityLabel(item.severity) }}
              </span>
            </td>
            <td style="font-size:12px;font-family:var(--font-mono);">{{ formatTime(item.occurred_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 分頁 -->
    <div v-if="totalPages > 1" class="pagination">
      <span>第 {{ page }} / {{ totalPages }} 頁</span>
      <button class="pg-btn" :disabled="page <= 1" @click="page--">‹</button>
      <button class="pg-btn" :disabled="page >= totalPages" @click="page++">›</button>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { dashboardApi, type RecentActivity } from '@/api/modules/dashboard'

const TYPE_LABELS: Record<string, string> = {
  doc_uploaded:  '文件上傳',
  saq_submitted: 'SAQ 提交',
  status_change: '狀態變更',
  cap_updated:   'CAP 更新',
  doc_expiring:  '文件到期',
}

export default defineComponent({
  name: 'SupplierActivityView',

  data() {
    return {
      isLoading: false,
      activities: [] as RecentActivity[],
      filterDays: 30,
      filterType: '',
      filterSeverity: '',
      page: 1,
      perPage: 20,
    }
  },

  computed: {
    filtered(): RecentActivity[] {
      return this.activities.filter(a => {
        if (this.filterType && a.event_type !== this.filterType) return false
        if (this.filterSeverity && a.severity !== this.filterSeverity) return false
        return true
      })
    },
    totalPages(): number { return Math.max(1, Math.ceil(this.filtered.length / this.perPage)) },
    paged(): RecentActivity[] {
      const start = (this.page - 1) * this.perPage
      return this.filtered.slice(start, start + this.perPage)
    },
  },

  watch: {
    filterDays() { this.load() },
    filterType()     { this.page = 1 },
    filterSeverity() { this.page = 1 },
  },

  mounted() { this.load() },

  methods: {
    async load() {
      this.isLoading = true
      this.page = 1
      try {
        const { data } = await dashboardApi.recentActivity({ days: this.filterDays, limit: 500 })
        this.activities = data.data
      } catch { /**/ }
      finally { this.isLoading = false }
    },

    applyFilter() { this.page = 1 },

    typeLabel: (t: string) => TYPE_LABELS[t] ?? t,

    severityLabel(s: string): string {
      return ({ warning: '需處理', pending: '待辦', info: '資訊' })[s] ?? s
    },

    formatTime(iso: string): string {
      return new Date(iso).toLocaleString('zh-TW', {
        month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
      })
    },
  },
})
</script>

<style scoped>
.filter-count { font-size: 13px; color: var(--text-secondary); margin-left: auto; }

.type-badge {
  display: inline-block;
  font-size: 11px;
  padding: 2px 7px;
  border-radius: 4px;
  font-weight: 500;
  white-space: nowrap;
  background: var(--surface-2);
  color: var(--text-secondary);
  border: 1px solid var(--border);
}
.type-badge--doc_uploaded  { background: #dbeafe; color: #1e40af; border-color: #bfdbfe; }
.type-badge--saq_submitted { background: #ede9fe; color: #5b21b6; border-color: #ddd6fe; }
.type-badge--status_change { background: #d1fae5; color: #065f46; border-color: #a7f3d0; }
.type-badge--cap_updated   { background: #fef3c7; color: #92400e; border-color: #fde68a; }
.type-badge--doc_expiring  { background: #fee2e2; color: #991b1b; border-color: #fecaca; }

.sev-dot {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 7px;
  border-radius: 99px;
}
.sev-dot--warning { background: #fee2e2; color: #991b1b; }
.sev-dot--pending { background: #dbeafe; color: #1e40af; }
.sev-dot--info    { background: #f0fdf4; color: #166534; }
</style>
