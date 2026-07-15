<template>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h1 class="page-title">批次匯入供應商</h1>
        <p class="page-subtitle">從 ERP 匯出 CSV，批次建立供應商主檔</p>
      </div>
      <button class="btn btn-secondary" @click="downloadTemplate">↓ 下載 CSV 範本</button>
    </div>

    <!-- 上傳區 -->
    <div
      class="card upload-area"
      :class="{ 'drag-over': isDragging }"
      @dragover.prevent="isDragging=true"
      @dragleave="isDragging=false"
      @drop.prevent="onDrop"
      @click="$refs.fileInput.click()"
    >
      <input ref="fileInput" type="file" accept=".csv,.txt" style="display:none;" @change="onFileSelect" />
      <div v-if="!selectedFile">
        <div style="font-size:32px;margin-bottom:12px;">📄</div>
        <p style="font-weight:600;margin-bottom:4px;">拖曳 CSV 檔案至此，或點擊選擇</p>
        <p style="font-size:13px;color:var(--text-secondary);">支援 .csv 格式，檔案大小上限 10 MB</p>
      </div>
      <div v-else style="text-align:center;">
        <div style="font-size:32px;margin-bottom:8px;">✓</div>
        <p style="font-weight:600;">{{ selectedFile.name }}</p>
        <p style="font-size:13px;color:var(--text-secondary);">{{ (selectedFile.size/1024).toFixed(1) }} KB</p>
      </div>
    </div>

    <!-- 欄位說明 -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-title">CSV 必要欄位（7 個）</div>
      <div class="mvd-grid">
        <div v-for="f in MVD_FIELDS" :key="f.key" class="mvd-item">
          <span class="mvd-key font-mono">{{ f.key }}</span>
          <span class="mvd-desc">{{ f.desc }}</span>
          <span v-if="f.required" class="badge" style="background:#fee2e2;color:#991b1b;font-size:10px;">必填</span>
        </div>
      </div>
    </div>

    <!-- 預覽（上傳後顯示） -->
    <div v-if="preview.length > 0" class="card" style="margin-bottom:16px;">
      <div class="card-title" style="margin-bottom:12px;">
        資料預覽（前 {{ preview.length }} 筆，共 {{ totalRows }} 筆）
      </div>
      <div class="table-container" style="overflow-x:auto;">
        <table class="data-table" style="min-width:700px;">
          <thead>
            <tr>
              <th v-for="f in MVD_FIELDS" :key="f.key">{{ f.key }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, i) in preview" :key="i">
              <td v-for="f in MVD_FIELDS" :key="f.key" style="font-size:12px;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                {{ row[f.key] || '—' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 匯入按鈕 -->
    <div v-if="selectedFile" style="display:flex;gap:12px;justify-content:flex-end;">
      <button class="btn btn-secondary" @click="resetFile">重新選擇</button>
      <button class="btn btn-primary" :disabled="isUploading" @click="doUpload">
        {{ isUploading ? '匯入中...' : `確認匯入（${totalRows} 筆）` }}
      </button>
    </div>

    <!-- 匯入結果 -->
    <div v-if="uploadResult" class="card result-card">
      <div class="result-row">
        <span class="result-label">總筆數</span>
        <span class="font-mono result-val">{{ uploadResult.total }}</span>
      </div>
      <div class="result-row">
        <span class="result-label">通過清洗</span>
        <span class="font-mono result-val" style="color:#166534;">{{ uploadResult.cleansed }}</span>
      </div>
      <div class="result-row">
        <span class="result-label">需補救</span>
        <span class="font-mono result-val" style="color:#991b1b;">{{ uploadResult.rejected }}</span>
      </div>
      <div style="margin-top:16px;">
        <button class="btn btn-primary" @click="goToReview">前往審核儀表板 →</button>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { useRouter } from 'vue-router'
import { supplierImportApi } from '@/api/modules/supplierImport'

const MVD_FIELDS = [
  { key: 'vendor_code',    desc: '廠商代碼（ERP）',      required: true  },
  { key: 'vat_number',     desc: 'VAT Number / 統編',    required: true  },
  { key: 'vendor_name',    desc: '廠商名稱',              required: true  },
  { key: 'spend_amount',   desc: '年採購額（數字）',       required: false },
  { key: 'country_code',   desc: '國家碼（如 TW、VN）',   required: false },
  { key: 'material_group', desc: '採購類別',              required: false },
  { key: 'primary_email',  desc: '主要聯絡信箱',           required: true  },
]

export default defineComponent({
  name: 'SupplierImportView',
  setup() { return { router: useRouter(), MVD_FIELDS } },

  data() {
    return {
      isDragging:   false,
      selectedFile: null as File | null,
      preview:      [] as Record<string, string>[],
      totalRows:    0,
      isUploading:  false,
      uploadResult: null as { batch_id: string; total: number; cleansed: number; rejected: number } | null,
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
      this.uploadResult = null
      const reader = new FileReader()
      reader.onload = (e) => {
        const text = (e.target?.result as string).replace(/^﻿/, '')
        const lines = text.split(/\r?\n/).filter(l => l.trim())
        if (lines.length < 2) return
        const headers = lines[0].split(',').map(h => h.trim())
        const rows = lines.slice(1, 6).map(l => {
          const vals = l.split(',')
          const row: Record<string, string> = {}
          this.MVD_FIELDS.forEach(f => {
            const idx = headers.findIndex(h =>
              h.toLowerCase().replace(/[\s_-]/g, '') === f.key.replace(/_/g, '')
              || h === f.key
            )
            row[f.key] = idx >= 0 ? (vals[idx] || '').trim() : ''
          })
          return row
        })
        this.preview = rows
        this.totalRows = lines.length - 1
      }
      reader.readAsText(file)
    },

    resetFile() {
      this.selectedFile = null
      this.preview = []
      this.totalRows = 0
      this.uploadResult = null
    },

    async doUpload() {
      if (!this.selectedFile) return
      this.isUploading = true
      try {
        const { data } = await supplierImportApi.upload(this.selectedFile)
        this.uploadResult = data
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '匯入失敗，請確認 CSV 格式')
      } finally {
        this.isUploading = false
      }
    },

    goToReview() {
      if (this.uploadResult) {
        this.router.push(`/suppliers/import/review?batch=${this.uploadResult.batch_id}`)
      }
    },

    downloadTemplate() {
      const headers = MVD_FIELDS.map(f => f.key).join(',')
      const blob = new Blob([headers + '\n'], { type: 'text/csv;charset=utf-8;' })
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = 'supplier_import_template.csv'
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
.upload-area:hover, .drag-over { border-color: var(--accent); background: #f0fdf4; }

.mvd-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.mvd-item { display: flex; align-items: center; gap: 8px; padding: 6px 0; }
.mvd-key { font-size: 12px; min-width: 130px; color: var(--accent); }
.mvd-desc { font-size: 13px; flex: 1; }

.result-card { padding: 24px; }
.result-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--border); }
.result-row:last-of-type { border-bottom: none; }
.result-label { font-size: 14px; color: var(--text-secondary); }
.result-val { font-size: 20px; font-weight: 700; }
</style>
