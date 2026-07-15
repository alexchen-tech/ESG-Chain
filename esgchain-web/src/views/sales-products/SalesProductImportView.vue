<template>
  <div class="page-container">
    <div class="breadcrumb">
      <button class="breadcrumb-link" @click="$router.push('/sales-products')">銷售產品</button>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current">批次匯入</span>
    </div>

    <div class="page-header">
      <div>
        <h1 class="page-title">批次匯入銷售產品</h1>
        <p class="page-subtitle">上傳 CSV 檔案批次建立銷售產品主檔，支援 CBAM / EUDR 法規旗標設定</p>
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
        <p style="font-size:13px;color:var(--text-secondary);">支援 .csv 格式，檔案大小上限 5 MB</p>
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
      <p style="font-size:12px;color:var(--text-secondary);margin-top:12px;">
        is_cbam_applicable / is_eudr_applicable 填入 <code>1</code> 或 <code>0</code>（預設為 0）
      </p>
    </div>

    <!-- 資料預覽 -->
    <div v-if="preview.length > 0" class="card" style="margin-bottom:16px;">
      <div class="card-title" style="margin-bottom:12px;">
        資料預覽（前 {{ preview.length }} 筆，共 {{ totalRows }} 筆）
      </div>
      <div style="overflow-x:auto;">
        <table class="data-table" style="min-width:800px;">
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
        <span class="font-mono result-val" style="color:#166534;">{{ result.created_count }}</span>
      </div>
      <div class="result-row">
        <span class="result-label">略過（重複）</span>
        <span class="font-mono result-val" style="color:var(--text-secondary);">{{ result.skipped_count }}</span>
      </div>
      <div v-if="result.warnings?.length" style="margin-top:12px;">
        <p style="font-size:13px;font-weight:600;color:#92400e;margin-bottom:8px;">{{ result.warnings.length }} 筆警告</p>
        <div v-for="(w, i) in result.warnings" :key="i" class="warning-row">{{ w }}</div>
      </div>
      <div style="margin-top:16px;display:flex;gap:8px;">
        <button class="btn btn-secondary" @click="resetFile">繼續匯入</button>
        <button class="btn btn-primary" @click="$router.push('/sales-products')">前往銷售產品列表 →</button>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { salesProductApi } from '@/api/modules/salesProducts'

const FIELDS = [
  { key: 'name',                desc: '產品名稱',                    required: true  },
  { key: 'product_code',        desc: '產品代碼（唯一，重複略過）',   required: false },
  { key: 'hs_code',             desc: 'HS 關稅碼（如 6109.10）',     required: false },
  { key: 'description',         desc: '產品描述',                    required: false },
  { key: 'unit',                desc: '單位（預設 pcs）',             required: false },
  { key: 'is_cbam_applicable',  desc: 'CBAM 適用（1=是，0=否）',     required: false },
  { key: 'is_eudr_applicable',  desc: 'EUDR 適用（1=是，0=否）',     required: false },
]

export default defineComponent({
  name: 'SalesProductImportView',
  setup() { return { FIELDS } },

  data() {
    return {
      isDragging:   false,
      selectedFile: null as File | null,
      preview:      [] as Record<string, string>[],
      totalRows:    0,
      isUploading:  false,
      result:       null as { created_count: number; skipped_count: number; warnings: string[] } | null,
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
        const { data } = await salesProductApi.import(this.selectedFile)
        this.result = data
        this.selectedFile = null
        this.preview = []
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '匯入失敗，請確認 CSV 格式與必填欄位')
      } finally {
        this.isUploading = false
      }
    },

    downloadTemplate() {
      const headers = FIELDS.map(f => f.key).join(',')
      const sample  = ['基本棉 T 恤', 'TEX-001', '6109.10', '100% 棉，圓領短袖', 'pcs', '0', '0']
      const csv = '﻿' + headers + '\n' + sample.join(',') + '\n'
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
      const url  = URL.createObjectURL(blob)
      const a    = document.createElement('a')
      a.href = url
      a.download = 'sales_product_import_template.csv'
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
.field-key  { font-size: 12px; min-width: 160px; color: var(--accent); }
.field-desc { font-size: 13px; flex: 1; }

.badge          { font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 600; white-space: nowrap; }
.badge-required { background: #fee2e2; color: #991b1b; }
.badge-optional { background: #f3f4f6; color: #6b7280; }

.preview-cell { font-size: 12px; max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.result-card { padding: 24px; }
.result-row  { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--border); }
.result-row:last-of-type { border-bottom: none; }
.result-label { font-size: 14px; color: var(--text-secondary); }
.result-val   { font-size: 20px; font-weight: 700; }
.warning-row  { font-size: 12px; color: #92400e; padding: 4px 0; border-bottom: 1px solid #fde68a; }
</style>
