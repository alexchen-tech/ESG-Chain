<template>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h1 class="page-title">矯正行動計畫</h1>
        <p class="page-subtitle">查看並處理指派給貴公司的矯正行動（CAP）</p>
      </div>
    </div>

    <div v-if="isLoading" class="loading-mask" style="padding:40px;text-align:center;">載入中...</div>

    <template v-else>
      <div v-if="caps.length === 0" class="empty-state">
        <div class="empty-icon">📋</div>
        <p>目前沒有待處理的矯正行動</p>
      </div>

      <table v-else class="data-table">
        <thead>
          <tr>
            <th>標題</th>
            <th style="width:70px;text-align:center;">優先級</th>
            <th style="width:90px;text-align:center;">狀態</th>
            <th style="width:100px;white-space:nowrap;">到期日</th>
            <th style="width:88px;text-align:center;">操作</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="cap in caps" :key="cap.id">
            <tr class="data-row" :class="{ 'row-overdue': cap.status === 'overdue', 'row-done': cap.status === 'completed' || cap.status === 'closed' }">
              <td>
                <div class="title-line">{{ parsedTitle(cap.title).description }}</div>
                <div v-if="cap.description" class="cap-desc">{{ cap.description }}</div>
              </td>
              <td style="text-align:center;">
                <span class="badge" :class="priorityBadgeClass(cap.priority)">{{ priorityLabel(cap.priority) }}</span>
              </td>
              <td style="text-align:center;">
                <span class="badge" :class="statusBadgeClass(cap.status)">{{ statusLabel(cap.status) }}</span>
              </td>
              <td style="white-space:nowrap;">
                <span class="font-mono">{{ formatDate(cap.due_date) }}</span>
              </td>
              <td style="text-align:center;">
                <div style="display:flex;gap:6px;justify-content:center;">
                  <button class="btn btn-secondary btn-sm" @click="toggleDetail(cap)">
                    {{ expandedId === cap.id ? '收合' : '詳情' }}
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="expandedId === cap.id">
              <td colspan="5" class="detail-cell">
                <div v-if="detailLoading" style="padding:16px;color:var(--text-secondary);">載入中...</div>
                <div v-else class="cap-detail">
                  <div v-if="cap.findings?.length" class="detail-block">
                    <div class="detail-block-title">問題發現</div>
                    <ul class="finding-list">
                      <li v-for="f in cap.findings" :key="f.id">{{ f.finding }}</li>
                    </ul>
                  </div>

                  <div class="detail-block">
                    <div class="detail-block-title">處理歷程</div>
                    <div v-if="!cap.updates?.length" class="empty-hint">尚無更新紀錄</div>
                    <div v-else class="timeline">
                      <div v-for="u in cap.updates" :key="u.id" class="timeline-item">
                        <span class="badge" :class="statusBadgeClass(u.status)">{{ statusLabel(u.status) }}</span>
                        <span class="timeline-time">{{ formatDateTime(u.created_at) }}</span>
                        <div v-if="u.notes" class="timeline-notes">{{ u.notes }}</div>
                        <div v-if="u.attachments?.length" class="timeline-attachments">
                          <button
                            v-for="att in u.attachments"
                            :key="att.id"
                            class="attachment-chip"
                            @click="downloadAttachment(att)"
                          >📎 {{ att.file_name }}</button>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div v-if="cap.status !== 'completed' && cap.status !== 'closed'" class="detail-block">
                    <div class="detail-block-title">更新狀態</div>
                    <div class="status-option-grid">
                      <label
                        v-for="opt in UPDATE_STATUS_OPTIONS"
                        :key="opt.value"
                        class="status-option"
                        :class="{ selected: updateForm.status === opt.value }"
                      >
                        <input type="radio" :value="opt.value" v-model="updateForm.status" hidden />
                        <span class="status-option-dot" :class="opt.dotCls"></span>
                        {{ opt.label }}
                      </label>
                    </div>
                    <textarea v-model="updateForm.notes" class="form-input" rows="2" style="resize:vertical;margin-top:10px;" placeholder="說明處理進度或備註（選填）…"></textarea>
                    <div style="margin-top:8px;">
                      <label class="form-label">佐證文件（選填，可多選）</label>
                      <input ref="fileInput" type="file" multiple class="form-input" @change="onPickFiles" />
                      <div v-if="updateForm.files.length" class="picked-files">
                        <span v-for="(f, idx) in updateForm.files" :key="idx" class="picked-file-chip">
                          {{ f.name }}
                          <button type="button" class="picked-file-remove" @click="updateForm.files.splice(idx, 1)">×</button>
                        </span>
                      </div>
                    </div>
                    <div style="margin-top:12px;">
                      <button class="btn btn-primary btn-sm" :disabled="isSubmitting" @click="submitUpdate(cap)">
                        {{ isSubmitting ? '送出中...' : '送出更新' }}
                      </button>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </template>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { portalCapApi, type CAP, type CAPAttachment } from '@/api/modules/portalCap'
