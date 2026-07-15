<template>
  <div class="page-container">
    <div class="breadcrumb">
      <button class="breadcrumb-link" @click="$router.push('/materials/items')">物料主檔</button>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current">批次匯入</span>
    </div>

    <div class="page-header">
      <div>
        <h1 class="page-title">批次匯入物料主檔</h1>
        <p class="page-subtitle">上傳 CSV 檔案批次建立或更新物料料號，item_code 已存在則更新名稱與 HS 碼</p>
      </div>
      <button class="btn btn-secondary" @click="downloadTemplate">↓ 下載 CSV 範本</button>
    </div>

    <!-- 上傳區 -->
    <div
      class="card upload-area"
      :class="{ 'drag-over': isDragging }"
      @dragover.prevent="isDragging = true"
      @dragleave="isDragging = false"
      @drop.prevent="onDrop"
      @click="$refs.fileInput.click()"
    >
      <input ref="fileInput" type="file" accept=".csv,.txt" style="display:none;" @change="onFileSelect" />
      <div v-if="!selectedFile">
        <div style="font-size:32px;margin-bottom:12px;">📄</div>
        <p style="font-weight:600;margin-bottom:4px;">拖曳 CSV 檔案至此，或點擊選擇</p>
        <p style="font-size:13px;color:var(--text-secondary);">支援 .csv 格式，無大小限制</p>
      </div>
      <div v-else style="text-align:center;">
        <div style="font-size:32px;margin-bottom:8px;">✓</div>
        <p style="font-weight:600;">{{ selectedFile.name }}</p>
        <p style="font-size:13px;color:var(--text-secondary);">{{ (selectedFile.size / 1024).toFixed(1) }} KB・{{ totalRows }} 筆資料</p>
      </div>
    </div>

    <!-- 欄位說明 -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-title">CSV 欄位說明（{{ FIELDS.length }} 個）</div>
      <div class="field-grid">
        <div v-for="f in FIELDS" :key="f.key" class="field-item">
          <span class="field-key font-mono">{{ f.key }}</span>
          <span class="field-desc">{{ f.desc }}</span>
          <span v-if="f.required" class="badge badge-required">必填</span>
          <span v-else class="badge badge-optional">選填</span>
        </div>
      </div>
      <div style="margin-top:14px;padding:10px 14px;background:#f8f6f3;border-radius:6px;font-size:12px;color:var(--text-secondary);line-height:1.8;">
        <strong style="color:var(--text-primary);">物料群組名稱</strong> 參考值：棉紡原料、合成纖維原料、天然纖維輔料、染料化學品、金屬配件、成衣縫製服務、染整加工服務、木製包材服務<br>
        <strong style="color:var(--text-primary);">recyclability_rating</strong> 參考值：high、medium、low、unknown
      </div>
    </div>

    <!-- 資料預覽 -->
    <div v-if="preview.length > 0" class="card" style="margin-bottom:16px;">
      <div class="card-title" style="margin-bottom:12px;">
        資料預覽（前 {{ preview.length }} 筆，共 {{ totalRows }} 筆）
      </div>
      <div style="overflow-x:auto;">
        <table class="data-table" style="min-width:900px;">
          <thead>
            <tr>
              <th v-for="f in FIELDS" :key="f.key">{{ f.key }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, i) in preview" :key="i">
              <td v-for="f in FIELDS" :key="f.key" class="preview-cell">
                {{ row[f.key] || '—' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 操作按鈕 -->
    <div v-if="selectedFile" style="display:flex;gap:12px;justify-content:flex-end;">
      <button class="btn btn-secondary" @click="resetFile">重新選擇</button>
      <button class="btn btn-primary" :disabled="isUploading" @click="doUpload">
        {{ isUploading ? '匯入中...' : `確認匯入（${totalRows} 筆）` }}
      </button>
    </div>

    <!-- 匯入結果 -->
    <div v-if="result" class="card result-card">
      <div class="result-row">
        <span class="result-label">新增筆數</span>
        <span class="font-mono result-val" style="color:#166534;">{{ result.created }}</span>
      </div>
      <div class="result-row">
        <span class="result-label">更新筆數</span>
        <span class="font-mono result-val" style="color:#1d4ed8;">{{ result.updated }}</span>
      </div>
      <div v-if="result.warnings?.length" style="margin-top:12px;">
        <p style="font-size:13px;font-weight:600;color:#92400e;margin-bottom:8px;">{{ result.warnings.length }} 筆警告</p>
        <div v-for="(w, i) in result.warnings" :key="i" class="warning-row">{{ w }}</div>
      </div>
      <div v-else style="margin-top:12px;font-size:13px;color:var(--text-secondary);text-align:center;">
        所有資料已成功匯入，無警告。
      </div>
      <div style="margin-top:16px;display:flex;gap:8px;">
        <button class="btn btn-secondary" @click="resetFile">繼續匯入</button>
        <button class="btn btn-primary" @click="$router.push('/materials/items')">前往物料主檔列表 →</button>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { materialItemApi } from '@/api/modules/compliance'

const FIELDS = [
  { key: 'item_code',             desc: '料號代碼（唯一識別，已存在則更新）',  required: true  },
  { key: 'name',                  desc: '物料名稱',                            required: true  },
  { key: 'hs_code',               desc: 'HS 關稅碼（如 52051100）',            required: false },
  { key: 'material_group_name',   desc: '物料群組名稱（須與系統一致）',         required: false },
  { key: 'unit',                  desc: '計量單位（kg、pcs、m2 等）',          required: false },
  { key: 'net_weight',            desc: '淨重（數字，公斤）',                   required: false },
  { key: 'pcr_percentage',        desc: '消費後回收料比例（0–100）',            required: false },
  { key: 'pir_percentage',        desc: '工業回收料比例（0–100）',              required: false },
  { key: 'bio_based_percentage',  desc: '生物基材料比例（0–100）',              required: false },
  { key: 'recyclability_rating',  desc: '可回收性評級',                         required: false },
  { key: 'description',           desc: '物料描述備註',                         required: false },
]

export default defineComponent({
  name: 'MaterialItemImportView',
  setup() { return { FIELDS } },

  data() {
    return {
      isDragging:   false,
      selectedFile: null as File | null,
      preview:      [] as Record<string, string>[],
      totalRows:    0,
      isUploading:  false,
      result:       null as { created: number; updated: number; warnings: string[] } | null,
    }
  },

  methods: {
    onDrop(e: DragEvent) {
      this.isDragging = false
      const file = e.dataTransfer?.files?.[0]
      if (file) this.loadFile(file)
    },

    onFileSelect(e: Event) {
      const file = (e.target as HTMLInputElement).files?.[0]
      if (file) this.loadFile(file)
    },

    loadFile(file: File) {
      this.selectedFile = file
      this.result = null
      const reader = new FileReader()
      reader.onload = (e) => {
        const text = (e.target?.result as string).replace(/^﻿/, '')
        const lines = text.split(/\r?\n/).filter(l => l.trim())
        if (lines.length < 2) return
        const headers = lines[0].split(',').map(h => h.trim())
        this.totalRows = lines.length - 1
        this.preview = lines.slice(1, 6).map(l => {
          const vals = l.split(',')
          const row: Record<string, string> = {}
          FIELDS.forEach(f => {
            const idx = headers.indexOf(f.key)
            row[f.key] = idx >= 0 ? (vals[idx] || '').trim() : ''
          })
          return row
        })
      }
      reader.readAsText(file)
    },

    resetFile() {
      this.selectedFile = null
      this.preview = []
      this.totalRows = 0
      this.result = null
    },

    async doUpload() {
      if (!this.selectedFile) return
      this.isUploading = true
      try {
        const { data } = await materialItemApi.import(this.selectedFile)
        this.result = data.data
        this.selectedFile = null
        this.preview = []
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '匯入失敗，請確認 CSV 格式')
      } finally {
        this.isUploading = false
      }
    },

    downloadTemplate() {
      const headers = FIELDS.map(f => f.key).join(',')
      const sample  = ['RAW-COT-001', '精梳棉 32S', '52051100', '棉紡原料', 'kg', '1.00', '0', '0', '0', 'low', '精梳棉紗，適用於高支棉織物']
      const csv = '﻿' + headers + '\n' + sample.join(',') + '\n'
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
      const url  = URL.createObjectURL(blob)
      const a    = document.createElement('a')
      a.href = url
      a.download = 'material_item_import_template.csv'
      a.click()
      URL.revokeObjectURL(url)
    },
  },
})
</script>

<style scoped>
.upload-area {
  border: 2px dashed var(--border);
  text-align: center;
  padding: 48px 24px;
  cursor: pointer;
  transition: all 0.2s;
  margin-bottom: 16px;
}
.upload-area:hover,
.drag-over { border-color: var(--accent); background: #f0fdf4; }

.field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.field-item { display: flex; align-items: center; gap: 8px; padding: 6px 0; border-bottom: 1px solid var(--border-light, #f0ede8); }
.field-key  { font-size: 12px; min-width: 180px; color: var(--accent); }
.field-desc { font-size: 13px; flex: 1; }

.badge          { font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 600; white-space: nowrap; }
.badge-required { background: #fee2e2; color: #991b1b; }
.badge-optional { background: #f3f4f6; color: #6b7280; }

.preview-cell { font-size: 12px; max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.result-card { padding: 24px; }
.result-row  { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--border); }
.result-row:last-of-type { border-bottom: none; }
.result-label { font-size: 14px; color: var(--text-secondary); }
.result-val   { font-size: 20px; font-weight: 700; }
.warning-row  { font-size: 12px; color: #92400e; padding: 4px 0; border-bottom: 1px solid #fde68a; }
</style>
