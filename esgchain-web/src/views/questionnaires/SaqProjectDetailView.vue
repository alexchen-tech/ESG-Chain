<template>
  <div class="page-container">
    <!-- 麵包屑 -->
    <div class="breadcrumb">
      <button class="breadcrumb-link" @click="router.push('/questionnaires/projects')">問卷專案</button>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current">{{ project?.name ?? '載入中...' }}</span>
    </div>

    <div v-if="isLoading" class="loading-mask">載入中...</div>
    <div v-else-if="!project" class="empty-state"><p>找不到此專案</p></div>
    <template v-else>
      <!-- Header -->
      <div class="page-header">
        <div>
          <div style="display:flex;align-items:center;gap:10px;">
            <h1 class="page-title">{{ project.name }}</h1>
            <span class="badge" :class="statusBadge(project.status)">{{ statusLabel(project.status) }}</span>
            <span v-if="project.template?.scoring_framework" class="badge badge-gray" style="font-size:11px;">{{ project.template?.scoring_framework }}</span>
          </div>
          <p class="page-subtitle">
            範本：{{ project.template?.name ?? '—' }}
            <span v-if="project.due_date"> · 截止：{{ project.due_date.slice(0,10) }}</span>
          </p>
        </div>
        <div style="display:flex;gap:8px;">
          <button
            v-if="project.status !== 'closed'"
            class="btn btn-secondary"
            @click="openWeightModal"
          >⚖ 題目權重</button>
          <button
            v-if="project.status !== 'closed'"
            class="btn btn-secondary"
            @click="openEditModal"
          >✏ 編輯</button>
          <button
            v-if="project.status !== 'closed'"
            class="btn btn-primary"
            @click="openSendModal"
          >發送給供應商</button>
          <button
            v-if="project.status === 'active'"
            class="btn btn-secondary"
            @click="showCloseConfirm = true"
          >結案</button>
        </div>
      </div>

      <!-- 進度摘要卡片 -->
      <div class="summary-cards">
        <div class="summary-card">
          <div class="summary-num font-mono">{{ saqs.length }}</div>
          <div class="summary-label">已發送</div>
        </div>
        <div class="summary-card">
          <div class="summary-num font-mono">{{ submittedCount }}</div>
          <div class="summary-label">已回覆</div>
        </div>
        <div class="summary-card">
          <div class="summary-num font-mono">{{ pendingCount }}</div>
          <div class="summary-label">待回覆</div>
        </div>
        <div class="summary-card" :class="{ 'card-warn': overdueCount > 0 }">
          <div class="summary-num font-mono">{{ overdueCount }}</div>
          <div class="summary-label">逾期</div>
        </div>
      </div>

      <!-- SAQ 列表 -->
      <div class="card" style="padding:0;margin-top:16px;">
        <div v-if="saqs.length === 0" class="empty-state" style="padding:40px;">
          <p>尚未發送任何問卷，點擊「發送給供應商」開始</p>
        </div>
        <table v-else class="data-table" style="table-layout:fixed;width:100%;">
          <colgroup>
            <col>
            <col style="width:72px;">
            <col style="width:88px;">
            <col style="width:96px;">
            <col style="width:96px;">
            <col style="width:88px;">
            <col style="width:72px;">
          </colgroup>
          <thead>
            <tr>
              <th>供應商</th>
              <th style="white-space:nowrap;">層級</th>
              <th style="white-space:nowrap;">狀態</th>
              <th style="white-space:nowrap;">發送時間</th>
              <th style="white-space:nowrap;">提交時間</th>
              <th style="white-space:nowrap;">分數</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="saq in saqs" :key="saq.id">
              <td style="overflow:hidden;">
                <div style="font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" :title="maskSupplierName(saq.supplier?.name)">{{ saq.supplier?.name ? maskSupplierName(saq.supplier.name) : '—' }}</div>
                <div style="font-size:12px;color:var(--text-secondary);">{{ saq.supplier?.code ?? '' }}</div>
              </td>
              <td style="white-space:nowrap;">Tier {{ saq.supplier?.tier ?? '—' }}</td>
              <td style="white-space:nowrap;"><span class="badge" :class="saqStatusBadge(saq.status)">{{ saqStatusLabel(saq.status) }}</span></td>
              <td class="font-mono" style="font-size:12px;white-space:nowrap;">{{ saq.sent_at?.slice(0,10) ?? '—' }}</td>
              <td class="font-mono" style="font-size:12px;white-space:nowrap;">{{ saq.submitted_at?.slice(0,10) ?? '—' }}</td>
              <td class="num font-mono" style="white-space:nowrap;">
                {{ saq.score != null ? saq.score.toFixed(1) : '—' }}
                <span v-if="saq.grade" class="badge badge-blue" style="margin-left:2px;font-size:10px;">{{ saq.grade }}</span>
              </td>
              <td style="white-space:nowrap;">
                <router-link
                  v-if="['submitted','under_review','review_returned','completed','reviewed'].includes(saq.status)"
                  :to="`/questionnaires/review/${saq.id}`"
                  class="btn btn-secondary btn-sm"
                >審核</router-link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- 發送 Modal -->
    <div v-if="showSendModal" class="modal-overlay" @click.self="showSendModal = false">
      <div class="modal modal-wide">
        <div class="modal-header">
          <span class="modal-title">發送給供應商</span>
          <button class="modal-close" @click="showSendModal = false">×</button>
        </div>

        <!-- Tab -->
        <div class="send-tabs">
          <button class="send-tab" :class="{ active: sendTab === 'group' }" @click="sendTab = 'group'">選擇群組</button>
          <button class="send-tab" :class="{ active: sendTab === 'search' }" @click="sendTab = 'search'">搜尋供應商</button>
        </div>

        <!-- 群組 Tab -->
        <div v-if="sendTab === 'group'" class="send-panel">
          <div v-if="groupsLoading" class="loading-mask" style="padding:24px;">載入中...</div>
          <div v-else-if="supplierGroups.length === 0" class="empty-inline">尚無供應商分組</div>
          <div v-else class="group-list">
            <div v-for="g in supplierGroups" :key="g.id" class="group-item">
              <div class="group-header" @click="toggleGroup(g.id)">
                <input
                  type="checkbox"
                  :checked="isGroupAllSelected(g.id)"
                  :indeterminate="isGroupPartial(g.id)"
                  @change="toggleGroupAll(g.id)"
                  @click.stop
                />
                <span style="font-weight:500;margin-left:8px;">{{ g.name }}</span>
                <span style="font-size:12px;color:var(--text-secondary);margin-left:6px;">({{ groupSuppliers(g.id).length }}家)</span>
                <span class="expand-icon">{{ expandedGroups.includes(g.id) ? '▾' : '▸' }}</span>
              </div>
              <div v-if="expandedGroups.includes(g.id)" class="group-suppliers">
                <label
                  v-for="s in groupSuppliers(g.id)"
                  :key="s.id"
                  class="supplier-row"
                  :class="{ 'already-sent': isSent(s.id) }"
                >
                  <input type="checkbox" :value="s.id" v-model="selectedIds" :disabled="isSent(s.id)" />
                  <span style="margin-left:8px;">{{ maskSupplierName(s.name) }}</span>
                  <span style="font-size:12px;color:var(--text-secondary);margin-left:6px;">{{ s.code }}</span>
                  <span v-if="isSent(s.id)" class="already-badge">已發送</span>
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- 搜尋 Tab -->
        <div v-if="sendTab === 'search'" class="send-panel">
          <input
            v-model="searchKeyword"
            class="form-input"
            placeholder="搜尋供應商名稱或代碼..."
            style="margin-bottom:12px;"
            @input="debounceSearch"
          />
          <div class="group-list">
            <div v-if="searchLoading" class="empty-inline">搜尋中...</div>
            <div v-else-if="searchResults.length === 0 && searchKeyword" class="empty-inline">無符合結果</div>
            <label
              v-for="s in searchResults"
              :key="s.id"
              class="supplier-row"
              :class="{ 'already-sent': isSent(s.id) }"
            >
              <input type="checkbox" :value="s.id" v-model="selectedIds" :disabled="isSent(s.id)" />
              <span style="margin-left:8px;">{{ maskSupplierName(s.name) }}</span>
              <span style="font-size:12px;color:var(--text-secondary);margin-left:6px;">{{ s.code }} · Tier {{ s.tier }}</span>
              <span v-if="isSent(s.id)" class="already-badge">已發送</span>
            </label>
          </div>
        </div>

        <!-- 六維模組預覽提示 -->
        <div v-if="selectedIds.length > 0" class="module-preview-hint">
          <span class="module-preview-icon">🧩</span>
          <span>核心模組：<strong>E1 ESG整體、E4 地緣風險</strong>（全體必選）</span>
          <span style="margin-left:8px;color:var(--text-secondary);font-size:11px;">其餘維度依供應商產業分類自動加掛，E6 另依適用法規篩題</span>
        </div>

        <div class="modal-footer" style="justify-content:space-between;">
          <span style="font-size:13px;color:var(--text-secondary);">
            {{ selectedIds.length > 0 ? `已選 ${selectedIds.length} 家` : '請選擇供應商' }}
          </span>
          <div style="display:flex;gap:8px;">
            <button class="btn btn-secondary" @click="showSendModal = false">取消</button>
            <button class="btn btn-primary" :disabled="!selectedIds.length || isSending" @click="doSend">
              {{ isSending ? '發送中...' : `確認發送（${selectedIds.length}）` }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- 編輯 Modal -->
    <div v-if="showEditModal" class="modal-overlay" @click.self="showEditModal = false">
      <div class="modal" style="min-width:480px;max-width:560px;">
        <div class="modal-header">
          <span class="modal-title">編輯專案資訊</span>
          <button class="modal-close" @click="showEditModal = false">×</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">專案名稱 <span style="color:#dc2626;">*</span></label>
            <input v-model="editForm.name" class="form-input" maxlength="120" placeholder="請輸入專案名稱" />
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group">
              <label class="form-label">開始日期</label>
              <input v-model="editForm.start_date" type="date" class="form-input" />
            </div>
            <div class="form-group">
              <label class="form-label">截止日期</label>
              <input v-model="editForm.due_date" type="date" class="form-input" />
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">評核框架</label>
            <div class="form-input" style="background:#f5f5f4;color:#6b7280;cursor:not-allowed;">
              {{ project?.template?.scoring_framework ?? '通用' }}
              <span style="font-size:11px;margin-left:6px;color:#9ca3af;">（由範本決定，不可修改）</span>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">說明</label>
            <textarea v-model="editForm.description" class="form-input" rows="3" placeholder="專案說明（選填）" />
          </div>
        </div>
        <div class="modal-footer" style="justify-content:space-between;">
          <button
            v-if="project?.status === 'draft'"
            class="btn btn-danger"
            :disabled="isDeleting"
            @click="doDelete"
          >{{ isDeleting ? '刪除中...' : '刪除專案' }}</button>
          <div v-else></div>
          <div style="display:flex;gap:8px;">
            <button class="btn btn-secondary" @click="showEditModal = false">取消</button>
            <button class="btn btn-primary" :disabled="!editForm.name.trim() || isSaving" @click="doSave">
              {{ isSaving ? '儲存中...' : '儲存變更' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- 題目權重 Modal -->
    <div v-if="showWeightModal" class="modal-overlay" @click.self="showWeightModal = false">
      <div class="modal modal-weight">
        <div class="modal-header">
          <span class="modal-title">題目權重設定</span>
          <button class="modal-close" @click="showWeightModal = false">×</button>
        </div>
        <div v-if="weightLoading" style="padding:32px;text-align:center;color:var(--text-secondary);">載入中...</div>
        <div v-else>
          <div style="padding:12px 0 4px;font-size:12px;color:var(--text-secondary);">
            輸入各題目的相對重要性（數值，後端自動正規化加總為 100%）。
            <span v-if="hasSubmittedSaqs" style="color:#d97706;margin-left:6px;">⚠ 本專案有已提交問卷，儲存後將自動重新計分。</span>
          </div>
          <div class="weight-table-wrap">
            <table class="data-table" style="table-layout:fixed;width:100%;">
              <colgroup>
                <col style="width:40px;">
                <col>
                <col style="width:100px;">
                <col style="width:80px;">
              </colgroup>
              <thead>
                <tr>
                  <th>#</th>
                  <th>題目</th>
                  <th style="white-space:nowrap;">相對值</th>
                  <th style="white-space:nowrap;text-align:right;">佔比</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(q, i) in weightDraft" :key="q.id">
                  <td class="num" style="color:var(--text-secondary);">{{ q.order }}</td>
                  <td style="font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" :title="q.question_text">{{ q.question_text }}</td>
                  <td>
                    <input
                      type="number"
                      v-model.number="q.rawWeight"
                      min="0"
                      step="1"
                      class="form-input weight-input font-mono"
                      @input="recalcPercent"
                    />
                  </td>
                  <td class="num font-mono" style="font-size:13px;text-align:right;">
                    {{ weightPercent(q.rawWeight) }}%
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="2" style="font-size:12px;color:var(--text-secondary);">合計</td>
                  <td class="num font-mono" style="font-weight:600;">{{ weightTotal }}</td>
                  <td class="num font-mono" style="font-weight:600;text-align:right;">100%</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
        <div class="modal-footer" style="justify-content:flex-end;">
          <button class="btn btn-secondary" @click="showWeightModal = false">取消</button>
          <button class="btn btn-primary" :disabled="weightLoading || isSavingWeights || weightTotal <= 0" @click="doSaveWeights">
            {{ isSavingWeights ? '儲存中...' : '儲存權重' }}
          </button>
        </div>
      </div>
    </div>

    <!-- 結案確認 Modal -->
    <div v-if="showCloseConfirm" class="modal-overlay" @click.self="showCloseConfirm = false">
      <div class="modal" style="min-width:360px;text-align:center;">
        <div class="modal-header"><span class="modal-title">確認結案</span></div>
        <p style="margin:20px 0;color:var(--text-secondary);">
          結案後將無法再發送問卷。<br>確定要將「{{ project?.name }}」結案嗎？
        </p>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showCloseConfirm = false">取消</button>
          <button class="btn btn-danger" :disabled="isClosing" @click="doClose">
            {{ isClosing ? '結案中...' : '確認結案' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { saqProjectApi, type SaqProject, type SAQ, type ProjectQuestion } from '@/api/modules/saq'
import { suppliersApi, supplierGroupsApi, type Supplier, type SupplierGroup } from '@/api/modules/suppliers'
import { SAQ_STATUS_LABEL, SAQ_STATUS_BADGE } from '@/utils/saqStatus'
import { maskSupplierName } from '@/utils/maskName'

export default defineComponent({
  name: 'SaqProjectDetailView',
  setup() { return { router: useRouter(), route: useRoute() } },

  data() {
    return {
      isLoading: false,
      isSending: false,
      isClosing: false,
      project: null as SaqProject | null,
      saqs: [] as SAQ[],
      // Send modal
      showSendModal: false,
      sendTab: 'group' as 'group' | 'search',
      selectedIds: [] as string[],
      supplierGroups: [] as SupplierGroup[],
      groupSuppliersMap: {} as Record<string, Supplier[]>,
      groupsLoading: false,
      expandedGroups: [] as string[],
      allSuppliers: [] as Supplier[],
      searchKeyword: '',
      searchResults: [] as Supplier[],
      searchLoading: false,
      searchTimer: null as ReturnType<typeof setTimeout> | null,
      // Close modal
      showCloseConfirm: false,
      // Edit modal
      showEditModal: false,
      isSaving: false,
      isDeleting: false,
      editForm: { name: '', start_date: '', due_date: '', description: '' },
      // Weight modal
      showWeightModal: false,
      weightLoading: false,
      isSavingWeights: false,
      weightDraft: [] as Array<ProjectQuestion & { rawWeight: number }>,
    }
  },

  computed: {
    submittedCount(): number {
      return this.saqs.filter(s => ['submitted', 'completed', 'reviewed', 'under_review'].includes(s.status)).length
    },
    pendingCount(): number {
      return this.saqs.filter(s => s.status === 'sent').length
    },
    overdueCount(): number {
      if (!this.project?.due_date) return 0
      const due = new Date(this.project.due_date)
      return this.saqs.filter(s => s.status === 'sent' && new Date() > due).length
    },
    sentSupplierIds(): string[] {
      return this.saqs.map(s => s.supplier_id)
    },
    weightTotal(): number {
      return this.weightDraft.reduce((s, q) => s + (q.rawWeight || 0), 0)
    },
    hasSubmittedSaqs(): boolean {
      return this.saqs.some(s => ['submitted', 'under_review', 'review_returned'].includes(s.status))
    },
  },

  mounted() { this.loadAll() },

  methods: {
    maskSupplierName,
    async loadAll() {
      this.isLoading = true
      const id = this.route.params.id as string
      try {
        const [pRes, saqRes] = await Promise.all([
          saqProjectApi.get(id),
          saqProjectApi.saqs(id),
        ])
        this.project = pRes.data.data
        this.saqs = saqRes.data.data
      } catch { this.project = null } finally { this.isLoading = false }
    },

    async openSendModal() {
      this.selectedIds = []
      this.sendTab = 'group'
      this.searchKeyword = ''
      this.searchResults = []
      this.showSendModal = true
      await this.loadGroupsAndSuppliers()
    },

    async loadGroupsAndSuppliers() {
      this.groupsLoading = true
      try {
        const [gRes, sRes] = await Promise.all([
          supplierGroupsApi.list(),
          suppliersApi.list({ per_page: 500 }),
        ])
        this.supplierGroups = gRes.data.data
        this.allSuppliers = sRes.data.data

        const map: Record<string, Supplier[]> = {}
        for (const g of this.supplierGroups) {
          map[g.id] = this.allSuppliers.filter(s => s.group_id === g.id)
        }
        this.groupSuppliersMap = map
      } finally { this.groupsLoading = false }
    },

    groupSuppliers(groupId: string): Supplier[] {
      return this.groupSuppliersMap[groupId] ?? []
    },

    isSent(supplierId: string): boolean {
      return this.sentSupplierIds.includes(supplierId)
    },

    isGroupAllSelected(groupId: string): boolean {
      const selectable = this.groupSuppliers(groupId).filter(s => !this.isSent(s.id))
      if (!selectable.length) return false
      return selectable.every(s => this.selectedIds.includes(s.id))
    },

    isGroupPartial(groupId: string): boolean {
      const selectable = this.groupSuppliers(groupId).filter(s => !this.isSent(s.id))
      return selectable.some(s => this.selectedIds.includes(s.id)) && !this.isGroupAllSelected(groupId)
    },

    toggleGroup(groupId: string) {
      const idx = this.expandedGroups.indexOf(groupId)
      if (idx >= 0) this.expandedGroups.splice(idx, 1)
      else this.expandedGroups.push(groupId)
    },

    toggleGroupAll(groupId: string) {
      const selectable = this.groupSuppliers(groupId).filter(s => !this.isSent(s.id)).map(s => s.id)
      if (this.isGroupAllSelected(groupId)) {
        this.selectedIds = this.selectedIds.filter(id => !selectable.includes(id))
      } else {
        this.selectedIds = [...new Set([...this.selectedIds, ...selectable])]
      }
    },

    debounceSearch() {
      if (this.searchTimer) clearTimeout(this.searchTimer)
      this.searchTimer = setTimeout(() => this.doSearch(), 300)
    },

    doSearch() {
      const kw = this.searchKeyword.trim().toLowerCase()
      if (!kw) { this.searchResults = []; return }
      this.searchResults = this.allSuppliers.filter(s =>
        s.name.toLowerCase().includes(kw) || (s.code ?? '').toLowerCase().includes(kw)
      ).slice(0, 30)
    },

    async doSend() {
      if (!this.selectedIds.length || !this.project) return
      this.isSending = true
      try {
        const { data } = await saqProjectApi.send(this.project.id, this.selectedIds)
        this.showSendModal = false
        alert(`已發送 ${data.created} 家${data.skipped > 0 ? `，跳過 ${data.skipped} 家（已發送過）` : ''}`)
        await this.loadAll()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '發送失敗')
      } finally { this.isSending = false }
    },

    async doClose() {
      if (!this.project) return
      this.isClosing = true
      try {
        const { data } = await saqProjectApi.close(this.project.id)
        this.project = data.data
        this.showCloseConfirm = false
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '結案失敗')
      } finally { this.isClosing = false }
    },

    openEditModal() {
      if (!this.project) return
      this.editForm = {
        name: this.project.name ?? '',
        start_date: (this.project as any).start_date?.slice(0, 10) ?? '',
        due_date: this.project.due_date?.slice(0, 10) ?? '',
        description: this.project.description ?? '',
      }
      this.showEditModal = true
    },

    async doSave() {
      if (!this.project || !this.editForm.name.trim()) return
      this.isSaving = true
      try {
        const payload: Record<string, any> = {
          name: this.editForm.name.trim(),
          start_date: this.editForm.start_date || null,
          due_date: this.editForm.due_date || null,
          description: this.editForm.description || null,
        }
        const { data } = await saqProjectApi.update(this.project.id, payload)
        this.project = data.data
        this.showEditModal = false
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '儲存失敗')
      } finally { this.isSaving = false }
    },

    async doDelete() {
      if (!this.project) return
      if (!window.confirm(`確定要刪除專案「${this.project.name}」？此操作無法復原。`)) return
      this.isDeleting = true
      try {
        await saqProjectApi.remove(this.project.id)
        this.showEditModal = false
        this.router.push('/questionnaires/projects')
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '刪除失敗')
      } finally { this.isDeleting = false }
    },

    async openWeightModal() {
      if (!this.project) return
      this.weightLoading = true
      this.showWeightModal = true
      try {
        const { data } = await saqProjectApi.questions(this.project.id)
        this.weightDraft = data.data.map(q => ({
          ...q,
          rawWeight: q.weight != null ? Math.round(q.weight * 1000) : 1,
        }))
      } finally {
        this.weightLoading = false
      }
    },

    recalcPercent() { /* reactive — computed handles it */ },

    weightPercent(raw: number): string {
      if (!this.weightTotal) return '0.0'
      return ((raw / this.weightTotal) * 100).toFixed(1)
    },

    async doSaveWeights() {
      if (!this.project || this.weightTotal <= 0) return
      this.isSavingWeights = true
      try {
        const weights = this.weightDraft.map(q => ({ id: q.id, weight: q.rawWeight || 0 }))
        const { data } = await saqProjectApi.updateWeights(this.project.id, weights)
        this.showWeightModal = false
        alert(data.message)
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '儲存失敗')
      } finally {
        this.isSavingWeights = false
      }
    },

    statusLabel(s: string) {
      return ({ draft: '草稿', active: '進行中', closed: '已結案' })[s] ?? s
    },

    statusBadge(s: string) {
      return ({ draft: 'badge-gray', active: 'badge-blue', closed: 'badge-green' })[s] ?? 'badge-gray'
    },

    saqStatusLabel: (s: string) => SAQ_STATUS_LABEL[s] ?? s,
    saqStatusBadge: (s: string) => SAQ_STATUS_BADGE[s] ?? 'badge-gray',

  },
})
</script>