import { portalNotificationsApi } from '@/api/modules/portalNotifications'

const UPDATE_STATUS_OPTIONS = [
  { value: 'in_progress', label: '處理中', dotCls: 'dot-blue' },
  { value: 'completed', label: '已完成', dotCls: 'dot-green' },
]

export default defineComponent({
  name: 'PortalCapView',

  data() {
    return {
      UPDATE_STATUS_OPTIONS,
      isLoading: false,
      isSubmitting: false,
      detailLoading: false,
      caps: [] as CAP[],
      expandedId: '' as string,
      updateForm: { status: 'in_progress', notes: '', files: [] as File[] },
    }
  },

  async mounted() {
    await this.loadData()
    portalNotificationsApi.markRead().catch(() => {})
  },

  methods: {
    async loadData() {
      this.isLoading = true
      try {
        const { data } = await portalCapApi.list()
        this.caps = data.data
      } finally { this.isLoading = false }
    },

    async toggleDetail(cap: CAP) {
      if (this.expandedId === cap.id) {
        this.expandedId = ''
        return
      }
      this.expandedId = cap.id
      this.updateForm = { status: cap.status === 'open' || cap.status === 'overdue' ? 'in_progress' : cap.status, notes: '', files: [] }
      if (!cap.updates) {
        this.detailLoading = true
        try {
          const { data } = await portalCapApi.get(cap.id)
          const idx = this.caps.findIndex(c => c.id === cap.id)
          if (idx !== -1) this.caps[idx] = data.data
        } finally { this.detailLoading = false }
      }
    },

    async submitUpdate(cap: CAP) {
      this.isSubmitting = true
      try {
        const payload: any = { status: this.updateForm.status }
        if (this.updateForm.notes.trim()) payload.notes = this.updateForm.notes.trim()
        const { data } = await portalCapApi.addUpdate(cap.id, payload)

        if (this.updateForm.files.length) {
          const updateId = data.data.updates?.[0]?.id
          for (const file of this.updateForm.files) {
            await portalCapApi.uploadAttachment(cap.id, file, updateId)
          }
        }

        const fresh = await portalCapApi.get(cap.id)
        const idx = this.caps.findIndex(c => c.id === cap.id)
        if (idx !== -1) this.caps[idx] = fresh.data.data
        this.updateForm = { status: 'in_progress', notes: '', files: [] }
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '更新失敗')
      } finally { this.isSubmitting = false }
    },

    onPickFiles(e: Event) {
      const input = e.target as HTMLInputElement
      if (input.files) this.updateForm.files.push(...Array.from(input.files))
      input.value = ''
    },

    async downloadAttachment(att: CAPAttachment) {
      try {
        const res = await portalCapApi.downloadAttachment(att.id)
        const url = URL.createObjectURL(new Blob([res.data as any]))
        const a = document.createElement('a')
        a.href = url
        a.download = att.file_name || 'file'
        a.click()
        URL.revokeObjectURL(url)
      } catch {
        alert('下載失敗')
      }
    },

    parsedTitle(title: string): { docType: string; description: string } {
      const m = title.match(/^(\[[^\]]+\])\s*(.+?)(?:\s*—\s*.+)?$/)
      if (m) return { docType: m[1].replace(/[\[\]]/g, ''), description: m[2] }
      return { docType: '', description: title }
    },

    formatDate(val: string | null | undefined): string {
      if (!val) return '—'
      return val.slice(0, 10)
    },

    formatDateTime(val: string | null | undefined): string {
      if (!val) return '—'
      return val.slice(0, 16).replace('T', ' ')
    },

    statusLabel(s: string) { return ({ open: '待處理', in_progress: '處理中', overdue: '已逾期', completed: '已完成', closed: '已關閉' })[s] ?? s },
    statusBadgeClass(s: string) { return ({ open: 'badge-yellow', in_progress: 'badge-blue', overdue: 'badge-red', completed: 'badge-green', closed: 'badge-gray' })[s] ?? 'badge-gray' },
    priorityLabel(p: string) { return ({ low: '低', medium: '中', high: '高', critical: '嚴重' })[p] ?? p },
    priorityBadgeClass(p: string) { return ({ low: 'badge-gray', medium: 'badge-yellow', high: 'badge-orange', critical: 'badge-red' })[p] ?? 'badge-gray' },
  },
})
</script>

