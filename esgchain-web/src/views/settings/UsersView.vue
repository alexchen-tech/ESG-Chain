<template>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h1 class="page-title">使用者管理</h1>
        <p class="page-subtitle">共 {{ pagination.total }} 位中心廠內部使用者</p>
      </div>
      <button class="btn btn-primary" @click="openCreateModal">＋ 新增使用者</button>
    </div>

    <!-- 過濾列 -->
    <div class="filter-bar" style="margin-bottom:16px;">
      <input v-model="filters.q" class="filter-input" style="width:220px;" placeholder="搜尋姓名或 Email" @keyup.enter="resetAndLoad" />
      <select v-model="filters.role" class="filter-select" @change="resetAndLoad">
        <option value="">全部角色</option>
        <option v-for="r in ROLE_OPTIONS" :key="r.value" :value="r.value">{{ r.label }}</option>
      </select>
      <select v-model="filters.is_active" class="filter-select" @change="resetAndLoad">
        <option value="">全部狀態</option>
        <option value="true">啟用中</option>
        <option value="false">已停用</option>
      </select>
      <button class="btn btn-secondary btn-sm" @click="resetAndLoad">搜尋</button>
    </div>

    <div class="table-container">
      <div v-if="isLoading" class="loading-mask">載入中...</div>
      <div v-else-if="users.length === 0" class="empty-state">
        <p>尚無符合條件的使用者</p>
      </div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>姓名</th>
            <th>Email</th>
            <th>角色</th>
            <th>狀態</th>
            <th>建立時間</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="u in users" :key="u.id">
            <td>{{ u.name }}</td>
            <td class="font-mono">{{ u.email }}</td>
            <td>
              <span v-for="r in u.roles" :key="r" class="badge badge-blue" style="margin-right:4px;">{{ roleLabel(r) }}</span>
            </td>
            <td>
              <span class="badge" :class="u.is_active ? 'badge-green' : 'badge-gray'">{{ u.is_active ? '啟用中' : '已停用' }}</span>
            </td>
            <td>{{ formatDate(u.created_at) }}</td>
            <td style="white-space:nowrap;">
              <button class="btn btn-secondary btn-sm" @click="openRoleModal(u)">編輯角色</button>
              <button class="btn btn-secondary btn-sm" @click="openToggleModal(u)">{{ u.is_active ? '停用' : '啟用' }}</button>
              <button class="btn btn-secondary btn-sm" @click="openResetPasswordModal(u)">重設密碼</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="pagination">
      <span>第 {{ pagination.current_page }} / {{ pagination.last_page }} 頁</span>
      <button class="pg-btn" :disabled="pagination.current_page <= 1" @click="goPage(pagination.current_page - 1)">‹</button>
      <button class="pg-btn" :disabled="pagination.current_page >= pagination.last_page" @click="goPage(pagination.current_page + 1)">›</button>
    </div>

    <!-- 新增使用者 Modal -->
    <div v-if="showCreateModal" class="modal-overlay" @click.self="showCreateModal=false">
      <div class="modal" style="min-width:380px;">
        <div class="modal-header">
          <span class="modal-title">新增使用者</span>
          <button class="modal-close" @click="showCreateModal=false">×</button>
        </div>
        <div class="form-group">
          <label class="form-label">姓名</label>
          <input v-model="createForm.name" class="form-input" placeholder="請輸入姓名" />
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input v-model="createForm.email" class="form-input" placeholder="請輸入 Email" />
        </div>
        <div class="form-group">
          <label class="form-label">角色</label>
          <select v-model="createForm.role" class="form-select">
            <option v-for="r in ROLE_OPTIONS" :key="r.value" :value="r.value">{{ r.label }}</option>
          </select>
        </div>
        <p v-if="createError" class="form-hint" style="color:#ef4444;">{{ createError }}</p>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showCreateModal=false">取消</button>
          <button class="btn btn-primary" :disabled="isSubmitting" @click="doCreate">{{ isSubmitting ? '建立中...' : '建立' }}</button>
        </div>
      </div>
    </div>

    <!-- 使用者編輯 Modal：角色 / 個人權限覆寫 -->
    <div v-if="showRoleModal" class="modal-overlay" @click.self="showRoleModal=false">
      <div class="modal" style="min-width:480px;">
        <div class="modal-header">
          <span class="modal-title">編輯使用者 — {{ activeUser?.name }}</span>
          <button class="modal-close" @click="showRoleModal=false">×</button>
        </div>

        <div class="detail-tabs" style="margin-bottom:16px;">
          <button
            class="detail-tab"
            :class="{ active: editModalTab === 'role' }"
            @click="editModalTab = 'role'"
          >角色</button>
          <button
            class="detail-tab"
            :class="{ active: editModalTab === 'permissions' }"
            @click="editModalTab = 'permissions'; loadUserPermissions()"
          >個人權限覆寫</button>
        </div>

        <div v-if="editModalTab === 'role'">
          <div class="form-group">
            <label class="form-label">角色</label>
            <select v-model="roleForm.role" class="form-select">
              <option v-for="r in ROLE_OPTIONS" :key="r.value" :value="r.value">{{ r.label }}</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">變更原因</label>
            <textarea v-model="roleForm.reason" class="form-textarea" placeholder="選填：說明變更原因"></textarea>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="showRoleModal=false">取消</button>
            <button class="btn btn-primary" :disabled="isSubmitting" @click="doUpdateRoles">確認</button>
          </div>
        </div>

        <div v-else>
          <template v-if="activeUser && activeUser.roles.includes('admin')">
            <p style="font-size:0.9rem;color:var(--text-secondary);padding:12px 0;">
              admin 已固定擁有全部權限，不可個別覆寫。
            </p>
          </template>
          <template v-else>
            <div v-if="isLoadingPermissions" class="loading-mask" style="position:static;padding:20px 0;">載入中...</div>
            <template v-else>
              <div v-for="mod in permissionModules" :key="mod" class="detail-section" style="margin-bottom:10px;">
                <div class="section-title" style="font-size:13px;">{{ MODULE_LABELS[mod] || mod }}</div>
                <div v-for="perm in permissionCatalog[mod]" :key="perm.name" style="display:flex;align-items:center;gap:8px;padding:4px 0;">
                  <template v-if="userRolePermissions.includes(perm.name)">
                    <input type="checkbox" checked disabled />
                    <span class="font-mono" style="font-size:12px;">{{ perm.name }}</span>
                    <span class="badge badge-gray" style="font-size:11px;">已透過角色「{{ roleLabel(activeUser?.roles?.[0] || '') }}」取得</span>
                  </template>
                  <template v-else>
                    <input
                      type="checkbox"
                      :checked="userDirectPermissions.includes(perm.name)"
                      :disabled="isSubmittingPermission"
                      @change="togglePersonalPermission(perm.name, ($event.target as HTMLInputElement).checked)"
                    />
                    <span class="font-mono" style="font-size:12px;">{{ perm.name }}</span>
                    <span v-if="userDirectPermissions.includes(perm.name)" class="badge badge-blue" style="font-size:11px;">個人授予</span>
                  </template>
                </div>
              </div>
              <p v-if="permissionError" class="form-hint" style="color:#ef4444;">{{ permissionError }}</p>
            </template>
          </template>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="showRoleModal=false">關閉</button>
          </div>
        </div>
      </div>
    </div>

    <!-- 停用/啟用 Modal -->
    <div v-if="showToggleModal" class="modal-overlay" @click.self="showToggleModal=false">
      <div class="modal" style="min-width:380px;">
        <div class="modal-header">
          <span class="modal-title">{{ activeUser?.is_active ? '停用帳號' : '啟用帳號' }} — {{ activeUser?.name }}</span>
          <button class="modal-close" @click="showToggleModal=false">×</button>
        </div>
        <div v-if="activeUser?.is_active" class="form-group">
          <label class="form-label">原因</label>
          <textarea v-model="toggleForm.reason" class="form-textarea" placeholder="選填：說明停用原因"></textarea>
        </div>
        <p v-else style="font-size:0.9rem;color:var(--text-secondary);">確定要重新啟用此帳號嗎？</p>
        <div v-if="toggleWarnings.length" class="form-group" style="background:var(--accent-soft);border:1px solid var(--accent-soft-border);border-radius:8px;padding:10px 12px;">
          <p v-for="(w, i) in toggleWarnings" :key="i" style="font-size:0.85rem;color:var(--text-primary);margin:0;">⚠ {{ w }}</p>
        </div>
        <div class="modal-footer">
          <template v-if="toggleWarnings.length">
            <button class="btn btn-primary" @click="showToggleModal=false">知道了</button>
          </template>
          <template v-else>
            <button class="btn btn-secondary" @click="showToggleModal=false">取消</button>
            <button class="btn btn-primary" :disabled="isSubmitting" @click="doToggleActive">確認</button>
          </template>
        </div>
      </div>
    </div>

    <!-- 重設密碼 Modal -->
    <div v-if="showResetPasswordModal" class="modal-overlay" @click.self="showResetPasswordModal=false">
      <div class="modal" style="min-width:380px;">
        <div class="modal-header">
          <span class="modal-title">重設密碼 — {{ activeUser?.name }}</span>
          <button class="modal-close" @click="showResetPasswordModal=false">×</button>
        </div>
        <template v-if="!newPassword">
          <p style="font-size:0.9rem;color:var(--text-secondary);">將產生一組新密碼，請於下一步妥善轉告使用者（僅顯示一次）。</p>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="showResetPasswordModal=false">取消</button>
            <button class="btn btn-primary" :disabled="isSubmitting" @click="doResetPassword">確認重設</button>
          </div>
        </template>
        <template v-else>
          <div class="form-group">
            <label class="form-label">新密碼（僅顯示一次，請立即轉告使用者）</label>
            <div class="form-input font-mono" style="user-select:all;">{{ newPassword }}</div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-primary" @click="showResetPasswordModal=false">關閉</button>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { usersApi, type ManagedUser, type InternalRole } from '@/api/modules/users'
