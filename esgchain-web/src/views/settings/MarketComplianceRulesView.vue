<template>
  <div class="market-rules-view">
    <div class="page-header">
      <div>
        <h1 class="page-title">市場合規規則</h1>
        <p class="page-subtitle">依目標市場定義出口文件合規要求</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">+ 新增規則</button>
    </div>

    <div v-if="loading" class="loading-state">載入中…</div>

    <div v-else>
      <div v-for="(rules, market) in groupedRules" :key="market" class="market-group">
        <div class="market-heading">
          <span class="market-badge">{{ market }}</span>
          <span class="rule-count">{{ rules.length }} 條規則</span>
        </div>
        <table class="rules-table">
          <thead>
            <tr>
              <th>文件類型</th>
              <th>法規範疇</th>
              <th>適用層級</th>
              <th>強制要求</th>
              <th>生效日期</th>
              <th>狀態</th>
              <th>備注</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="rule in rules" :key="rule.id" :class="{ inactive: !rule.is_active }">
              <td class="doc-type font-mono" :title="rule.doc_type">{{ regulationLabel(rule.doc_type) }}</td>
              <td><span class="badge-scope badge-scope--material">{{ PROGRAM_LABELS[rule.program] ?? rule.program }}</span></td>
              <td>
                <span class="badge-scope" :class="rule.scope === 'product' ? 'badge-scope--product' : 'badge-scope--material'">
                  {{ rule.scope === 'product' ? '產品層' : '物料層' }}
                </span>
              </td>
              <td>
                <span :class="rule.is_mandatory ? 'badge-mandatory' : 'badge-optional'">
                  {{ rule.is_mandatory ? '強制' : '選擇性' }}
                </span>
              </td>
              <td class="font-mono">{{ formatDate(rule.effective_from) }}</td>
              <td>
                <span :class="rule.is_active ? 'badge-active' : 'badge-inactive'">
                  {{ rule.is_active ? '啟用' : '停用' }}
                </span>
              </td>
              <td class="notes-cell">{{ rule.notes || '—' }}</td>
              <td class="actions-cell">
                <button class="btn-icon" title="編輯" @click="openEdit(rule)">✏️</button>
                <button
                  class="btn-icon"
                  :title="rule.is_active ? '停用' : '啟用'"
                  @click="toggleActive(rule)"
                >{{ rule.is_active ? '⏸' : '▶' }}</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="Object.keys(groupedRules).length === 0" class="empty-state">
        尚無規則，請點擊「新增規則」建立第一條。
      </div>
    </div>

    <!-- 新增/編輯 Modal -->
    <div v-if="modal.open" class="modal-overlay" @click.self="closeModal">
      <div class="modal-card">
        <div class="modal-header">
          <h2>{{ modal.id ? '編輯規則' : '新增規則' }}</h2>
          <button class="btn-close" @click="closeModal">✕</button>
        </div>
        <form class="modal-body" @submit.prevent="submitModal">
          <div class="form-row">
            <label>地區 <span class="required">*</span></label>
            <select v-model="modal.form.market" required>
              <option value="">請選擇</option>
              <option v-for="m in marketOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
          </div>
          <div class="form-row">
            <label>法規 <span class="required">*</span></label>
            <select v-model="modal.form.doc_type" required>
              <option value="">請選擇</option>
              <option v-for="d in regulationOptions" :key="d.value" :value="d.value">{{ d.label }}</option>
              <option value="__custom__">其他（自訂代碼）…</option>
            </select>
            <input
              v-if="modal.form.doc_type === '__custom__'"
              v-model="customDocType"
              type="text"
              placeholder="自訂法規代碼，e.g. NEW_REGULATION_CODE"
              maxlength="50"
              style="margin-top:8px;"
            />
          </div>
          <div class="form-row">
            <label>法規範疇</label>
            <select v-model="modal.form.program" class="form-select" style="width:100%;margin-bottom:10px;">
              <option v-for="p in PROGRAMS" :key="p" :value="p">{{ PROGRAM_LABELS[p] }}</option>
            </select>
            <span style="font-size:11px;color:var(--text-secondary);">供「出口審查」篩選只跑哪個範疇的檢查項目；不確定歸類時選「一般文件」</span>
          </div>
          <div class="form-row">
            <label>適用層級</label>
            <select v-model="modal.form.scope" class="form-select" style="width:100%;margin-bottom:10px;">
              <option value="material">物料層 — 依產品物料群組的文件需求觸發</option>
              <option value="product">產品層 — 該市場一律適用（如 DPP/CPSIA）</option>
            </select>
            <label>強制要求</label>
            <label class="toggle">
              <input v-model="modal.form.is_mandatory" type="checkbox" />
              <span>{{ modal.form.is_mandatory ? '是（強制）' : '否（選擇性）' }}</span>
            </label>
          </div>
          <div class="form-row">
            <label>生效日期 <span class="required">*</span></label>
            <input v-model="modal.form.effective_from" type="date" required />
          </div>
          <div v-if="modal.id" class="form-row">
            <label>啟用狀態</label>
            <label class="toggle">
              <input v-model="modal.form.is_active" type="checkbox" />
              <span>{{ modal.form.is_active ? '啟用' : '停用' }}</span>
            </label>
          </div>
          <div class="form-row">
            <label>備注</label>
            <textarea v-model="modal.form.notes" rows="2" placeholder="法規背景說明（選填）"></textarea>
          </div>
          <div v-if="modal.error" class="form-error">{{ modal.error }}</div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="closeModal">取消</button>
            <button type="submit" class="btn btn-primary" :disabled="modal.saving">
              {{ modal.saving ? '儲存中…' : '儲存' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import {
  marketComplianceRulesApi,
  type MarketComplianceRule,
} from '@/api/modules/marketComplianceRules'
import { EXPORT_MARKETS, MARKET_LABELS } from '@/constants/markets'

// 地區（第一階選項）：與 MarketComplianceChecker/出口市場審查實際使用的 market 值一致
// （非 market_definitions.code，該表為管理員自訂的品牌/客戶專屬市場定義，字面值不同，
// 例如 EU_MARKET vs 這裡的 EU，兩者是獨立的詞彙表，不可混用）
const MARKETS = EXPORT_MARKETS.map(value => ({ value, label: MARKET_LABELS[value] }))

// 對應後端 App\Models\MarketComplianceRule::PROGRAMS
const PROGRAMS = ['dpp', 'cbam', 'eudr', 'uflpa', 'general']
const PROGRAM_LABELS: Record<string, string> = {
  dpp: 'DPP', cbam: 'CBAM', eudr: 'EUDR', uflpa: 'UFLPA', general: '一般文件',
}

// 法規（第二階選項）：對應 doc_type，沿用全站既有的文件類型詞彙（見
// ProductionBatchDetailView.vue/SupplierComplianceDetailView.vue 的 DOC_TYPE_LABELS）
const REGULATIONS = [
  { value: 'EUDR_DDS',           label: 'EUDR DDS（禁伐林盡職調查聲明）' },
  { value: 'UFLPA_DECLARATION',  label: 'UFLPA 聲明（防止強迫勞動法）' },
  { value: 'CBAM_REPORT',        label: 'CBAM 報告（碳邊境調整機制）' },
  { value: 'CMRT',               label: 'CMRT（衝突礦產報告）' },
  { value: 'SDS',                label: 'SDS（安全資料表）' },
  { value: 'CE_DOC',             label: 'CE 認證' },
  { value: 'ORIGIN_CERT',        label: '原產地證明' },
  { value: 'GRS',                label: 'GRS 認證（回收材料）' },
  { value: 'DPP_DECLARATION',    label: 'DPP 聲明（數位產品護照）' },
  { value: 'CPSIA_CERT',         label: 'CPSIA 認證（美國消費品安全）' },
  { value: 'PROP65_DECLARATION', label: 'Prop 65 聲明（加州有害物質警示）' },
  { value: 'FORMALDEHYDE_TEST',  label: '甲醛測試報告' },
  { value: 'JP_QUALITY_LABEL',   label: '日本品質標示' },
  { value: 'MSA_STATEMENT',      label: 'MSA 聲明（現代奴役法）' },
  { value: 'OTHER',              label: '其他' },
]

interface ModalForm {
  market: string
  doc_type: string
  program: string
  scope: 'material' | 'product'
  is_mandatory: boolean
  effective_from: string
  is_active: boolean
  notes: string
}

export default defineComponent({
  name: 'MarketComplianceRulesView',

  data() {
    return {
      loading: false,
      rules: [] as MarketComplianceRule[],
      marketOptions: MARKETS,
      regulationOptions: REGULATIONS,
      PROGRAMS,
      PROGRAM_LABELS,
      customDocType: '',
      modal: {
        open: false,
        id: null as string | null,
        saving: false,
        error: '',
        form: this.emptyForm(),
      },
    }
  },

  computed: {
    groupedRules(): Record<string, MarketComplianceRule[]> {
      const grouped: Record<string, MarketComplianceRule[]> = {}
      for (const rule of this.rules) {
        if (!grouped[rule.market]) grouped[rule.market] = []
        grouped[rule.market].push(rule)
      }
      return grouped
    },
  },

  mounted() {
    this.loadRules()
  },

  methods: {
    emptyForm(): ModalForm {
      return {
        market: '',
        doc_type: '',
        program: 'general',
        scope: 'material' as 'material' | 'product',
        is_mandatory: true,
        effective_from: '',
        is_active: true,
        notes: '',
      }
    },

    async loadRules() {
      this.loading = true
      try {
        const res = await marketComplianceRulesApi.list()
        this.rules = res.data.data
      } finally {
        this.loading = false
      }
    },

    openCreate() {
      this.modal.id = null
      this.modal.form = this.emptyForm()
      this.customDocType = ''
      this.modal.error = ''
      this.modal.open = true
    },

    openEdit(rule: MarketComplianceRule) {
      const isKnown = REGULATIONS.some(r => r.value === rule.doc_type)
      this.modal.id = rule.id
      this.modal.form = {
        market: rule.market,
        doc_type: isKnown ? rule.doc_type : '__custom__',
        program: rule.program ?? 'general',
        scope: rule.scope ?? 'material',
        is_mandatory: rule.is_mandatory,
        effective_from: rule.effective_from,
        is_active: rule.is_active,
        notes: rule.notes ?? '',
      }
      this.customDocType = isKnown ? '' : rule.doc_type
      this.modal.error = ''
      this.modal.open = true
    },

    closeModal() {
      this.modal.open = false
    },

    async submitModal() {
      if (this.modal.form.doc_type === '__custom__' && !this.customDocType.trim()) {
        this.modal.error = '請輸入自訂法規代碼'
        return
      }
      this.modal.saving = true
      this.modal.error = ''
      try {
        const payload = {
          ...this.modal.form,
          doc_type: this.modal.form.doc_type === '__custom__' ? this.customDocType.trim().toUpperCase() : this.modal.form.doc_type,
          notes: this.modal.form.notes || null,
        }
        if (this.modal.id) {
          await marketComplianceRulesApi.update(this.modal.id, payload)
        } else {
          await marketComplianceRulesApi.create(payload)
        }
        await this.loadRules()
        this.closeModal()
      } catch (err: any) {
        const msg = err?.response?.data?.message || err?.response?.data?.errors
        this.modal.error = typeof msg === 'object' ? Object.values(msg).flat().join(' ') : (msg || '儲存失敗，請稍後再試。')
      } finally {
        this.modal.saving = false
      }
    },

    regulationLabel(docType: string): string {
      return REGULATIONS.find(r => r.value === docType)?.label ?? docType
    },

    formatDate(value: string): string {
      return value ? value.slice(0, 10) : '—'
    },

    async toggleActive(rule: MarketComplianceRule) {
      try {
        await marketComplianceRulesApi.update(rule.id, { is_active: !rule.is_active })
        await this.loadRules()
      } catch {
        alert('操作失敗，請稍後再試。')
      }
    },
  },
})
</script>

<style scoped>
.badge-scope { display: inline-block; white-space: nowrap; font-size: 11px; padding: 2px 8px; border-radius: 3px; border: 1px solid var(--border); }
.badge-scope--product { color: var(--accent); background: #eef4f1; }
.badge-scope--material { color: var(--text-secondary); background: var(--surface-2); }
.market-rules-view {
  padding: 24px;
  max-width: 1180px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 28px;
}

.page-title {
  font-size: 20px;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0 0 4px;
}

.page-subtitle {
  font-size: 13px;
  color: var(--text-secondary);
  margin: 0;
}

.market-group {
  margin-bottom: 28px;
}

.market-heading {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 10px;
}

.market-badge {
  background: var(--accent);
  color: #fff;
  font-size: 12px;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 4px;
  letter-spacing: 0.5px;
}

.rule-count {
  font-size: 12px;
  color: var(--text-secondary);
}

.rules-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
  background: var(--surface);
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid var(--border);
}

.rules-table th {
  background: var(--surface-2);
  padding: 8px 12px;
  text-align: left;
  font-size: 11px;
  font-weight: 600;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 1px solid var(--border);
}

.rules-table td {
  padding: 10px 12px;
  border-bottom: 1px solid var(--border);
  color: var(--text-primary);
  white-space: nowrap;
}

.rules-table tr:last-child td {
  border-bottom: none;
}

.rules-table tr.inactive td {
  opacity: 0.5;
}

.doc-type { font-family: 'Fira Code', monospace; font-size: 12px; }
.font-mono { font-family: 'Fira Code', monospace; font-size: 12px; }

.badge-mandatory,
.badge-optional,
.badge-active,
.badge-inactive {
  display: inline-block;
  white-space: nowrap;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 11px;
}
.badge-mandatory { background: #fee2e2; color: #b91c1c; font-weight: 600; }
.badge-optional  { background: var(--surface-2); color: var(--text-secondary); }
.badge-active    { background: #dcfce7; color: #15803d; font-weight: 600; }
.badge-inactive  { background: var(--surface-2); color: var(--text-secondary); }

.notes-cell { color: var(--text-secondary); max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.actions-cell { white-space: nowrap; }

.btn-icon {
  background: none;
  border: none;
  cursor: pointer;
  padding: 4px 6px;
  font-size: 14px;
  opacity: 0.7;
  border-radius: 4px;
}
.btn-icon:hover { opacity: 1; background: var(--surface-2); }

/* Modal */
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.4);
  display: flex; align-items: center; justify-content: center;
  z-index: 1000;
}
.modal-card {
  background: var(--surface);
  border-radius: 12px;
  width: 480px;
  max-width: 95vw;
  box-shadow: 0 8px 32px rgba(0,0,0,0.18);
}
.modal-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 20px 24px 16px;
  border-bottom: 1px solid var(--border);
}
.modal-header h2 { font-size: 16px; font-weight: 600; margin: 0; }
.btn-close { background: none; border: none; cursor: pointer; font-size: 16px; color: var(--text-secondary); }

.modal-body { padding: 20px 24px; }
.form-row { margin-bottom: 16px; display: flex; flex-direction: column; gap: 6px; }
.form-row label { font-size: 12px; font-weight: 500; color: var(--text-secondary); }
.form-row input[type=text],
.form-row input[type=date],
.form-row select,
.form-row textarea {
  width: 100%; padding: 8px 10px;
  border: 1px solid var(--border); border-radius: 6px;
  background: var(--bg); color: var(--text-primary);
  font-size: 13px; box-sizing: border-box;
}
.form-row textarea { resize: vertical; }
.toggle { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px !important; font-weight: 400 !important; color: var(--text-primary) !important; }
.required { color: #e53e3e; }
.form-error { color: #e53e3e; font-size: 12px; margin-bottom: 8px; }

.modal-footer {
  display: flex; justify-content: flex-end; gap: 10px;
  padding: 16px 24px 20px;
  border-top: 1px solid var(--border);
}

.btn { padding: 8px 18px; border-radius: 6px; font-size: 13px; font-weight: 500; border: none; cursor: pointer; }
.btn-primary { background: var(--accent); color: #fff; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-secondary { background: var(--surface-2); color: var(--text-primary); border: 1px solid var(--border); }

.empty-state { color: var(--text-secondary); font-size: 14px; text-align: center; padding: 48px 0; }
.loading-state { color: var(--text-secondary); font-size: 14px; padding: 24px 0; }
</style>
