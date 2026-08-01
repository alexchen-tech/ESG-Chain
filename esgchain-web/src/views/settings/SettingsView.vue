<template>
  <div class="page-container">
    <div class="page-header" style="margin-bottom:12px;">
      <div>
        <h1 class="page-title">系統設定</h1>
        <p class="page-subtitle">組織架構、供應商分組</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="settings-tabs">
      <button
        v-for="tab in TABS"
        :key="tab.key"
        class="settings-tab"
        :class="{ active: activeTab === tab.key }"
        @click="activeTab = tab.key"
      >{{ tab.label }}</button>
    </div>

    <!-- ══ 組織架構 ══ -->
    <div v-show="activeTab === 'org'">
      <div class="tab-action-bar">
        <button class="btn btn-primary" @click="openCreateOuModal(null)">+ 新增組織單位</button>
      </div>
      <div v-if="ouLoading" class="loading-mask">載入中...</div>
      <div v-else-if="orgTree.length === 0" class="empty-state">
        <p>尚無組織單位，請建立根節點（母公司）</p>
        <button class="btn btn-primary" style="margin-top:12px;" @click="openCreateOuModal(null)">+ 建立根節點</button>
      </div>
      <div v-else class="table-container" style="padding:0;">
        <OrgUnitNode
          v-for="node in orgTree" :key="node.id" :unit="node"
          @edit="openEditOuModal" @delete="confirmDeleteOu" @add-child="openCreateOuModal"
        />
      </div>
    </div>

    <!-- 新增組織單位 Modal -->
    <div v-if="showCreateOuModal" class="modal-overlay" @click.self="showCreateOuModal=false">
      <div class="modal" style="min-width:420px;">
        <div class="modal-header">
          <span class="modal-title">{{ ouForm.parent_id ? '新增子單位' : '新增組織單位' }}</span>
          <button class="modal-close" @click="showCreateOuModal=false">×</button>
        </div>
        <div class="form-group"><label class="form-label">名稱 *</label><input v-model="ouForm.name" class="form-input" placeholder="台灣採購事業部" /></div>
        <div class="form-group"><label class="form-label">代碼 *</label><input v-model="ouForm.code" class="form-input" placeholder="TW-BU" maxlength="32" /></div>
        <div class="form-group">
          <label class="form-label">類型 *</label>
          <select v-model="ouForm.type" class="form-select">
            <option value="headquarters">母公司</option><option value="subsidiary">子公司</option>
            <option value="business_unit">事業部</option><option value="department">部門</option><option value="branch">分支/辦公室</option>
          </select>
        </div>
        <div v-if="ouForm.type !== 'headquarters'" class="form-group">
          <label class="form-label">上層單位 *</label>
          <select v-model="ouForm.parent_id" class="form-select">
            <option value="">請選擇上層單位</option>
            <option v-for="u in flatUnitsForParent" :key="u.id" :value="u.id">{{ '　'.repeat(u.depth - 1) }}{{ u.name }} ({{ u.code }})</option>
          </select>
        </div>
        <div class="form-group"><label class="form-label">國家/地區碼</label><CountrySelect v-model="ouForm.country_code" /></div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showCreateOuModal=false">取消</button>
          <button class="btn btn-primary" :disabled="isSubmitting" @click="createOu">{{ isSubmitting ? '建立中...' : '建立' }}</button>
        </div>
      </div>
    </div>
    <div v-if="editingOu" class="modal-overlay" @click.self="editingOu=null">
      <div class="modal" style="min-width:420px;">
        <div class="modal-header"><span class="modal-title">編輯組織單位</span><button class="modal-close" @click="editingOu=null">×</button></div>
        <div class="form-group"><label class="form-label">名稱 *</label><input v-model="editingOu.name" class="form-input" /></div>
        <div class="form-group"><label class="form-label">代碼 *</label><input v-model="editingOu.code" class="form-input" maxlength="32" /></div>
        <div class="form-group"><label class="form-label">國家/地區碼</label><CountrySelect v-model="editingOu.country_code" /></div>
        <div class="form-group">
          <label class="form-label">狀態</label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;"><input type="checkbox" v-model="editingOu.is_active" /><span>啟用</span></label>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="editingOu=null">取消</button>
          <button class="btn btn-primary" :disabled="isSubmitting" @click="updateOu">{{ isSubmitting ? '儲存中...' : '儲存' }}</button>
        </div>
      </div>
    </div>
    <div v-if="deleteTargetOu" class="modal-overlay" @click.self="deleteTargetOu=null">
      <div class="modal" style="max-width:380px;text-align:center;">
        <div class="modal-header"><span class="modal-title">確認刪除</span></div>
        <p style="margin:16px 0;color:var(--text-secondary);">確定要刪除「{{ deleteTargetOu.name }}」？此操作無法復原。</p>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="deleteTargetOu=null">取消</button>
          <button class="btn btn-danger" :disabled="isSubmitting" @click="deleteOu">確認刪除</button>
        </div>
      </div>
    </div>

    <!-- ══ 供應商分組 ══ -->
    <div v-show="activeTab === 'groups'">
      <div class="tab-action-bar">
        <button class="btn btn-primary" @click="openCreateGroupModal">+ 新增分組</button>
      </div>
      <div class="table-container">
        <div v-if="groupsLoading" class="loading-mask">載入中...</div>
        <div v-else-if="groups.length === 0" class="empty-state"><p>尚無供應商分組</p></div>
        <table v-else class="data-table">
          <thead>
            <tr>
              <th style="width:180px;">分組名稱</th>
              <th>說明</th>
              <th style="width:320px;">廠商文件要求</th>
              <th style="width:120px;">操作</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="g in groups" :key="g.id">
              <td style="font-weight:600;">{{ g.name }}</td>
              <td style="font-size:13px;color:var(--text-secondary);">{{ g.description || '—' }}</td>
              <td>
                <div style="display:flex;flex-wrap:wrap;gap:6px;">
                  <span v-if="!g.required_doc_types?.length" style="color:var(--text-secondary);font-size:13px;">—</span>
                  <span v-for="dt in g.required_doc_types" :key="dt" class="doc-chip">{{ docTypeLabel(dt) }}</span>
                </div>
              </td>
              <td>
                <div style="display:flex;gap:6px;">
                  <button class="btn btn-secondary btn-sm" @click="openEditGroupModal(g)">編輯</button>
                  <button class="btn btn-danger btn-sm" @click="confirmDeleteGroup(g)">刪除</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 新增分組 Modal -->
      <div v-if="showCreateGroupModal" class="modal-overlay" @click.self="showCreateGroupModal=false">
        <div class="modal" style="min-width:440px;">
          <div class="modal-header">
            <span class="modal-title">新增供應商分組</span>
            <button class="modal-close" @click="showCreateGroupModal=false">×</button>
          </div>
          <div class="form-group"><label class="form-label">分組名稱 *</label><input v-model="groupForm.name" class="form-input" placeholder="T1 關鍵供應商" /></div>
          <div class="form-group"><label class="form-label">說明</label><textarea v-model="groupForm.description" class="form-textarea" placeholder="選填說明"></textarea></div>
          <div class="form-group">
            <label class="form-label">廠商文件要求</label>
            <p style="font-size:12px;color:var(--text-secondary);margin-bottom:8px;">此分組供應商需提供的資質文件</p>
            <div class="checkbox-grid">
              <label v-for="opt in VENDOR_DOC_TYPE_OPTIONS" :key="opt.value" class="checkbox-item">
                <input type="checkbox" :value="opt.value" v-model="groupForm.required_doc_types" />
                <span>{{ opt.label }}</span>
              </label>
            </div>
            <div v-if="groupForm.required_doc_types.length" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:10px;">
              <span v-for="dt in groupForm.required_doc_types" :key="dt" class="doc-chip">{{ docTypeLabel(dt) }}</span>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="showCreateGroupModal=false">取消</button>
            <button class="btn btn-primary" :disabled="isSubmitting" @click="createGroup">{{ isSubmitting ? '建立中...' : '建立' }}</button>
          </div>
        </div>
      </div>

      <!-- 編輯分組 Modal -->
      <div v-if="editingGroup" class="modal-overlay" @click.self="editingGroup=null">
        <div class="modal" style="min-width:440px;">
          <div class="modal-header">
            <span class="modal-title">編輯供應商分組</span>
            <button class="modal-close" @click="editingGroup=null">×</button>
          </div>
          <div class="form-group"><label class="form-label">分組名稱 *</label><input v-model="editingGroup.name" class="form-input" /></div>
          <div class="form-group"><label class="form-label">說明</label><textarea v-model="editingGroup.description" class="form-textarea"></textarea></div>
          <div class="form-group">
            <label class="form-label">廠商文件要求</label>
            <div class="checkbox-grid">
              <label v-for="opt in VENDOR_DOC_TYPE_OPTIONS" :key="opt.value" class="checkbox-item">
                <input type="checkbox" :value="opt.value" v-model="editingGroup.required_doc_types" />
                <span>{{ opt.label }}</span>
              </label>
            </div>
            <div v-if="editingGroup.required_doc_types.length" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:10px;">
              <span v-for="dt in editingGroup.required_doc_types" :key="dt" class="doc-chip">{{ docTypeLabel(dt) }}</span>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="editingGroup=null">取消</button>
            <button class="btn btn-primary" :disabled="isSubmitting" @click="saveGroup">{{ isSubmitting ? '儲存中...' : '儲存' }}</button>
          </div>
        </div>
      </div>

      <!-- 刪除分組 Modal -->
      <div v-if="deleteTargetGroup" class="modal-overlay" @click.self="deleteTargetGroup=null">
        <div class="modal" style="max-width:380px;text-align:center;">
          <div class="modal-header"><span class="modal-title">確認刪除</span></div>
          <p style="margin:16px 0;color:var(--text-secondary);">確定要刪除分組「{{ deleteTargetGroup.name }}」？</p>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="deleteTargetGroup=null">取消</button>
            <button class="btn btn-danger" :disabled="isSubmitting" @click="deleteGroup">確認刪除</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import {
  settingsApi, orgUnitsApi,
  type SupplierGroup, type OrgUnit, type OrgUnitType
} from '@/api/modules/settings'
import OrgUnitNode from '@/components/common/OrgUnitNode.vue'
import CountrySelect from '@/components/common/CountrySelect.vue'

