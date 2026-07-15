<template>
  <div class="page-container" @click="groupDropOpen = false">
    <div class="breadcrumb">
      <button class="breadcrumb-link" @click="$router.push('/materials/items')">物料管理</button>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current">物料主檔</span>
    </div>

    <div class="page-header">
      <div>
        <h1 class="page-title">物料主檔</h1>
        <p class="page-subtitle">共 {{ total }} 筆料號</p>
      </div>
      <div style="display:flex;gap:8px;">
        <button class="btn btn-secondary" @click="$router.push('/materials/items/import')">↑ CSV 批次匯入</button>
        <button class="btn btn-primary" @click="triggerCsvImport">＋ 新增料號</button>
      </div>
    </div>

    <!-- 篩選 -->
    <div class="filter-bar">
      <input
        v-model="searchQuery"
        class="filter-input"
        placeholder="搜尋料號或品名..."
        style="width:220px;"
        @input="onSearchInput"
      />
      <div class="group-filter-wrap" style="position:relative;" @click.stop>
        <button class="filter-select group-filter-btn" style="cursor:pointer;" @click.stop="groupDropOpen = !groupDropOpen">
          {{ selectedGroupIds.length ? `群組：${selectedGroupIds.length} 個` : '所有群組' }} ▾
        </button>
        <div v-if="groupDropOpen" class="group-drop">
          <label v-for="g in materialGroups" :key="g.id" class="group-drop-item">
            <input type="checkbox" :value="g.id" v-model="selectedGroupIds" @change="loadItems(1)" />
            {{ g.name }}
          </label>
          <div v-if="selectedGroupIds.length" class="group-drop-clear" @click="selectedGroupIds=[]; groupDropOpen=false; loadItems(1)">清除篩選</div>
        </div>
      </div>
      <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;user-select:none;">
        <input type="checkbox" v-model="activeOnly" @change="loadItems(1)" />
        <span>僅顯示啟用</span>
      </label>
      <button v-if="selectedGroupIds.length || activeOnly || searchQuery" class="btn btn-secondary btn-sm" @click="searchQuery=''; selectedGroupIds=[]; activeOnly=false; loadItems(1)">✕ 清除</button>
    </div>

    <!-- 清單 -->
    <div class="table-container">
      <div v-if="isLoading" class="loading-mask">載入中...</div>
      <div v-else-if="items.length === 0" class="empty-state" style="padding:60px 0;">
        <p style="font-size:15px;font-weight:600;margin-bottom:6px;">尚無料號資料</p>
        <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px;">料號代碼僅可透過 CSV 匯入或 ERP 同步建立，請使用上方「CSV 匯入」</p>
        <button class="btn btn-primary" @click="triggerCsvImport">+ 新增第一筆料號（CSV 匯入）</button>
      </div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th class="num" style="width:40px;">#</th>
            <th style="width:140px;">料號代碼</th>
            <th>品名</th>
            <th style="width:110px;">HS Code</th>
            <th style="width:100px;">物料群組</th>
            <th style="width:48px;">單位</th>
            <th style="width:90px;">回收成分</th>
            <th style="width:60px;">狀態</th>
            <th style="width:160px;">操作</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="(item, idx) in items" :key="item.id">
            <tr :class="{ 'row-inactive': !item.is_active }">
              <td class="num" style="color:var(--text-secondary);font-size:12px;">{{ (currentPage - 1) * perPage + idx + 1 }}</td>
              <td>
                <span class="font-mono" style="font-size:12px;font-weight:700;color:var(--accent);">{{ item.item_code }}</span>
              </td>
              <td>
                <div style="font-weight:500;font-size:14px;">{{ item.name }}</div>
                <div v-if="item.description" class="row-desc">{{ item.description }}</div>
              </td>
              <td>
                <span v-if="item.hs_code" class="hs-chip font-mono">{{ item.hs_code }}</span>
                <span v-else style="color:var(--text-secondary);">—</span>
              </td>
              <td>
                <span v-if="item.material_group" class="badge badge-gray" style="font-size:11px;">{{ item.material_group.name }}</span>
                <span v-else style="color:var(--text-secondary);">—</span>
              </td>
              <td class="font-mono" style="font-size:13px;color:var(--text-secondary);">{{ item.unit || '—' }}</td>
              <td>
                <div style="display:flex;flex-direction:column;gap:3px;align-items:flex-start;">
                  <span v-if="item.pcr_percentage && item.pcr_percentage > 0" class="pcr-badge">PCR {{ item.pcr_percentage }}%</span>
                  <span v-if="item.pir_percentage && item.pir_percentage > 0" class="pir-badge">PIR {{ item.pir_percentage }}%</span>
                  <span v-if="!item.pcr_percentage && !item.pir_percentage" style="color:var(--text-secondary);font-size:12px;">—</span>
                </div>
              </td>
              <td>
                <span class="badge" :class="item.is_active ? 'badge-green' : 'badge-gray'" style="font-size:11px;">
                  {{ item.is_active ? '啟用' : '停用' }}
                </span>
              </td>
              <td>
                <div class="action-cell">
                  <button class="btn btn-secondary btn-sm" @click="$router.push(`/materials/items/${item.id}`)">詳情</button>
                  <button class="btn btn-secondary btn-sm" @click="openEditModal(item)">編輯</button>
                  <button class="btn btn-danger btn-sm" :disabled="isSubmitting" @click="confirmDestroy(item)">✕</button>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- 分頁 -->
    <div v-if="totalPages > 1" class="pagination">
      <span>第 {{ currentPage }} / {{ totalPages }} 頁</span>
      <button class="pg-btn" :disabled="currentPage <= 1" @click="loadItems(currentPage - 1)">‹</button>
      <button class="pg-btn" :disabled="currentPage >= totalPages" @click="loadItems(currentPage + 1)">›</button>
    </div>

    <!-- 編輯 Modal（料號代碼僅可透過 CSV 匯入或 ERP 同步建立，此處僅供編輯既有料號） -->
    <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
      <div class="modal" style="min-width:520px;">
        <div class="modal-header">
          <span class="modal-title">編輯料號</span>
          <button class="modal-close" @click="closeModal">×</button>
        </div>

        <div style="display:flex;gap:12px;">
          <div class="form-group" style="flex:0 0 140px;">
            <label class="form-label">料號代碼</label>
            <input
              v-model="form.item_code"
              class="form-input font-mono"
              disabled
              style="background:var(--surface-2);color:var(--text-secondary);"
            />
            <p style="font-size:11px;color:var(--text-secondary);margin-top:4px;">僅可透過 CSV 匯入或 ERP 同步建立/變更</p>
          </div>
          <div class="form-group" style="flex:1;">
            <label class="form-label">品名 *</label>
            <input v-model="form.name" class="form-input" placeholder="棉質面料 32S" />
          </div>
        </div>

        <div style="display:flex;gap:12px;">
          <div class="form-group" style="flex:1;">
            <label class="form-label">HS Code</label>
            <input
              v-model="form.hs_code"
              class="form-input font-mono"
              placeholder="52081100"
              @blur="inferMaterialGroup"
            />
            <p v-if="hsInferredGroup" style="font-size:11px;color:var(--accent);margin-top:4px;font-weight:500;">
              ✦ 建議群組：{{ hsInferredGroup.name }}
            </p>
          </div>
          <div class="form-group" style="flex:1;">
            <label class="form-label">物料群組 *</label>
            <select v-model="form.material_group_id" class="form-select">
              <option value="">請選擇物料群組</option>
              <option v-for="mg in materialGroups" :key="mg.id" :value="mg.id">{{ mg.name }}</option>
            </select>
          </div>
          <div class="form-group" style="flex:0 0 90px;">
            <label class="form-label">計量單位</label>
            <input v-model="form.unit" class="form-input" placeholder="KG" />
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">說明</label>
          <textarea v-model="form.description" class="form-textarea" rows="2" placeholder="選填物料規格、備注..."></textarea>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px;" class="form-group">
          <div>
            <label class="form-label">淨重（kg/unit）</label>
            <input v-model.number="form.net_weight" class="form-input font-mono" type="number" min="0" step="0.0001" placeholder="選填" />
          </div>
          <div>
            <label class="form-label">PCR（%）</label>
            <input v-model.number="form.pcr_percentage" class="form-input font-mono" type="number" min="0" max="100" step="0.01" placeholder="消費後回收" />
          </div>
          <div>
            <label class="form-label">PIR（%）</label>
            <input v-model.number="form.pir_percentage" class="form-input font-mono" type="number" min="0" max="100" step="0.01" placeholder="製程廢料" />
          </div>
          <div>
            <label class="form-label">Bio-based（%）</label>
            <input v-model.number="form.bio_based_percentage" class="form-input font-mono" type="number" min="0" max="100" step="0.01" placeholder="生物基" />
          </div>
        </div>
        <div class="form-group" style="max-width:200px;">
          <label class="form-label">可回收性評級</label>
          <select v-model="form.recyclability_rating" class="form-select">
            <option value="">未設定</option>
            <option value="high">高（易回收）</option>
            <option value="medium">中</option>
            <option value="low">低（難回收）</option>
            <option value="not_rated">未評估</option>
          </select>
        </div>

        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;">
            <input type="checkbox" v-model="form.is_active" />
            <span>啟用此料號（停用後不可在 BOM 中選取）</span>
          </label>
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary" @click="closeModal">取消</button>
          <button class="btn btn-primary" :disabled="isSubmitting" @click="saveItem">
            {{ isSubmitting ? '儲存中...' : '儲存變更' }}
          </button>
        </div>
      </div>
    </div>

    <!-- 刪除/停用確認 -->
    <div v-if="deleteTarget" class="modal-overlay" @click.self="deleteTarget=null">
      <div class="modal" style="max-width:400px;">
        <div class="modal-header">
          <span class="modal-title">{{ deleteHasBomLines ? '料號使用中' : '確認刪除' }}</span>
        </div>
        <template v-if="deleteHasBomLines">
          <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px 14px;margin-bottom:4px;font-size:13px;color:#92400e;">
            料號 <strong class="font-mono">{{ deleteTarget.item_code }}</strong> 已被
            <strong>{{ deleteBomLineCount }}</strong> 筆 BOM 料件引用，無法直接刪除。<br/>
            <span style="margin-top:6px;display:block;">建議改為「停用」，停用後不顯示於 BOM 下拉，現有引用仍保留。</span>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="deleteTarget=null">取消</button>
            <button class="btn btn-warning" :disabled="isSubmitting" @click="deactivateItem">{{ isSubmitting ? '處理中...' : '改為停用' }}</button>
          </div>
        </template>
        <template v-else>
          <p style="margin:16px 0;color:var(--text-secondary);font-size:13px;">
            確定刪除料號 <strong class="font-mono">{{ deleteTarget.item_code }}</strong>「{{ deleteTarget.name }}」？此操作無法復原。
          </p>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="deleteTarget=null">取消</button>
            <button class="btn btn-danger" :disabled="isSubmitting" @click="destroyItem">{{ isSubmitting ? '刪除中...' : '確認刪除' }}</button>
          </div>
        </template>
      </div>
    </div>

    <!-- CSV 匯入結果 -->
    <div v-if="importResult" class="modal-overlay" @click.self="importResult=null">
      <div class="modal" style="max-width:420px;">
        <div class="modal-header">
          <span class="modal-title">CSV 匯入完成</span>
          <button class="modal-close" @click="importResult=null">×</button>
        </div>
        <div style="display:flex;justify-content:center;gap:40px;padding:24px 0 8px;">
          <div style="text-align:center;">
            <div class="font-mono" style="font-size:40px;font-weight:700;color:var(--accent);line-height:1;">{{ importResult.created }}</div>
            <div style="font-size:12px;color:var(--text-secondary);margin-top:6px;">新增</div>
          </div>
          <div style="width:1px;background:var(--border);"></div>
          <div style="text-align:center;">
            <div class="font-mono" style="font-size:40px;font-weight:700;color:#d97706;line-height:1;">{{ importResult.updated }}</div>
            <div style="font-size:12px;color:var(--text-secondary);margin-top:6px;">更新</div>
          </div>
        </div>
        <div v-if="importResult.warnings.length" style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;margin-top:12px;">
          <p style="font-size:12px;font-weight:600;color:#92400e;margin-bottom:6px;">{{ importResult.warnings.length }} 筆警告</p>
          <div style="max-height:150px;overflow-y:auto;">
            <p v-for="(w, i) in importResult.warnings" :key="i" style="font-size:12px;color:#92400e;margin:3px 0;">{{ w }}</p>
          </div>
        </div>
        <p v-else style="font-size:13px;color:var(--text-secondary);margin-top:12px;text-align:center;">所有資料已成功匯入，無警告。</p>
        <div class="modal-footer">
          <button class="btn btn-primary" @click="importResult=null">確認</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { materialItemApi, materialGroupApi, type MaterialItem, type MaterialGroup } from '@/api/modules/compliance'

