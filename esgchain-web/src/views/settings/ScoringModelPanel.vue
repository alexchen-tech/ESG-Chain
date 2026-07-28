<template>
  <div class="section-card">
    <div class="section-header">
      <div>
        <h2 class="section-title">評分模型</h2>
        <p class="section-desc">依 SASB 產業設定 E/S/G 三大構面權重與等第門檻，供 esgchain-ai 評分服務計算供應商總分與等第。</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">＋ 新增評分模型</button>
    </div>

    <div v-if="isLoading" class="loading-state">載入中...</div>
    <div v-else-if="!models.length" class="empty-state">尚無評分模型設定</div>

    <table v-else class="pillar-table">
      <thead>
        <tr>
          <th>名稱</th>
          <th>SASB 產業</th>
          <th style="text-align:center;">E 權重</th>
          <th style="text-align:center;">S 權重</th>
          <th style="text-align:center;">G 權重</th>
          <th>等第門檻（A/B/C/D）</th>
          <th style="width:70px;text-align:center;">狀態</th>
          <th style="width:130px;"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="m in models" :key="m.id">
          <td>{{ m.name }}</td>
          <td><code v-if="m.sasb_industry_code" class="font-mono" style="font-size:12px;">{{ m.sasb_industry_code }}</code><span v-else style="color:var(--text-secondary);">全產業預設</span></td>
          <td class="font-mono" style="text-align:center;">{{ m.weight_e }}</td>
          <td class="font-mono" style="text-align:center;">{{ m.weight_s }}</td>
          <td class="font-mono" style="text-align:center;">{{ m.weight_g }}</td>
          <td class="font-mono" style="font-size:12px;">
            {{ m.grade_a_threshold ?? '—' }} / {{ m.grade_b_threshold ?? '—' }} / {{ m.grade_c_threshold ?? '—' }} / {{ m.grade_d_threshold ?? '—' }}
          </td>
          <td style="text-align:center;">
            <span class="match-badge" :class="m.is_active ? 'match-ok' : 'match-warn'">{{ m.is_active ? '啟用' : '停用' }}</span>
          </td>
          <td>
            <button class="btn-icon-secondary" @click="openEdit(m)">編輯</button>
            <button class="btn-icon-danger" :disabled="deletingId === m.id" @click="remove(m)">
              <span v-if="deletingId === m.id">...</span>
              <span v-else>刪除</span>
            </button>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- 新增/編輯 Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-box">
        <h3 class="modal-title">{{ editingId ? '編輯評分模型' : '新增評分模型' }}</h3>

        <div class="form-group">
          <label class="form-label">名稱</label>
          <input v-model="form.name" class="form-input" placeholder="例：紡織業預設模型" maxlength="100" />
        </div>

        <div class="form-group">
          <label class="form-label">SASB 產業（選填，留空表示全產業預設）</label>
          <select v-model="form.sasb_industry_code" class="form-input">
            <option value="">— 全產業預設 —</option>
            <option v-for="ind in sasbIndustries" :key="ind.code" :value="ind.code">
              {{ ind.code }}　{{ ind.industry }}
            </option>
          </select>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">E 權重</label>
            <input v-model.number="form.weight_e" type="number" step="0.01" min="0" max="1" class="form-input font-mono" />
          </div>
          <div class="form-group">
            <label class="form-label">S 權重</label>
            <input v-model.number="form.weight_s" type="number" step="0.01" min="0" max="1" class="form-input font-mono" />
          </div>
          <div class="form-group">
            <label class="form-label">G 權重</label>
            <input v-model.number="form.weight_g" type="number" step="0.01" min="0" max="1" class="form-input font-mono" />
          </div>
        </div>
        <p class="form-hint" :class="{ 'form-hint-warn': !weightSumOk }">
          三項權重總和：{{ weightSum.toFixed(2) }}<template v-if="!weightSumOk">（建議合計為 1）</template>
        </p>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">A 等第門檻</label>
            <input v-model.number="form.grade_a_threshold" type="number" step="0.1" class="form-input font-mono" />
          </div>
          <div class="form-group">
            <label class="form-label">B 等第門檻</label>
            <input v-model.number="form.grade_b_threshold" type="number" step="0.1" class="form-input font-mono" />
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">C 等第門檻</label>
            <input v-model.number="form.grade_c_threshold" type="number" step="0.1" class="form-input font-mono" />
          </div>
          <div class="form-group">
            <label class="form-label">D 等第門檻</label>
            <input v-model.number="form.grade_d_threshold" type="number" step="0.1" class="form-input font-mono" />
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">說明（選填）</label>
          <textarea v-model="form.description" class="form-input" rows="2"></textarea>
        </div>

        <div v-if="editingId" class="form-group">
          <label class="form-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" v-model="form.is_active" />
            啟用此模型
          </label>
        </div>

        <div v-if="formError" class="error-msg">{{ formError }}</div>
        <div class="modal-actions">
          <button class="btn btn-secondary" @click="showModal = false">取消</button>
          <button class="btn btn-primary" :disabled="isSaving || !form.name" @click="submit">
            <span v-if="isSaving">儲存中...</span>
            <span v-else>{{ editingId ? '儲存' : '新增' }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { scoringModelsApi, settingsApi } from '@/api/modules/settings'

const FORM_DEFAULT = {
  name: '',
  sasb_industry_code: '',
  weight_e: 0.34,
  weight_s: 0.33,
  weight_g: 0.33,
  grade_a_threshold: 80,
  grade_b_threshold: 60,
  grade_c_threshold: 40,
  grade_d_threshold: 20,
  description: '',
  is_active: true,
}

export default {
  name: 'ScoringModelPanel',

  data() {
    return {
      isLoading: false,
      models: [],
      sasbIndustries: [],
      showModal: false,
      isSaving: false,
      deletingId: null,
      editingId: null,
      formError: '',
      form: { ...FORM_DEFAULT },
    }
  },

  computed: {
    weightSum() {
      return (Number(this.form.weight_e) || 0) + (Number(this.form.weight_s) || 0) + (Number(this.form.weight_g) || 0)
    },
    weightSumOk() {
      return Math.abs(this.weightSum - 1) < 0.01
    },
  },

  mounted() {
    this.loadModels()
    this.loadSasbIndustries()
  },

  methods: {
    async loadModels() {
      this.isLoading = true
      try {
        const res = await scoringModelsApi.list()
        this.models = res.data.data || []
      } finally {
        this.isLoading = false
      }
    },

    async loadSasbIndustries() {
      const res = await settingsApi.sasb.list()
      this.sasbIndustries = res.data.data || []
    },

    openCreate() {
      this.editingId = null
      this.form = { ...FORM_DEFAULT }
      this.formError = ''
      this.showModal = true
    },

    openEdit(m) {
      this.editingId = m.id
      this.form = {
        name: m.name,
        sasb_industry_code: m.sasb_industry_code || '',
        weight_e: m.weight_e,
        weight_s: m.weight_s,
        weight_g: m.weight_g,
        grade_a_threshold: m.grade_a_threshold,
        grade_b_threshold: m.grade_b_threshold,
        grade_c_threshold: m.grade_c_threshold,
        grade_d_threshold: m.grade_d_threshold,
        description: m.description || '',
        is_active: m.is_active,
      }
      this.formError = ''
      this.showModal = true
    },

    async submit() {
      this.formError = ''
      this.isSaving = true
      try {
        const payload = { ...this.form, sasb_industry_code: this.form.sasb_industry_code || null }
        // 等第門檻若留空則不送，讓 esgchain-ai 套用自己的預設值（該端不接受 null）
        for (const key of ['grade_a_threshold', 'grade_b_threshold', 'grade_c_threshold', 'grade_d_threshold']) {
          if (payload[key] === null || payload[key] === '') delete payload[key]
        }
        if (this.editingId) {
          await scoringModelsApi.update(this.editingId, payload)
        } else {
          await scoringModelsApi.create(payload)
        }
        this.showModal = false
        await this.loadModels()
      } catch (e) {
        const data = e?.response?.data
        if (data?.errors) {
          this.formError = Object.values(data.errors).flat().join('；')
        } else if (Array.isArray(data?.detail)) {
          // esgchain-ai（FastAPI/Pydantic）驗證錯誤格式
          this.formError = data.detail.map((d) => `${(d.loc || []).slice(-1)[0]}：${d.msg}`).join('；')
        } else {
          this.formError = data?.message || data?.detail || '儲存失敗'
        }
      } finally {
        this.isSaving = false
      }
    },

    async remove(m) {
      if (!confirm(`確定刪除評分模型「${m.name}」？`)) return
      this.deletingId = m.id
      try {
        await scoringModelsApi.remove(m.id)
        await this.loadModels()
      } catch {
        alert('刪除失敗')
      } finally {
        this.deletingId = null
      }
    },
  },
}
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

.loading-state,
.empty-state {
  text-align: center;
  padding: 32px;
  color: var(--text-secondary);
}

.pillar-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.pillar-table th {
  text-align: left;
  padding: 8px 12px;
  border-bottom: 1px solid var(--border);
  font-weight: 500;
  color: var(--text-secondary);
  background: var(--surface-alt, #faf9f7);
}

.pillar-table td {
  padding: 10px 12px;
  border-bottom: 1px solid var(--border-light, #eeece8);
  vertical-align: middle;
}

.match-badge {
  display: inline-block;
  font-size: 12px;
  padding: 2px 8px;
  border-radius: 10px;
  white-space: nowrap;
}
.match-ok { background: #e6f4ec; color: #1a4d3e; border: 1px solid #b8e0c8; }
.match-warn { background: #fff4e0; color: #a05a00; border: 1px solid #f0d59c; }

.btn-icon-secondary {
  background: none;
  border: 1px solid var(--border);
  color: var(--text-primary);
  padding: 3px 8px;
  border-radius: 4px;
  font-size: 12px;
  cursor: pointer;
  margin-right: 6px;
}
.btn-icon-secondary:hover { border-color: var(--accent); color: var(--accent); }

.btn-icon-danger {
  background: none;
  border: 1px solid #dc2626;
  color: #dc2626;
  padding: 3px 8px;
  border-radius: 4px;
  font-size: 12px;
  cursor: pointer;
  transition: all 0.15s;
}
.btn-icon-danger:hover:not(:disabled) { background: #dc2626; color: #fff; }
.btn-icon-danger:disabled { opacity: 0.5; cursor: not-allowed; }

/* Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.4);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-box {
  background: var(--surface);
  border-radius: 8px;
  padding: 28px;
  width: 520px;
  max-width: 90vw;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-title {
  font-size: 16px;
  font-weight: 600;
  margin: 0 0 20px;
}

.form-row { display: flex; gap: 12px; }
.form-row .form-group { flex: 1; }

.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 6px; }
.form-input {
  width: 100%;
  padding: 8px 10px;
  border: 1px solid var(--border);
  border-radius: 4px;
  font-size: 13px;
  background: var(--surface);
  box-sizing: border-box;
}

.form-hint { font-size: 12px; color: var(--text-secondary); margin-top: -8px; margin-bottom: 16px; }
.form-hint-warn { color: #c0392b; }

.error-msg {
  color: #dc2626;
  font-size: 13px;
  margin-bottom: 12px;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 20px;
}

.btn-secondary {
  background: var(--surface);
  border: 1px solid var(--border);
  color: var(--text-primary);
  padding: 8px 16px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 13px;
}
</style>