<style scoped>
.cap-desc { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
.title-line { font-size: 13px; color: var(--text-primary); }
.row-overdue { background: rgba(220, 38, 38, 0.04); }
.row-done { opacity: 0.7; }

.detail-cell { background: var(--surface-2); padding: 0; }
.cap-detail { padding: 16px 20px; display: flex; flex-direction: column; gap: 18px; }
.detail-block-title { font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 8px; letter-spacing: .02em; }
.empty-hint { font-size: 12px; color: var(--text-secondary); }

.finding-list { margin: 0; padding-left: 18px; font-size: 13px; color: var(--text-primary); line-height: 1.7; }

.timeline { display: flex; flex-direction: column; gap: 10px; }
.timeline-item { background: var(--surface); border: 1px solid var(--border); border-radius: 6px; padding: 8px 12px; }
.timeline-time { font-size: 11px; color: var(--text-secondary); margin-left: 8px; }
.timeline-notes { font-size: 12px; color: var(--text-primary); margin-top: 4px; }
.timeline-attachments { margin-top: 6px; display: flex; flex-wrap: wrap; gap: 6px; }
.attachment-chip {
  font-size: 11px; padding: 3px 8px; border-radius: 4px; border: 1px solid var(--border);
  background: var(--surface); color: var(--accent); cursor: pointer;
}

.status-option-grid { display: flex; gap: 8px; }
.status-option {
  display: flex; align-items: center; gap: 6px; padding: 6px 12px; border: 1px solid var(--border);
  border-radius: 6px; font-size: 13px; cursor: pointer; color: var(--text-primary);
}
.status-option.selected { border-color: var(--accent); background: var(--surface-2); }
.status-option-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.dot-blue { background: #2563eb; }
.dot-green { background: #16a34a; }

.form-label { font-size: 12px; color: var(--text-secondary); display: block; margin-bottom: 4px; }
.picked-files { margin-top: 6px; display: flex; flex-wrap: wrap; gap: 6px; }
.picked-file-chip {
  font-size: 11px; padding: 3px 8px; border-radius: 4px; background: var(--surface-2);
  border: 1px solid var(--border); display: inline-flex; align-items: center; gap: 6px;
}
.picked-file-remove { border: none; background: none; cursor: pointer; color: var(--text-secondary); font-size: 13px; }
</style>