let searchTimer: ReturnType<typeof setTimeout> | null = null

export default defineComponent({
  name: 'MaterialItemsView',


  data() {
    return {
      items: [] as MaterialItem[],
      materialGroups: [] as MaterialGroup[],
      isLoading: false,
      isSubmitting: false,

      searchQuery: '',
      activeOnly: false,
      selectedGroupIds: [] as string[],
      groupDropOpen: false,
      currentPage: 1,
      perPage: 20,
      total: 0,

      showModal: false,
      form: {
        id: '',
        item_code: '',
        name: '',
        hs_code: '',
        material_group_id: '',
        unit: '',
        description: '',
        is_active: true,
        net_weight: null as number | null,
        pcr_percentage: null as number | null,
        pir_percentage: null as number | null,
        bio_based_percentage: null as number | null,
        recyclability_rating: '' as string,
      },
      hsInferredGroup: null as MaterialGroup | null,

      deleteTarget: null as MaterialItem | null,
      deleteHasBomLines: false,
      deleteBomLineCount: 0,

      importResult: null as { created: number; updated: number; warnings: string[] } | null,
    }
  },

  computed: {
    totalPages(): number {
      return Math.ceil(this.total / this.perPage)
    },

  },

  mounted() {
    this.loadItems(1)
    this.loadMaterialGroups()
  },

  methods: {
    async loadItems(page: number) {
      this.isLoading = true
      this.currentPage = page
      try {
        const { data } = await materialItemApi.list({
          search: this.searchQuery || undefined,
          active_only: this.activeOnly || undefined,
          material_group_ids: this.selectedGroupIds.length ? this.selectedGroupIds.join(',') : undefined,
          page,
          per_page: this.perPage,
        })
        const result = data.data
        this.items = result.data
        this.total = result.total
      } finally {
        this.isLoading = false
      }
    },

    onSearchInput() {
      if (searchTimer) clearTimeout(searchTimer)
      searchTimer = setTimeout(() => this.loadItems(1), 350)
    },

    async loadMaterialGroups() {
      const { data } = await materialGroupApi.list()
      this.materialGroups = data.data
    },

    downloadTemplate() {
      const headers = ['item_code', 'name', 'hs_code', 'material_group_name', 'unit', 'net_weight', 'pcr_percentage', 'pir_percentage', 'bio_based_percentage', 'recyclability_rating', 'description']
      const sample  = ['RAW-COT-001', '精梳棉 32S', '52051100', '棉紡原料', 'kg', '1.00', '0', '0', '0', 'low', '精梳棉紗，適用於高支棉織物']
      const bom = '﻿' + headers.join(',') + '\n' + sample.join(',') + '\n'
      const blob = new Blob([bom], { type: 'text/csv;charset=utf-8;' })
      const url  = URL.createObjectURL(blob)
      const a    = document.createElement('a')
      a.href = url
      a.download = 'material_item_import_template.csv'
      a.click()
      URL.revokeObjectURL(url)
    },

    triggerCsvImport() {
      (this.$refs.csvFileInput as HTMLInputElement)?.click()
    },

    openEditModal(item: MaterialItem) {
      this.form = {
        id: item.id,
        item_code: item.item_code,
        name: item.name,
        hs_code: item.hs_code ?? '',
        material_group_id: item.material_group_id ?? '',
        unit: item.unit ?? '',
        description: item.description ?? '',
        is_active: item.is_active,
        net_weight: item.net_weight ?? null,
        pcr_percentage: item.pcr_percentage ?? null,
        pir_percentage: item.pir_percentage ?? null,
        bio_based_percentage: item.bio_based_percentage ?? null,
        recyclability_rating: item.recyclability_rating ?? '',
      }
      this.hsInferredGroup = null
      this.showModal = true
    },

    closeModal() { this.showModal = false },

    inferMaterialGroup() {
      const hs = this.form.hs_code.trim()
      if (!hs) { this.hsInferredGroup = null; return }
      const matched = this.materialGroups.find(mg =>
        (mg.hs_code_prefixes ?? []).some(p => hs.startsWith(p))
      )
      this.hsInferredGroup = matched ?? null
      if (matched && !this.form.material_group_id) {
        this.form.material_group_id = matched.id
      }
    },

    async saveItem() {
      if (!this.form.name) return
      this.isSubmitting = true
      try {
        const payload = {
          name: this.form.name,
          hs_code: this.form.hs_code || null,
          material_group_id: this.form.material_group_id || null,
          unit: this.form.unit || null,
          description: this.form.description || null,
          is_active: this.form.is_active,
          net_weight: this.form.net_weight,
          pcr_percentage: this.form.pcr_percentage,
          pir_percentage: this.form.pir_percentage,
          bio_based_percentage: this.form.bio_based_percentage,
          recyclability_rating: this.form.recyclability_rating || null,
        }
        await materialItemApi.update(this.form.id, payload)
        this.showModal = false
        await this.loadItems(this.currentPage)
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '儲存失敗')
      } finally {
        this.isSubmitting = false
      }
    },

    async confirmDestroy(item: MaterialItem) {
      this.deleteTarget = item
      this.deleteHasBomLines = false
      this.deleteBomLineCount = 0
      try {
        await materialItemApi.destroy(item.id)
        this.deleteTarget = null
        await this.loadItems(this.currentPage)
      } catch (e: any) {
        if (e?.response?.status === 422 && e?.response?.data?.bom_line_count) {
          this.deleteHasBomLines = true
          this.deleteBomLineCount = e.response.data.bom_line_count
        } else {
          alert(e?.response?.data?.message ?? '刪除失敗')
          this.deleteTarget = null
        }
      }
    },

    async destroyItem() {
      if (!this.deleteTarget) return
      this.isSubmitting = true
      try {
        await materialItemApi.destroy(this.deleteTarget.id)
        this.deleteTarget = null
        await this.loadItems(this.currentPage)
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '刪除失敗')
      } finally { this.isSubmitting = false }
    },

    async deactivateItem() {
      if (!this.deleteTarget) return
      this.isSubmitting = true
      try {
        await materialItemApi.update(this.deleteTarget.id, { is_active: false })
        this.deleteTarget = null
        await this.loadItems(this.currentPage)
      } finally { this.isSubmitting = false }
    },

    async handleImport(event: Event) {
      const file = (event.target as HTMLInputElement).files?.[0]
      if (!file) return
      try {
        const { data } = await materialItemApi.import(file)
        this.importResult = data.data
        await this.loadItems(1)
      } catch (e: any) {
        alert(e?.response?.data?.message ?? 'CSV 匯入失敗')
      } finally {
        (event.target as HTMLInputElement).value = ''
      }
    },
  },
})
</script>

