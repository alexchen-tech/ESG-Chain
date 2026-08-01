<template>
  <div class="section-card">
    <div class="section-header">
      <div>
        <h2 class="section-title">角色管理</h2>
        <p class="section-desc">依模組管理各角色的操作權限，admin 角色固定擁有全部權限，不可調整</p>
      </div>
    </div>

    <div v-if="isLoading" class="loading-mask">載入中...</div>

    <template v-else>
      <div v-for="mod in modules" :key="mod" class="module-section">
        <div class="module-header" @click="toggleModule(mod)">
          <span class="module-chevron" :class="{ open: openModules.includes(mod) }">›</span>
          <span class="module-name">{{ MODULE_LABELS[mod] || mod }}</span>
          <span class="module-count">{{ catalog[mod].length }} 項權限</span>
        </div>

        <div v-show="openModules.includes(mod)" class="module-body">
          <table class="data-table matrix-table">
            <thead>
              <tr>
                <th style="width:40%;">權限</th>
                <th v-for="r in EDITABLE_ROLES" :key="r.value" style="text-align:center;">{{ r.label }}</th>
                <th style="text-align:center;">admin</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="perm in catalog[mod]" :key="perm.name">
                <td>
                  <div class="font-mono" style="font-size:12.5px;">{{ perm.name }}</div>
                  <div style="font-size:12px;color:var(--text-secondary);">{{ perm.description }}</div>
                </td>
                <td v-for="r in EDITABLE_ROLES" :key="r.value" style="text-align:center;">
                  <input
                    type="checkbox"
                    :checked="hasPermission(r.value, perm.name)"
                    :disabled="isSubmitting"
                    @change="togglePermission(r.value, perm.name, ($event.target as HTMLInputElement).checked)"
                  />
                </td>
                <td style="text-align:center;color:var(--text-secondary);" title="admin 角色固定擁有全部權限，不可調整">✓（固定）</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <p v-if="saveError" class="form-hint" style="color:#ef4444;margin-top:12px;">{{ saveError }}</p>
    </template>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { permissionsApi, type PermissionCatalog } from '@/api/modules/permissions'

const EDITABLE_ROLES: { value: string; label: string }[] = [
  { value: 'buyer', label: '採購商' },
  { value: 'sustain', label: '永續長' },
  { value: 'comply', label: '法遵' },
  { value: 'analyst', label: '分析師' },
]

const MODULE_LABELS: Record<string, string> = {
  users: '使用者管理',
  suppliers: '供應商管理',
  saqs: 'SAQ 審核',
  'assessment-series': '評核系列',
  'saq-projects': 'SAQ 問卷專案',
  caps: 'CAP 矯正行動',
  settings: '系統設定',
  'market-compliance-rules': '市場合規規則',
  'market-definitions': '目標市場定義',
}

export default defineComponent({
  name: 'RolesView',

  data() {
    return {
      EDITABLE_ROLES,
      MODULE_LABELS,
      catalog: {} as PermissionCatalog,
      // roleName -> permission[] 目前擁有的權限
      rolePermissions: {} as Record<string, string[]>,
      openModules: [] as string[],
      isLoading: false,
      isSubmitting: false,
      saveError: '',
    }
  },

  computed: {
    modules(): string[] {
      return Object.keys(this.catalog)
    },
  },

  mounted() {
    this.loadData()
  },

  methods: {
    async loadData() {
      this.isLoading = true
      try {
        const [catalogRes, ...roleResList] = await Promise.all([
          permissionsApi.catalog(),
          ...EDITABLE_ROLES.map((r) => permissionsApi.rolePermissions(r.value)),
        ])
        this.catalog = catalogRes.data.data
        this.openModules = Object.keys(this.catalog)

        const map: Record<string, string[]> = {}
        EDITABLE_ROLES.forEach((r, i) => {
          map[r.value] = roleResList[i].data.data.permissions
        })
        this.rolePermissions = map
      } finally {
        this.isLoading = false
      }
    },

    toggleModule(mod: string) {
      const idx = this.openModules.indexOf(mod)
      if (idx >= 0) this.openModules.splice(idx, 1)
      else this.openModules.push(mod)
    },

    hasPermission(role: string, permission: string): boolean {
      return (this.rolePermissions[role] || []).includes(permission)
    },

    async togglePermission(role: string, permission: string, checked: boolean) {
      const current = this.rolePermissions[role] || []
      const next = checked
        ? [...current, permission]
        : current.filter((p) => p !== permission)

      // 樂觀更新，失敗時回滾
      const prev = this.rolePermissions[role]
      this.rolePermissions = { ...this.rolePermissions, [role]: next }
      this.isSubmitting = true
      this.saveError = ''
      try {
        const { data } = await permissionsApi.updateRolePermissions(role, next)
        this.rolePermissions = { ...this.rolePermissions, [role]: data.data.permissions }
      } catch (e: any) {
        this.rolePermissions = { ...this.rolePermissions, [role]: prev }
        this.saveError = e?.response?.data?.message || '更新失敗'
      } finally {
        this.isSubmitting = false
      }
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

.module-section {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  margin-bottom: 12px;
  overflow: hidden;
}
.module-header {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px;
  cursor: pointer;
  background: var(--surface-2);
}
.module-header:hover { background: var(--accent-soft); }
.module-chevron { font-size: 14px; transition: transform 0.15s; display: inline-block; color: var(--text-secondary); }
.module-chevron.open { transform: rotate(90deg); }
.module-name { font-weight: 600; font-size: 14px; }
.module-count { margin-left: auto; font-size: 12px; color: var(--text-secondary); }
.module-body { padding: 0; }
.matrix-table th { white-space: nowrap; }
</style>
