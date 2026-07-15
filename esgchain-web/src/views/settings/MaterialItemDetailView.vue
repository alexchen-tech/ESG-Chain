<template>
  <div class="page-container">
    <!-- 頁頭 -->
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <button class="btn btn-secondary btn-sm" @click="$router.push('/materials/items')">← 返回列表</button>
        <div v-if="item">
          <h1 class="page-title">{{ item.name }}</h1>
          <p class="page-subtitle" style="font-family:var(--font-mono);">{{ item.item_code }}<span v-if="item.hs_code"> · {{ item.hs_code }}</span><span v-if="item.material_group"> · {{ item.material_group.name }}</span></p>
        </div>
      </div>
      <div v-if="item" style="display:flex;gap:8px;align-items:center;">
        <span class="badge" :class="item.is_active ? 'badge-green' : 'badge-gray'">{{ item.is_active ? '啟用' : '停用' }}</span>
        <button class="btn btn-primary btn-sm" @click="showEditModal = true">編輯</button>
      </div>
    </div>

    <div v-if="isLoading" class="loading-mask">載入中...</div>
    <div v-else-if="!item" class="empty-state"><p>料號不存在</p></div>
    <div v-else class="detail-layout">
      <div class="detail-main">

        <!-- Tab 導覽 -->
        <div class="detail-tabs">
          <button v-for="t in TABS" :key="t.key" class="detail-tab" :class="{ active: activeTab === t.key }" @click="switchTab(t.key)">
            {{ t.label }}
          </button>
        </div>

        <!-- 基本資料 -->
        <div v-show="activeTab === 'info'" class="detail-section tab-panel">
          <div class="detail-grid" style="grid-template-columns:repeat(3,1fr);">
            <div class="detail-item">
              <span class="detail-label">料號代碼</span>
              <span class="detail-value font-mono" style="font-weight:700;color:var(--accent);">{{ item.item_code }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">品名</span>
              <span class="detail-value">{{ item.name }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">HS Code</span>
              <span class="detail-value font-mono">{{ item.hs_code || '—' }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">物料群組</span>
              <span class="detail-value">{{ item.material_group?.name || '—' }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">計量單位</span>
              <span class="detail-value font-mono">{{ item.unit || '—' }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">淨重（kg/unit）</span>
              <span class="detail-value font-mono">{{ item.net_weight != null ? item.net_weight : '—' }}</span>
            </div>
            <div class="detail-item" style="grid-column:1/-1;">
              <span class="detail-label">說明</span>
              <span class="detail-value">{{ item.description || '—' }}</span>
            </div>
          </div>

          <div class="section-title" style="margin-top:20px;">可回收成分</div>
          <div class="detail-grid" style="grid-template-columns:repeat(4,1fr);">
            <div class="detail-item">
              <span class="detail-label">PCR（消費後回收）</span>
              <span class="detail-value font-mono">{{ item.pcr_percentage != null ? item.pcr_percentage + '%' : '—' }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">PIR（製程廢料）</span>
              <span class="detail-value font-mono">{{ item.pir_percentage != null ? item.pir_percentage + '%' : '—' }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Bio-based</span>
              <span class="detail-value font-mono">{{ item.bio_based_percentage != null ? item.bio_based_percentage + '%' : '—' }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">可回收性評級</span>
              <span class="detail-value">{{ RECYCLABILITY_LABELS[item.recyclability_rating ?? ''] ?? '—' }}</span>
            </div>
          </div>
        </div>

        <!-- 碳排資料庫 -->
        <div v-show="activeTab === 'emissions'" class="detail-section tab-panel">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
            <span class="section-title" style="margin:0;padding:0;border:none;">碳排資料庫</span>
            <button class="btn btn-primary btn-sm" @click="openBuyerInputModal">＋ 代填碳排</button>
          </div>
          <div v-if="emissionLoading" class="empty-inline">載入中...</div>
          <div v-else-if="!emissionGroups.length" class="empty-inline">尚無碳排記錄</div>
          <table v-else class="data-table">
            <thead>
              <tr>
                <th>供應商</th>
                <th style="text-align:right;">最新碳排值</th>
                <th>來源</th>
                <th>提報期間</th>
                <th>記錄數</th>
                <th>操作</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="g in emissionGroups" :key="g.supplier_id ?? 'buyer'">
                <td>
                  <span style="font-size:13px;font-weight:500;">{{ g.supplier_name }}</span>
                  <span v-if="!g.latest.supplier_id" class="badge badge-gray" style="font-size:10px;margin-left:6px;">未指定 BOM</span>
                </td>
                <td style="text-align:right;">
                  <span v-if="g.latest.is_estimated" style="margin-right:4px;" title="AI 估算">🤖</span>
                  <span v-if="g.latest.is_flagged" style="margin-right:4px;" :title="g.latest.flag_reason ?? '異常'">⚠️</span>
                  <span class="font-mono" style="font-weight:700;">{{ g.latest.emissions_value.toFixed(4) }}</span>
                  <span style="font-size:11px;color:var(--text-secondary);margin-left:3px;">kgCO₂e/{{ item.unit || '件' }}</span>
                </td>
                <td><span class="source-badge" :class="`source-${g.latest.source}`">{{ sourceLabel(g.latest.source) }}</span></td>
                <td style="font-size:12px;color:var(--text-secondary);">{{ g.latest.reported_period || '—' }}</td>
                <td style="font-size:12px;color:var(--text-secondary);">{{ g.history_count }} 筆</td>
                <td>
                  <button v-if="!g.latest.is_flagged" class="btn btn-secondary btn-sm" style="font-size:11px;" @click="openFlagModal(g.latest)">標記異常</button>
                  <button v-else class="btn btn-secondary btn-sm" style="font-size:11px;color:#d97706;" :disabled="isSubmitting" @click="doUnflag(g.latest.id)">取消標記</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- 來源供應商 -->
        <div v-show="activeTab === 'suppliers'" class="detail-section tab-panel">
          <div class="section-title" style="margin-bottom:12px;">來源供應商</div>
          <p style="font-size:12px;color:var(--text-secondary);margin-bottom:12px;">從 BOM 明細推算：此料號在各 BOM 中指定的 primary supplier</p>
          <div v-if="bomSuppliersLoading" class="empty-inline">載入中...</div>
          <div v-else-if="!bomSuppliers.length" class="empty-inline">此料號尚未被指定於任何 BOM</div>
          <table v-else class="data-table">
            <thead>
              <tr>
                <th>供應商名稱</th>
                <th style="text-align:right;width:90px;">BOM 數量</th>
                <th style="text-align:right;">最新碳排值</th>
                <th>來源</th>
                <th>提報期間</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in bomSuppliers" :key="s.supplier_id">
                <td style="font-weight:500;">{{ s.supplier_name }}</td>
                <td style="text-align:right;" class="font-mono">{{ s.bom_count }}</td>
                <td style="text-align:right;">
                  <span v-if="s.latest_emission">
                    <span class="font-mono" style="font-weight:700;">{{ s.latest_emission.emissions_value.toFixed(4) }}</span>
                    <span style="font-size:11px;color:var(--text-secondary);margin-left:3px;">kgCO₂e/{{ item.unit || '件' }}</span>
                  </span>
                  <span v-else style="color:#d97706;font-size:13px;">● 待填報</span>
                </td>
                <td>
                  <span v-if="s.latest_emission" class="source-badge" :class="`source-${s.latest_emission.source}`">{{ sourceLabel(s.latest_emission.source) }}</span>
                  <span v-else style="color:var(--text-secondary);">—</span>
                </td>
                <td style="font-size:12px;color:var(--text-secondary);">{{ s.latest_emission?.reported_period || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- 化學組成 -->
        <div v-show="activeTab === 'chemicals'" class="detail-section tab-panel">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
            <span class="section-title" style="margin:0;padding:0;border:none;">化學組成</span>
            <button class="btn btn-primary btn-sm" @click="openAddChemicalModal">＋ 新增化學成分</button>
          </div>
          <div v-if="chemicalLoading" class="empty-inline">載入中...</div>
          <div v-else-if="!chemicalList.length" class="empty-inline">尚無化學成分記錄</div>
          <table v-else class="data-table">
            <thead>
              <tr><th>CAS No.</th><th>物質名稱</th><th>重量佔比（%）</th><th>法規清單</th><th>來源</th><th>操作</th></tr>
            </thead>
            <tbody>
              <tr v-for="chem in chemicalList" :key="chem.id">
                <td class="font-mono" style="font-size:12px;">{{ chem.cas_no }}</td>
                <td>{{ chem.chemical?.substance_name ?? '—' }}</td>
                <td class="font-mono">{{ chem.weight_percentage != null ? chem.weight_percentage + '%' : '—' }}</td>
                <td>
                  <span v-if="chem.chemical?.regulated_lists && Object.keys(chem.chemical.regulated_lists).length">
                    <span v-for="(v, k) in chem.chemical.regulated_lists" :key="k" class="badge badge-red" style="margin-right:4px;font-size:10px;">{{ k }}</span>
                  </span>
                  <span v-else style="color:var(--text-secondary);">—</span>
                </td>
                <td><span class="badge badge-gray" style="font-size:11px;">{{ { portal_supplier:'供應商填報', buyer_input:'買方代填', ai_estimated:'AI估算' }[chem.source] ?? chem.source }}</span></td>
                <td><button class="btn btn-danger btn-sm" style="font-size:11px;" @click="deleteChemical(chem)">刪除</button></td>
              </tr>
            </tbody>
          </table>

          <!-- 合規掃描結果 -->
          <div style="margin-top:20px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
              <span class="section-title" style="margin:0;padding:0;border:none;">合規掃描結果</span>
              <button class="btn btn-secondary btn-sm" :disabled="scanLoading" @click="doScan">{{ scanLoading ? '掃描中...' : '重新掃描' }}</button>
            </div>
            <div v-if="alertLoading" class="empty-inline">載入中...</div>
            <div v-else-if="!chemicalAlerts.length" style="font-size:13px;color:#16a34a;">✓ 未偵測到受管制物質</div>
            <div v-else style="display:flex;flex-direction:column;gap:6px;">
              <div v-for="a in chemicalAlerts" :key="a.id" class="alert-row" :class="`alert-${a.alert_level}`">
                <span class="alert-tag">{{ { reach_svhc:'REACH SVHC', rohs:'RoHS' }[a.regulated_list] ?? a.regulated_list }}</span>
                <span style="font-size:13px;font-weight:500;">{{ a.substance_name ?? a.cas_no }}</span>
                <span v-if="a.restriction_notes" style="font-size:12px;color:var(--text-secondary);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ a.restriction_notes }}</span>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- 編輯 Modal -->
    <div v-if="showEditModal && item" class="modal-overlay" @click.self="showEditModal=false">
      <div class="modal" style="min-width:540px;">
        <div class="modal-header">
          <span class="modal-title">編輯料號 — {{ item.item_code }}</span>
          <button class="modal-close" @click="showEditModal=false">×</button>
        </div>
        <div style="display:flex;gap:12px;">
          <div class="form-group" style="flex:1;">
            <label class="form-label">品名 *</label>
            <input v-model="editForm.name" class="form-input" />
          </div>
          <div class="form-group" style="flex:0 0 90px;">
            <label class="form-label">計量單位</label>
            <input v-model="editForm.unit" class="form-input" />
          </div>
        </div>
        <div style="display:flex;gap:12px;">
          <div class="form-group" style="flex:1;">
            <label class="form-label">HS Code</label>
            <input v-model="editForm.hs_code" class="form-input font-mono" />
          </div>
          <div class="form-group" style="flex:1;">
            <label class="form-label">物料群組</label>
            <select v-model="editForm.material_group_id" class="form-select">
              <option value="">—</option>
              <option v-for="g in materialGroups" :key="g.id" :value="g.id">{{ g.name }}</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">說明</label>
          <textarea v-model="editForm.description" class="form-textarea" rows="2"></textarea>
        </div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;" class="form-group">
          <div>
            <label class="form-label">淨重（kg）</label>
            <input v-model.number="editForm.net_weight" class="form-input font-mono" type="number" min="0" step="0.0001" />
          </div>
          <div>
            <label class="form-label">PCR（%）</label>
            <input v-model.number="editForm.pcr_percentage" class="form-input font-mono" type="number" min="0" max="100" step="0.01" />
          </div>
          <div>
            <label class="form-label">PIR（%）</label>
            <input v-model.number="editForm.pir_percentage" class="form-input font-mono" type="number" min="0" max="100" step="0.01" />
          </div>
          <div>
            <label class="form-label">Bio-based（%）</label>
            <input v-model.number="editForm.bio_based_percentage" class="form-input font-mono" type="number" min="0" max="100" step="0.01" />
          </div>
        </div>
        <div class="form-group" style="display:flex;align-items:center;gap:16px;">
          <div style="flex:0 0 200px;">
            <label class="form-label">可回收性評級</label>
            <select v-model="editForm.recyclability_rating" class="form-select">
              <option value="">未設定</option>
              <option value="high">高（易回收）</option>
              <option value="medium">中</option>
              <option value="low">低（難回收）</option>
              <option value="not_rated">未評估</option>
            </select>
          </div>
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;margin-top:16px;">
            <input type="checkbox" v-model="editForm.is_active" />
            <span>啟用此料號</span>
          </label>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showEditModal=false">取消</button>
          <button class="btn btn-primary" :disabled="isSubmitting" @click="saveEdit">{{ isSubmitting ? '儲存中...' : '儲存變更' }}</button>
        </div>
      </div>
    </div>

    <!-- 代填碳排 Modal -->
    <div v-if="buyerInputModal.show" class="modal-overlay" @click.self="buyerInputModal.show=false">
      <div class="modal" style="min-width:440px;">
        <div class="modal-header">
          <span class="modal-title">代填碳排</span>
          <button class="modal-close" @click="buyerInputModal.show=false">×</button>
        </div>
        <div class="form-group">
          <label class="form-label">碳排值（kgCO₂e/件）*</label>
          <input v-model.number="buyerInputModal.emissions_value" type="number" min="0" step="0.000001" class="form-input font-mono" placeholder="0.000000" />
        </div>
        <div style="display:flex;gap:12px;">
          <div class="form-group" style="flex:1;">
            <label class="form-label">提報期間</label>
            <select v-model="buyerInputModal.reported_period" class="form-select">
              <option value="">不指定</option>
              <option v-for="p in reportedPeriodOptions" :key="p" :value="p">{{ p }}</option>
            </select>
          </div>
          <div class="form-group" style="flex:1;">
            <label class="form-label">計算方法</label>
            <input v-model="buyerInputModal.calculation_method" class="form-input" placeholder="ISO 14067..." />
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="buyerInputModal.show=false">取消</button>
          <button class="btn btn-primary" :disabled="isSubmitting || !buyerInputModal.emissions_value" @click="submitBuyerInput">
            {{ isSubmitting ? '儲存中...' : '確認代填' }}
          </button>
        </div>
      </div>
    </div>

    <!-- 標記異常 Modal -->
    <div v-if="flagModal.show" class="modal-overlay" @click.self="flagModal.show=false">
      <div class="modal" style="max-width:400px;">
        <div class="modal-header">
          <span class="modal-title">標記異常</span>
          <button class="modal-close" @click="flagModal.show=false">×</button>
        </div>
        <div class="form-group">
          <label class="form-label">異常原因 *</label>
          <textarea v-model="flagModal.reason" class="form-textarea" rows="3"></textarea>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="flagModal.show=false">取消</button>
          <button class="btn btn-danger" :disabled="isSubmitting || !flagModal.reason" @click="doFlag">{{ isSubmitting ? '標記中...' : '確認標記' }}</button>
        </div>
      </div>
    </div>

    <!-- 新增化學成分 Modal -->
    <div v-if="chemicalModal.show" class="modal-overlay" @click.self="chemicalModal.show=false">
      <div class="modal" style="min-width:420px;">
        <div class="modal-header">
          <span class="modal-title">新增化學成分</span>
          <button class="modal-close" @click="chemicalModal.show=false">×</button>
        </div>
        <div style="padding:16px;display:flex;flex-direction:column;gap:12px;">
          <div>
            <label class="form-label">CAS No. *</label>
            <div style="display:flex;gap:8px;">
              <input v-model="chemicalModal.cas_no" class="form-input" placeholder="7439-97-6" style="flex:1;" />
              <button class="btn btn-secondary btn-sm" :disabled="!chemicalModal.cas_no || chemicalModal.lookupLoading" @click="lookupCas">查詢</button>
            </div>
            <p v-if="chemicalModal.lookupResult" style="font-size:12px;color:var(--accent);margin-top:6px;">✓ {{ chemicalModal.lookupResult.substance_name }}</p>
            <p v-if="chemicalModal.lookupError" style="font-size:12px;color:#dc2626;margin-top:6px;">{{ chemicalModal.lookupError }}</p>
          </div>
          <div>
            <label class="form-label">重量佔比（%）</label>
            <input v-model.number="chemicalModal.weight_percentage" class="form-input font-mono" type="number" min="0" max="100" step="0.01" />
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="chemicalModal.show=false">取消</button>
          <button class="btn btn-primary" :disabled="!chemicalModal.cas_no || isSubmitting" @click="doAddChemical">{{ isSubmitting ? '新增中...' : '確認新增' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { materialItemApi, materialGroupApi, type MaterialItem, type MaterialGroup, type MaterialBomSupplier } from '@/api/modules/compliance'
import { materialEmissionApi, type MaterialEmissionGroup, type MaterialItemEmission } from '@/api/modules/materialEmissions'
import { chemicalApi, type MaterialItemChemical } from '@/api/modules/suppliers'
import http from '@/api/http'

const TABS = [
  { key: 'info', label: '識別資訊' },
  { key: 'emissions', label: '碳排資料庫' },
  { key: 'suppliers', label: '來源供應商' },
  { key: 'chemicals', label: '化學組成' },
]

const RECYCLABILITY_LABELS: Record<string, string> = {
  high: '高（易回收）',
  medium: '中',
  low: '低（難回收）',
  not_rated: '未評估',
}

export default defineComponent({
  name: 'MaterialItemDetailView',

  data() {
    return {
      TABS,
      RECYCLABILITY_LABELS,
      isLoading: false,
      isSubmitting: false,
      item: null as MaterialItem | null,
      materialGroups: [] as MaterialGroup[],
      activeTab: 'info',

      // 碳排
      emissionLoading: false,
      emissionGroups: [] as MaterialEmissionGroup[],

      // 來源供應商
      bomSuppliersLoading: false,
      bomSuppliers: [] as MaterialBomSupplier[],

      // 化學組成
      chemicalLoading: false,
      chemicalList: [] as MaterialItemChemical[],
      chemicalAlerts: [] as any[],
      alertLoading: false,
      scanLoading: false,

      // 編輯
      showEditModal: false,
      editForm: {
        name: '', hs_code: '', material_group_id: '', unit: '', description: '',
        is_active: true,
        net_weight: null as number | null,
        pcr_percentage: null as number | null,
        pir_percentage: null as number | null,
        bio_based_percentage: null as number | null,
        recyclability_rating: '',
      },

      // 代填碳排
      buyerInputModal: { show: false, emissions_value: null as number | null, reported_period: '', calculation_method: '' },

      // 標記異常
      flagModal: { show: false, emissionId: '', reason: '' },

      // 新增化學成分
      chemicalModal: {
        show: false, cas_no: '', weight_percentage: null as number | null,
        lookupLoading: false, lookupResult: null as any, lookupError: '',
      },
    }
  },

  computed: {
    itemId(): string { return this.$route.params.id as string },
    reportedPeriodOptions(): string[] {
      const year = new Date().getFullYear()
      const opts: string[] = []
      for (let y = year; y >= year - 2; y--) {
        for (const q of ['Q4', 'Q3', 'Q2', 'Q1']) opts.push(`${y}-${q}`)
      }
      return opts
    },
  },

  async mounted() {
    this.isLoading = true
    try {
      const [itemRes, groupRes] = await Promise.all([
        materialItemApi.show(this.itemId),
        materialGroupApi.list(),
      ])
      this.item = itemRes.data.data
      this.materialGroups = groupRes.data.data
    } finally {
      this.isLoading = false
    }
  },

  methods: {
    async switchTab(key: string) {
      this.activeTab = key
      if (key === 'emissions' && !this.emissionGroups.length) await this.loadEmissions()
      if (key === 'suppliers' && !this.bomSuppliers.length) await this.loadBomSuppliers()
      if (key === 'chemicals' && !this.chemicalList.length) await this.loadChemicals()
    },

    async loadEmissions() {
      this.emissionLoading = true
      try {
        const { data } = await materialEmissionApi.list(this.itemId)
        this.emissionGroups = data.data
      } finally { this.emissionLoading = false }
    },

    async loadBomSuppliers() {
      this.bomSuppliersLoading = true
      try {
        const { data } = await materialItemApi.bomSuppliers(this.itemId)
        this.bomSuppliers = data.data
      } finally { this.bomSuppliersLoading = false }
    },

    async loadChemicals() {
      this.chemicalLoading = true
      this.alertLoading = true
      try {
        const [chemRes, alertRes] = await Promise.all([
          chemicalApi.listChemicals(this.itemId),
          http.get<{ success: boolean; data: { data: any[] } }>(`/api/v1/chemical-compliance-alerts?material_item_id=${this.itemId}&per_page=50`),
        ])
        this.chemicalList = chemRes.data.data
        this.chemicalAlerts = alertRes.data.data?.data ?? []
      } finally { this.chemicalLoading = false; this.alertLoading = false }
    },

    sourceLabel(source: string): string {
      return { 'portal-self': '供應商提報', 'buyer-input': '買方代填', 'ai-estimated': 'AI估算', 'system_default': '系統預設' }[source] ?? source
    },

    openBuyerInputModal() {
      this.buyerInputModal = { show: true, emissions_value: null, reported_period: '', calculation_method: '' }
    },

    async submitBuyerInput() {
      if (!this.buyerInputModal.emissions_value) return
      this.isSubmitting = true
      try {
        await materialEmissionApi.create(this.itemId, {
          emissions_value: this.buyerInputModal.emissions_value,
          reported_period: this.buyerInputModal.reported_period || undefined,
          calculation_method: this.buyerInputModal.calculation_method || undefined,
        })
        this.buyerInputModal.show = false
        await this.loadEmissions()
      } catch (e: any) { alert(e?.response?.data?.message ?? '代填失敗') }
      finally { this.isSubmitting = false }
    },

    openFlagModal(emission: MaterialItemEmission) {
      this.flagModal = { show: true, emissionId: emission.id, reason: '' }
    },

    async doFlag() {
      if (!this.flagModal.reason) return
      this.isSubmitting = true
      try {
        await materialEmissionApi.flag(this.flagModal.emissionId, this.flagModal.reason)
        this.flagModal.show = false
        await this.loadEmissions()
      } finally { this.isSubmitting = false }
    },

    async doUnflag(emissionId: string) {
      this.isSubmitting = true
      try {
        await materialEmissionApi.unflag(emissionId)
        await this.loadEmissions()
      } finally { this.isSubmitting = false }
    },

    openAddChemicalModal() {
      this.chemicalModal = { show: true, cas_no: '', weight_percentage: null, lookupLoading: false, lookupResult: null, lookupError: '' }
    },

    async lookupCas() {
      this.chemicalModal.lookupLoading = true
      this.chemicalModal.lookupResult = null
      this.chemicalModal.lookupError = ''
      try {
        const { data } = await chemicalApi.lookup(this.chemicalModal.cas_no)
        this.chemicalModal.lookupResult = data.data
      } catch { this.chemicalModal.lookupError = '查無此 CAS No.' }
      finally { this.chemicalModal.lookupLoading = false }
    },

    async doAddChemical() {
      this.isSubmitting = true
      try {
        await chemicalApi.addChemical(this.itemId, {
          cas_no: this.chemicalModal.cas_no,
          weight_percentage: this.chemicalModal.weight_percentage ?? undefined,
          source: 'buyer_input',
        })
        this.chemicalModal.show = false
        await this.loadChemicals()
      } finally { this.isSubmitting = false }
    },

    async deleteChemical(chem: MaterialItemChemical) {
      if (!confirm(`確定刪除 CAS ${chem.cas_no}？`)) return
      await chemicalApi.deleteChemical(this.itemId, chem.id)
      await this.loadChemicals()
    },

    async doScan() {
      this.scanLoading = true
      try {
        await http.post(`/api/v1/material-items/${this.itemId}/chemical-compliance-scan`)
        const { data } = await http.get<{ success: boolean; data: { data: any[] } }>(`/api/v1/chemical-compliance-alerts?material_item_id=${this.itemId}&per_page=50`)
        this.chemicalAlerts = data.data?.data ?? []
      } catch (e: any) { alert(e?.response?.data?.message ?? '掃描失敗') }
      finally { this.scanLoading = false }
    },

    openEditModal() {
      if (!this.item) return
      this.editForm = {
        name: this.item.name,
        hs_code: this.item.hs_code ?? '',
        material_group_id: this.item.material_group_id ?? '',
        unit: this.item.unit ?? '',
        description: this.item.description ?? '',
        is_active: this.item.is_active,
        net_weight: this.item.net_weight ?? null,
        pcr_percentage: this.item.pcr_percentage ?? null,
        pir_percentage: this.item.pir_percentage ?? null,
        bio_based_percentage: this.item.bio_based_percentage ?? null,
        recyclability_rating: this.item.recyclability_rating ?? '',
      }
    },

    async saveEdit() {
      if (!this.editForm.name) return
      this.isSubmitting = true
      try {
        const { data } = await materialItemApi.update(this.itemId, {
          name: this.editForm.name,
          hs_code: this.editForm.hs_code || null,
          material_group_id: this.editForm.material_group_id || null,
          unit: this.editForm.unit || null,
          description: this.editForm.description || null,
          is_active: this.editForm.is_active,
          net_weight: this.editForm.net_weight,
          pcr_percentage: this.editForm.pcr_percentage,
          pir_percentage: this.editForm.pir_percentage,
          bio_based_percentage: this.editForm.bio_based_percentage,
          recyclability_rating: (this.editForm.recyclability_rating as any) || null,
        })
        this.item = data.data
        this.showEditModal = false
      } catch (e: any) { alert(e?.response?.data?.message ?? '儲存失敗') }
      finally { this.isSubmitting = false }
    },
  },

  watch: {
    showEditModal(val: boolean) {
      if (val) this.openEditModal()
    },
  },
})
</script>

<style scoped>
/* ── 佈局（直接複用供應商詳情 class 結構）── */
.detail-layout { display: flex; flex-direction: column; gap: 0; }
.detail-main { display: flex; flex-direction: column; gap: 0; }

.detail-tabs {
  display: flex;
  background: var(--surface);
  border: 1px solid var(--border);
  border-bottom: none;
  border-radius: 8px 8px 0 0;
  overflow: hidden;
}
.detail-tab {
  padding: 11px 20px; border: none; background: none; cursor: pointer;
  font-size: 13.5px; font-weight: 500; color: #57534e;
  border-bottom: 2px solid transparent; margin-bottom: -1px;
  transition: all 0.15s; white-space: nowrap;
}
.detail-tab:hover { color: var(--text-primary); background: var(--surface-2); }
.detail-tab.active { color: var(--accent); border-bottom-color: var(--accent); font-weight: 600; background: var(--surface); }

.tab-panel {
  border-radius: 0 0 8px 8px !important;
  border-top: none !important;
  margin-bottom: 16px;
}
.detail-section {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 8px; padding: 20px 24px; margin-bottom: 16px;
}
.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px 32px; }
.detail-item { display: flex; flex-direction: column; gap: 6px; }
.detail-label { font-size: 11px; color: #a8998f; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; }
.detail-value { font-size: 14px; color: var(--text-primary); line-height: 1.45; }
.section-title {
  font-size: 11.5px; font-weight: 700; color: var(--text-secondary);
  text-transform: uppercase; letter-spacing: 0.07em;
  margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid var(--border);
}
.empty-inline { font-size: 13px; color: var(--text-secondary); padding: 8px 0; }

/* 碳排 source badge */
.source-badge { font-size: 10px; padding: 2px 7px; border-radius: 10px; font-weight: 600; white-space: nowrap; }
.source-portal-self { background: #d1fae5; color: #065f46; }
.source-buyer-input { background: #dbeafe; color: #1e40af; }
.source-ai-estimated, .source-system_default { background: #f3f4f6; color: #6b7280; border: 1px dashed #d1d5db; }

/* 合規 alert row */
.alert-row { display: flex; align-items: center; gap: 10px; padding: 8px 12px; border-radius: 6px; margin-bottom: 4px; }
.alert-tag { font-size: 11px; font-weight: 700; padding: 2px 7px; border-radius: 4px; white-space: nowrap; }
.alert-critical { background: #fef2f2; }
.alert-critical .alert-tag { background: #fee2e2; color: #dc2626; }
.alert-warning { background: #fffbeb; }
.alert-warning .alert-tag { background: #fef3c7; color: #d97706; }
.alert-info { background: #eff6ff; }
.alert-info .alert-tag { background: #dbeafe; color: #1d4ed8; }
</style>
