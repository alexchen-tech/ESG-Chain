<template>
  <div class="page-container">
    <div class="breadcrumb">
      <span class="breadcrumb-parent">物料管理</span>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current">物料群組</span>
    </div>
    <div class="page-header">
      <div>
        <h1 class="page-title">物料群組</h1>
        <p class="page-subtitle">管理材料分類、HS Code 前綴與合規文件需求</p>
      </div>
      <button class="btn btn-primary" @click="openCreateModal">＋ 新增物料群組</button>
    </div>

    <div class="card" style="padding:0;overflow:hidden;">
      <div v-if="loading" class="loading-mask" style="padding:48px;text-align:center;">載入中...</div>
      <div v-else-if="materialGroups.length === 0" class="empty-state"><p>尚無物料群組</p></div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th style="width:180px;padding-left:20px;">群組名稱</th>
            <th>說明</th>
            <th style="width:220px;">HS Code 前綴</th>
            <th style="width:220px;">合規文件類型</th>
            <th style="width:80px;">系統</th>
            <th style="width:120px;">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="mg in materialGroups" :key="mg.id">
            <td style="font-weight:600;padding-left:20px;">{{ mg.name }}</td>
            <td style="font-size:13px;color:var(--text-secondary);">{{ mg.description || '—' }}</td>
            <td>
              <div style="display:flex;flex-wrap:wrap;gap:4px;">
                <span v-if="!mg.hs_code_prefixes?.length" style="color:var(--text-secondary);font-size:13px;">—</span>
                <span v-for="p in mg.hs_code_prefixes" :key="p" class="hs-chip font-mono">{{ p }}</span>
              </div>
            </td>
            <td>
              <div style="display:flex;flex-wrap:wrap;gap:4px;">
                <span v-if="!mg.required_doc_types?.length" style="color:var(--text-secondary);font-size:13px;">—</span>
                <span v-for="dt in mg.required_doc_types" :key="dt" class="badge badge-gray" style="font-size:11px;">{{ dt }}</span>
              </div>
            </td>
            <td>
              <span v-if="mg.is_system" class="badge badge-green" style="font-size:11px;">系統</span>
              <span v-else style="color:var(--text-secondary);font-size:13px;">—</span>
            </td>
            <td>
              <div style="display:flex;gap:6px;">
                <button class="btn btn-secondary btn-sm" @click="openEditModal(mg)">編輯</button>
                <button v-if="!mg.is_system" class="btn btn-danger btn-sm" @click="deleteTarget = mg">刪除</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 新增/編輯 Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal" style="min-width:480px;">
        <div class="modal-header">
          <span class="modal-title">{{ form.id ? '編輯物料群組' : '新增物料群組' }}</span>
          <button class="modal-close" @click="showModal = false">×</button>
        </div>
        <div class="form-group">
          <label class="form-label">群組名稱 *</label>
          <input v-model="form.name" class="form-input" placeholder="棉紡原料" />
        </div>
        <div class="form-group">
          <label class="form-label">說明</label>
          <textarea v-model="form.description" class="form-textarea" placeholder="選填說明"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">HS Code 前綴</label>
          <p style="font-size:12px;color:var(--text-secondary);margin-bottom:6px;">輸入前綴後按 Enter 新增</p>
          <div style="display:flex;gap:6px;margin-bottom:8px;">
            <input v-model="hsInput" class="form-input" placeholder="5208" style="flex:1;" @keyup.enter="addHsCode" />
            <button class="btn btn-secondary btn-sm" @click="addHsCode">新增</button>
          </div>
          <div style="display:flex;flex-wrap:wrap;gap:6px;">
            <span v-for="(p, idx) in form.hs_code_prefixes" :key="p" class="hs-chip font-mono" style="cursor:pointer;" @click="form.hs_code_prefixes.splice(idx, 1)" title="點擊移除">{{ p }} ×</span>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">合規文件類型</label>
          <div class="checkbox-grid">
            <label v-for="opt in DOC_TYPE_OPTIONS" :key="opt.value" class="checkbox-item">
              <input type="checkbox" :value="opt.value" v-model="form.required_doc_types" />
              <span>{{ opt.label }}</span>
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showModal = false">取消</button>
          <button class="btn btn-primary" :disabled="submitting" @click="save">{{ submitting ? '儲存中...' : (form.id ? '儲存' : '建立') }}</button>
        </div>
      </div>
    </div>

    <!-- 刪除確認 Modal -->
    <div v-if="deleteTarget" class="modal-overlay" @click.self="deleteTarget = null">
      <div class="modal" style="max-width:380px;text-align:center;">
        <div class="modal-header"><span class="modal-title">確認刪除</span></div>
        <p style="margin:16px 0;color:var(--text-secondary);">確定要刪除「{{ deleteTarget.name }}」？</p>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="deleteTarget = null">取消</button>
          <button class="btn btn-danger" :disabled="submitting" @click="remove">確認刪除</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { materialGroupApi, type MaterialGroup } from '@/api/modules/compliance'

