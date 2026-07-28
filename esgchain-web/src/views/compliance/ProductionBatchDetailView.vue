<template>
  <div class="page-container">
    <div class="breadcrumb">
      <span class="breadcrumb-parent" style="cursor:pointer;" @click="$router.push('/compliance/production-batches')">生產批號</span>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current">{{ batch?.erp_batch_no ?? '' }}</span>
    </div>

    <div class="page-header">
      <div style="display:flex; align-items:center; gap:12px;">
        <button class="btn btn-secondary btn-sm" @click="$router.push('/compliance/production-batches')">← 返回列表</button>
        <div v-if="batch">
          <h1 class="page-title font-mono">{{ batch.erp_batch_no }}</h1>
          <p class="page-subtitle">
            <span v-if="batch.sales_product_name">{{ batch.sales_product_name }} · </span>
            <span class="font-mono">{{ batch.sales_product_model_no || batch.sales_product_code || '—' }}</span>
          </p>
        </div>
      </div>
      <div v-if="batch" style="display:flex;gap:8px;align-items:center;">
        <span :class="batch.matched ? 'status-badge status-matched' : 'status-badge status-pending'">
          {{ batch.matched ? '✓ 已匹配' : '待匹配' }}
        </span>
        <span class="source-badge" :class="'source-' + batch.source">{{ batchSourceLabel(batch.source) }}</span>
      </div>
    </div>

    <div v-if="isLoading" class="loading-mask">載入中…</div>
    <div v-else-if="!batch" class="empty-state"><p>找不到此生產批號</p></div>
    <div v-else class="detail-layout">
      <div class="detail-main">

        <!-- Tab 導覽 -->
        <div class="detail-tabs">
          <button
            v-for="t in TABS" :key="t.key"
            class="detail-tab"
            :class="{ active: activeTab === t.key }"
            @click="switchTab(t.key)"
          >{{ t.label }}</button>
        </div>

        <!-- ══ 批號資訊 ══ -->
        <div v-show="activeTab === 'info'" class="detail-section tab-panel">
          <div class="detail-grid">
            <div class="detail-item">
              <span class="detail-label">ERP 採購單號</span>
              <span class="detail-value font-mono">{{ batch.erp_order_no || '—' }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">生產日期</span>
              <span class="detail-value">{{ batch.production_date || '—' }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">數量</span>
              <span class="detail-value font-mono">{{ batch.quantity }} {{ batch.unit }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">來源</span>
              <span class="source-badge" :class="'source-' + batch.source">{{ batchSourceLabel(batch.source) }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">匹配狀態</span>
              <span :class="batch.matched ? 'status-badge status-matched' : 'status-badge status-pending'">
                {{ batch.matched ? '✓ 已匹配' : '待匹配' }}
              </span>
            </div>
            <div class="detail-item">
              <span class="detail-label">所屬產品</span>
              <router-link
                v-if="batch.sales_product_name && batch.sales_product_id"
                :to="`/sales-products/${batch.sales_product_id}`"
                class="detail-link"
              >
                {{ batch.sales_product_name }}
                <span class="font-mono" style="font-size:11px;">{{ batch.sales_product_model_no || batch.sales_product_code }}</span>
              </router-link>
              <span v-else class="detail-value" style="color:var(--text-secondary);">—</span>
            </div>
          </div>

        </div>

        <!-- ══ 碳足跡與循環經濟 ══ -->
        <div v-show="activeTab === 'sustainability'" class="detail-section tab-panel">
          <div v-if="passportLoading" class="empty-inline">載入永續資料中…</div>
          <template v-else-if="passport">
            <!-- 碳足跡 -->
            <div class="section-title">碳足跡</div>
            <div class="detail-grid" style="grid-template-columns:repeat(3,1fr);">
              <div class="detail-item">
                <span class="detail-label">批次 PCF</span>
                <span class="detail-value font-mono">
                  <template v-if="passport.carbon_footprint.batch_lot_pcf != null">
                    {{ passport.carbon_footprint.batch_lot_pcf }} kgCO₂e
                    <span style="color:var(--text-secondary);font-weight:400;font-size:11px;"> ({{ passport.carbon_footprint.batch_lot_pcf_source }})</span>
                  </template>
                  <span v-else style="color:var(--text-secondary);">—</span>
                </span>
              </div>
              <div class="detail-item">
                <span class="detail-label">產品 PCF（單位）</span>
                <span class="detail-value font-mono">
                  {{ passport.carbon_footprint.product_total_pcf != null ? passport.carbon_footprint.product_total_pcf + ' kgCO₂e/' + (passport.carbon_footprint.functional_unit || '單位') : '—' }}
                </span>
              </div>
              <div class="detail-item">
                <span class="detail-label">ISO 14067 就緒</span>
                <span class="tag" :class="passport.carbon_footprint.iso14067_ready ? 'tag-ok' : 'tag-gray'">
                  {{ passport.carbon_footprint.iso14067_ready ? '是' : '否' }}
                </span>
                <ul v-if="passport.carbon_footprint.iso14067_gap_reasons.length" class="gap-reason-list">
                  <li v-for="reason in passport.carbon_footprint.iso14067_gap_reasons" :key="reason">{{ reason }}</li>
                </ul>
              </div>
            </div>

            <!-- 循環經濟 -->
            <div class="section-title" style="margin-top:20px;">循環經濟</div>
            <div v-if="!passport.circularity" class="empty-inline">
              尚無循環經濟快照資料
              <router-link
                v-if="batch.sales_product_id"
                :to="`/sales-products/${batch.sales_product_id}?tab=circularity`"
                target="_blank"
                class="detail-link"
                style="margin-left:6px;"
              >前往產品頁計算 →</router-link>
            </div>
            <div v-else class="detail-grid" style="grid-template-columns:repeat(4,1fr);">
              <div class="detail-item">
                <span class="detail-label">再生料比例</span>
                <span class="detail-value font-mono">{{ passport.circularity.recycled_content_ratio != null ? passport.circularity.recycled_content_ratio + '%' : '—' }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">PCR / PIR</span>
                <span class="detail-value font-mono">{{ passport.circularity.pcr_ratio ?? '—' }}% / {{ passport.circularity.pir_ratio ?? '—' }}%</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">生物基比例</span>
                <span class="detail-value font-mono">{{ passport.circularity.bio_based_ratio != null ? passport.circularity.bio_based_ratio + '%' : '—' }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">資料完整度</span>
                <span class="tag" :class="passport.circularity.data_ready ? 'tag-ok' : 'tag-pending'">
                  {{ passport.circularity.data_ready ? '完整' : '不完整' }}
                </span>
              </div>
            </div>

            <!-- 包材資訊 -->
            <div class="section-title" style="margin-top:20px;">包材資訊</div>
            <div v-if="!passport.packaging" class="empty-inline">
              尚未填寫包材資訊
              <router-link
                v-if="batch.sales_product_id"
                :to="`/sales-products/${batch.sales_product_id}?tab=info`"
                target="_blank"
                class="detail-link"
                style="margin-left:6px;"
              >前往填寫 →</router-link>
            </div>
            <div v-else class="detail-grid" style="grid-template-columns:repeat(4,1fr);">
              <div class="detail-item">
                <span class="detail-label">再生料比例</span>
                <span class="detail-value font-mono">{{ passport.packaging.recycled_content_ratio != null ? passport.packaging.recycled_content_ratio + '%' : '—' }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">可回收</span>
                <span class="detail-value">{{ passport.packaging.recyclable == null ? '—' : (passport.packaging.recyclable ? '是' : '否') }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">可重複使用</span>
                <span class="detail-value">{{ passport.packaging.reusable == null ? '—' : (passport.packaging.reusable ? '是' : '否') }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">包材說明</span>
                <span class="detail-value">{{ passport.packaging.material_description || '—' }}</span>
              </div>
            </div>

            <!-- 電池規格（dpp_category = battery 專用，非電池產品不顯示） -->
            <template v-if="passport.battery_spec">
              <div class="section-title" style="margin-top:20px;">電池規格</div>
              <div class="detail-grid" style="grid-template-columns:repeat(3,1fr);">
                <div class="detail-item">
                  <span class="detail-label">電池類別</span>
                  <span class="detail-value">{{ batteryCategoryLabel(passport.battery_spec.battery_category) }}</span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">化學系統</span>
                  <span class="detail-value">{{ passport.battery_spec.chemistry || '—' }}</span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">額定容量／電壓</span>
                  <span class="detail-value font-mono">{{ passport.battery_spec.rated_capacity_ah ?? '—' }} Ah / {{ passport.battery_spec.rated_voltage_v ?? '—' }} V</span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">重量</span>
                  <span class="detail-value font-mono">{{ passport.battery_spec.weight_kg != null ? passport.battery_spec.weight_kg + ' kg' : '—' }}</span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">循環壽命</span>
                  <span class="detail-value font-mono">{{ passport.battery_spec.performance.cycle_life ?? '—' }}</span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">預期使用年限</span>
                  <span class="detail-value font-mono">{{ passport.battery_spec.performance.expected_lifetime_years ?? '—' }} 年</span>
                </div>
              </div>
              <div class="detail-grid" style="grid-template-columns:repeat(4,1fr);margin-top:10px;">
                <div class="detail-item">
                  <span class="detail-label">鋰回收含量</span>
                  <span class="detail-value font-mono">{{ passport.battery_spec.critical_materials.lithium_recycled_content_ratio != null ? passport.battery_spec.critical_materials.lithium_recycled_content_ratio + '%' : '—' }}</span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">鈷回收含量</span>
                  <span class="detail-value font-mono">{{ passport.battery_spec.critical_materials.cobalt_recycled_content_ratio != null ? passport.battery_spec.critical_materials.cobalt_recycled_content_ratio + '%' : '—' }}</span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">鎳回收含量</span>
                  <span class="detail-value font-mono">{{ passport.battery_spec.critical_materials.nickel_recycled_content_ratio != null ? passport.battery_spec.critical_materials.nickel_recycled_content_ratio + '%' : '—' }}</span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">鉛回收含量</span>
                  <span class="detail-value font-mono">{{ passport.battery_spec.critical_materials.lead_recycled_content_ratio != null ? passport.battery_spec.critical_materials.lead_recycled_content_ratio + '%' : '—' }}</span>
                </div>
              </div>
            </template>
          </template>
        </div>

        <!-- ══ 有害物質揭露 ══ -->
        <div v-show="activeTab === 'hazard'" class="detail-section tab-panel">
          <div v-if="passportLoading" class="empty-inline">載入永續資料中…</div>
          <template v-else-if="passport">
            <div class="section-title">有害物質揭露</div>
            <div v-if="!passport.hazard_disclosure" class="empty-inline">尚無判定資料</div>
            <template v-else>
              <div class="detail-grid" style="grid-template-columns:1fr;">
                <div class="detail-item">
                  <span class="detail-label">揭露狀態</span>
                  <span class="tag" :class="passport.hazard_disclosure.has_hazardous_substance ? 'tag-pending' : 'tag-ok'">
                    {{ passport.hazard_disclosure.has_hazardous_substance ? '存在未解除警示' : '無未解除警示' }}
                  </span>
                </div>
              </div>
              <div v-if="passport.hazard_disclosure.alerts.length" style="margin-top:8px;display:flex;flex-direction:column;gap:4px;">
                <span v-for="a in passport.hazard_disclosure.alerts" :key="a.chemical_id" style="font-size:12px;color:var(--text-secondary);">
                  {{ a.chemical_name || a.chemical_id }} — {{ a.alert_level }}（{{ a.status }}）
                </span>
              </div>
            </template>

            <!-- 塑膠微纖維釋放風險 -->
            <div class="section-title" style="margin-top:20px;">塑膠微纖維釋放風險</div>
            <div v-if="!passport.microfiber_release_risks.length" class="empty-inline">尚未填報任何物料的微纖維釋放風險</div>
            <div v-else class="tag-list">
              <span v-for="m in passport.microfiber_release_risks" :key="m.material_name" class="tag" :class="microfiberTagClass(m.risk)">
                {{ m.material_name }}：{{ microfiberLabel(m.risk) }}
              </span>
            </div>
          </template>
        </div>

        <!-- ══ 供應鏈合規 ══ -->
        <div v-show="activeTab === 'supply-chain'" class="detail-section tab-panel">
          <div v-if="passportLoading" class="empty-inline">載入永續資料中…</div>
          <template v-else-if="passport">
            <!-- 供應鏈製程級地點：批次×製程類型 → 實際供應商/廠區選定 -->
            <div class="section-title">供應鏈製程級地點</div>
            <div v-if="processFacilitiesLoading" class="empty-inline">載入中…</div>
            <div v-else-if="!processFacilities.length" class="empty-inline">此產品 BOM 尚無核可供應商廠區資料，無法列出製程類型</div>
            <div v-else class="origins-grid">
              <div v-for="pf in processFacilities" :key="pf.process_type" class="origin-card">
                <div class="origin-header">
                  <span class="origin-name">{{ facilityTypeLabel(pf.process_type) }}</span>
                  <span class="tag" :class="pf.confirmed ? 'tag-ok' : 'tag-pending'">{{ pf.confirmed ? '已選定' : '待選定' }}</span>
                </div>

                <template v-if="pf.confirmed && editingProcessType !== pf.process_type">
                  <div class="origin-detail">{{ pf.selected?.facility_name || '—' }}</div>
                  <div v-if="pf.selected?.country" class="origin-detail">{{ pf.selected.country }}</div>
                  <div class="origin-detail">{{ pf.selected?.supplier_name || '—' }}</div>

                  <!-- SAQ 六維風險盡職調查串接：染整/濕製程/印花→環境管理，成衣縫製→社會責任 -->
                  <template v-if="dueDiligenceFor(pf.process_type)">
                    <div v-if="dueDiligenceFor(pf.process_type)!.status === 'assessed'" style="margin-top:8px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                      <span class="tag" :class="'risk-' + dueDiligenceFor(pf.process_type)!.risk_level">
                        {{ dueDiligenceFor(pf.process_type)!.dimension_label }}風險：{{ riskLevelLabel(dueDiligenceFor(pf.process_type)!.risk_level) }}（{{ dueDiligenceFor(pf.process_type)!.score }}）
                      </span>
                      <router-link
                        v-if="pf.selected?.supplier_id"
                        :to="`/compliance/suppliers/${pf.selected.supplier_id}`"
                        class="detail-link"
                        style="font-size:12px;"
                      >查看供應商評分 →</router-link>
                    </div>
                    <div v-else-if="dueDiligenceFor(pf.process_type)!.status === 'not_assessed'" style="margin-top:8px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                      <span class="tag tag-pending">{{ dueDiligenceFor(pf.process_type)!.dimension_label }}：尚未完成評分</span>
                      <router-link
                        v-if="pf.selected?.supplier_id"
                        :to="`/compliance/suppliers/${pf.selected.supplier_id}`"
                        class="detail-link"
                        style="font-size:12px;"
                      >查看供應商評分 →</router-link>
                    </div>
                  </template>

                  <button class="btn btn-secondary btn-sm" style="margin-top:8px;" @click="toggleProcessFacilityEdit(pf)">更換</button>
                </template>

                <template v-else>
                  <div v-if="editingProcessType !== pf.process_type">
                    <div class="empty-inline" style="padding:0 0 8px;">尚未選定實際供應商</div>
                    <button class="btn btn-secondary btn-sm" @click="toggleProcessFacilityEdit(pf)">選定供應商</button>
                  </div>
                  <div v-else class="origin-form" style="margin-top:8px;">
                    <div class="form-group">
                      <label class="form-label">選擇供應商廠區</label>
                      <select v-model="processFacilityForm.candidateKey" class="form-select" :disabled="processFacilitySaving">
                        <option value="">請選擇</option>
                        <option v-for="c in pf.candidates" :key="c.supplier_id + '|' + c.facility_id" :value="c.supplier_id + '|' + c.facility_id">
                          {{ c.supplier_name }}（{{ c.facility_name }}）
                        </option>
                      </select>
                    </div>
                    <div style="display:flex;gap:8px;margin-top:8px;">
                      <button
                        class="btn btn-primary btn-sm"
                        :disabled="!processFacilityForm.candidateKey || processFacilitySaving"
                        @click="selectProcessFacility(pf)"
                      >{{ processFacilitySaving ? '儲存中…' : '確認選定' }}</button>
                      <button
                        v-if="pf.confirmed && pf.selected"
                        class="btn btn-danger btn-sm"
                        :disabled="processFacilitySaving"
                        @click="clearProcessFacility(pf)"
                      >清除選定</button>
                      <button class="btn btn-secondary btn-sm" :disabled="processFacilitySaving" @click="toggleProcessFacilityEdit(pf)">取消</button>
                    </div>
                  </div>
                </template>
              </div>
            </div>

            <!-- 供應鏈合規調查：BOM 表出發 → 選擇供應商 → 溯源調查 → 合規調查清單
                 （整合原本分開的「原物料合規與溯源管理」，同一物料的溯源填寫/供應商確認
                 直接在卡片內就地展開，不用再切去另一個區塊找同一個物料） -->
            <div class="section-title" style="margin-top:20px;">供應鏈合規調查</div>
            <p class="section-hint">依 BOM 表逐一物料呈現：這批貨的這個物料選了哪家供應商、溯源資料是否齊全、該供應商是否具備此物料類別要求的合規文件</p>
            <div v-if="!passport.supply_chain_compliance.length" class="empty-inline">此產品尚無 BOM 明細</div>
            <div v-else class="scc-list">
              <div v-for="sc in passport.supply_chain_compliance" :key="sc.bom_line_id" class="scc-card">
                <div class="scc-header">
                  <span class="scc-material">{{ sc.material_name }}</span>
                  <span v-if="sc.selected_supplier" class="tag" :class="sc.supplier_confirmed ? 'tag-ok' : 'tag-pending'">
                    {{ sc.supplier_confirmed ? '已選定' : '建議（未確認）' }}：{{ sc.selected_supplier.name }}
                  </span>
                  <span v-else class="tag tag-uflpa">尚無核可供應商</span>
                  <button
                    class="btn btn-secondary btn-sm"
                    style="margin-left:auto;"
                    @click="toggleOriginEdit(sc.bom_line_id)"
                  >{{ editingBomLineId === sc.bom_line_id ? '取消' : (originForBomLine(sc.bom_line_id) ? '編輯溯源' : '+ 新增溯源') }}</button>
                </div>

                <div class="scc-section">
                  <span class="scc-label">溯源調查</span>
                  <template v-if="sc.traceability">
                    <span class="scc-value">{{ sc.traceability.origin_country || '—' }}<span v-if="sc.traceability.facility_name"> · {{ sc.traceability.facility_name }}</span></span>
                    <span v-if="sc.traceability.certification_ref" class="scc-value font-mono">{{ sc.traceability.certification_ref }}</span>
                  </template>
                  <span v-else class="no-data">尚無溯源紀錄</span>
                </div>

                <div class="scc-section">
                  <span class="scc-label">合規調查</span>
                  <div v-if="!sc.required_doc_types.length" class="no-data">此物料類別無合規文件要求</div>
                  <div v-else-if="!sc.selected_supplier" class="no-data">尚未選定供應商，無法查核文件</div>
                  <div v-else class="scc-docs">
                    <span
                      v-for="d in sc.doc_statuses" :key="d.doc_type"
                      class="doc-chip" :class="`doc-chip--${d.status}`"
                    >
                      {{ docTypeLabel(d.doc_type) }}：{{ d.status }}
                      <span v-if="d.expires_at" class="doc-exp">{{ d.expires_at }}</span>
                    </span>
                  </div>
                </div>

                <!-- 就地展開的溯源／實際供應商編輯表單 -->
                <div v-if="editingBomLineId === sc.bom_line_id" class="origin-form" style="margin-top:12px;">
                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label">原產國 (ISO) <span class="req">*</span></label>
                      <input v-model="originForm.origin_country" class="form-input" placeholder="TW" maxlength="2" style="text-transform:uppercase;" />
                    </div>
                    <div class="form-group">
                      <label class="form-label">農場 / 設施名稱</label>
                      <input v-model="originForm.facility_name" class="form-input" placeholder="Green Farm Co." />
                    </div>
                  </div>
                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label">GPS 緯度</label>
                      <input v-model="originForm.gps_lat" class="form-input font-mono" placeholder="23.697810" type="number" step="0.000001" />
                    </div>
                    <div class="form-group">
                      <label class="form-label">GPS 經度</label>
                      <input v-model="originForm.gps_lng" class="form-input font-mono" placeholder="120.960515" type="number" step="0.000001" />
                    </div>
                  </div>
                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label">實際供應商（最後合規確認） <span class="req">*</span></label>
                      <select v-model="originForm.supplier_id" class="form-select" :disabled="originBomLineSuppliersLoading" @change="onOriginSupplierSelected">
                        <option value="">未指定</option>
                        <option v-for="s in originBomLineSupplierOptions" :key="s.id" :value="s.id">{{ s.name }} ({{ s.code }})</option>
                      </select>
                      <span v-if="!originBomLineSuppliersLoading && !originBomLineSupplierOptions.length" style="display:block;font-size:11px;color:#dc2626;">
                        此物料尚無核可供應商清單，請先至「物料管理」對應物料頁面登錄
                      </span>
                      <span v-else style="font-size:11px;color:var(--text-secondary);">僅列出此物料的核可供應商清單（主/備）</span>
                    </div>
                  </div>
                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label">收成年份</label>
                      <input v-model="originForm.harvest_year" class="form-input font-mono" placeholder="2025" type="number" maxlength="4" />
                    </div>
                    <div class="form-group">
                      <label class="form-label">認證編號</label>
                      <input v-model="originForm.certification_ref" class="form-input" placeholder="GOTS-2025-XXXX" />
                    </div>
                  </div>
                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label">運輸方式</label>
                      <select v-model="originForm.transport_mode" class="form-select">
                        <option value="">未設定</option>
                        <option value="sea">海運</option>
                        <option value="air">空運</option>
                        <option value="road">陸運</option>
                        <option value="rail">鐵路</option>
                        <option value="multimodal">多式聯運</option>
                        <option value="unknown">未知</option>
                      </select>
                    </div>
                    <div class="form-group">
                      <label class="form-label">運輸距離（公里）</label>
                      <input v-model="originForm.transport_distance_km" class="form-input font-mono" placeholder="8500" type="number" step="0.01" />
                    </div>
                  </div>
                  <div style="display:flex;gap:8px;">
                    <button
                      class="btn btn-primary btn-sm"
                      :disabled="!originForm.origin_country || originSaving"
                      @click="submitOrigin"
                    >{{ originSaving ? '儲存中…' : '儲存' }}</button>
                    <button
                      v-if="originForBomLine(sc.bom_line_id)"
                      class="btn btn-danger btn-sm"
                      @click="deleteOrigin(originForBomLine(sc.bom_line_id).id)"
                    >刪除溯源紀錄</button>
                  </div>
                </div>
              </div>
            </div>

            <!-- 其他原料溯源（未對應 BOM 物料，如商業機密考量不連結特定物料的自由填寫紀錄） -->
            <div class="section-title-row" style="margin-top:20px;">
              <div class="section-title">其他原料溯源（未對應 BOM 物料）</div>
              <button class="btn btn-secondary btn-sm" @click="toggleUnmappedOriginForm">
                {{ showUnmappedOriginForm ? '取消' : '+ 新增' }}
              </button>
            </div>

            <div v-if="showUnmappedOriginForm" class="origin-form">
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">原料名稱 <span class="req">*</span></label>
                  <input v-model="originForm.material_name" class="form-input" placeholder="如：有機棉" />
                </div>
                <div class="form-group">
                  <label class="form-label">原產國 (ISO) <span class="req">*</span></label>
                  <input v-model="originForm.origin_country" class="form-input" placeholder="TW" maxlength="2" style="text-transform:uppercase;" />
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">農場 / 設施名稱</label>
                <input v-model="originForm.facility_name" class="form-input" placeholder="Green Farm Co." />
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">GPS 緯度</label>
                  <input v-model="originForm.gps_lat" class="form-input font-mono" placeholder="23.697810" type="number" step="0.000001" />
                </div>
                <div class="form-group">
                  <label class="form-label">GPS 經度</label>
                  <input v-model="originForm.gps_lng" class="form-input font-mono" placeholder="120.960515" type="number" step="0.000001" />
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">收成年份</label>
                  <input v-model="originForm.harvest_year" class="form-input font-mono" placeholder="2025" type="number" maxlength="4" />
                </div>
                <div class="form-group">
                  <label class="form-label">認證編號</label>
                  <input v-model="originForm.certification_ref" class="form-input" placeholder="GOTS-2025-XXXX" />
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">運輸方式</label>
                  <select v-model="originForm.transport_mode" class="form-select">
                    <option value="">未設定</option>
                    <option value="sea">海運</option>
                    <option value="air">空運</option>
                    <option value="road">陸運</option>
                    <option value="rail">鐵路</option>
                    <option value="multimodal">多式聯運</option>
                    <option value="unknown">未知</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">運輸距離（公里）</label>
                  <input v-model="originForm.transport_distance_km" class="form-input font-mono" placeholder="8500" type="number" step="0.01" />
                </div>
              </div>
              <button
                class="btn btn-primary btn-sm"
                :disabled="!originForm.material_name || !originForm.origin_country || originSaving"
                @click="submitOrigin"
              >{{ originSaving ? '儲存中…' : '新增溯源記錄' }}</button>
            </div>

            <div v-if="!unmappedOrigins.length && !showUnmappedOriginForm" class="origin-empty">
              尚無未對應 BOM 物料的溯源紀錄
            </div>
            <div v-else class="origins-grid">
              <div v-for="o in unmappedOrigins" :key="o.id" class="origin-card">
                <div class="origin-header">
                  <span class="origin-name">{{ o.material_name }}</span>
                  <span class="origin-country">{{ o.origin_country }}</span>
                  <button class="remove-btn" @click="deleteOrigin(o.id)">×</button>
                </div>
                <div v-if="o.facility_name" class="origin-detail">{{ o.facility_name }}</div>
                <div v-if="o.gps_lat && o.gps_lng" class="origin-gps">
                  <span class="font-mono">{{ formatGps(o.gps_lat, o.gps_lng) }}</span>
                  <a :href="googleMapsUrl(o.gps_lat, o.gps_lng)" target="_blank" class="maps-link">在地圖查看</a>
                </div>
                <div v-if="o.harvest_year" class="origin-detail">收成年份：{{ o.harvest_year }}</div>
                <div v-if="o.certification_ref" class="origin-detail cert">{{ o.certification_ref }}</div>
                <div v-if="o.transport_mode || o.transport_distance_km" class="origin-detail">
                  運輸：{{ transportModeLabel(o.transport_mode) }}<span v-if="o.transport_distance_km">（{{ o.transport_distance_km }} km）</span>
                </div>
              </div>
            </div>
          </template>
        </div>

      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { productionBatchApi, rawMaterialOriginApi, processFacilityApi, processDueDiligenceApi, type ProductionBatch, type BatchPassport, type BatchProcessFacilityItem, type ProcessDueDiligenceItem } from '@/api/modules/productionBatch'
import { type BomLine } from '@/api/modules/salesProducts'

// 比照 SupplierComplianceDetailView.vue 的文件類型標籤，維持全站一致的顯示名稱
const DOC_TYPE_LABELS: Record<string, string> = {
  UFLPA_DECLARATION: 'UFLPA 聲明', EUDR_DDS: 'EUDR DDS', CMRT: 'CMRT 衝突礦產報告',
  SDS: 'SDS 安全資料表', CE_DOC: 'CE 認證', ORIGIN_CERT: '原產地證明', GRS: 'GRS 認證', OTHER: '其他',
}

const TRANSPORT_MODE_LABELS: Record<string, string> = {
  sea: '海運', air: '空運', road: '陸運', rail: '鐵路', multimodal: '多式聯運', unknown: '未知',
}

const BATCH_SOURCE_LABELS: Record<string, string> = { webhook: 'Webhook 推送', csv: 'CSV 匯入', manual: '手動建立' }

const MICROFIBER_RISK_LABELS: Record<string, string> = { low: '低', medium: '中', high: '高', not_rated: '未評估' }
const MICROFIBER_TAG_CLASS: Record<string, string> = { low: 'tag-ok', medium: 'tag-pending', high: 'tag-uflpa', not_rated: 'tag-gray' }

const FACILITY_TYPE_LABELS: Record<string, string> = {
  manufacturing: '一般製造', weaving: '織布', knitting: '針織', dyeing: '染整', printing: '印花',
  wet_processing: '濕製程', garment_assembly: '成衣製造', warehouse: '倉儲', office: '辦公室', other: '其他',
}

const BATTERY_CATEGORY_LABELS: Record<string, string> = {
  portable: '可攜式', industrial: '工業用', ev: '電動車', lmt: 'LMT 輕型載具',
}

const ORIGIN_FORM_DEFAULT = {
  id: '',
  bom_line_id: '',
  material_name: '',
  origin_country: '',
  facility_name: '',
  gps_lat: '',
  gps_lng: '',
  harvest_year: '',
  certification_ref: '',
  transport_mode: '',
  transport_distance_km: '',
  supplier_id: '',
}

const TABS = [
  { key: 'info',           label: '批號資訊' },
  { key: 'sustainability', label: '碳足跡與循環經濟' },
  { key: 'hazard',         label: '有害物質揭露' },
  { key: 'supply-chain',   label: '供應鏈合規' },
]

export default defineComponent({
  name: 'ProductionBatchDetailView',

  data() {
    return {
      TABS,
      activeTab: 'info',
      isLoading: false,
      batch: null as ProductionBatch | null,

      editingBomLineId: null as string | null,
      showUnmappedOriginForm: false,
      originForm: { ...ORIGIN_FORM_DEFAULT } as Record<string, string>,
      originSaving: false,
      allSuppliers: [] as { id: string; name: string; code: string | null }[],
      bomLines: [] as BomLine[],
      approvedSuppliersCache: {} as Record<string, { id: string; name: string; code: string | null }[]>,
      originBomLineSuppliersLoading: false,

      passport: null as BatchPassport | null,
      passportLoading: false,

      processFacilities: [] as BatchProcessFacilityItem[],
      processFacilitiesLoading: false,
      editingProcessType: null as string | null,
      processFacilityForm: { candidateKey: '' },
      processFacilitySaving: false,

      processDueDiligence: [] as ProcessDueDiligenceItem[],
    }
  },

  async mounted() {
    await this.loadBatch()
    this.loadPassport()
    this.loadAllSuppliers()
    this.loadBomLines()
    this.loadProcessFacilities()
    this.loadProcessDueDiligence()
  },

  computed: {
    // 未選定 BOM 物料時可從全部供應商選擇；選定後限制為該物料的核可供應商清單
    originBomLineSupplierOptions(): { id: string; name: string; code: string | null }[] {
      if (!this.originForm.bom_line_id) return this.allSuppliers
      const line = this.bomLines.find(bl => bl.id === this.originForm.bom_line_id)
      if (!line?.material_item_id) return this.allSuppliers
      return this.approvedSuppliersCache[line.material_item_id] ?? []
    },
    // 未對應任何 BOM 物料的原料溯源紀錄（自由填寫，如商業機密考量不連結特定物料）
    unmappedOrigins() {
      return (this.batch?.raw_material_origins ?? []).filter(o => !o.bom_line_id)
    },
  },

  methods: {
    async loadAllSuppliers() {
      try {
        const http = (await import('@/api/http')).default
        const res = await http.get<any>('/api/v1/suppliers?per_page=500')
        this.allSuppliers = res.data?.data?.data ?? res.data?.data ?? []
      } catch { /* silent */ }
    },

    async loadBomLines() {
      if (!this.batch?.sales_product_id) return
      try {
        const { salesProductApi } = await import('@/api/modules/salesProducts')
        const { data } = await salesProductApi.bomLines(this.batch.sales_product_id)
        this.bomLines = data.data
      } catch { /* silent */ }
    },

    // 選定 BOM 物料後，把「實際供應商」下拉限制為該物料的核可供應商清單，
    // 對應「批號→選供應商→合規調查」——批次實際供應商應來自物料核可清單，不是任選
    async onOriginBomLineSelected(resetSupplier = true) {
      if (resetSupplier) this.originForm.supplier_id = ''
      const line = this.bomLines.find(bl => bl.id === this.originForm.bom_line_id)
      if (line && !this.originForm.material_name) {
        this.originForm.material_name = line.effective_material_name ?? line.material_name
      }
      if (!line?.material_item_id || this.approvedSuppliersCache[line.material_item_id]) return

      this.originBomLineSuppliersLoading = true
      try {
        const { materialItemApi } = await import('@/api/modules/compliance')
        const { data } = await materialItemApi.approvedSuppliers(line.material_item_id)
        this.approvedSuppliersCache[line.material_item_id] = data.data.map(s => ({
          id: s.supplier_id,
          name: s.supplier?.name ?? s.supplier_id,
          code: s.supplier?.code ?? null,
        }))
      } catch {
        this.approvedSuppliersCache[line.material_item_id] = []
      } finally {
        this.originBomLineSuppliersLoading = false
      }
    },

    // 選定實際供應商後，若該供應商僅有單一廠區，自動帶入原產國/設施名稱
    // （已填寫的欄位不覆蓋，尊重使用者手動輸入）
    async onOriginSupplierSelected() {
      if (!this.originForm.supplier_id) return
      try {
        const { facilityApi } = await import('@/api/modules/suppliers')
        const { data } = await facilityApi.list(this.originForm.supplier_id)
        if (data.data.length === 1) {
          const facility = data.data[0]
          if (!this.originForm.facility_name) this.originForm.facility_name = facility.name
          if (!this.originForm.origin_country && facility.country) this.originForm.origin_country = facility.country
        }
      } catch { /* silent */ }
    },

    async loadBatch() {
      this.isLoading = true
      try {
        const { data } = await productionBatchApi.get(this.$route.params.id as string)
        this.batch = data.data
      } finally {
        this.isLoading = false
      }
    },

    switchTab(key: string) {
      this.activeTab = key
    },

    async loadPassport() {
      if (!this.batch) return
      this.passportLoading = true
      try {
        const { data } = await productionBatchApi.passport(this.batch.id)
        this.passport = data.data
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '產出批次護照失敗')
      } finally {
        this.passportLoading = false
      }
    },

    async loadProcessFacilities() {
      if (!this.batch) return
      this.processFacilitiesLoading = true
      try {
        const { data } = await processFacilityApi.list(this.batch.id)
        this.processFacilities = data.data
      } catch { /* silent */ } finally {
        this.processFacilitiesLoading = false
      }
    },

    async loadProcessDueDiligence() {
      if (!this.batch) return
      try {
        const { data } = await processDueDiligenceApi.list(this.batch.id)
        this.processDueDiligence = data.data
      } catch { /* silent */ }
    },

    // 已選定製程供應商對應的 SAQ 六維風險盡職調查結果（環境管理/社會責任）
    dueDiligenceFor(processType: string): ProcessDueDiligenceItem | null {
      return this.processDueDiligence.find(d => d.process_type === processType) ?? null
    },

    riskLevelLabel(level: string | null): string {
      return ({ low: '低', medium: '中', high: '高', unknown: '未知' } as Record<string, string>)[level ?? ''] ?? level ?? '—'
    },

    // 供應鏈製程級地點：同時只能有一張卡片在編輯（跟原料溯源的 editingBomLineId 分開，避免互相干擾）
    toggleProcessFacilityEdit(pf: BatchProcessFacilityItem) {
      if (this.editingProcessType === pf.process_type) {
        this.editingProcessType = null
        this.processFacilityForm = { candidateKey: '' }
        return
      }
      this.editingProcessType = pf.process_type
      this.processFacilityForm = {
        candidateKey: pf.selected ? `${pf.selected.supplier_id}|${pf.selected.facility_id ?? ''}` : '',
      }
    },

    async selectProcessFacility(pf: BatchProcessFacilityItem) {
      if (!this.batch || !this.processFacilityForm.candidateKey) return
      const [supplierId, facilityId] = this.processFacilityForm.candidateKey.split('|')
      this.processFacilitySaving = true
      try {
        await processFacilityApi.select(this.batch.id, {
          process_type: pf.process_type,
          supplier_id: supplierId,
          supplier_facility_id: facilityId || null,
        })
        this.editingProcessType = null
        this.processFacilityForm = { candidateKey: '' }
        await this.loadProcessFacilities()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '選定製程供應商失敗')
      } finally {
        this.processFacilitySaving = false
      }
    },

    async clearProcessFacility(pf: BatchProcessFacilityItem) {
      if (!this.batch || !pf.selected?.id) return
      if (!confirm('確認清除此製程類型的選定廠區？')) return
      this.processFacilitySaving = true
      try {
        await processFacilityApi.clear(this.batch.id, pf.selected.id)
        this.editingProcessType = null
        this.processFacilityForm = { candidateKey: '' }
        await this.loadProcessFacilities()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '清除選定失敗')
      } finally {
        this.processFacilitySaving = false
      }
    },

    // 該 BOM 物料此批次現有的溯源紀錄（一個 BOM 行對應一筆，供卡片內就地編輯）
    originForBomLine(bomLineId: string) {
      return (this.batch?.raw_material_origins ?? []).find(o => o.bom_line_id === bomLineId) ?? null
    },

    // 供應鏈合規調查卡片「編輯溯源」：卡片內就地展開表單，預填既有溯源資料（若有）
    async toggleOriginEdit(bomLineId: string) {
      if (this.editingBomLineId === bomLineId) {
        this.editingBomLineId = null
        this.originForm = { ...ORIGIN_FORM_DEFAULT }
        return
      }

      const existing = this.originForBomLine(bomLineId)
      this.originForm = {
        ...ORIGIN_FORM_DEFAULT,
        id: existing?.id ?? '',
        bom_line_id: bomLineId,
        material_name: existing?.material_name ?? '',
        origin_country: existing?.origin_country ?? '',
        facility_name: existing?.facility_name ?? '',
        gps_lat: existing?.gps_lat ?? '',
        gps_lng: existing?.gps_lng ?? '',
        harvest_year: existing?.harvest_year != null ? String(existing.harvest_year) : '',
        certification_ref: existing?.certification_ref ?? '',
        transport_mode: existing?.transport_mode ?? '',
        transport_distance_km: existing?.transport_distance_km != null ? String(existing.transport_distance_km) : '',
        supplier_id: existing?.supplier_id ?? '',
      }
      this.editingBomLineId = bomLineId
      await this.onOriginBomLineSelected(false)
    },

    toggleUnmappedOriginForm() {
      this.showUnmappedOriginForm = !this.showUnmappedOriginForm
      this.originForm = { ...ORIGIN_FORM_DEFAULT }
    },

    async submitOrigin() {
      if (!this.batch) return
      this.originSaving = true
      try {
        const payload: any = {
          material_name: this.originForm.material_name || this.bomLines.find(bl => bl.id === this.originForm.bom_line_id)?.material_name || '未命名物料',
          origin_country: (this.originForm.origin_country ?? '').toUpperCase(),
        }
        if (this.originForm.facility_name)     payload.facility_name     = this.originForm.facility_name
        if (this.originForm.gps_lat)           payload.gps_lat           = parseFloat(this.originForm.gps_lat)
        if (this.originForm.gps_lng)           payload.gps_lng           = parseFloat(this.originForm.gps_lng)
        if (this.originForm.harvest_year)      payload.harvest_year      = parseInt(this.originForm.harvest_year)
        if (this.originForm.certification_ref) payload.certification_ref = this.originForm.certification_ref
        if (this.originForm.transport_mode)    payload.transport_mode    = this.originForm.transport_mode
        if (this.originForm.transport_distance_km) payload.transport_distance_km = parseFloat(this.originForm.transport_distance_km)
        if (this.originForm.supplier_id)       payload.supplier_id       = this.originForm.supplier_id
        if (this.originForm.bom_line_id)       payload.bom_line_id       = this.originForm.bom_line_id

        if (this.originForm.id) {
          const res = await rawMaterialOriginApi.update(this.batch.id, this.originForm.id, payload)
          const idx = this.batch.raw_material_origins.findIndex(o => o.id === this.originForm.id)
          if (idx >= 0) this.batch.raw_material_origins.splice(idx, 1, res.data.data)
        } else {
          const res = await rawMaterialOriginApi.create(this.batch.id, payload)
          this.batch.raw_material_origins.push(res.data.data)
        }

        this.originForm = { ...ORIGIN_FORM_DEFAULT }
        this.editingBomLineId = null
        this.showUnmappedOriginForm = false
        await this.loadPassport()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '儲存失敗')
      } finally {
        this.originSaving = false
      }
    },

    async deleteOrigin(originId: string) {
      if (!this.batch) return
      if (!confirm('確認刪除此溯源記錄？')) return
      await rawMaterialOriginApi.destroy(this.batch.id, originId)
      this.batch.raw_material_origins = this.batch.raw_material_origins.filter(o => o.id !== originId)
      this.editingBomLineId = null
      this.originForm = { ...ORIGIN_FORM_DEFAULT }
      await this.loadPassport()
    },

    formatGps(lat: string | null, lng: string | null): string {
      if (!lat || !lng) return ''
      const latN = parseFloat(lat)
      const lngN = parseFloat(lng)
      return `${Math.abs(latN).toFixed(6)}°${latN >= 0 ? 'N' : 'S'} ${Math.abs(lngN).toFixed(6)}°${lngN >= 0 ? 'E' : 'W'}`
    },

    googleMapsUrl(lat: string | null, lng: string | null): string {
      return `https://www.google.com/maps?q=${lat},${lng}`
    },

    transportModeLabel(mode: string | null): string {
      return mode ? (TRANSPORT_MODE_LABELS[mode] ?? mode) : '—'
    },

    docTypeLabel(docType: string): string {
      return DOC_TYPE_LABELS[docType] ?? docType
    },

    microfiberLabel(risk: string): string {
      return MICROFIBER_RISK_LABELS[risk] ?? risk
    },
    microfiberTagClass(risk: string): string {
      return MICROFIBER_TAG_CLASS[risk] ?? 'tag-gray'
    },
    facilityTypeLabel(type: string): string {
      return FACILITY_TYPE_LABELS[type] ?? type
    },
    batchSourceLabel(source: string): string {
      return BATCH_SOURCE_LABELS[source] ?? source
    },
    batteryCategoryLabel(category: string | null): string {
      if (!category) return '—'
      return BATTERY_CATEGORY_LABELS[category] ?? category
    },
  },
})
</script>

<style scoped>
/* .detail-tabs/.detail-grid/.detail-item/.detail-label/.detail-value/.detail-link/.section-title 等
   已在 components.css 共用，這裡只留這個頁面獨有的視覺元件 */
.detail-layout { display: flex; flex-direction: column; gap: 0; }
.detail-main { display: flex; flex-direction: column; gap: 0; }

.tab-panel {
  border-radius: 0 0 10px 10px !important;
  border-top: none !important;
  margin-bottom: 16px;
}

.section-title-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 8px; }
.section-subtitle { font-size: 12.5px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 10px; }
.empty-inline { font-size: 13px; color: var(--text-secondary); padding: 8px 0; }
.gap-reason-list { margin: 6px 0 0; padding-left: 16px; font-size: 12px; color: var(--text-secondary); line-height: 1.6; }

.status-badge {
  display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px;
  font-size: 11px; font-weight: 600; white-space: nowrap;
}
.status-matched { background: #dcfce7; color: #16a34a; }
.status-pending { background: #fff7ed; color: #ea580c; }

.source-badge { display: inline-block; padding: 2px 7px; border-radius: 4px; font-size: 11px; font-family: 'Fira Code', monospace; font-weight: 600; }
.source-webhook { background: #eff6ff; color: #2563eb; }
.source-csv { background: #f5f3ff; color: #7c3aed; }
.source-manual { background: var(--surface-2); color: var(--text-secondary); border: 1px solid var(--border); }

/* 原料溯源表單 */
.origin-form {
  background: var(--surface-2); border-radius: 8px; padding: 14px; margin-bottom: 12px;
  display: flex; flex-direction: column; gap: 10px; border: 1px solid var(--border);
}
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.form-group { display: flex; flex-direction: column; gap: 4px; }
.form-label { font-size: 12px; font-weight: 600; color: var(--text-secondary); }
.req { color: #dc2626; }
.form-input {
  border: 1px solid var(--border); border-radius: 6px; padding: 7px 10px;
  font-size: 13px; background: var(--surface); color: var(--text-primary);
}
.form-input:focus { outline: none; border-color: var(--accent); }
.form-select { border: 1px solid var(--border); border-radius: 6px; padding: 7px 10px; font-size: 13px; background: var(--surface); color: var(--text-primary); }

.origins-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 8px; }
.origin-empty { font-size: 13px; color: var(--text-secondary); text-align: center; padding: 20px 0; font-style: italic; }
.origin-card { border: 1px solid var(--border); border-radius: 8px; padding: 12px; margin-bottom: 8px; background: var(--surface); }
.origin-card:hover { border-color: var(--accent); }
.origin-header { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.origin-name { font-weight: 600; font-size: 13px; flex: 1; color: var(--text-primary); }
.origin-country {
  background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 4px; padding: 1px 7px;
  font-size: 11px; font-family: 'Fira Code', monospace; font-weight: 700; color: #15803d;
}
.remove-btn { background: none; border: none; color: #dc2626; cursor: pointer; font-size: 16px; line-height: 1; padding: 0; opacity: 0.6; }
.remove-btn:hover { opacity: 1; }
.origin-detail { font-size: 12px; color: var(--text-secondary); margin-top: 4px; }
.origin-gps { display: flex; align-items: center; gap: 10px; margin-top: 4px; font-size: 12px; }
.maps-link { color: var(--accent); text-decoration: none; font-size: 12px; }
.maps-link:hover { text-decoration: underline; }
.cert { font-family: 'Fira Code', monospace; color: #7c3aed; }

.tag-list { display: flex; flex-wrap: wrap; gap: 6px; }
.section-hint { font-size: 11px; color: var(--text-secondary); margin: 2px 0 12px; }

/* 供應鏈合規調查卡片：BOM 表 → 選擇供應商 → 溯源調查 → 合規調查清單 */
.scc-list { display: flex; flex-direction: column; gap: 10px; }
.scc-card { border: 1px solid var(--border); border-radius: 8px; padding: 12px 14px; background: var(--surface); }
.scc-header { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; flex-wrap: wrap; }
.scc-material { font-weight: 600; font-size: 13.5px; color: var(--text-primary); }
.scc-section { display: flex; align-items: flex-start; gap: 8px; padding: 4px 0; font-size: 12.5px; }
.scc-label { flex-shrink: 0; width: 64px; font-size: 11px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .04em; padding-top: 2px; }
.scc-value { color: var(--text-primary); margin-right: 10px; }
.scc-docs { display: flex; flex-wrap: wrap; gap: 6px; }

.doc-chip { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 500; padding: 2px 8px; border-radius: 4px; background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary); white-space: nowrap; }
.doc-chip--valid { border-color: #86efac; background: #f0fdf4; color: #15803d; }
.doc-chip--missing { border-color: #fecaca; background: #fef2f2; color: #b91c1c; }
.doc-chip--expired { border-color: #fed7aa; background: #fff7ed; color: #c2410c; }
.doc-chip--expiring_soon { border-color: #fde68a; background: #fffbeb; color: #92400e; }
.doc-exp { font-size: 10px; font-weight: 400; opacity: 0.8; }
</style>
