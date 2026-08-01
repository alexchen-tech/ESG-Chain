<template>
  <div class="section-card">
    <div class="section-header">
      <div>
        <h2 class="section-title">國家風險評等</h2>
        <p class="section-desc">各國勞工、環境、地緣政治風險等級（1–5），用於 SAQ 評分後自動推導 RiskAssessment 的 impact 值</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">+ 新增國家</button>
    </div>

    <div v-if="isLoading" class="loading-mask">載入中...</div>
    <div v-else class="table-container" style="overflow-x:auto;">
      <table class="data-table">
        <thead>
          <tr>
            <th>國家代碼</th>
            <th>國家名稱</th>
            <th>勞工人權風險</th>
            <th>環境監管風險</th>
            <th>地緣政治風險</th>
            <th title="地緣政治風險細分支柱">政治穩定</th>
            <th title="地緣風險細分支柱">環境法規</th>
            <th title="地緣風險細分支柱">社會穩定</th>
            <th title="地緣風險細分支柱">法規遵循</th>
            <th>資料來源</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in ratings" :key="r.id">
            <td><span class="font-mono" style="font-weight:600;">{{ r.country_code }}</span></td>
            <td>{{ r.country_name }}</td>
            <td><span class="risk-badge" :class="riskClass(r.labor_risk)">{{ r.labor_risk }}</span></td>
            <td><span class="risk-badge" :class="riskClass(r.env_risk)">{{ r.env_risk }}</span></td>
            <td><span class="risk-badge" :class="riskClass(r.geo_risk)">{{ r.geo_risk }}</span></td>
            <td>
              <span v-if="r.sub_scores?.political" class="risk-badge" :class="riskClass(r.sub_scores.political)">{{ r.sub_scores.political }}</span>
              <span v-else class="sub-empty">—</span>
            </td>
            <td>
              <span v-if="r.sub_scores?.environmental" class="risk-badge" :class="riskClass(r.sub_scores.environmental)">{{ r.sub_scores.environmental }}</span>
              <span v-else class="sub-empty">—</span>
            </td>
            <td>
              <span v-if="r.sub_scores?.social" class="risk-badge" :class="riskClass(r.sub_scores.social)">{{ r.sub_scores.social }}</span>
              <span v-else class="sub-empty">—</span>
            </td>
            <td>
              <span v-if="r.sub_scores?.regulatory" class="risk-badge" :class="riskClass(r.sub_scores.regulatory)">{{ r.sub_scores.regulatory }}</span>
              <span v-else class="sub-empty">—</span>
            </td>
            <td style="font-size:12px;color:var(--text-secondary);">{{ r.source }}</td>
            <td>
              <button class="btn btn-secondary btn-sm" @click="openEdit(r)">編輯</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 新增 Modal -->
    <div v-if="showCreate" class="modal-overlay" @click.self="showCreate=false">
      <div class="modal" style="min-width:420px;">
        <div class="modal-header">
          <span class="modal-title">新增國家風險評等</span>
          <button class="modal-close" @click="showCreate=false">×</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">國家代碼 <span style="color:var(--danger)">*</span></label>
            <input v-model="createForm.country_code" class="form-input font-mono" maxlength="2" placeholder="例：SG" style="text-transform:uppercase;" />
            <p class="form-hint">ISO 3166-1 alpha-2，2 碼大寫</p>
          </div>
          <div class="form-group">
            <label class="form-label">國家名稱 <span style="color:var(--danger)">*</span></label>
            <input v-model="createForm.country_name" class="form-input" placeholder="例：新加坡" />
          </div>
          <div v-for="field in riskFields" :key="field.key" class="form-group">
            <label class="form-label">{{ field.label }}</label>
            <select v-model="createForm[field.key]" class="form-select">
              <option v-for="n in 5" :key="n" :value="n">{{ n }} — {{ riskLevelLabel(n) }}</option>
            </select>
          </div>
          <div class="sub-scores-section">
            <div class="sub-scores-title">E4 細分支柱（選填）</div>
            <div class="sub-scores-grid">
              <div v-for="f in subScoreFields" :key="f.key" class="form-group" style="margin:0;">
                <label class="form-label" style="font-size:12px;">{{ f.label }}</label>
                <select v-model="createForm.sub_scores[f.key]" class="form-select" style="font-size:13px;">
                  <option :value="null">— 不填 —</option>
                  <option v-for="n in 5" :key="n" :value="n">{{ n }} — {{ riskLevelLabel(n) }}</option>
                </select>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showCreate=false">取消</button>
          <button class="btn btn-primary" :disabled="isSaving || !createForm.country_code || !createForm.country_name" @click="saveCreate">
            {{ isSaving ? '建立中...' : '建立' }}
          </button>
        </div>
      </div>
    </div>

    <!-- 編輯 Modal -->
    <div v-if="editTarget" class="modal-overlay" @click.self="editTarget=null">
      <div class="modal" style="min-width:420px;">
        <div class="modal-header">
          <span class="modal-title">編輯國家風險評等 — {{ editTarget.country_code }} {{ editTarget.country_name }}</span>
          <button class="modal-close" @click="editTarget=null">×</button>
        </div>
        <div class="modal-body">
          <div v-for="field in riskFields" :key="field.key" class="form-group">
            <label class="form-label">{{ field.label }}</label>
            <select v-model="editForm[field.key]" class="form-select">
              <option v-for="n in 5" :key="n" :value="n">{{ n }} — {{ riskLevelLabel(n) }}</option>
            </select>
          </div>
          <div class="sub-scores-section">
            <div class="sub-scores-title">E4 細分支柱（選填）</div>
            <div class="sub-scores-grid">
              <div v-for="f in subScoreFields" :key="f.key" class="form-group" style="margin:0;">
                <label class="form-label" style="font-size:12px;">{{ f.label }}</label>
                <select v-model="editForm.sub_scores[f.key]" class="form-select" style="font-size:13px;">
                  <option :value="null">— 不填 —</option>
                  <option v-for="n in 5" :key="n" :value="n">{{ n }} — {{ riskLevelLabel(n) }}</option>
                </select>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="editTarget=null">取消</button>
          <button class="btn btn-primary" :disabled="isSaving" @click="saveEdit">
            {{ isSaving ? '儲存中...' : '儲存' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import http from '@/api/http'

interface SubScores {
  political: number | null
  environmental: number | null
  social: number | null
  regulatory: number | null
}

interface CountryRiskRating {
  id: string
  country_code: string
  country_name: string
  labor_risk: number
  env_risk: number
  geo_risk: number
  sub_scores: SubScores | null
  source: string
}

const EMPTY_SUB_SCORES = (): SubScores => ({
  political: null, environmental: null, social: null, regulatory: null,
})

export default defineComponent({
  name: 'CountryRiskView',
  data() {
    return {
      isLoading: false,
      isSaving: false,
      ratings: [] as CountryRiskRating[],
      showCreate: false,
      createForm: {
        country_code: '', country_name: '',
        labor_risk: 3, env_risk: 3, geo_risk: 3,
        sub_scores: EMPTY_SUB_SCORES(),
      } as Record<string, any>,
      editTarget: null as CountryRiskRating | null,
      editForm: {
        labor_risk: 3, env_risk: 3, geo_risk: 3,
        sub_scores: EMPTY_SUB_SCORES(),
      } as Record<string, any>,
      riskFields: [
        { key: 'labor_risk', label: '勞工人權風險（S 維度 impact 依據）' },
        { key: 'env_risk',   label: '環境監管風險（E 維度 impact 依據）' },
        { key: 'geo_risk',   label: '地緣政治風險（GP 維度 impact 依據）' },
      ],
      subScoreFields: [
        { key: 'political',     label: '政治穩定' },
        { key: 'environmental', label: '環境法規' },
        { key: 'social',        label: '社會穩定' },
        { key: 'regulatory',    label: '法規遵循' },
      ],
    }
  },
  async created() {
    await this.loadData()
  },
  methods: {
    async loadData() {
      this.isLoading = true
      try {
        const res = await http.get('/api/v1/settings/country-risk-ratings')
        this.ratings = res.data.data
      } finally {
        this.isLoading = false
      }
    },
    openCreate() {
      this.createForm = {
        country_code: '', country_name: '',
        labor_risk: 3, env_risk: 3, geo_risk: 3,
        sub_scores: EMPTY_SUB_SCORES(),
      }
      this.showCreate = true
    },
    async saveCreate() {
      this.isSaving = true
      try {
        const payload: any = {
          ...this.createForm,
          country_code: this.createForm.country_code.toUpperCase(),
        }
        // 若 sub_scores 全為 null，不傳
        const ss = payload.sub_scores as SubScores
        if (!ss.political && !ss.environmental && !ss.social && !ss.regulatory) {
          delete payload.sub_scores
        }
        await http.post('/api/v1/settings/country-risk-ratings', payload)
        this.showCreate = false
        await this.loadData()
      } finally {
        this.isSaving = false
      }
    },
    openEdit(r: CountryRiskRating) {
      this.editTarget = r
      this.editForm = {
        labor_risk: r.labor_risk,
        env_risk: r.env_risk,
        geo_risk: r.geo_risk,
        sub_scores: r.sub_scores ? { ...r.sub_scores } : EMPTY_SUB_SCORES(),
      }
    },
    async saveEdit() {
      if (!this.editTarget) return
      this.isSaving = true
      try {
        const payload: any = { ...this.editForm }
        const ss = payload.sub_scores as SubScores
        if (!ss.political && !ss.environmental && !ss.social && !ss.regulatory) {
          payload.sub_scores = null
        }
        await http.patch(`/api/v1/settings/country-risk-ratings/${this.editTarget.id}`, payload)
        this.editTarget = null
        await this.loadData()
      } finally {
        this.isSaving = false
      }
    },
    riskClass(v: number | null): string {
      if (!v) return ''
      if (v >= 5) return 'risk-extreme'
      if (v >= 4) return 'risk-high'
      if (v >= 3) return 'risk-medium'
      return 'risk-low'
    },
    riskLevelLabel(v: number): string {
      return ['', '極低', '低', '中', '高', '極高'][v] ?? ''
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

.risk-badge {
  display: inline-block;
  width: 28px;
  height: 28px;
  line-height: 28px;
  text-align: center;
  border-radius: 6px;
  font-weight: 700;
  font-size: 13px;
}
.risk-extreme { background: #fee2e2; color: #b91c1c; }
.risk-high    { background: #fed7aa; color: #c2410c; }
.risk-medium  { background: #fef9c3; color: #a16207; }
.risk-low     { background: #dcfce7; color: #15803d; }

.sub-empty {
  color: var(--text-secondary);
  font-size: 13px;
}

.sub-scores-section {
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 12px 14px;
  background: var(--surface-2, #f0ede6);
}

.sub-scores-title {
  font-size: 12px;
  font-weight: 600;
  color: var(--text-secondary);
  margin-bottom: 10px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.sub-scores-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.modal-body   { padding: 16px 20px; display: flex; flex-direction: column; gap: 12px; }
.modal-footer { padding: 12px 20px; display: flex; justify-content: flex-end; gap: 8px; border-top: 1px solid var(--border); }
</style>