const TABS = [
  { key: 'org',             label: '組織架構' },
  { key: 'groups',          label: '供應商分組' },
]

const VENDOR_DOC_TYPE_OPTIONS = [
  { value: 'SMETA_AUDIT',   label: 'SMETA 稽核' },
  { value: 'ISO_9001',      label: 'ISO 9001' },
  { value: 'FACTORY_AUDIT', label: '工廠稽核' },
  { value: 'HIGG_FEM',      label: 'Higg FEM' },
  { value: 'OEKO_TEX',      label: 'OEKO-TEX' },
  { value: 'BSCI',          label: 'BSCI' },
  { value: 'ZDHC_MRSL',     label: 'ZDHC MRSL' },
]

const MATERIAL_DOC_TYPE_OPTIONS = [
  { value: 'UFLPA',        label: 'UFLPA' },
  { value: 'REACH_SDS',    label: 'REACH SDS' },
  { value: 'ROHS',         label: 'RoHS' },
  { value: 'ORIGIN_CERT',  label: '產地證明' },
  { value: 'MATERIAL_TEST',label: '物性測試' },
  { value: 'CMRT',         label: 'CMRT 衝突礦產' },
]

const VENDOR_LABEL: Record<string, string> = Object.fromEntries(VENDOR_DOC_TYPE_OPTIONS.map(o => [o.value, o.label]))