<style scoped>

.breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 13px; margin-bottom: 16px; }
.breadcrumb-link { background: none; border: none; color: var(--accent); cursor: pointer; padding: 0; font-size: 13px; }
.breadcrumb-link:hover { text-decoration: underline; }
.breadcrumb-sep { color: var(--text-secondary); }
.breadcrumb-current { color: var(--text-primary); font-weight: 500; }

.summary-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 8px; }
.summary-card {
  background: var(--surface); border: 1px solid var(--border); border-radius: 8px;
  padding: 16px; text-align: center;
}
.summary-card.card-warn { border-color: #fca5a5; background: #fff5f5; }
.summary-num { font-size: 28px; font-weight: 700; color: var(--accent); }
.summary-label { font-size: 12px; color: var(--text-secondary); margin-top: 4px; }

.modal-wide { min-width: 580px; max-width: 700px; }

.send-tabs { display: flex; border-bottom: 1px solid var(--border); margin-bottom: 12px; }
.send-tab {
  background: none; border: none; cursor: pointer;
  padding: 8px 16px; font-size: 14px; color: var(--text-secondary);
  border-bottom: 2px solid transparent; margin-bottom: -1px;
}
.send-tab.active { color: var(--accent); border-bottom-color: var(--accent); font-weight: 500; }

.send-panel { max-height: 320px; overflow-y: auto; }

.group-list { display: flex; flex-direction: column; gap: 4px; }
.group-item { border: 1px solid var(--border); border-radius: 6px; overflow: hidden; }
.group-header {
  display: flex; align-items: center; padding: 10px 12px; background: var(--surface-2);
  cursor: pointer; user-select: none;
}
.group-header:hover { background: var(--border); }
.expand-icon { margin-left: auto; color: var(--text-secondary); }
.group-suppliers { padding: 4px 0; }

.supplier-row {
  display: flex; align-items: center; padding: 7px 16px; cursor: pointer;
  border-top: 1px solid var(--border);
}
.supplier-row:hover { background: var(--surface-2); }
.supplier-row.already-sent { opacity: 0.6; cursor: default; }

.already-badge {
  margin-left: auto; font-size: 11px; padding: 2px 8px;
  background: #e0f2fe; color: #0369a1; border-radius: 99px;
}
.module-preview-hint {
  margin: 8px 0 4px;
  padding: 8px 12px;
  background: rgba(26,77,62,0.06);
  border-radius: 6px;
  font-size: 12px;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}
.module-preview-icon { font-size: 14px; }

.empty-inline { padding: 20px; text-align: center; color: var(--text-secondary); font-size: 13px; }

.modal-body { display: flex; flex-direction: column; gap: 14px; padding: 4px 0 8px; }
.form-group { display: flex; flex-direction: column; gap: 4px; }
.form-label { font-size: 13px; font-weight: 500; color: var(--text-primary); }
.lock-hint { font-size: 11px; font-weight: 400; color: var(--text-secondary); margin-left: 6px; }

.modal-weight { min-width: 620px; max-width: 720px; }
.weight-table-wrap { max-height: 400px; overflow-y: auto; margin-top: 8px; }
.weight-input { padding: 4px 8px; height: 30px; width: 80px; text-align: right; }
</style>