import { permissionsApi, type PermissionCatalog } from '@/api/modules/permissions'

const ROLE_OPTIONS: { value: InternalRole; label: string }[] = [
  { value: 'admin', label: '管理員' },
  { value: 'buyer', label: '採購商' },
  { value: 'sustain', label: '永續長' },
  { value: 'comply', label: '法遵' },
  { value: 'analyst', label: '分析師' },
]

// 依模組分組顯示個人權限覆寫用，沿用 RolesView.vue 的模組中文標籤慣例
const MODULE_LABELS: Record<string, string> = {
  users: '使用者管理',
  suppliers: '供應商管理',
  saqs: 'SAQ 審核',
  caps: 'CAP 矯正行動',
  settings: '系統設定',
  'market-compliance-rules': '市場合規規則',
  'market-definitions': '目標市場定義',
}

export default defineComponent({
  name: 'UsersView',

  data() {
    return {
      ROLE_OPTIONS,
      MODULE_LABELS,
      users: [] as ManagedUser[],
      isLoading: false,
      isSubmitting: false,
      filters: { q: '', role: '', is_active: '' },
      pagination: { current_page: 1, per_page: 20, total: 0, last_page: 1 },

      showCreateModal: false,
      createForm: { name: '', email: '', role: 'analyst' as InternalRole },
      createError: '',

      showRoleModal: false,
      roleForm: { role: 'analyst' as InternalRole, reason: '' },
      editModalTab: 'role' as 'role' | 'permissions',
      permissionCatalog: {} as PermissionCatalog,
      userRolePermissions: [] as string[],
      userDirectPermissions: [] as string[],
      isLoadingPermissions: false,
      isSubmittingPermission: false,
      permissionError: '',

      showToggleModal: false,
      toggleForm: { reason: '' },
      toggleWarnings: [] as string[],

      showResetPasswordModal: false,
      newPassword: '',

      activeUser: null as ManagedUser | null,
    }
  },

  computed: {
    permissionModules(): string[] {
      return Object.keys(this.permissionCatalog)
    },
  },

  mounted() {
    this.loadData()
  },

  methods: {
    async loadData() {
      this.isLoading = true
      try {
        const { data } = await usersApi.list({
          page: this.pagination.current_page,
          per_page: this.pagination.per_page,
          role: this.filters.role || undefined,
          is_active: this.filters.is_active || undefined,
          q: this.filters.q || undefined,
        })
        this.users = data.data
        this.pagination = data.pagination
      } finally {
        this.isLoading = false
      }
    },

    resetAndLoad() {
      this.pagination.current_page = 1
      this.loadData()
    },

    goPage(page: number) {
      this.pagination.current_page = page
      this.loadData()
    },

    roleLabel(role: string) {
      return ROLE_OPTIONS.find((r) => r.value === role)?.label || role
    },

    formatDate(d: string) {
      if (!d) return '—'
      return new Date(d).toLocaleString('zh-TW', { year: 'numeric', month: '2-digit', day: '2-digit' })
    },

    openCreateModal() {
      this.createForm = { name: '', email: '', role: 'analyst' }
      this.createError = ''
      this.showCreateModal = true
    },

    async doCreate() {
      this.isSubmitting = true
      this.createError = ''
      try {
        await usersApi.create(this.createForm)
        this.showCreateModal = false
        this.resetAndLoad()
      } catch (e: any) {
        this.createError = e?.response?.data?.message || '建立失敗'
      } finally {
        this.isSubmitting = false
      }
    },

    openRoleModal(u: ManagedUser) {
      this.activeUser = u
      this.roleForm = { role: (u.roles[0] as InternalRole) || 'analyst', reason: '' }
      this.editModalTab = 'role'
      this.userRolePermissions = []
      this.userDirectPermissions = []
      this.permissionError = ''
      this.showRoleModal = true
    },

    async loadUserPermissions() {
      if (!this.activeUser || this.activeUser.roles.includes('admin')) return
      this.isLoadingPermissions = true
      this.permissionError = ''
      try {
        const [catalogRes, userPermRes] = await Promise.all([
          permissionsApi.catalog(),
          usersApi.permissions(this.activeUser.id),
        ])
        this.permissionCatalog = catalogRes.data.data
        this.userRolePermissions = userPermRes.data.data.role_permissions
        this.userDirectPermissions = userPermRes.data.data.direct_permissions
      } catch (e: any) {
        this.permissionError = e?.response?.data?.message || '載入個人權限失敗'
      } finally {
        this.isLoadingPermissions = false
      }
    },

    async togglePersonalPermission(permission: string, checked: boolean) {
      if (!this.activeUser) return
      this.isSubmittingPermission = true
      this.permissionError = ''
      try {
        const { data } = checked
          ? await usersApi.grantPermission(this.activeUser.id, permission)
          : await usersApi.revokePermission(this.activeUser.id, permission)
        this.userRolePermissions = data.data.role_permissions
        this.userDirectPermissions = data.data.direct_permissions
      } catch (e: any) {
        this.permissionError = e?.response?.data?.message || '更新個人權限失敗'
      } finally {
        this.isSubmittingPermission = false
      }
    },

    async doUpdateRoles() {
      if (!this.activeUser) return
      this.isSubmitting = true
      try {
        await usersApi.updateRoles(this.activeUser.id, [this.roleForm.role], this.roleForm.reason || undefined)
        this.showRoleModal = false
        this.loadData()
      } finally {
        this.isSubmitting = false
      }
    },

    openToggleModal(u: ManagedUser) {
      this.activeUser = u
      this.toggleForm = { reason: '' }
      this.toggleWarnings = []
      this.showToggleModal = true
    },

    async doToggleActive() {
      if (!this.activeUser) return
      this.isSubmitting = true
      try {
        const nextActive = !this.activeUser.is_active
        const { data } = await usersApi.toggleActive(this.activeUser.id, nextActive, this.toggleForm.reason || undefined)
        this.loadData()
        if (data.warnings && data.warnings.length) {
          // 有進行中工作警告時，先讓使用者看到警告內容，不立即關閉 modal
          this.toggleWarnings = data.warnings
        } else {
          this.showToggleModal = false
        }
      } finally {
        this.isSubmitting = false
      }
    },

    openResetPasswordModal(u: ManagedUser) {
      this.activeUser = u
      this.newPassword = ''
      this.showResetPasswordModal = true
    },

    async doResetPassword() {
      if (!this.activeUser) return
      this.isSubmitting = true
      try {
        const { data } = await usersApi.resetPassword(this.activeUser.id)
        this.newPassword = data.data.new_password
      } finally {
        this.isSubmitting = false
      }
    },
  },
})
</script>
