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
          <div class="detail-grid" style="grid-template-columns:repeat(6,1fr);">
            <div class="detail-item">
              <span class="detail-label">纖維類型</span>
              <span class="detail-value">{{ FIBER_TYPE_LABELS[item.fiber_type ?? ''] ?? '—' }}</span>
            </div>
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
            <div class="detail-item">
              <span class="detail-label">塑膠微纖維釋放風險</span>
              <span class="detail-value">{{ MICROFIBER_RISK_LABELS[item.microfiber_release_risk ?? ''] ?? '—' }}</span>
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
          <template v-else>
            <!-- 同物料跨供應商比較摘要 -->
            <div v-if="emissionStats" class="compare-stats">
              <div class="compare-stat">
                <div class="compare-stat-label">最低（最佳）</div>
                <div class="compare-stat-value">{{ emissionStats.min.value.toFixed(4) }}<span class="compare-stat-unit">/{{ item.unit || '件' }}</span></div>
                <div class="compare-stat-supplier">{{ maskSupplierName(emissionStats.min.supplier_name) }}</div>
              </div>
              <div class="compare-stat">
                <div class="compare-stat-label">平均</div>
                <div class="compare-stat-value">{{ emissionStats.avg.toFixed(4) }}<span class="compare-stat-unit">/{{ item.unit || '件' }}</span></div>
                <div class="compare-stat-supplier">{{ emissionStats.count }} 家可比較</div>
              </div>
              <div class="compare-stat">
                <div class="compare-stat-label">最高</div>
                <div class="compare-stat-value">{{ emissionStats.max.value.toFixed(4) }}<span class="compare-stat-unit">/{{ item.unit || '件' }}</span></div>
                <div class="compare-stat-supplier">{{ maskSupplierName(emissionStats.max.supplier_name) }}</div>
              </div>
            </div>

            <table class="data-table">
              <thead>
                <tr>
                  <th>供應商</th>
                  <th style="text-align:right;">最新碳排值</th>
                  <th style="width:160px;">相對比較</th>
                  <th>來源</th>
                  <th>提報期間</th>
                  <th>記錄數</th>
                  <th>操作</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="g in sortedEmissionGroups" :key="g.supplier_id ?? 'buyer'">
                  <td>
                    <span style="font-size:13px;font-weight:500;">{{ maskSupplierName(g.supplier_name) }}</span>
                    <span v-if="!g.latest.supplier_id" class="badge badge-gray" style="font-size:10px;margin-left:6px;">未指定 BOM</span>
                  </td>
                  <td style="text-align:right;">
                    <span v-if="g.latest.is_estimated" style="margin-right:4px;" title="AI 估算">🤖</span>
                    <span v-if="g.latest.is_flagged" style="margin-right:4px;" :title="g.latest.flag_reason ?? '異常'">⚠️</span>
                    <span class="font-mono" style="font-weight:700;">{{ g.latest.emissions_value.toFixed(4) }}</span>
                    <span style="font-size:11px;color:var(--text-secondary);margin-left:3px;">kgCO₂e/{{ item.unit || '件' }}</span>
                  </td>
                  <td>
                    <div v-if="!g.latest.is_flagged && emissionStats" class="meter-cell">
                      <div class="meter-track">
                        <div class="meter-fill" :style="{ width: meterWidth(g.latest.emissions_value) + '%', background: meterColor(g.latest.emissions_value) }"></div>
                      </div>
                      <span v-if="g.latest.emissions_value === emissionStats.min.value" class="meter-tag meter-tag--best">最低</span>
                      <span v-else-if="g.latest.emissions_value === emissionStats.max.value" class="meter-tag meter-tag--worst">最高</span>
                    </div>
                    <span v-else style="font-size:11px;color:var(--text-secondary);">已標記異常，排除比較</span>
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
          </template>
        </div>

        <!-- 來源供應商 -->
        <div v-show="activeTab === 'suppliers'" class="detail-section tab-panel">
          <!-- 物料層級核可供應商清單 -->
          <div class="section-title" style="margin-bottom:8px;">核可供應商清單（主/備）</div>
          <p style="font-size:12px;color:var(--text-secondary);margin-bottom:12px;">
            對此物料只需登記一次，所有使用此物料的產品共用，不需在每個產品的 BOM 明細各自重複登記。
          </p>
          <div class="detail-grid" style="grid-template-columns:1fr 1fr auto;margin-bottom:12px;">
            <div class="detail-item">
              <span class="detail-label">供應商</span>
              <select v-model="addApprovedForm.supplier_id" class="form-select">
                <option value="">— 請選擇 —</option>
                <option v-for="s in allSuppliers" :key="s.id" :value="s.id">{{ maskSupplierName(s.name) }} ({{ s.code }})</option>
              </select>
            </div>
            <div class="detail-item">
              <span class="detail-label">角色</span>
              <select v-model="addApprovedForm.role" class="form-select">
                <option value="primary">主要供應商</option>
                <option value="alternate">備用供應商</option>
              </select>
            </div>
            <div class="detail-item" style="justify-content:flex-end;">
              <button class="btn btn-primary btn-sm" :disabled="!addApprovedForm.supplier_id || addingApproved" @click="addApprovedSupplier" style="margin-top:auto;">
                {{ addingApproved ? '新增中…' : '＋ 新增' }}
              </button>
            </div>
          </div>
          <div v-if="approvedSuppliersLoading" class="empty-inline">載入中...</div>
          <div v-else-if="!approvedSuppliers.length" class="empty-inline">尚無核可供應商，請新增第一筆</div>
          <table v-else class="data-table" style="margin-bottom:20px;">
            <thead>
              <tr><th>供應商</th><th>角色</th><th>來源</th><th style="width:160px;"></th></tr>
            </thead>
            <tbody>
              <tr v-for="s in approvedSuppliers" :key="s.id">
                <td style="font-weight:500;">{{ maskSupplierName(s.supplier?.name) }}</td>
                <td>
                  <span v-if="s.role === 'primary'" class="badge badge-green" style="font-size:10px;">主要供應商</span>
                  <span v-else style="font-size:11px;color:var(--text-secondary);">備用供應商</span>
                </td>
                <td>
                  <span class="source-badge" :class="`source-${s.source}`">{{ sourceLabel(s.source) }}</span>
                </td>
                <td>
                  <button v-if="s.role !== 'primary'" class="btn btn-secondary btn-sm" style="font-size:11px;" @click="setApprovedRole(s, 'primary')">設為主要</button>
                  <button class="btn btn-danger btn-sm" style="font-size:11px;margin-left:4px;" @click="removeApprovedSupplier(s)">移除</button>
                </td>
              </tr>
            </tbody>
          </table>

          <div class="section-title" style="margin-bottom:12px;">來源供應商分布</div>
          <p style="font-size:12px;color:var(--text-secondary);margin-bottom:12px;">
            此料號在各產品 BOM 中，目前指定的主供應商與其他核准供應商（AVL）碳排比較。可切換主供應商以改用更低碳的核准來源。
          </p>
          <div v-if="bomSuppliersLoading" class="empty-inline">載入中...</div>
          <div v-else-if="!bomSuppliers.length" class="empty-inline">此料號尚未被指定於任何 BOM</div>
          <div v-else class="bom-line-groups">
            <div v-for="line in bomSuppliers" :key="line.bom_line_id" class="bom-line-group">
              <div class="bom-line-group-head">
                <router-link :to="`/sales-products/${line.sales_product_id}`" class="bom-line-product-link">
                  {{ line.product_name }}
                  <span class="font-mono" style="font-size:11px;">{{ line.product_model_no || line.product_code }}</span>
                </router-link>
                <span v-if="hasSwitchOpportunity(line)" class="switch-hint">⚠ 有更低碳供應商可切換</span>
              </div>
              <table class="data-table">
                <thead>
                  <tr>
                    <th>供應商</th>
                    <th style="text-align:right;">碳排值</th>
                    <th>來源</th>
                    <th>角色</th>
                    <th style="width:100px;"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="s in line.suppliers" :key="s.bom_line_supplier_id" :class="{ 'is-primary-row': s.role === 'primary' }">
                    <td style="font-weight:500;">{{ maskSupplierName(s.supplier_name) }}</td>
                    <td style="text-align:right;">
                      <span v-if="s.emissions_value != null">
                        <span class="font-mono" style="font-weight:700;">{{ s.emissions_value.toFixed(4) }}</span>
                        <span style="font-size:11px;color:var(--text-secondary);margin-left:3px;">/{{ item.unit || '件' }}</span>
                      </span>
                      <span v-else style="color:#d97706;font-size:13px;">● 待填報</span>
                    </td>
                    <td>
                      <span v-if="s.source" class="source-badge" :class="`source-${s.source}`">{{ sourceLabel(s.source) }}</span>
                      <span v-else style="color:var(--text-secondary);">—</span>
                    </td>
                    <td>
                      <span v-if="s.role === 'primary'" class="badge badge-green" style="font-size:10px;">主供應商</span>
                      <span v-else style="font-size:11px;color:var(--text-secondary);">核准供應商</span>
                    </td>
                    <td>
                      <button
                        v-if="s.role !== 'primary'"
                        class="btn btn-secondary btn-sm"
                        style="font-size:11px;"
                        :disabled="switchingLineId === line.bom_line_id"
                        @click="doSwitchPrimary(line, s)"
                      >
                        {{ switchingLineId === line.bom_line_id ? '切換中…' : '設為主供應商' }}
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
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
          <div style="flex:0 0 200px;">
            <label class="form-label">塑膠微纖維釋放風險</label>
            <select v-model="editForm.microfiber_release_risk" class="form-select">
              <option value="">未設定</option>
              <option value="low">低</option>
              <option value="medium">中</option>
              <option value="high">高</option>
              <option value="not_rated">未評估</option>
            </select>
          </div>
          <div style="flex:0 0 220px;">
            <label class="form-label">纖維類型</label>
            <select v-model="editForm.fiber_type" class="form-select">
              <option value="">不適用（化學品/服務類）</option>
              <option value="cotton">棉 Cotton</option>
              <option value="organic_cotton">有機棉 Organic Cotton</option>
              <option value="cotton_blend">棉混紡 Cotton Blend</option>
              <option value="recycled_polyester">再生聚酯 Recycled Polyester</option>
              <option value="polyester">聚酯纖維 Polyester</option>
              <option value="nylon">尼龍 Nylon</option>
              <option value="wool">羊毛 Wool</option>
              <option value="linen">麻 Linen</option>
              <option value="silk">絲 Silk</option>
              <option value="lyocell">天絲 Lyocell</option>
              <option value="spandex">彈性纖維 Spandex</option>
              <option value="acrylic">壓克力纖維 Acrylic</option>
              <option value="rubber">橡膠 Rubber</option>
              <option value="wood_pulp">木漿 Wood Pulp</option>
              <option value="pta_feedstock">石化原料 PTA Feedstock</option>
              <option value="metal">金屬 Metal</option>
              <option value="other">其他</option>
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
import { materialItemApi, materialGroupApi, type MaterialItem, type MaterialGroup, type MaterialBomLineRow, type MaterialBomLineSupplierOption, type MaterialItemSupplier } from '@/api/modules/compliance'
import { materialEmissionApi, type MaterialEmissionGroup, type MaterialItemEmission } from '@/api/modules/materialEmissions'
import { chemicalApi, type MaterialItemChemical } from '@/api/modules/suppliers'
import http from '@/api/http'
import { maskSupplierName } from '@/utils/maskName'

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