<style scoped>
/* Breadcrumb */
.breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 13px; margin-bottom: 16px; }
.breadcrumb-link { background: none; border: none; color: var(--accent); cursor: pointer; padding: 0; font-size: 13px; }
.breadcrumb-link:hover { text-decoration: underline; }
.breadcrumb-sep { color: var(--text-secondary); }
.breadcrumb-current { color: var(--text-primary); font-weight: 500; }

/* Table */
.row-inactive { opacity: 0.45; }
.row-desc { font-size: 11px; color: var(--text-secondary); margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 260px; }
.hs-chip { font-size: 12px; background: var(--surface-2); color: var(--text-primary); padding: 2px 8px; border-radius: 4px; border: 1px solid var(--border); font-weight: 500; }

/* Buttons */
.btn-warning { background: #f59e0b; color: #fff; border: none; }
.btn-warning:hover:not(:disabled) { background: #d97706; }

/* 群組下拉篩選 */
.group-filter-btn { white-space: nowrap; user-select: none; }
.group-drop { position: absolute; top: calc(100% + 4px); left: 0; min-width: 180px; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); z-index: 50; padding: 6px 0; }
.group-drop-item { display: flex; align-items: center; gap: 8px; padding: 7px 14px; font-size: 13px; color: var(--text-primary); cursor: pointer; }
.group-drop-item:hover { background: var(--surface-2); }
.group-drop-clear { padding: 6px 14px; font-size: 12px; color: var(--accent); cursor: pointer; border-top: 1px solid var(--border); margin-top: 4px; }
.group-drop-clear:hover { background: var(--surface-2); }

/* Action cell */
.action-cell { display: flex; align-items: center; gap: 6px; flex-wrap: nowrap; white-space: nowrap; }

/* 回收成分 badge */
.pcr-badge { display: inline-block; padding: 1px 6px; border-radius: 4px; background: #d1fae5; color: #065f46; font-size: 10px; font-weight: 600; white-space: nowrap; }
.pir-badge { display: inline-block; padding: 1px 6px; border-radius: 4px; background: #dbeafe; color: #1e40af; font-size: 10px; font-weight: 600; white-space: nowrap; }

</style>
