<template>
  <div class="page-container">
    <!-- 頁頭 -->
    <div class="page-header">
      <div style="display:flex; align-items:center; gap:12px;">
        <button class="btn btn-secondary btn-sm" @click="$router.push('/sales-products')">← 返回列表</button>
        <div v-if="product">
          <h1 class="page-title">{{ product.name }}</h1>
          <p class="page-subtitle">
            <span v-if="product.product_code" class="font-mono">{{ product.product_code }} · </span>
            <span class="font-mono">{{ product.hs_code }}</span>
            <span v-if="product.customer_name"> · {{ product.customer_name }}</span>
          </p>
        </div>
      </div>
      <div v-if="product" style="display:flex;gap:8px;align-items:center;">
        <span class="badge" :class="complianceBadgeClass(product.upstream_compliance_status)">
          {{ STATUS_LABELS[product.upstream_compliance_status] ?? product.upstream_compliance_status }}
        </span>
        <template v-if="!isEditing">
          <button class="btn btn-secondary btn-sm" @click="enterEditMode">編輯</button>
          <button class="btn btn-danger btn-sm" @click="showDeleteConfirm = true">刪除</button>
        </template>
        <template v-else>
          <button class="btn btn-secondary btn-sm" @click="cancelEdit">取消</button>
          <button class="btn btn-primary btn-sm" :disabled="isSaving" @click="saveEdit">
            {{ isSaving ? '儲存中…' : '儲存' }}
          </button>
        </template>
      </div>
    </div>

    <div v-if="isLoading" class="loading-mask">載入中…</div>
    <div v-else-if="!product" class="empty-state"><p>找不到此銷售商品</p></div>
    <div v-else class="detail-layout">
      <div class="detail-main">

        <!-- Tab 導覽 -->
        <div class="detail-tabs">
          <button
            v-for="t in visibleTabs" :key="t.key"
            class="detail-tab"
            :class="{ active: activeTab === t.key }"
            @click="switchTab(t.key)"
          >{{ t.label }}</button>
        </div>

        <!-- ══ 基本資訊 ══ -->
        <div v-show="activeTab === 'info'" class="detail-section tab-panel">
          <div class="detail-grid">
            <div class="detail-item">
              <span class="detail-label">品項名稱</span>
              <input v-if="isEditing" v-model="editForm.name" class="form-input" />
              <span v-else class="detail-value">{{ product.name }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">SKU 品號</span>
              <span class="detail-value font-mono">{{ product.product_code || '—' }}</span>
              <span v-if="isEditing" style="display:block;font-size:11px;color:var(--text-secondary);">僅可透過 ERP 同步建立，不可修改</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">型號</span>
              <input v-if="isEditing" v-model="editForm.model_no" class="form-input font-mono" />
              <span v-else class="detail-value font-mono">{{ product.model_no || '—' }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">HS Code</span>
              <input v-if="isEditing" v-model="editForm.hs_code" class="form-input font-mono" />
              <span v-else class="detail-value font-mono">{{ product.hs_code }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">單位</span>
              <input v-if="isEditing" v-model="editForm.unit" class="form-input" />
              <span v-else class="detail-value">{{ product.unit || '—' }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">單價</span>
              <input v-if="isEditing" v-model.number="editForm.unit_price" class="form-input font-mono" type="number" />
              <span v-else class="detail-value font-mono">
                {{ product.unit_price != null ? product.unit_price.toFixed(2) : '—' }}
                <span v-if="product.unit_price != null && product.currency" style="font-size:11px;opacity:0.6;"> {{ product.currency }}</span>
              </span>
            </div>
            <div class="detail-item">
              <span class="detail-label">幣別</span>
              <select v-if="isEditing" v-model="editForm.currency" class="form-select">
                <option>USD</option><option>EUR</option><option>TWD</option>
              </select>
              <span v-else class="detail-value">{{ product.currency || '—' }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">客戶</span>
              <select v-if="isEditing" v-model="editForm.customer_id" class="form-select">
                <option value="">— 不指定 —</option>
                <option v-for="c in allCustomers" :key="c.id" :value="c.id">{{ c.name }} ({{ c.code }})</option>
              </select>
              <span v-else class="detail-value">{{ product.customer_name || '—' }}</span>
            </div>
            <div class="detail-item" style="grid-column:1/-1;">
              <span class="detail-label">說明</span>
              <textarea v-if="isEditing" v-model="editForm.description" class="form-input" rows="2" />
              <span v-else class="detail-value">{{ product.description || '—' }}</span>
            </div>
          </div>

          <!-- 內含碳排量摘要 -->
          <div class="stat-highlight" style="margin-top:20px;">
            <div class="stat-highlight-title">內含碳排量</div>
            <div style="display:flex;align-items:baseline;gap:10px;margin-top:8px;">
              <template v-if="product.embedded_emissions != null">
                <span class="stat-highlight-value">{{ product.embedded_emissions.toFixed(4) }}</span>
                <span style="font-size:13px;color:var(--text-secondary);">kgCO₂e/u</span>
                <span class="tag" :class="`esb-${product.emissions_source}`" style="margin-left:4px;">
                  {{ { pcf_sync: 'PCF 同步', supplier_reported: '供應商回報', manual: '手動輸入' }[product.emissions_source ?? ''] ?? product.emissions_source }}
                </span>
              </template>
              <span v-else style="color:var(--text-secondary);font-size:14px;">尚未計算 — 請至 PCF 快照 Tab 觸發計算</span>
            </div>
            <div v-if="product.emissions_updated_at" style="font-size:11px;color:var(--text-secondary);margin-top:4px;">
              更新於 {{ product.emissions_updated_at.slice(0, 16) }}
            </div>
          </div>

          <!-- 包材資訊 -->
          <div class="section-title" style="margin-top:20px;display:flex;align-items:center;justify-content:space-between;">
            <span>包材資訊</span>
            <button v-if="!packagingEditing" class="btn btn-secondary btn-xs" @click="openPackagingEdit">
              {{ packaging ? '編輯' : '＋ 填寫包材資訊' }}
            </button>
          </div>
          <div v-if="!packagingEditing" class="detail-grid" style="grid-template-columns:repeat(4,1fr);">
            <div class="detail-item">
              <span class="detail-label">再生料比例</span>
              <span class="detail-value font-mono">{{ packaging?.recycled_content_ratio != null ? packaging.recycled_content_ratio + '%' : '—' }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">可回收</span>
              <span class="detail-value">{{ packaging?.recyclable == null ? '—' : (packaging.recyclable ? '是' : '否') }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">可重複使用</span>
              <span class="detail-value">{{ packaging?.reusable == null ? '—' : (packaging.reusable ? '是' : '否') }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">包材說明</span>
              <span class="detail-value">{{ packaging?.material_description || '—' }}</span>
            </div>
          </div>
          <div v-else class="detail-grid" style="grid-template-columns:repeat(4,1fr);">
            <div class="detail-item">
              <span class="detail-label">再生料比例（%）</span>
              <input v-model.number="packagingForm.recycled_content_ratio" class="form-input font-mono" type="number" min="0" max="100" step="0.01" />
            </div>
            <div class="detail-item">
              <span class="detail-label">可回收</span>
              <select v-model="packagingForm.recyclable" class="form-select">
                <option :value="null">未設定</option>
                <option :value="true">是</option>
                <option :value="false">否</option>
              </select>
            </div>
            <div class="detail-item">
              <span class="detail-label">可重複使用</span>
              <select v-model="packagingForm.reusable" class="form-select">
                <option :value="null">未設定</option>
                <option :value="true">是</option>
                <option :value="false">否</option>
              </select>
            </div>
            <div class="detail-item">
              <span class="detail-label">包材說明</span>
              <input v-model="packagingForm.material_description" class="form-input" placeholder="如：再生紙箱" />
            </div>
            <div style="grid-column:1/-1;display:flex;gap:8px;">
              <button class="btn btn-secondary btn-sm" @click="packagingEditing = false">取消</button>
              <button class="btn btn-primary btn-sm" :disabled="packagingSaving" @click="savePackaging">
                {{ packagingSaving ? '儲存中…' : '儲存' }}
              </button>
            </div>
          </div>
        </div>

        <!-- ══ 法規適用 ══ -->
        <div v-show="activeTab === 'regulations'" class="detail-section tab-panel">
          <div class="detail-section-head">適用法規清單</div>
          <p class="section-hint">人工確認後的正式適用法規，供出口審查與合規檢核採用</p>
          <div v-if="product.applicable_regulations?.length" class="reg-tags">
            <span v-for="r in product.applicable_regulations" :key="r" class="tag tag-reg tag-removable">
              {{ r }}
              <button class="tag-remove" :disabled="regSaving" title="移除" @click="removeApplicableReg(r)">×</button>
            </span>
          </div>
          <p v-else class="empty-hint">尚無明確適用法規，可從下方 AI 推算法規採用</p>

          <div class="detail-section-head" style="margin-top:20px;">AI 推算法規</div>
          <p class="section-hint">系統依 BOM 物料群組自動推算，採用後才會列入正式適用法規清單</p>
          <div v-if="unadoptedInferredRegs.length || adoptedInferredRegs.length" class="reg-tags">
            <span v-for="r in unadoptedInferredRegs" :key="r" class="tag tag-inferred tag-adoptable">
              {{ r }}
              <button class="tag-adopt" :disabled="regSaving" title="採用" @click="adoptRegulation(r)">＋ 採用</button>
            </span>
            <span v-for="r in adoptedInferredRegs" :key="r" class="tag tag-inferred tag-adopted" title="已採用至適用法規清單">
              ✓ {{ r }}
            </span>
          </div>
          <p v-else class="empty-hint">尚無 AI 推算結果</p>

          <div style="margin-top:16px;display:flex;gap:8px;">
            <button class="btn btn-secondary" :disabled="syncingReg" @click="syncRegulations">
              {{ syncingReg ? '推算中…' : '🔄 重新推算法規' }}
            </button>
            <button v-if="unadoptedInferredRegs.length" class="btn btn-secondary" :disabled="regSaving" @click="adoptAllRegulations">
              {{ regSaving ? '採用中…' : `採用全部建議（${unadoptedInferredRegs.length}）` }}
            </button>
          </div>
        </div>

        <!-- ══ 電池規格（dpp_category = battery 專用） ══ -->
        <div v-show="activeTab === 'battery'" class="detail-section tab-panel">
          <div class="detail-section-head">電池類別與化學系統</div>
          <p class="section-hint">依 EU 電池法規 (EU) 2023/1542 DPP 最小揭露欄位，人工填報</p>
          <div class="detail-grid" style="grid-template-columns:repeat(3,1fr);">
            <div class="detail-item">
              <span class="detail-label">電池類別</span>
              <select v-model="batteryForm.battery_category" class="form-select">
                <option :value="null">未設定</option>
                <option value="portable">可攜式</option>
                <option value="industrial">工業用</option>
                <option value="ev">電動車</option>
                <option value="lmt">LMT 輕型載具</option>
              </select>
            </div>
            <div class="detail-item">
              <span class="detail-label">化學系統</span>
              <input v-model="batteryForm.chemistry" class="form-input" placeholder="如 Li-ion NMC / LFP / 鉛酸" />
            </div>
            <div class="detail-item">
              <span class="detail-label">重量（kg）</span>
              <input v-model.number="batteryForm.weight_kg" class="form-input font-mono" type="number" min="0" step="0.001" />
            </div>
            <div class="detail-item">
              <span class="detail-label">額定容量（Ah）</span>
              <input v-model.number="batteryForm.rated_capacity_ah" class="form-input font-mono" type="number" min="0" step="0.001" />
            </div>
            <div class="detail-item">
              <span class="detail-label">額定電壓（V）</span>
              <input v-model.number="batteryForm.rated_voltage_v" class="form-input font-mono" type="number" min="0" step="0.01" />
            </div>
          </div>

          <div class="detail-section-head" style="margin-top:20px;">關鍵原料回收含量（%）</div>
          <p class="section-hint">EU 電池法規指定金屬，與紡織品泛用再生料比例是不同的欄位</p>
          <div class="detail-grid" style="grid-template-columns:repeat(4,1fr);">
            <div class="detail-item">
              <span class="detail-label">鋰</span>
              <input v-model.number="batteryForm.lithium_recycled_content_ratio" class="form-input font-mono" type="number" min="0" max="100" step="0.01" />
            </div>
            <div class="detail-item">
              <span class="detail-label">鈷</span>
              <input v-model.number="batteryForm.cobalt_recycled_content_ratio" class="form-input font-mono" type="number" min="0" max="100" step="0.01" />
            </div>
            <div class="detail-item">
              <span class="detail-label">鎳</span>
              <input v-model.number="batteryForm.nickel_recycled_content_ratio" class="form-input font-mono" type="number" min="0" max="100" step="0.01" />
            </div>
            <div class="detail-item">
              <span class="detail-label">鉛</span>
              <input v-model.number="batteryForm.lead_recycled_content_ratio" class="form-input font-mono" type="number" min="0" max="100" step="0.01" />
            </div>
          </div>

          <div class="detail-section-head" style="margin-top:20px;">效能與耐久性</div>
          <div class="detail-grid" style="grid-template-columns:repeat(3,1fr);">
            <div class="detail-item">
              <span class="detail-label">循環壽命（次）</span>
              <input v-model.number="batteryForm.cycle_life" class="form-input font-mono" type="number" min="0" />
            </div>
            <div class="detail-item">
              <span class="detail-label">預期使用年限（年）</span>
              <input v-model.number="batteryForm.expected_lifetime_years" class="form-input font-mono" type="number" min="0" />
            </div>
            <div class="detail-item">
              <span class="detail-label">放電效率（%）</span>
              <input v-model.number="batteryForm.discharge_efficiency_ratio" class="form-input font-mono" type="number" min="0" max="100" step="0.01" />
            </div>
            <div class="detail-item">
              <span class="detail-label">操作溫度範圍</span>
              <input v-model="batteryForm.operating_temp_range" class="form-input" placeholder="如 -20°C ~ 60°C" />
            </div>
            <div class="detail-item" style="grid-column:1/-1;">
              <span class="detail-label">初始容量 / SoH 說明</span>
              <input v-model="batteryForm.initial_capacity_soh_note" class="form-input" placeholder="初始容量與健康狀態（SoH）量測方法說明" />
            </div>
          </div>

          <div style="margin-top:16px;">
            <button class="btn btn-primary btn-sm" :disabled="batterySpecSaving" @click="saveBatterySpec">
              {{ batterySpecSaving ? '儲存中…' : '儲存電池規格' }}
            </button>
          </div>
        </div>

        <!-- ══ BOM 明細 ══ -->
        <div v-show="activeTab === 'bom'" class="detail-section tab-panel">
          <div v-if="bomLoading" class="loading-hint">載入中…</div>
          <div v-else>
            <table v-if="bomLines.length" class="data-table">
              <thead>
                <tr>
                  <th>物料名稱</th>
                  <th>HS Code</th>
                  <th>類型</th>
                  <th>數量</th>
                  <th>子產品</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="bl in bomLines" :key="bl.id">
                  <td>
                    {{ bl.child_sales_product_id ? (bl.child_sales_product?.name ?? '—') : (bl.effective_material_name ?? bl.material_name) }}
                    <span v-if="bl.linkage_status === 'unlinked' && !bl.child_sales_product_id" class="badge badge-unlinked" style="margin-left:6px;font-size:10px;">未連結主檔</span>
                  </td>
                  <td class="font-mono" style="font-size:11px;">{{ (bl.effective_hs_code ?? bl.hs_code) || '—' }}</td>
                  <td>
                    <span v-if="bl.child_sales_product_id" class="tag tag-blue">子產品</span>
                    <span v-else class="tag tag-gray">{{ BOM_LINE_TYPE_LABELS[bl.bom_line_type] ?? bl.bom_line_type }}</span>
                  </td>
                  <td class="font-mono">{{ bl.quantity ?? '—' }}</td>
                  <td>
                    <span v-if="bl.child_sales_product_id" class="font-mono" style="font-size:11px;">
                      {{ bl.child_sales_product?.product_code || bl.child_sales_product_id?.slice(0, 8) }}…
                    </span>
                    <span v-else class="no-data">—</span>
                  </td>
                  <td>
                    <button class="btn btn-danger btn-sm" title="依商業機密保全考量刪除" @click="deleteBomLine(bl.id)">✕</button>
                  </td>
                </tr>
              </tbody>
            </table>
            <p v-else class="empty-hint">尚無 BOM 明細</p>

            <p class="erp-note">BOM 結構由 ERP 同步匯入（不可手動新增）；如涉商業機密，可刪除個別列。</p>

            <!-- 上游供應商彙總（由 BOM 明細＋物料核可供應商清單反推，唯讀；於「物料管理」維護核可清單） -->
            <div class="detail-section-head" style="margin-top:24px;">上游供應商彙總</div>
            <p class="section-hint">依 BOM 明細與物料核可供應商清單自動反推，唯讀；如需新增/調整核可供應商，請至「物料管理」對應物料頁面維護</p>
            <table v-if="upstreamDetails.length" class="data-table">
              <thead>
                <tr>
                  <th>供應商</th>
                  <th>物料群組</th>
                  <th>製程廠區</th>
                  <th>合規狀態</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="s in upstreamDetails" :key="s.supplier_id">
                  <td>{{ maskSupplierName(s.supplier_name) }}</td>
                  <td>{{ s.material_group || '—' }}</td>
                  <td>
                    <template v-if="s.supplier_facility_name">
                      {{ s.supplier_facility_name }}
                      <span class="tag tag-gray" style="margin-left:4px;">{{ FACILITY_TYPE_LABELS[s.facility_type ?? ''] ?? s.facility_type }}</span>
                    </template>
                    <span v-else class="no-data">—</span>
                  </td>
                  <td>
                    <span class="status-dot" :class="`status-dot--${s.status}`" :title="STATUS_LABELS[s.status] ?? s.status"></span>
                    {{ STATUS_LABELS[s.status] ?? s.status }}
                  </td>
                </tr>
              </tbody>
            </table>
            <p v-else class="empty-hint">尚無上游供應商資料——BOM 明細的物料尚未套用核可供應商清單</p>
          </div>
        </div>

        <!-- ══ 生產批次 ══ -->
        <div v-show="activeTab === 'batches'" class="detail-section tab-panel">
          <table v-if="productionBatches.length" class="data-table">
            <thead>
              <tr>
                <th>生產批號</th>
                <th>生產工單</th>
                <th>生產日期</th>
                <th style="text-align:right;">數量</th>
                <th style="text-align:right;">批次碳排</th>
                <th>縫製工廠</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="b in productionBatches" :key="b.id">
                <td class="font-mono">
                  <router-link :to="`/compliance/production-batches/${b.id}`" style="color:var(--accent);text-decoration:none;">{{ b.erp_batch_no }}</router-link>
                </td>
                <td class="font-mono" style="font-size:11px;color:var(--text-secondary);">{{ b.erp_order_no || '—' }}</td>
                <td class="font-mono">{{ b.production_date || '—' }}</td>
                <td class="font-mono" style="text-align:right;">{{ b.quantity != null ? Number(b.quantity).toLocaleString() : '—' }} {{ b.unit }}</td>
                <td class="font-mono" style="text-align:right;">
                  <template v-if="b.lot_pcf != null">{{ Number(b.lot_pcf).toFixed(2) }} <span class="unit">kgCO₂e/件</span></template>
                  <template v-else>—</template>
                </td>
                <td>{{ b.supplier_name ? maskSupplierName(b.supplier_name) : '—' }}<span v-if="b.supplier_code" class="font-mono" style="font-size:11px;color:var(--text-secondary);"> · {{ b.supplier_code }}</span></td>
              </tr>
            </tbody>
          </table>
          <p v-else class="empty-hint">尚無生產批次紀錄</p>
        </div>

        <!-- ══ PCF 快照 ══ -->
        <div v-show="activeTab === 'pcf'" class="detail-section tab-panel">
          <div v-if="pcfLoading" class="loading-hint">載入中…</div>
          <div v-else>
            <div v-if="pcfSnapshot" class="pcf-summary">
              <div class="pcf-total">
                <span class="pcf-label">總 PCF</span>
                <span class="pcf-val font-mono">{{ pcfSnapshot.total_pcf?.toFixed(4) ?? '—' }} kgCO₂e/u</span>
              </div>
              <div class="pcf-meta">
                <span class="tag" :class="pcfSnapshot.iso14067_ready ? 'tag-ok' : 'tag-warn'">
                  {{ pcfSnapshot.iso14067_ready ? 'ISO 14067 Ready' : '資料不完整' }}
                </span>
                <span class="font-mono" style="font-size:11px;color:var(--text-secondary);">
                  快照時間：{{ pcfSnapshot.snapshot_at?.slice(0, 16) }}
                </span>
                <span v-if="pcfSnapshot.pcr_incomplete_lines > 0" class="tag tag-warn" style="font-size:11px;">
                  {{ pcfSnapshot.pcr_incomplete_lines }} 筆資料不完整
                </span>
              </div>

              <!-- PCF 明細 -->
              <div v-if="pcfSnapshot.lines?.length" style="margin-top:16px;">
                <div class="detail-section-head">PCF 構成明細</div>
                <table class="data-table" style="margin-top:8px;">
                  <thead><tr><th>項目</th><th>類型</th><th>數量</th><th>kgCO₂e/u</th></tr></thead>
                  <tbody>
                    <tr v-for="(line, i) in pcfSnapshot.lines" :key="i">
                      <td>{{ line.material_name ?? '—' }}</td>
                      <td>{{ line.bom_line_type ?? '—' }}</td>
                      <td class="font-mono">{{ line.quantity ?? '—' }}</td>
                      <td class="font-mono">{{ line.line_pcf?.toFixed(4) ?? '—' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <p v-else class="empty-hint">尚無 PCF 快照</p>

            <div style="margin-top:16px;">
              <button class="btn btn-secondary" :disabled="recalcLoading" @click="recalcPcf">
                {{ recalcLoading ? '計算中…' : '🔄 重新計算 PCF' }}
              </button>
            </div>
          </div>
        </div>

        <!-- ══ 循環經濟 ══ -->
        <div v-show="activeTab === 'circularity'" class="detail-section tab-panel">
          <div v-if="circularityLoading" class="loading-hint">載入中…</div>
          <div v-else>
            <div v-if="circularitySnapshot" class="pcf-summary">
              <div class="pcf-meta" style="margin-bottom:16px;">
                <span class="tag" :class="circularitySnapshot.data_ready ? 'tag-ok' : 'tag-warn'">
                  {{ circularitySnapshot.data_ready ? '資料完整' : `${circularitySnapshot.incomplete_lines_count} 筆物料缺重量資料` }}
                </span>
                <span class="font-mono" style="font-size:11px;color:var(--text-secondary);">
                  計算時間：{{ circularitySnapshot.calculated_at?.slice(0, 16) }}
                </span>
              </div>

              <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
                <div class="pcf-total">
                  <span class="pcf-label">回收材料比（PCR+PIR）</span>
                  <span class="pcf-val font-mono">{{ circularitySnapshot.recycled_content_ratio != null ? (circularitySnapshot.recycled_content_ratio * 100).toFixed(1) + '%' : '—' }}</span>
                </div>
                <div class="pcf-total">
                  <span class="pcf-label">生物基比例</span>
                  <span class="pcf-val font-mono">{{ circularitySnapshot.bio_based_ratio != null ? (circularitySnapshot.bio_based_ratio * 100).toFixed(1) + '%' : '—' }}</span>
                </div>
                <div class="pcf-total">
                  <span class="pcf-label">總重量</span>
                  <span class="pcf-val font-mono">{{ circularitySnapshot.total_weight_kg != null ? circularitySnapshot.total_weight_kg.toFixed(3) + ' kg' : '—' }}</span>
                </div>
              </div>

              <div v-if="circularitySnapshot.composition_breakdown?.length" style="margin-top:20px;">
                <div class="detail-section-head">成分佔比</div>
                <div style="display:flex;flex-direction:column;gap:8px;margin-top:8px;">
                  <div v-for="item in circularitySnapshot.composition_breakdown" :key="item.fiber_type" style="display:flex;align-items:center;gap:10px;">
                    <span style="width:160px;font-size:13px;color:var(--text-primary);">{{ item.fiber_type }}</span>
                    <div style="flex:1;background:var(--surface-2);border-radius:4px;height:10px;overflow:hidden;">
                      <div style="background:var(--accent);height:100%;" :style="{ width: item.percentage + '%' }"></div>
                    </div>
                    <span class="font-mono" style="width:60px;text-align:right;font-size:13px;">{{ item.percentage.toFixed(1) }}%</span>
                  </div>
                </div>
              </div>

              <div v-if="Object.keys(circularitySnapshot.recyclability_summary || {}).length" style="margin-top:20px;">
                <div class="detail-section-head">可回收性分布</div>
                <div style="display:flex;gap:16px;margin-top:8px;flex-wrap:wrap;">
                  <span v-for="(pct, rating) in circularitySnapshot.recyclability_summary" :key="rating" class="tag" style="font-size:12px;">
                    {{ RECYCLABILITY_LABELS[rating] ?? rating }}：{{ pct.toFixed(1) }}%
                  </span>
                </div>
              </div>
            </div>
            <p v-else class="empty-hint">尚無循環經濟資料</p>

            <div style="margin-top:16px;">
              <button class="btn btn-secondary" :disabled="circularityRecalcLoading" @click="recalcCircularity">
                {{ circularityRecalcLoading ? '計算中…' : '🔄 重新計算' }}
              </button>
            </div>
          </div>
        </div>

        <!-- ══ 碳排回報 ══ -->
        <div v-show="activeTab === 'emissions'" class="detail-section tab-panel">
          <div v-if="emissionsLoading" class="loading-hint">載入中…</div>
          <div v-else>
            <table v-if="emissions.length" class="data-table">
              <thead>
                <tr>
                  <th>供應商</th>
                  <th>kgCO₂e/unit</th>
                  <th>計算說明</th>
                  <th>回報時間</th>
                  <th>確認狀態</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="e in emissions" :key="e.id">
                  <td>{{ e.supplier?.name ? maskSupplierName(e.supplier.name) : '—' }}</td>
                  <td class="font-mono">{{ e.emissions_value.toFixed(4) }}</td>
                  <td>{{ e.calculation_note || '—' }}</td>
                  <td class="font-mono" style="font-size:11px;white-space:nowrap;">{{ e.reported_at?.slice(0, 16) }}</td>
                  <td>
                    <span v-if="e.confirmed_at" class="tag tag-ok">已確認</span>
                    <span v-else class="tag tag-pending">待確認</span>
                  </td>
                  <td>
                    <button
                      v-if="!e.confirmed_at"
                      class="btn btn-secondary"
                      style="font-size:11px;padding:3px 8px;"
                      :disabled="confirmingEmission[e.id]"
                      @click="confirmEmission(e.id)"
                    >
                      {{ confirmingEmission[e.id] ? '確認中…' : '確認採用' }}
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
            <p v-else class="empty-hint">尚無供應商碳排回報</p>
          </div>
        </div>

      </div>
    </div>

    <!-- 刪除確認 Modal -->
    <div v-if="showDeleteConfirm" class="modal-overlay" @click.self="showDeleteConfirm = false">
      <div class="modal" style="min-width:340px;">
        <div class="modal-header">
          <span class="modal-title">確認刪除</span>
          <button class="modal-close" @click="showDeleteConfirm = false">×</button>
        </div>
        <p style="padding:16px 0;font-size:14px;">確定刪除「{{ product?.name }}」？此動作無法復原。</p>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showDeleteConfirm = false">取消</button>
          <button class="btn btn-danger" :disabled="isDeleting" @click="doDelete">
            {{ isDeleting ? '刪除中…' : '確認刪除' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { salesProductApi, type SalesProduct, type BomLine, type PcfSnapshot, type ProductionBatch, type ProductPackaging, type ProductBatterySpec } from '@/api/modules/salesProducts'
import { customersApi, type Customer } from '@/api/modules/customers'
import { circularityApi, type CircularitySnapshot } from '@/api/modules/circularity'
import { maskSupplierName } from '@/utils/maskName'

const TABS = [
  { key: 'info',        label: '基本資訊' },
  { key: 'regulations', label: '法規適用' },
  { key: 'battery',     label: '電池規格', dppCategory: 'battery' },
  { key: 'bom',         label: 'BOM 明細' },
  { key: 'batches',     label: '生產批次' },
  { key: 'pcf',         label: 'PCF 快照' },
  { key: 'circularity', label: '循環經濟' },
  { key: 'emissions',   label: '碳排回報' },
]

const RECYCLABILITY_LABELS: Record<string, string> = {
  high: '高（易回收）', medium: '中', low: '低（難回收）', not_rated: '未評級',
}

const STATUS_LABELS: Record<string, string> = {
  valid: '合規', expiring_soon: '即將到期', expired: '已過期',
  missing: '缺文件', unconfigured: '未設定',
}

const FACILITY_TYPE_LABELS: Record<string, string> = {
  manufacturing: '一般製造', weaving: '織布', knitting: '針織', dyeing: '染整', printing: '印花',
  wet_processing: '濕製程', garment_assembly: '成衣製造', warehouse: '倉儲', office: '辦公室', other: '其他',
}

const BOM_LINE_TYPE_LABELS: Record<string, string> = {
  material: '物料', service: '服務',
}

export default defineComponent({
  name: 'SalesProductDetailView',
  data() {
    return {
      isLoading: true,
      isSaving: false,
      isDeleting: false,
      isEditing: false,
      showDeleteConfirm: false,
      product: null as SalesProduct | null,
      productionBatches: [] as ProductionBatch[],
      editForm: {} as Partial<SalesProduct> & { customer_id: string },
      activeTab: 'info',
      // BOM
      bomLines: [] as BomLine[],
      bomLoading: false,
      addBomForm: { material_name: '', hs_code: '' },
      addingBom: false,
      // PCF
      pcfSnapshot: null as PcfSnapshot | null,
      pcfLoading: false,
      recalcLoading: false,
      circularitySnapshot: null as CircularitySnapshot | null,
      circularityLoading: false,
      circularityRecalcLoading: false,
      RECYCLABILITY_LABELS,
      // 包材資訊
      packaging: null as ProductPackaging | null,
      packagingEditing: false,
      packagingSaving: false,
      packagingForm: {
        recycled_content_ratio: null as number | null,
        recyclable: null as boolean | null,
        reusable: null as boolean | null,
        material_description: '',
      },
      // 電池規格（dpp_category = battery 專用）
      batterySpec: null as ProductBatterySpec | null,
      batterySpecLoading: false,
      batterySpecSaving: false,
      batteryForm: {
        battery_category: null as string | null,
        chemistry: '',
        rated_capacity_ah: null as number | null,
        rated_voltage_v: null as number | null,
        weight_kg: null as number | null,
        lithium_recycled_content_ratio: null as number | null,
        cobalt_recycled_content_ratio: null as number | null,
        nickel_recycled_content_ratio: null as number | null,
        lead_recycled_content_ratio: null as number | null,
        cycle_life: null as number | null,
        expected_lifetime_years: null as number | null,
        discharge_efficiency_ratio: null as number | null,
        initial_capacity_soh_note: '',
        operating_temp_range: '',
      },
      // 碳排
      emissions: [] as any[],
      emissionsLoading: false,
      confirmingEmission: {} as Record<string, boolean>,
      // 法規
      syncingReg: false,
      regSaving: false,
      // 輔助資料
      allCustomers: [] as Customer[],
      TABS,
      STATUS_LABELS,
      FACILITY_TYPE_LABELS,
      BOM_LINE_TYPE_LABELS,
    }
  },
  computed: {
    // 依 dpp_category 過濾類別專屬分頁（如電池規格僅 dpp_category=battery 顯示）
    visibleTabs() {
      return TABS.filter(t => !t.dppCategory || t.dppCategory === this.product?.dpp_category)
    },
    // AI 推算法規中尚未被人工採用進「適用法規清單」的項目
    unadoptedInferredRegs(): string[] {
      const applicable = this.product?.applicable_regulations ?? []
      return (this.product?.inferred_regulations ?? []).filter(r => !applicable.includes(r))
    },
    // AI 推算法規中已被採用的項目（用於在建議區顯示「已採用」狀態）
    adoptedInferredRegs(): string[] {
      const applicable = this.product?.applicable_regulations ?? []
      return (this.product?.inferred_regulations ?? []).filter(r => applicable.includes(r))
    },
    // 上游供應商彙總（由 show() 的 upstream_details 帶回，BOM＋物料核可清單反推而得）
    upstreamDetails(): any[] {
      return (this.product as any)?.upstream_details ?? []
    },
  },
  async mounted() {
    const tab = this.$route.query.tab
    if (typeof tab === 'string' && TABS.some(t => t.key === tab)) {
      this.activeTab = tab
    }
    await this.loadProduct()
    this.loadAuxData()
    this.loadPackaging()
  },
  methods: {
    maskSupplierName,

    async loadProduct() {
      this.isLoading = true
      try {
        const { data } = await salesProductApi.show(this.$route.params.id as string)
        this.product = data.data
        this.productionBatches = data.data.production_batches ?? []
      } finally {
        this.isLoading = false
      }
    },
    async loadAuxData() {
      try {
        const { data } = await customersApi.list({ per_page: 200, status: 'active' })
        this.allCustomers = data.data
      } catch { /* silent */ }
    },
    // ── 包材資訊 ──
    async loadPackaging() {
      try {
        const { data } = await salesProductApi.packaging(this.$route.params.id as string)
        this.packaging = data.data
      } catch { /* silent */ }
    },
    openPackagingEdit() {
      this.packagingForm = {
        recycled_content_ratio: this.packaging?.recycled_content_ratio ?? null,
        recyclable: this.packaging?.recyclable ?? null,
        reusable: this.packaging?.reusable ?? null,
        material_description: this.packaging?.material_description ?? '',
      }
      this.packagingEditing = true
    },
    async savePackaging() {
      this.packagingSaving = true
      try {
        const { data } = await salesProductApi.upsertPackaging(this.$route.params.id as string, this.packagingForm)
        this.packaging = data.data
        this.packagingEditing = false
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '儲存失敗')
      } finally {
        this.packagingSaving = false
      }
    },
    switchTab(key: string) {
      this.activeTab = key
      if (key === 'bom' && !this.bomLines.length && !this.bomLoading) this.loadBom()
      if (key === 'pcf' && !this.pcfSnapshot && !this.pcfLoading) this.loadPcf()
      if (key === 'circularity' && !this.circularitySnapshot && !this.circularityLoading) this.loadCircularity()
      if (key === 'emissions' && !this.emissions.length && !this.emissionsLoading) this.loadEmissions()
      if (key === 'battery' && !this.batterySpec && !this.batterySpecLoading) this.loadBatterySpec()
    },
    // ── 電池規格 ──
    async loadBatterySpec() {
      this.batterySpecLoading = true
      try {
        const { data } = await salesProductApi.batterySpec(this.$route.params.id as string)
        this.batterySpec = data.data
        if (this.batterySpec) {
          this.batteryForm = {
            battery_category: this.batterySpec.battery_category,
            chemistry: this.batterySpec.chemistry ?? '',
            rated_capacity_ah: this.batterySpec.rated_capacity_ah,
            rated_voltage_v: this.batterySpec.rated_voltage_v,
            weight_kg: this.batterySpec.weight_kg,
            lithium_recycled_content_ratio: this.batterySpec.lithium_recycled_content_ratio,
            cobalt_recycled_content_ratio: this.batterySpec.cobalt_recycled_content_ratio,
            nickel_recycled_content_ratio: this.batterySpec.nickel_recycled_content_ratio,
            lead_recycled_content_ratio: this.batterySpec.lead_recycled_content_ratio,
            cycle_life: this.batterySpec.cycle_life,
            expected_lifetime_years: this.batterySpec.expected_lifetime_years,
            discharge_efficiency_ratio: this.batterySpec.discharge_efficiency_ratio,
            initial_capacity_soh_note: this.batterySpec.initial_capacity_soh_note ?? '',
            operating_temp_range: this.batterySpec.operating_temp_range ?? '',
          }
        }
      } catch { /* silent */ } finally {
        this.batterySpecLoading = false
      }
    },
    async saveBatterySpec() {
      this.batterySpecSaving = true
      try {
        const { data } = await salesProductApi.upsertBatterySpec(this.$route.params.id as string, this.batteryForm)
        this.batterySpec = data.data
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '儲存失敗')
      } finally {
        this.batterySpecSaving = false
      }
    },
    // ── BOM ──
    async loadBom() {
      this.bomLoading = true
      try {
        const { data } = await salesProductApi.bomLines(this.$route.params.id as string)
        this.bomLines = data.data
      } finally { this.bomLoading = false }
    },
    async addBomLine() {
      if (!this.addBomForm.material_name) return
      this.addingBom = true
      try {
        await salesProductApi.createBomLine(this.$route.params.id as string, {
          material_name: this.addBomForm.material_name,
          hs_code: this.addBomForm.hs_code || undefined,
          bom_line_type: 'material',
        })
        this.addBomForm = { material_name: '', hs_code: '' }
        await this.loadBom()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '新增失敗')
      } finally { this.addingBom = false }
    },
    async deleteBomLine(lineId: string) {
      try {
        await salesProductApi.destroyBomLine(this.$route.params.id as string, lineId)
        await this.loadBom()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '刪除失敗')
      }
    },
    // ── PCF ──
    async loadPcf() {
      this.pcfLoading = true
      try {
        const { data } = await salesProductApi.pcfLatest(this.$route.params.id as string)
        this.pcfSnapshot = data.data
      } finally { this.pcfLoading = false }
    },
    async recalcPcf() {
      this.recalcLoading = true
      try {
        const { data } = await salesProductApi.pcfRecalc(this.$route.params.id as string)
        this.pcfSnapshot = data.data
        await this.loadProduct()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '計算失敗')
      } finally { this.recalcLoading = false }
    },
    async loadCircularity() {
      this.circularityLoading = true
      try {
        const { data } = await circularityApi.latest(this.$route.params.id as string)
        this.circularitySnapshot = data.data
      } finally { this.circularityLoading = false }
    },
    async recalcCircularity() {
      this.circularityRecalcLoading = true
      try {
        const { data } = await circularityApi.recalc(this.$route.params.id as string)
        this.circularitySnapshot = data.data
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '計算失敗')
      } finally { this.circularityRecalcLoading = false }
    },
    // ── 碳排回報 ──
    async loadEmissions() {
      this.emissionsLoading = true
      try {
        const { data } = await salesProductApi.emissionReports(this.$route.params.id as string)
        this.emissions = data.data
      } finally { this.emissionsLoading = false }
    },
    async confirmEmission(emissionId: string) {
      this.confirmingEmission[emissionId] = true
      try {
        await salesProductApi.confirmEmission(this.$route.params.id as string, emissionId)
        await this.loadEmissions()
        await this.loadProduct()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '確認失敗')
      } finally { this.confirmingEmission[emissionId] = false }
    },
    // ── 法規推算 ──
    async syncRegulations() {
      this.syncingReg = true
      try {
        const { data } = await salesProductApi.syncRegulations(this.$route.params.id as string)
        if (this.product) {
          this.product.inferred_regulations = data.data.inferred_regulations
          this.product.applicable_regulations = data.data.applicable_regulations
        }
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '推算失敗')
      } finally { this.syncingReg = false }
    },
    // 採用單一 AI 推算法規：併入 applicable_regulations 並儲存
    async adoptRegulation(reg: string) {
      if (!this.product) return
      const next = Array.from(new Set([...(this.product.applicable_regulations ?? []), reg]))
      await this.saveApplicableRegs(next)
    },
    // 採用所有尚未採用的 AI 推算法規
    async adoptAllRegulations() {
      if (!this.product) return
      const next = Array.from(new Set([...(this.product.applicable_regulations ?? []), ...this.unadoptedInferredRegs]))
      await this.saveApplicableRegs(next)
    },
    // 從適用法規清單移除一項（人工取消確認）
    async removeApplicableReg(reg: string) {
      if (!this.product) return
      const next = (this.product.applicable_regulations ?? []).filter(r => r !== reg)
      await this.saveApplicableRegs(next)
    },
    async saveApplicableRegs(regs: string[]) {
      if (!this.product) return
      this.regSaving = true
      try {
        const { data } = await salesProductApi.update(this.product.id, { applicable_regulations: regs })
        this.product.applicable_regulations = data.data.applicable_regulations
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '更新適用法規清單失敗')
      } finally { this.regSaving = false }
    },
    // ── 編輯 ──
    enterEditMode() {
      if (!this.product) return
      this.editForm = {
        name: this.product.name,
        product_code: this.product.product_code ?? '',
        model_no: this.product.model_no ?? '',
        hs_code: this.product.hs_code,
        unit: this.product.unit ?? '',
        unit_price: this.product.unit_price,
        currency: this.product.currency ?? 'USD',
        description: this.product.description ?? '',
        customer_id: this.product.customer_id ?? '',
      }
      this.isEditing = true
    },
    cancelEdit() { this.isEditing = false },
    async saveEdit() {
      if (!this.product) return
      this.isSaving = true
      try {
        const payload = { ...this.editForm, customer_id: (this.editForm.customer_id as string) || null }
        await salesProductApi.update(this.product.id, payload)
        await this.loadProduct()
        this.isEditing = false
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '儲存失敗')
      } finally { this.isSaving = false }
    },
    // ── 刪除 ──
    async doDelete() {
      if (!this.product) return
      this.isDeleting = true
      try {
        await salesProductApi.destroy(this.product.id)
        this.$router.push('/sales-products')
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '刪除失敗')
        this.isDeleting = false
      }
    },
    complianceBadgeClass(status: string) {
      return {
        valid:         'badge-green',
        expiring_soon: 'badge-yellow',
        expired:       'badge-red',
        missing:       'badge-red',
        unconfigured:  'badge-gray',
      }[status] ?? 'badge-gray'
    },
  },
})
</script>

<style scoped>
.detail-layout { display: flex; flex-direction: column; gap: 0; }
.detail-main { display: flex; flex-direction: column; gap: 0; }

/* .detail-tabs/.detail-tab 已移至 components.css 共用（跟供應商/物料詳情頁同一套） */
.tab-panel {
  border-radius: 0 0 8px 8px !important;
  border-top: none !important;
  margin-bottom: 16px;
}

/* ── Section ── */
.detail-section {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 20px 24px;
  margin-bottom: 16px;
}

/* .detail-grid/.detail-item/.detail-label/.detail-value 已移至 components.css 共用 */

/* ── Badge（供應商合規狀態）── */
.badge-green    { background: #dcfce7; color: #166534; }
.badge-yellow   { background: #fef9c3; color: #854d0e; }
.badge-red      { background: #fee2e2; color: #991b1b; }
.badge-gray     { background: var(--surface-2); color: #57534e; border: 1px solid var(--border); }
.badge-unlinked { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }

/* ── 補充 tag ── */
.tag-warn { background: #fef9c3; color: #854d0e; border: 1px solid #fde047; }

/* ── 雜項 ── */
.no-data { font-size: 13px; color: var(--text-secondary); }


.reg-tags { display: flex; flex-wrap: wrap; gap: 6px; }
.tag-reg { background: #ede9fe; color: #6d28d9; border: 1px solid #c4b5fd; }
.tag-inferred { background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; }
.section-hint { font-size: 11px; color: var(--text-secondary); margin: 2px 0 8px; }

.tag-removable { display: inline-flex; align-items: center; gap: 5px; }
.tag-remove {
  border: none; background: transparent; color: inherit; opacity: 0.55;
  cursor: pointer; font-size: 13px; line-height: 1; padding: 0;
}
.tag-remove:hover:not(:disabled) { opacity: 1; color: #b91c1c; }
.tag-remove:disabled { cursor: not-allowed; }

.tag-adoptable { display: inline-flex; align-items: center; gap: 6px; }
.tag-adopt {
  border: 1px solid #bae6fd; background: #fff; color: #0369a1;
  border-radius: 3px; font-size: 10.5px; padding: 1px 6px; cursor: pointer; line-height: 1.6;
}
.tag-adopt:hover:not(:disabled) { background: #0369a1; color: #fff; }
.tag-adopt:disabled { cursor: not-allowed; opacity: 0.6; }
.tag-adopted { opacity: 0.6; }

.detail-section-head {
  font-size: 11.5px;
  font-weight: 700;
  letter-spacing: .07em;
  text-transform: uppercase;
  color: var(--text-secondary);
  margin-bottom: 8px;
}


.pcf-summary {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 16px;
}
.pcf-total {
  display: flex; flex-direction: column; gap: 4px;
  background: var(--accent-soft); border: 1px solid var(--accent-soft-border);
  border-radius: 8px; padding: 10px 14px;
}
.pcf-label { font-size: 12px; font-weight: 600; color: var(--text-secondary); }
.pcf-val { font-size: 22px; font-weight: 700; color: var(--accent); }
.pcf-meta { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

.add-row {
  display: flex;
  gap: 8px;
  align-items: center;
  margin-top: 14px;
  padding-top: 12px;
  border-top: 1px dashed var(--border);
}

.loading-hint { font-size: 13px; color: var(--text-secondary); padding: 20px 0; }
.empty-hint { font-size: 13px; color: var(--text-secondary); padding: 16px 0; }
.erp-note { font-size: 12px; color: var(--text-secondary); padding: 10px 2px 0; border-top: 1px dashed var(--border); margin-top: 10px; }

/* 碳排來源 badge */
.esb-pcf_sync        { background: #dcfce7; color: #15803d; padding: 1px 6px; border-radius: 3px; font-size: 11px; font-weight: 700; }
.esb-supplier_reported { background: #dbeafe; color: #1d4ed8; padding: 1px 6px; border-radius: 3px; font-size: 11px; font-weight: 700; }
.esb-manual          { background: #f3f4f6; color: #6b7280; padding: 1px 6px; border-radius: 3px; font-size: 11px; font-weight: 700; }

.tag-blue { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
.tag-gray { background: #f3f4f6; color: #6b7280; border: 1px solid #e5e7eb; }
</style>