const DOC_TYPE_OPTIONS = [
  { value: 'EUDR_DDS', label: 'EUDR 盡職調查聲明' },
  { value: 'UFLPA_DECLARATION', label: 'UFLPA 聲明' },
  { value: 'CMRT', label: 'CMRT 衝突礦產報告' },
  { value: 'SDS', label: 'SDS 安全資料表' },
  { value: 'CE_DOC', label: 'CE 合規文件' },
]

export default defineComponent({
  name: 'MaterialGroupsView',
  data() {
    return {
      DOC_TYPE_OPTIONS,
      loading: false,
      submitting: false,
      materialGroups: [] as MaterialGroup[],
      showModal: false,
      form: { id: '', name: '', description: '', hs_code_prefixes: [] as string[], required_doc_types: [] as string[] },
      hsInput: '',
      deleteTarget: null as MaterialGroup | null,
    }
  },
  mounted() { this.load() },
  methods: {
    async load() {
      this.loading = true
      try { const { data } = await materialGroupApi.list(); this.materialGroups = data.data } finally { this.loading = false }
    },
    openCreateModal() {
      this.form = { id: '', name: '', description: '', hs_code_prefixes: [], required_doc_types: [] }
      this.hsInput = ''
      this.showModal = true
    },
    openEditModal(mg: MaterialGroup) {
      this.form = { id: mg.id, name: mg.name, description: mg.description ?? '', hs_code_prefixes: [...(mg.hs_code_prefixes ?? [])], required_doc_types: [...(mg.required_doc_types ?? [])] }
      this.hsInput = ''
      this.showModal = true
    },
    addHsCode() {
      const v = this.hsInput.trim()
      if (v && !this.form.hs_code_prefixes.includes(v)) this.form.hs_code_prefixes.push(v)
      this.hsInput = ''
    },
    async save() {
      if (!this.form.name) return
      this.submitting = true
      try {
        const p = { name: this.form.name, description: this.form.description || null, hs_code_prefixes: this.form.hs_code_prefixes, required_doc_types: this.form.required_doc_types }
        this.form.id ? await materialGroupApi.update(this.form.id, p) : await materialGroupApi.create(p)
        this.showModal = false
        await this.load()
      } finally { this.submitting = false }
    },
    async remove() {
      if (!this.deleteTarget) return
      this.submitting = true
      try { await (materialGroupApi as any).destroy(this.deleteTarget.id); this.deleteTarget = null; await this.load() } finally { this.submitting = false }
    },
  },
})
</script>

<style scoped>
.hs-chip {
  display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 4px;
  font-size: 12px; background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);
}
.checkbox-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
.checkbox-item { display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; }
.empty-state { padding: 64px; text-align: center; color: var(--text-secondary); }
</style>