const MICROFIBER_RISK_LABELS: Record<string, string> = {
  low: '低',
  medium: '中',
  high: '高',
  not_rated: '未評估',
}

const FIBER_TYPE_LABELS: Record<string, string> = {
  cotton: '棉 Cotton',
  organic_cotton: '有機棉 Organic Cotton',
  cotton_blend: '棉混紡 Cotton Blend',
  recycled_polyester: '再生聚酯 Recycled Polyester',
  polyester: '聚酯纖維 Polyester',
  nylon: '尼龍 Nylon',
  wool: '羊毛 Wool',
  linen: '麻 Linen',
  silk: '絲 Silk',
  lyocell: '天絲 Lyocell',
  spandex: '彈性纖維 Spandex',
  acrylic: '壓克力纖維 Acrylic',
  rubber: '橡膠 Rubber',
  wood_pulp: '木漿 Wood Pulp',
  pta_feedstock: '石化原料 PTA Feedstock',
  metal: '金屬 Metal',
  other: '其他',
}

export default defineComponent({
  name: 'MaterialItemDetailView',

  data() {
    return {
      TABS,
      RECYCLABILITY_LABELS,
      MICROFIBER_RISK_LABELS,
      FIBER_TYPE_LABELS,
      isLoading: false,
      isSubmitting: false,
      item: null as MaterialItem | null,
      materialGroups: [] as MaterialGroup[],
      activeTab: 'info',

      // 碳排
      emissionLoading: false,
      emissionGroups: [] as MaterialEmissionGroup[],

      // 物料層級核可供應商清單（主/備）
      approvedSuppliers: [] as MaterialItemSupplier[],
      approvedSuppliersLoading: false,
      allSuppliers: [] as { id: string; name: string; code: string | null }[],
      addApprovedForm: { supplier_id: '', role: 'alternate' as 'primary' | 'alternate' },
      addingApproved: false,

      // 來源供應商
      bomSuppliersLoading: false,
      bomSuppliers: [] as MaterialBomLineRow[],
      switchingLineId: '' as string,

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
        microfiber_release_risk: '',
        fiber_type: '',
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
    // 依最新碳排值由低到高排序（排除異常標記，讓最具參考價值的供應商排最前）
    sortedEmissionGroups(): MaterialEmissionGroup[] {
      return [...this.emissionGroups].sort((a, b) => {
        if (a.latest.is_flagged !== b.latest.is_flagged) return a.latest.is_flagged ? 1 : -1
        return a.latest.emissions_value - b.latest.emissions_value
      })
    },
    // 同物料跨供應商比較統計（排除已標記異常的記錄）
    emissionStats(): { min: { value: number; supplier_name: string }; max: { value: number; supplier_name: string }; avg: number; count: number } | null {
      const comparable = this.emissionGroups.filter(g => !g.latest.is_flagged)
      if (!comparable.length) return null
      const values = comparable.map(g => g.latest.emissions_value)
      const minGroup = comparable.reduce((a, b) => a.latest.emissions_value <= b.latest.emissions_value ? a : b)
      const maxGroup = comparable.reduce((a, b) => a.latest.emissions_value >= b.latest.emissions_value ? a : b)
      return {
        min: { value: minGroup.latest.emissions_value, supplier_name: minGroup.supplier_name },
        max: { value: maxGroup.latest.emissions_value, supplier_name: maxGroup.supplier_name },
        avg: values.reduce((s, v) => s + v, 0) / values.length,
        count: comparable.length,
      }
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
    maskSupplierName,
    async switchTab(key: string) {
      this.activeTab = key
      if (key === 'emissions' && !this.emissionGroups.length) await this.loadEmissions()
      if (key === 'suppliers' && !this.bomSuppliers.length) await this.loadBomSuppliers()
      if (key === 'suppliers' && !this.approvedSuppliers.length) await this.loadApprovedSuppliers()
      if (key === 'suppliers' && !this.allSuppliers.length) await this.loadAllSuppliers()
      if (key === 'chemicals' && !this.chemicalList.length) await this.loadChemicals()
    },

    async loadEmissions() {
      this.emissionLoading = true
      try {
        const { data } = await materialEmissionApi.list(this.itemId)
        this.emissionGroups = data.data
      } finally { this.emissionLoading = false }
    },

    // ── 物料層級核可供應商清單 ──
    async loadApprovedSuppliers() {
      this.approvedSuppliersLoading = true
      try {
        const { data } = await materialItemApi.approvedSuppliers(this.itemId)
        this.approvedSuppliers = data.data
      } finally { this.approvedSuppliersLoading = false }
    },
    async loadAllSuppliers() {
      try {
        const http = (await import('@/api/http')).default
        const res = await http.get<any>('/api/v1/suppliers?per_page=500')
        this.allSuppliers = res.data?.data?.data ?? res.data?.data ?? []
      } catch { /* silent */ }
    },
    async addApprovedSupplier() {
      if (!this.addApprovedForm.supplier_id) return
      this.addingApproved = true
      try {
        await materialItemApi.addApprovedSupplier(this.itemId, { ...this.addApprovedForm })
        this.addApprovedForm = { supplier_id: '', role: 'alternate' }
        await this.loadApprovedSuppliers()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '新增失敗')
      } finally { this.addingApproved = false }
    },
    async setApprovedRole(s: MaterialItemSupplier, role: 'primary' | 'alternate') {
      try {
        await materialItemApi.setApprovedSupplierRole(this.itemId, s.id, role)
        await this.loadApprovedSuppliers()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '操作失敗')
      }
    },
    async removeApprovedSupplier(s: MaterialItemSupplier) {
      if (!confirm(`確認將「${maskSupplierName(s.supplier?.name)}」從此物料的核可清單移除？`)) return
      try {
        await materialItemApi.removeApprovedSupplier(this.itemId, s.id)
        await this.loadApprovedSuppliers()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '移除失敗')
      }
    },

    async loadBomSuppliers() {
      this.bomSuppliersLoading = true
      try {
        const { data } = await materialItemApi.bomSuppliers(this.itemId)
        this.bomSuppliers = data.data
      } finally { this.bomSuppliersLoading = false }
    },

    // 該 BOM 行是否存在比目前主供應商碳排更低的核准供應商（提示切換機會）
    hasSwitchOpportunity(line: MaterialBomLineRow): boolean {
      const primary = line.suppliers.find(s => s.role === 'primary')
      if (!primary || primary.emissions_value == null) return false
      return line.suppliers.some(s => s.role !== 'primary' && !s.is_flagged && s.emissions_value != null && s.emissions_value < primary.emissions_value!)
    },

    async doSwitchPrimary(line: MaterialBomLineRow, target: MaterialBomLineSupplierOption) {
      if (!confirm(`確認將「${line.product_name}」此物料的主供應商切換為 ${maskSupplierName(target.supplier_name)}？\n將觸發 PCF 重新計算。`)) return
      this.switchingLineId = line.bom_line_id
      try {
        await materialItemApi.switchPrimarySupplier(this.itemId, line.bom_line_id, target.supplier_id)
        await this.loadBomSuppliers()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '切換失敗')
      } finally { this.switchingLineId = '' }
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

    // 相對比較 meter：此供應商碳排值在同物料所有可比較供應商中的相對位置（0–100%）
    meterWidth(value: number): number {
      const s = this.emissionStats
      if (!s) return 0
      if (s.max.value === s.min.value) return 50
      return ((value - s.min.value) / (s.max.value - s.min.value)) * 100
    },
    // 單一序列強度色階（淺→深綠），數值越高（碳排越高）顏色越深；純相對排名，非絕對門檻
    meterColor(value: number): string {
      const ratio = this.meterWidth(value) / 100
      const ramp = ['#d4e8dd', '#a3cbb8', '#5a9478', '#1a4d3e']
      const idx = Math.min(ramp.length - 1, Math.floor(ratio * ramp.length))
      return ramp[idx]
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
        microfiber_release_risk: this.item.microfiber_release_risk ?? '',
        fiber_type: this.item.fiber_type ?? '',
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
          microfiber_release_risk: (this.editForm.microfiber_release_risk as any) || null,
          fiber_type: this.editForm.fiber_type || null,
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

/* .detail-tabs/.detail-tab 已移至 components.css 共用（跟供應商/銷售產品詳情頁同一套） */

.tab-panel {
  border-radius: 0 0 8px 8px !important;
  border-top: none !important;
  margin-bottom: 16px;
}
.detail-section {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 8px; padding: 20px 24px; margin-bottom: 16px;
}
/* .detail-grid/.detail-item/.detail-label/.detail-value 已移至 components.css 共用 */
.section-title {
  font-size: 11.5px; font-weight: 700; color: var(--text-secondary);
  text-transform: uppercase; letter-spacing: 0.07em;
  margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid var(--border);
}
.empty-inline { font-size: 13px; color: var(--text-secondary); padding: 8px 0; }

/* 同物料跨供應商碳排比較 */
.compare-stats { display: flex; gap: 12px; margin-bottom: 16px; }
.compare-stat { flex: 1; background: var(--surface-2); border: 1px solid var(--border); border-radius: 8px; padding: 10px 14px; }
.compare-stat-label { font-size: 11px; color: var(--text-secondary); font-weight: 600; letter-spacing: .02em; }
.compare-stat-value { font-family: var(--font-mono); font-size: 18px; font-weight: 700; color: var(--text-primary); margin-top: 2px; }
.compare-stat-unit { font-size: 11px; font-weight: 400; color: var(--text-secondary); }
.compare-stat-supplier { font-size: 11.5px; color: var(--text-secondary); margin-top: 2px; }

.meter-cell { display: flex; align-items: center; gap: 6px; }
.meter-track { flex: 1; height: 6px; border-radius: 3px; background: #eef4f1; overflow: hidden; }
.meter-fill { height: 100%; border-radius: 3px; }
.meter-tag { font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 8px; white-space: nowrap; }
.meter-tag--best { background: #d1fae5; color: #065f46; }
.meter-tag--worst { background: #fee2e2; color: #b91c1c; }

/* 來源供應商分布（跨產品 BOM 行） */
.bom-line-groups { display: flex; flex-direction: column; gap: 18px; }
.bom-line-group { border: 1px solid var(--border); border-radius: 8px; padding: 12px 14px; }
.bom-line-group-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.bom-line-product-link { font-size: 13px; font-weight: 600; color: var(--text-primary); text-decoration: none; display: flex; align-items: center; gap: 6px; }
.bom-line-product-link:hover { text-decoration: underline; color: var(--accent); }
.switch-hint { font-size: 11px; color: #d97706; font-weight: 600; }
.is-primary-row { background: #f0f9f4; }

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