export default defineComponent({
  name: 'SettingsView',
  components: { OrgUnitNode, CountrySelect },

  data() {
    return {
      TABS,
      VENDOR_DOC_TYPE_OPTIONS,
      activeTab: 'org',
      isSubmitting: false,

      // 組織架構
      orgTree: [] as OrgUnit[],
      flatUnits: [] as OrgUnit[],
      ouLoading: false,
      showCreateOuModal: false,
      ouForm: { name: '', code: '', type: 'headquarters' as OrgUnitType, parent_id: '' as string | null, country_code: 'TW' },
      editingOu: null as OrgUnit | null,
      deleteTargetOu: null as OrgUnit | null,

      // 供應商分組
      groups: [] as SupplierGroup[],
      groupsLoading: false,
      showCreateGroupModal: false,
      groupForm: { name: '', description: '', required_doc_types: [] as string[] },
      editingGroup: null as (SupplierGroup & { required_doc_types: string[] }) | null,
      deleteTargetGroup: null as SupplierGroup | null,
    }
  },

  computed: {
    flatUnitsForParent(): OrgUnit[] { return this.flatUnits.filter(u => u.depth < 4) },
  },

  mounted() {
    this.loadOrgTree()
    this.loadGroups()
  },

  methods: {
    docTypeLabel(v: string) { return VENDOR_LABEL[v] ?? v },

    // ── 組織架構 ──
    async loadOrgTree() {
      this.ouLoading = true
      try {
        const [t, l] = await Promise.all([orgUnitsApi.tree(), orgUnitsApi.list()])
        this.orgTree = t.data.data; this.flatUnits = l.data.data
      } finally { this.ouLoading = false }
    },
    openCreateOuModal(parent: OrgUnit | null) {
      this.ouForm = { name: '', code: '', type: parent ? 'department' : 'headquarters', parent_id: parent?.id ?? null, country_code: 'TW' }
      this.showCreateOuModal = true
    },
    async createOu() {
      if (!this.ouForm.name || !this.ouForm.code) return
      this.isSubmitting = true
      try {
        await orgUnitsApi.create({ name: this.ouForm.name, code: this.ouForm.code.toUpperCase(), type: this.ouForm.type, parent_id: this.ouForm.type === 'headquarters' ? null : (this.ouForm.parent_id || null), country_code: this.ouForm.country_code.toUpperCase() || 'TW' })
        this.showCreateOuModal = false; await this.loadOrgTree()
      } catch (e: any) { alert(e?.response?.data?.message ?? '建立失敗') } finally { this.isSubmitting = false }
    },
    openEditOuModal(unit: OrgUnit) { this.editingOu = { ...unit } },
    async updateOu() {
      if (!this.editingOu) return
      this.isSubmitting = true
      try {
        await orgUnitsApi.update(this.editingOu.id, { name: this.editingOu.name, code: this.editingOu.code, country_code: this.editingOu.country_code, is_active: this.editingOu.is_active })
        this.editingOu = null; await this.loadOrgTree()
      } catch (e: any) { alert(e?.response?.data?.message ?? '更新失敗') } finally { this.isSubmitting = false }
    },
    confirmDeleteOu(unit: OrgUnit) { this.deleteTargetOu = unit },
    async deleteOu() {
      if (!this.deleteTargetOu) return
      this.isSubmitting = true
      try { await orgUnitsApi.remove(this.deleteTargetOu.id); this.deleteTargetOu = null; await this.loadOrgTree() }
      catch (e: any) { alert(e?.response?.data?.message ?? '刪除失敗') } finally { this.isSubmitting = false }
    },

    // ── 供應商分組 ──
    async loadGroups() {
      this.groupsLoading = true
      try { const { data } = await settingsApi.groups.list(); this.groups = data.data } finally { this.groupsLoading = false }
    },
    openCreateGroupModal() { this.groupForm = { name: '', description: '', required_doc_types: [] }; this.showCreateGroupModal = true },
    async createGroup() {
      if (!this.groupForm.name) return
      this.isSubmitting = true
      try { await settingsApi.groups.create({ name: this.groupForm.name, description: this.groupForm.description, required_doc_types: this.groupForm.required_doc_types }); this.showCreateGroupModal = false; await this.loadGroups() }
      finally { this.isSubmitting = false }
    },
    openEditGroupModal(g: SupplierGroup) { this.editingGroup = { ...g, required_doc_types: [...(g.required_doc_types ?? [])] } },
    async saveGroup() {
      if (!this.editingGroup) return
      this.isSubmitting = true
      try { await settingsApi.groups.update(this.editingGroup.id, { name: this.editingGroup.name, description: this.editingGroup.description ?? undefined, required_doc_types: this.editingGroup.required_doc_types }); this.editingGroup = null; await this.loadGroups() }
      finally { this.isSubmitting = false }
    },
    confirmDeleteGroup(g: SupplierGroup) { this.deleteTargetGroup = g },
    async deleteGroup() {
      if (!this.deleteTargetGroup) return
      this.isSubmitting = true
      try { await settingsApi.groups.delete(this.deleteTargetGroup.id); this.deleteTargetGroup = null; await this.loadGroups() }
      finally { this.isSubmitting = false }
    },
  },
})
</script>

<style scoped>
/* Tab bar */
.settings-tabs { display: flex; gap: 0; border-bottom: 1px solid var(--border); margin-bottom: 20px; flex-wrap: wrap; }
.settings-tab { padding: 10px 18px; border: none; background: none; cursor: pointer; font-size: 14px; color: var(--text-secondary); border-bottom: 2px solid transparent; margin-bottom: -1px; transition: color 0.15s; white-space: nowrap; }
.settings-tab:hover { color: var(--text-primary); }
.settings-tab.active { color: var(--accent); border-bottom-color: var(--accent); font-weight: 600; }

/* Action bar above table */
.tab-action-bar { display: flex; justify-content: flex-end; margin-bottom: 12px; }

/* Doc type chips */
.doc-chip { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 500; background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; white-space: nowrap; }

/* HS Code chip */
.hs-chip { font-size: 11px; background: var(--surface-2); color: var(--text-secondary); padding: 2px 6px; border-radius: 4px; border: 1px solid var(--border); }

/* Checkboxes */
.checkbox-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 8px; }
.checkbox-item { display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; padding: 2px 0; }
.checkbox-item input[type="checkbox"] { width: 14px; height: 14px; accent-color: var(--accent); cursor: pointer; }
</style>
