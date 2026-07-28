<template>
  <div class="page-container" @click="activeRegInfo = ''">
    <div class="breadcrumb">
      <span class="breadcrumb-parent">商品合規管理</span>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current">合規看板</span>
    </div>
    <div class="page-header">
      <div>
        <h1 class="page-title">合規看板</h1>
        <p class="page-subtitle">EUDR / UFLPA / CMRT / SDS / CE 合規文件管理</p>
      </div>
    </div>

    <!-- 主 Tab -->
    <div class="tab-bar">
      <button class="tab-btn" :class="{ active: activeTab === 'supplier' }" @click="activeTab = 'supplier'">
        供應商視角
        <span class="tab-count">{{ supplierData.length }}</span>
      </button>
      <button class="tab-btn" :class="{ active: activeTab === 'sales-products' }" @click="switchToSalesProducts">
        銷售產品合規
        <span class="tab-count" v-if="salesProducts.length">{{ salesProducts.length }}</span>
      </button>
      <button class="tab-btn" :class="{ active: activeTab === 'matrix' }" @click="switchToMatrix">
        矩陣視角
      </button>
      <button class="tab-btn" :class="{ active: activeTab === 'dpp' }" @click="switchToDpp">
        ESPR/DPP
      </button>
      <button class="tab-btn" :class="{ active: activeTab === 'pending' }" @click="switchToPending">
        待審核
        <span class="tab-count" v-if="pendingLoaded || pendingDocs.length > 0">{{ pendingDocs.length }}</span>
      </button>
    </div>

    <div v-if="isLoading" class="loading-mask" style="padding:64px;text-align:center;">載入中...</div>

    <!-- 供應商視角 -->
    <template v-else-if="activeTab === 'supplier'">
      <!-- KPI 卡 -->
      <div class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-value">{{ supplierData.length }}</div>
          <div class="kpi-label">供應商總數</div>
        </div>
        <div class="kpi-card kpi-card--green">
          <div class="kpi-value kpi-value--green">{{ supplierStats.compliant }}</div>
          <div class="kpi-label">合規</div>
        </div>
        <div class="kpi-card kpi-card--orange">
          <div class="kpi-value kpi-value--orange">{{ supplierStats.expiring }}</div>
          <div class="kpi-label">即將到期</div>
        </div>
        <div class="kpi-card kpi-card--red">
          <div class="kpi-value kpi-value--red">{{ supplierStats.issues }}</div>
          <div class="kpi-label">需要處理</div>
        </div>
        <div class="kpi-card kpi-card--gray">
          <div class="kpi-value kpi-value--gray">{{ supplierStats.noDocs }}</div>
          <div class="kpi-label">尚無文件</div>
        </div>
      </div>

      <!-- 篩選 + 搜尋 -->
      <div class="filter-row">
        <div class="filter-chips">
          <button class="filter-chip" :class="{ active: supplierFilter === 'all' }" @click="supplierFilter = 'all'">
            全部
          </button>
          <button class="filter-chip filter-chip--red" :class="{ active: supplierFilter === 'issues' }" @click="supplierFilter = 'issues'">
            <span class="chip-dot chip-dot--red"></span>需要處理
            <span class="chip-count">{{ supplierStats.issues }}</span>
          </button>
          <button class="filter-chip filter-chip--orange" :class="{ active: supplierFilter === 'expiring' }" @click="supplierFilter = 'expiring'">
            <span class="chip-dot chip-dot--orange"></span>即將到期
            <span class="chip-count">{{ supplierStats.expiring }}</span>
          </button>
          <button class="filter-chip filter-chip--gray" :class="{ active: supplierFilter === 'nodocs' }" @click="supplierFilter = 'nodocs'">
            尚無文件
          </button>
        </div>
        <input v-model="supplierSearch" class="search-input" placeholder="搜尋供應商名稱…" />
      </div>

      <!-- 表格 -->
      <div class="card" style="padding:0;overflow:hidden;">
        <table class="data-table">
          <thead>
            <tr>
              <th style="padding-left:20px;">供應商</th>
              <th style="width:90px;text-align:center;">有效文件</th>
              <th style="width:90px;text-align:center;">即將到期</th>
              <th style="width:90px;text-align:center;">已過期</th>
              <th style="width:160px;">缺漏文件類型</th>
              <th style="width:100px;text-align:center;">合規狀態</th>
              <th style="width:32px;"></th>
            </tr>
          </thead>
          <tbody>
            <template v-if="filteredSuppliers.length === 0">
              <tr>
                <td colspan="7" class="empty-row">無符合條件的供應商</td>
              </tr>
            </template>
            <tr
              v-for="s in filteredSuppliers"
              :key="s.supplier_id"
              class="clickable-row"
              @click="router.push(`/compliance/suppliers/${s.supplier_id}`)"
            >
              <td style="padding-left:20px;">
                <div class="supplier-name">{{ s.supplier_name || s.supplier_id }}</div>
              </td>
              <td style="text-align:center;">
                <span class="stat-badge stat-badge--green" v-if="s.valid_count > 0">{{ s.valid_count }}</span>
                <span class="stat-zero" v-else>0</span>
              </td>
              <td style="text-align:center;">
                <span class="stat-badge stat-badge--orange" v-if="s.expiring_soon_count > 0">{{ s.expiring_soon_count }}</span>
                <span class="stat-zero" v-else>0</span>
              </td>
              <td style="text-align:center;">
                <span class="stat-badge stat-badge--red" v-if="s.expired_count > 0">{{ s.expired_count }}</span>
                <span class="stat-zero" v-else>0</span>
              </td>
              <td>
                <div v-if="s.missing_required_types.length" class="missing-tags">
                  <span v-for="t in s.missing_required_types" :key="t" class="missing-tag">{{ t }}</span>
                </div>
                <span v-else class="none-indicator">—</span>
              </td>
              <td style="text-align:center;">
                <span class="status-pill" :class="supplierStatusClass(s)">{{ supplierStatusLabel(s) }}</span>
              </td>
              <td class="row-arrow">›</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- 矩陣視角 -->
    <template v-else-if="activeTab === 'matrix'">
      <!-- 篩選列 -->
      <div class="filter-row" style="margin-bottom:16px;">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
          <label style="font-size:13px;color:var(--text-secondary);white-space:nowrap;">供應商群組：</label>
          <select v-model="selectedMatGroupId" class="search-input" style="width:180px;" @change="loadMatrixData">
            <option value="">全部供應商群組</option>
            <option v-for="g in supplierGroups" :key="g.id" :value="g.id">{{ g.name }}</option>
          </select>
          <label style="font-size:13px;color:var(--text-secondary);white-space:nowrap;">Tier：</label>
          <select v-model="selectedTier" class="search-input" style="width:110px;" @change="loadMatrixData">
            <option value="">全部 Tier</option>
            <option value="1">Tier 1</option>
            <option value="2">Tier 2</option>
            <option value="3">Tier 3</option>
          </select>
          <label style="font-size:13px;color:var(--text-secondary);white-space:nowrap;">風險分數 ≥</label>
          <input
            v-model="riskScoreMin"
            type="number" min="0" max="100" step="1"
            class="search-input"
            style="width:120px;"
            placeholder="（未評分不列入）"
            @input="onRiskScoreInput"
          />
        </div>
      </div>

      <div v-if="matrixLoading" style="padding:48px;text-align:center;color:var(--text-secondary);">載入中...</div>
      <div v-else-if="!matrixData" style="padding:48px;text-align:center;color:var(--text-secondary);">無資料</div>
      <template v-else>
        <div class="card" style="padding:0;overflow:auto;">
          <table class="matrix-table">
            <thead>
              <tr>
                <th class="matrix-th-row">物料群組</th>
                <th v-for="dt in matrixData.doc_types" :key="dt" class="matrix-th-col">
                  <div class="matrix-th-inner">
                    <span>{{ docTypeLabel(dt) }}</span>
                    <span class="reg-help-btn" @click.stop="toggleRegInfo(dt)">?</span>
                  </div>
                  <div v-if="activeRegInfo === dt" class="reg-info-popover" @click.stop>
                    <div class="reg-info-title">{{ REG_INFO[dt]?.name }}</div>
                    <div class="reg-info-body">{{ REG_INFO[dt]?.desc }}</div>
                    <div class="reg-info-link" v-if="REG_INFO[dt]?.scope">適用範圍：{{ REG_INFO[dt]?.scope }}</div>
                  </div>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in matrixData.rows" :key="row.material_group_id">
                <td class="matrix-td-label">{{ row.material_group_name }}</td>
                <td
                  v-for="dt in matrixData.doc_types"
                  :key="dt"
                  class="matrix-td-cell"
                  :class="cellClass(row.cells[dt])"
                  @click="row.cells[dt] ? openDrill(row.material_group_id, row.material_group_name, dt) : null"
                >
                  <template v-if="row.cells[dt]">
                    <div class="cell-value">{{ row.cells[dt]!.compliant }}/{{ row.cells[dt]!.total }}</div>
                    <div class="cell-pct">{{ row.cells[dt]!.pct }}%</div>
                  </template>
                  <span v-else class="cell-na-text">—</span>
                </td>
              </tr>
              <tr v-if="matrixData.rows.length === 0">
                <td :colspan="(matrixData.doc_types.length + 1)" class="empty-row">無物料群組資料</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

    </template>

    <!-- ESPR/DPP 視角 -->
    <template v-else-if="activeTab === 'dpp'">
      <div v-if="dppLoading" style="padding:64px;text-align:center;color:var(--text-secondary);">載入中...</div>
      <template v-else>
        <div class="card" style="padding:0;overflow:hidden;">
          <table class="data-table">
            <thead>
              <tr>
                <th style="padding-left:20px;">產品名稱</th>
                <th style="width:120px;text-align:center;">DPP 狀態</th>
                <th style="width:220px;">材料完整度</th>
                <th style="width:220px;">供應商合規率</th>
                <th style="width:260px;">問題項目</th>
                <th style="width:32px;"></th>
              </tr>
            </thead>
            <tbody>
              <template v-if="dppData.length === 0">
                <tr><td colspan="6" class="empty-row">無產品資料</td></tr>
              </template>
              <tr
                v-for="p in dppData"
                :key="p.product_id"
                class="clickable-row"
                @click="openDppDrawer(p.product_id, p.product_name)"
              >
                <td style="padding-left:20px;">
                  <div class="supplier-name">{{ p.product_name }}</div>
                  <div style="font-size:11px;color:var(--text-secondary);">{{ p.bom_line_count }} 筆物料明細</div>
                </td>
                <td style="text-align:center;">
                  <span class="status-pill" :class="dppStatusClass(p.readiness_status)">{{ dppStatusLabel(p.readiness_status) }}</span>
                </td>
                <td>
                  <div class="dpp-bar-row">
                    <div class="dpp-readiness-bar-wrap" style="flex:1;">
                      <div class="dpp-readiness-bar" :class="p.material_completeness_pct < 50 ? 'dpp-readiness-bar--red' : p.material_completeness_pct < 100 ? 'dpp-readiness-bar--yellow' : ''" :style="{ width: p.material_completeness_pct + '%' }"></div>
                    </div>
                    <span class="dpp-bar-label">{{ p.material_completeness_pct }}%</span>
                  </div>
                </td>
                <td>
                  <div class="dpp-bar-row">
                    <div class="dpp-readiness-bar-wrap" style="flex:1;">
                      <div class="dpp-readiness-bar" :class="p.supplier_compliance_pct < 50 ? 'dpp-readiness-bar--red' : p.supplier_compliance_pct < 80 ? 'dpp-readiness-bar--yellow' : ''" :style="{ width: p.supplier_compliance_pct + '%' }"></div>
                    </div>
                    <span class="dpp-bar-label">{{ p.supplier_compliance_pct }}%</span>
                  </div>
                </td>
                <td>
                  <div v-if="p.issues.length" style="display:flex;flex-direction:column;gap:2px;">
                    <span v-for="issue in p.issues" :key="issue" style="font-size:11px;color:#b45309;">{{ issue }}</span>
                  </div>
                  <span v-else class="none-indicator">—</span>
                </td>
                <td class="row-arrow">›</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </template>

    <!-- 銷售產品合規 -->
    <template v-else-if="activeTab === 'sales-products'">
      <div v-if="salesProductsLoading" style="padding:64px;text-align:center;color:var(--text-secondary);">載入中...</div>
      <template v-else>
        <!-- KPI -->
        <div class="kpi-grid">
          <div class="kpi-card">
            <div class="kpi-value">{{ salesProducts.length }}</div>
            <div class="kpi-label">銷售產品</div>
          </div>
          <div class="kpi-card" style="border-top:3px solid #2563eb;">
            <div class="kpi-value" style="color:#2563eb;">{{ salesProductStats.eudr }}</div>
            <div class="kpi-label">EUDR 適用</div>
          </div>
          <div class="kpi-card" style="border-top:3px solid #7c3aed;">
            <div class="kpi-value" style="color:#7c3aed;">{{ salesProductStats.uflpa }}</div>
            <div class="kpi-label">UFLPA 適用</div>
          </div>
          <div class="kpi-card kpi-card--green">
            <div class="kpi-value kpi-value--green">{{ salesProductStats.hasInferred }}</div>
            <div class="kpi-label">AI 已推論</div>
          </div>
          <div class="kpi-card kpi-card--gray">
            <div class="kpi-value kpi-value--gray">{{ salesProductStats.unset }}</div>
            <div class="kpi-label">法規未設定</div>
          </div>
        </div>

        <!-- 篩選 + 搜尋 -->
        <div class="filter-row">
          <div class="filter-chips">
            <button class="filter-chip" :class="{ active: salesProductsFilter === '' }" @click="salesProductsFilter = ''">全部</button>
            <button class="filter-chip" :class="{ active: salesProductsFilter === 'EUDR' }" @click="salesProductsFilter = 'EUDR'" style="color:#2563eb;">EUDR</button>
            <button class="filter-chip" :class="{ active: salesProductsFilter === 'UFLPA' }" @click="salesProductsFilter = 'UFLPA'" style="color:#7c3aed;">UFLPA</button>
            <button class="filter-chip" :class="{ active: salesProductsFilter === 'CMRT' }" @click="salesProductsFilter = 'CMRT'">CMRT</button>
            <button class="filter-chip" :class="{ active: salesProductsFilter === 'ESPR' }" @click="salesProductsFilter = 'ESPR'" style="color:#16a34a;">ESPR</button>
            <button class="filter-chip filter-chip--gray" :class="{ active: salesProductsFilter === 'unset' }" @click="salesProductsFilter = 'unset'">法規未設定</button>
          </div>
          <input v-model="salesProductsSearch" class="search-input" placeholder="搜尋產品名稱或品號…" />
        </div>

        <div class="card" style="padding:0;overflow:hidden;">
          <table class="data-table">
            <thead>
              <tr>
                <th style="padding-left:20px;">產品名稱</th>
                <th style="width:120px;">HS 碼</th>
                <th style="width:220px;">適用法規（人工）</th>
                <th style="width:200px;">AI 推論法規</th>
                <th style="width:110px;text-align:right;padding-right:16px;">碳排強度</th>
                <th style="width:60px;text-align:center;">操作</th>
              </tr>
            </thead>
            <tbody>
              <template v-if="filteredSalesProducts.length === 0">
                <tr><td colspan="6" class="empty-row">無符合條件的產品</td></tr>
              </template>
              <tr v-for="p in filteredSalesProducts" :key="p.id">
                <td style="padding-left:20px;">
                  <div class="supplier-name">{{ p.name }}</div>
                  <div style="font-size:11px;color:var(--text-secondary);margin-top:2px;font-family:monospace;">{{ p.product_code || '—' }}</div>
                </td>
                <td>
                  <span class="font-mono" style="font-size:12px;color:var(--text-secondary);">{{ p.hs_code || '—' }}</span>
                </td>
                <td>
                  <div v-if="(p.applicable_regulations ?? []).length" class="reg-tags">
                    <span v-for="reg in p.applicable_regulations" :key="reg" class="reg-tag reg-tag--manual">{{ reg }}</span>
                  </div>
                  <span v-else class="none-indicator">未設定</span>
                </td>
                <td>
                  <div v-if="(p.inferred_regulations ?? []).length" class="reg-tags">
                    <span
                      v-for="reg in p.inferred_regulations"
                      :key="reg"
                      class="reg-tag"
                      title="系統自動推算"
                    >{{ reg }}<span class="reg-src-chip">系統</span></span>
                  </div>
                  <span v-else class="none-indicator">—</span>
                </td>
                <td style="text-align:right;padding-right:16px;">
                  <span v-if="p.embedded_emissions" class="font-mono" style="font-size:12px;">
                    {{ p.embedded_emissions }}
                    <span style="font-size:10px;color:var(--text-secondary);">kgCO₂e</span>
                  </span>
                  <span v-else class="none-indicator">—</span>
                </td>
                <td style="text-align:center;">
                  <button class="action-btn" @click.stop="openProductEdit(p)" title="編輯法規聲明">✎</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </template>

    <!-- 待審核工作台 -->
    <template v-else-if="activeTab === 'pending'">
      <!-- 篩選列 -->
      <div class="filter-row" style="margin-bottom:16px;">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
          <input v-model="pendingFilter.search" class="search-input" placeholder="搜尋供應商名稱…" />
          <select v-model="pendingFilter.docType" class="search-input" style="width:200px;">
            <option value="">全部文件類型</option>
            <option value="UFLPA_DECLARATION">UFLPA_DECLARATION</option>
            <option value="EUDR_DDS">EUDR_DDS</option>
            <option value="CMRT">CMRT</option>
            <option value="SDS">SDS</option>
            <option value="CE_DOC">CE_DOC</option>
            <option value="ORIGIN_CERT">ORIGIN_CERT</option>
            <option value="OTHER">OTHER</option>
          </select>
          <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--text-secondary);cursor:pointer;">
            <input type="checkbox" v-model="pendingFilter.missingExpiry" style="width:15px;height:15px;" />
            只顯示缺漏有效期
          </label>
        </div>
      </div>

      <div v-if="pendingLoading" style="padding:64px;text-align:center;color:var(--text-secondary);">載入中...</div>
      <template v-else>
        <div v-if="filteredPendingDocs.length === 0 && !pendingLoading" class="card" style="padding:48px;text-align:center;color:var(--text-secondary);">
          所有文件均已完成審核 ✓
        </div>
        <div v-else class="card" style="padding:0;overflow:hidden;">
          <table class="data-table">
            <thead>
              <tr>
                <th style="padding-left:20px;">供應商</th>
                <th style="width:160px;">文件類型</th>
                <th style="width:200px;">檔案名</th>
                <th style="width:100px;">上傳日</th>
                <th style="width:130px;">到期日</th>
                <th style="width:90px;text-align:center;">狀態</th>
                <th style="width:80px;text-align:center;">審核</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="doc in filteredPendingDocs" :key="doc.id">
                <td style="padding-left:20px;">
                  <div class="supplier-name">{{ doc.supplier_name || doc.supplier_id }}</div>
                </td>
                <td>
                  <span class="font-mono" style="font-size:12px;">{{ doc.doc_type }}</span>
                </td>
                <td>
                  <span style="font-size:13px;color:var(--text-secondary);">{{ doc.file_name || '—' }}</span>
                </td>
                <td>
                  <span class="font-mono" style="font-size:13px;">{{ doc.uploaded_at || '—' }}</span>
                </td>
                <td>
                  <span v-if="doc.missing_expiry" style="color:#dc2626;font-size:13px;font-weight:600;">⚠ 未填有效期</span>
                  <span v-else class="font-mono" style="font-size:13px;">{{ doc.expires_at }}</span>
                </td>
                <td style="text-align:center;">
                  <span class="status-pill" :class="docStatusClass(doc.status)">{{ docStatusLabel(doc.status) }}</span>
                </td>
                <td style="text-align:center;">
                  <button
                    v-if="authStore.userRole !== 'analyst'"
                    class="btn btn-sm btn-outline"
                    @click="openVerifyModal(doc)"
                    style="font-size:12px;padding:4px 10px;"
                  >審核…</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </template>

    <!-- 驗證 Modal -->
    <div v-if="verifyModal.open" class="modal-overlay" @click.self="verifyModal.open = false">
      <div class="modal" style="min-width:440px;max-width:500px;">
        <div class="modal-header">
          <span class="modal-title">審核文件</span>
          <button class="modal-close" @click="verifyModal.open = false">×</button>
        </div>
        <div style="padding:0 0 16px;">
          <!-- 文件摘要 -->
          <div class="verify-summary">
            <div class="verify-row">
              <span class="verify-label">供應商</span>
              <span class="verify-value">{{ verifyModal.doc?.supplier_name || '—' }}</span>
            </div>
            <div class="verify-row">
              <span class="verify-label">文件類型</span>
              <span class="verify-value font-mono">{{ verifyModal.doc?.doc_type }}</span>
            </div>
            <div class="verify-row">
              <span class="verify-label">檔案名稱</span>
              <span class="verify-value font-mono" style="font-size:12px;">{{ verifyModal.doc?.file_name || '—' }}</span>
            </div>
            <div class="verify-row">
              <span class="verify-label">上傳日期</span>
              <span class="verify-value font-mono">{{ verifyModal.doc?.uploaded_at || '—' }}</span>
            </div>
          </div>
          <!-- 下載按鈕 -->
          <div style="margin:12px 0;">
            <button class="btn btn-secondary btn-sm" :disabled="verifyModal.downloading" @click="downloadModalDoc" style="width:100%;">
              {{ verifyModal.downloading ? '下載中…' : '⬇ 下載文件' }}
            </button>
          </div>
          <!-- 有效期 -->
          <div v-if="verifyModal.doc?.missing_expiry" style="margin-bottom:12px;">
            <label class="form-label" style="color:#dc2626;">有效期至 <span style="font-size:11px;">（必填，文件未填有效期）</span></label>
            <input v-model="verifyModal.expiresAt" type="date" class="form-input" style="width:100%;" />
            <div v-if="!verifyModal.expiresAt" style="font-size:11px;color:#dc2626;margin-top:4px;">有效期為必填欄位</div>
          </div>
          <!-- 備註 -->
          <div style="margin-bottom:4px;">
            <label class="form-label">審核備註（選填）</label>
            <textarea
              v-model="verifyModal.notes"
              class="form-input"
              rows="3"
              maxlength="500"
              placeholder="審核意見（選填，最多 500 字）"
              style="width:100%;resize:vertical;"
            ></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary btn-sm" @click="verifyModal.open = false">取消</button>
          <button
            class="btn btn-primary btn-sm"
            :disabled="verifyModal.submitting || (verifyModal.doc?.missing_expiry && !verifyModal.expiresAt)"
            @click="submitVerify"
          >{{ verifyModal.submitting ? '審核中…' : '確認審核' }}</button>
        </div>
      </div>
    </div>

    <!-- DPP Drawer（根層級，所有 Tab 下均可顯示） -->
    <div v-if="dppDrawerOpen" class="drawer-overlay" @click="dppDrawerOpen = false"></div>
    <div v-if="dppDrawerOpen" class="drawer" style="width:420px;">
      <div class="drawer-header">
        <span class="drawer-title">{{ dppDrawerTitle }}</span>
        <button class="drawer-close" @click="dppDrawerOpen = false">×</button>
      </div>
      <div v-if="dppDrawerLoading" style="padding:32px;text-align:center;color:var(--text-secondary);">載入中...</div>
      <template v-else-if="dppDrawerData">
        <div class="drawer-body">
          <!-- Section 1: 材料清單 -->
          <div class="dpp-section">
            <div class="dpp-section-header">
              <span class="dpp-section-title">材料清單完整性</span>
              <span class="dpp-section-pct">{{ dppDrawerData.sections.material_list.complete }}/{{ dppDrawerData.sections.material_list.total }}</span>
            </div>
            <div class="dpp-readiness-bar-wrap" style="margin-bottom:8px;">
              <div class="dpp-readiness-bar" :style="{ width: (dppDrawerData.sections.material_list.total > 0 ? Math.round(dppDrawerData.sections.material_list.complete / dppDrawerData.sections.material_list.total * 100) : 0) + '%' }"></div>
            </div>
            <div v-for="item in dppDrawerData.sections.material_list.items" :key="item.material_name" class="dpp-list-row">
              <span class="dpp-check" :class="item.has_group ? 'dpp-check--ok' : 'dpp-check--fail'">{{ item.has_group ? '✓' : '✗' }}</span>
              <div class="dpp-list-info">
                <div class="dpp-list-name">{{ item.material_name }}</div>
                <div class="dpp-list-sub">{{ item.hs_code || '—' }} · {{ item.material_group || '未設定物料群組' }}</div>
              </div>
            </div>
            <div v-if="dppDrawerData.sections.material_list.items.length === 0" class="dpp-empty">無物料明細資料</div>
          </div>

          <!-- Section 2: 供應商合規聲明 -->
          <div class="dpp-section">
            <div class="dpp-section-header">
              <span class="dpp-section-title">供應商合規聲明</span>
              <span class="dpp-section-pct">{{ dppDrawerData.sections.supplier_compliance.compliant }}/{{ dppDrawerData.sections.supplier_compliance.total_required }}</span>
            </div>
            <div class="dpp-readiness-bar-wrap" style="margin-bottom:8px;">
              <div class="dpp-readiness-bar"
                :class="dppDrawerData.sections.supplier_compliance.total_required > 0 && (dppDrawerData.sections.supplier_compliance.compliant / dppDrawerData.sections.supplier_compliance.total_required) < 0.5 ? 'dpp-readiness-bar--red' : ''"
                :style="{ width: (dppDrawerData.sections.supplier_compliance.total_required > 0 ? Math.round(dppDrawerData.sections.supplier_compliance.compliant / dppDrawerData.sections.supplier_compliance.total_required * 100) : 0) + '%' }">
              </div>
            </div>
            <div v-for="(item, i) in dppDrawerData.sections.supplier_compliance.items" :key="i" class="dpp-list-row">
              <span class="status-pill" :class="docStatusClass(item.status)" style="font-size:11px;padding:2px 8px;flex-shrink:0;">{{ docStatusLabel(item.status) }}</span>
              <div class="dpp-list-info">
                <div class="dpp-list-name">{{ item.supplier_name }}</div>
                <div class="dpp-list-sub">{{ item.doc_type }}<template v-if="item.expires_at"> · 到期 {{ item.expires_at }}</template></div>
              </div>
            </div>
            <div v-if="dppDrawerData.sections.supplier_compliance.items.length === 0" class="dpp-empty">無需求文件</div>
          </div>

          <!-- Section PCR: 循環材料追蹤 -->
          <div v-if="dppDrawerData.pcr" class="dpp-section">
            <div class="dpp-section-header">
              <div class="dpp-section-title">循環材料（PCR）</div>
              <span class="dpp-section-pct">{{ dppDrawerData.pcr.compliant }}/{{ dppDrawerData.pcr.pcr_lines }}</span>
            </div>
            <div v-if="!dppDrawerData.pcr.pcr_lines" class="dpp-empty">無含 PCR 佔比的 BomLine</div>
            <template v-else>
              <div class="dpp-readiness-bar-wrap">
                <div
                  class="dpp-readiness-bar"
                  :class="dppDrawerData.pcr.coverage_pct < 50 ? 'dpp-readiness-bar--red' : ''"
                  :style="{ width: dppDrawerData.pcr.coverage_pct + '%' }"
                ></div>
              </div>
              <div style="font-size:12px;margin-top:6px;color:var(--text-secondary);">
                {{ dppDrawerData.pcr.coverage_pct }}% 的含 PCR 物料已取得 GRS 認證（primary 供應商 valid）
                <span v-if="!dppDrawerData.pcr.ready" style="color:#b45309;"> — 未達 80% 門檻</span>
              </div>
            </template>
          </div>

          <!-- Section 3: 法規標記 -->
          <div class="dpp-section">
            <div class="dpp-section-title" style="margin-bottom:10px;">法規標記</div>
            <div v-if="!dppDrawerData.sections.regulations.all_regulations.length" class="dpp-empty">未標記任何法規</div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
              <span
                v-for="reg in dppDrawerData.sections.regulations.inferred_regulations || []"
                :key="`inf-${reg}`"
                class="reg-tag"
                :class="reg === 'ESPR' ? 'reg-tag--espr' : ''"
                title="系統自動推算"
              >{{ reg }}<span class="reg-src-chip">系統</span></span>
              <span
                v-for="reg in (dppDrawerData.sections.regulations.applicable_regulations || []).filter((r: string) => !(dppDrawerData.sections.regulations.inferred_regulations || []).includes(r))"
                :key="`man-${reg}`"
                class="reg-tag reg-tag--manual"
                :class="reg === 'ESPR' ? 'reg-tag--espr' : ''"
                title="人工聲明"
              >{{ reg }}<span class="reg-src-chip reg-src-chip--manual">手動</span></span>
            </div>
            <div v-if="!dppDrawerData.sections.regulations.has_espr" style="margin-top:8px;font-size:12px;color:#b45309;">
              ⚠ 尚未標記 ESPR 法規，請至產品設定中新增
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- 產品法規編輯 Modal -->
    <div v-if="editProductModal" class="modal-overlay" @click.self="editProductModal = false">
      <div class="modal" style="min-width:420px;max-width:480px;">
        <div class="modal-header">
          <span class="modal-title">編輯法規聲明 — {{ editingProduct?.name }}</span>
          <button class="modal-close" @click="editProductModal = false">×</button>
        </div>
        <div>
          <div class="form-label" style="margin-bottom:10px;">人工聲明適用法規</div>
          <div style="display:flex;flex-wrap:wrap;gap:10px;">
            <label
              v-for="opt in REGULATION_OPTIONS"
              :key="opt"
              style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:14px;user-select:none;"
            >
              <input
                type="checkbox"
                :value="opt"
                :checked="editRegulations.includes(opt)"
                @change="toggleEditReg(opt)"
                style="width:15px;height:15px;cursor:pointer;"
              />
              {{ opt }}
            </label>
          </div>
          <div style="margin-top:10px;font-size:12px;color:var(--text-secondary);">勾選 ESPR 後，該產品將納入 DPP 追蹤。</div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary btn-sm" @click="editProductModal = false">取消</button>
          <button class="btn btn-primary btn-sm" :disabled="isSubmitting" @click="saveProductEdit">
            {{ isSubmitting ? '儲存中…' : '儲存' }}
          </button>
        </div>
      </div>
    </div>

    <!-- 矩陣 Drill-Down Drawer（根層級） -->
    <div v-if="drillOpen" class="drawer-overlay" @click="drillOpen = false"></div>
    <div v-if="drillOpen" class="drawer">
      <div class="drawer-header">
        <span class="drawer-title">{{ drillTitle }}</span>
        <button class="drawer-close" @click="drillOpen = false">×</button>
      </div>
      <div v-if="drillLoading" style="padding:32px;text-align:center;color:var(--text-secondary);">載入中...</div>
      <template v-else-if="drillData">
        <div class="drawer-body">
          <div v-for="s in drillData.suppliers" :key="s.supplier_id" class="drill-supplier-row">
            <div class="drill-supplier-info">
              <div class="drill-supplier-name">
                {{ s.supplier_name }}
                <span class="tier-badge">T{{ s.tier }}</span>
                <span class="onboarding-chip" :class="s.onboarding_stage === 'certified' ? 'onboarding-chip--certified' : 'onboarding-chip--other'">{{ s.onboarding_stage }}</span>
              </div>
              <div class="drill-supplier-meta">
                <span v-if="s.supplier_group" class="drill-supplier-group">{{ s.supplier_group }}</span>
                <span class="drill-risk-score font-mono">風險：{{ s.risk_score !== null ? s.risk_score : '—' }}</span>
              </div>
            </div>
            <div class="drill-supplier-right">
              <span class="status-pill" :class="docStatusClass(s.status)">{{ docStatusLabel(s.status) }}</span>
              <div class="drill-expires" v-if="s.expires_at">到期：{{ s.expires_at }}</div>
            </div>
          </div>
          <div v-if="drillData.suppliers.length === 0" style="text-align:center;color:var(--text-secondary);padding:32px;">無供應商資料</div>
        </div>
        <div class="drawer-footer" v-if="drillData.suppliers.length > 0">
          <a
            v-for="s in drillData.suppliers.filter(x => x.status !== 'valid')"
            :key="s.supplier_id"
            :href="`/cap?supplier_id=${s.supplier_id}`"
            class="btn btn-sm btn-outline"
            style="font-size:12px;"
          >查看 CAP：{{ s.supplier_name }}</a>
        </div>
      </template>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { useRouter } from 'vue-router'
import { complianceDashboardApi, complianceDocApi, type SupplierComplianceSummary, type MatrixData, type MatrixDrillData, type MatrixCell, type DppProduct, type DppDetail, type PendingVerificationDoc } from '@/api/modules/compliance'
import { salesProductApi, type SalesProduct } from '@/api/modules/salesProducts'
import { settingsApi } from '@/api/modules/settings'
import { useAuthStore } from '@/stores/auth'

const REGULATION_OPTIONS = ['EUDR', 'UFLPA', 'CMRT', 'REACH', 'CE', 'ESPR']

const REG_INFO: Record<string, { name: string; desc: string; scope: string }> = {
  EUDR_DDS: {
    name: 'EUDR — 歐盟森林砍伐法規',
    desc: '要求進口商確認產品不來自非法砍伐土地，需提交盡職調查聲明（DDS）。',
    scope: '牛皮革、可可、咖啡、棕櫚油、大豆、木材及其衍生品。',
  },
  UFLPA_DECLARATION: {
    name: 'UFLPA — 維吾爾強迫勞動預防法',
    desc: '美國法規，假定新疆生產或使用新疆原料的商品均涉及強迫勞動，進口時需可反駁的聲明文件。',
    scope: '棉花、番茄、多晶矽及新疆供應鏈相關原材料。',
  },
  CMRT: {
    name: 'CMRT — 衝突礦產報告範本',
    desc: '由 RMI 制定的標準化問卷，追蹤 3TG 礦產（錫、鉭、鎢、金）是否來自衝突地區。',
    scope: '含 3TG 的電子零件、連接器、金屬配件等。',
  },
  SDS: {
    name: 'SDS — 安全資料表',
    desc: '依 REACH 規範要求，化學品供應商需提供 SDS，揭露成分、危害、安全處置方式。',
    scope: '染料、整理劑、化學助劑、黏合劑等化學原料。',
  },
  CE_DOC: {
    name: 'CE — 歐盟合規聲明',
    desc: '產品符合歐盟相關安全、健康、環保指令的合規聲明，是進入歐盟市場的必要標誌。',
    scope: '電子電氣產品、機械設備、個人防護裝備等。',
  },
}

export default defineComponent({
  name: 'MaterialComplianceView',
  setup() { return { router: useRouter(), authStore: useAuthStore() } },
  data() {
    return {
      isLoading: false,
      activeTab: 'supplier' as 'supplier' | 'sales-products' | 'matrix' | 'dpp' | 'pending',
      supplierFilter: 'all' as string,
      supplierSearch: '',
      salesProductsFilter: '' as string,
      salesProductsSearch: '',
      salesProductsLoading: false,
      supplierData: [] as SupplierComplianceSummary[],
      salesProducts: [] as SalesProduct[],
      REG_INFO,
      activeRegInfo: '' as string,
      // 矩陣
      matrixData: null as MatrixData | null,
      matrixLoading: false,
      supplierGroups: [] as { id: string; name: string }[],
      selectedMatGroupId: '',
      selectedTier: '' as string,
      riskScoreMin: '' as string,
      riskDebounceTimer: null as ReturnType<typeof setTimeout> | null,
      // DPP
      dppData: [] as DppProduct[],
      dppLoading: false,
      dppDrawerOpen: false,
      dppDrawerLoading: false,
      dppDrawerData: null as DppDetail | null,
      dppDrawerTitle: '',
      // Matrix Drill
      drillOpen: false,
      drillLoading: false,
      drillData: null as MatrixDrillData | null,
      drillTitle: '',
      // 產品編輯
      editProductModal: false,
      editingProduct: null as SalesProduct | null,
      editRegulations: [] as string[],
      isSubmitting: false,
      REGULATION_OPTIONS,
      // 待審核工作台
      pendingDocs: [] as PendingVerificationDoc[],
      pendingLoading: false,
      pendingLoaded: false,
      pendingFilter: { search: '', docType: '', missingExpiry: false },
      pendingSubmitting: {} as Record<string, boolean>,
      // 驗證 Modal
      verifyModal: {
        open: false,
        doc: null as PendingVerificationDoc | null,
        expiresAt: '',
        notes: '',
        downloading: false,
        submitting: false,
      },
    }
  },

  computed: {
    supplierStats() {
      return {
        compliant: this.supplierData.filter(s => s.expired_count === 0 && s.missing_required_types.length === 0 && s.expiring_soon_count === 0 && s.total_docs > 0).length,
        expiring: this.supplierData.filter(s => s.expiring_soon_count > 0 && s.expired_count === 0 && s.missing_required_types.length === 0).length,
        issues: this.supplierData.filter(s => s.expired_count > 0 || s.missing_required_types.length > 0).length,
        noDocs: this.supplierData.filter(s => s.total_docs === 0).length,
      }
    },
    salesProductStats() {
      return {
        eudr: this.salesProducts.filter(p =>
          [...(p.applicable_regulations ?? []), ...(p.inferred_regulations ?? [])].includes('EUDR')
        ).length,
        uflpa: this.salesProducts.filter(p =>
          [...(p.applicable_regulations ?? []), ...(p.inferred_regulations ?? [])].includes('UFLPA')
        ).length,
        hasInferred: this.salesProducts.filter(p => (p.inferred_regulations ?? []).length > 0).length,
        unset: this.salesProducts.filter(p =>
          !(p.applicable_regulations ?? []).length && !(p.inferred_regulations ?? []).length
        ).length,
      }
    },
    filteredSuppliers(): SupplierComplianceSummary[] {
      let list = this.supplierData
      if (this.supplierFilter === 'issues') list = list.filter(s => s.expired_count > 0 || s.missing_required_types.length > 0)
      else if (this.supplierFilter === 'expiring') list = list.filter(s => s.expiring_soon_count > 0 && s.expired_count === 0 && s.missing_required_types.length === 0)
      else if (this.supplierFilter === 'nodocs') list = list.filter(s => s.total_docs === 0)
      if (this.supplierSearch.trim()) {
        const kw = this.supplierSearch.trim().toLowerCase()
        list = list.filter(s => (s.supplier_name || s.supplier_id).toLowerCase().includes(kw))
      }
      return list
    },
    filteredPendingDocs(): PendingVerificationDoc[] {
      let list = this.pendingDocs
      if (this.pendingFilter.search.trim()) {
        const kw = this.pendingFilter.search.trim().toLowerCase()
        list = list.filter(d => (d.supplier_name || '').toLowerCase().includes(kw))
      }
      if (this.pendingFilter.docType) {
        list = list.filter(d => d.doc_type === this.pendingFilter.docType)
      }
      if (this.pendingFilter.missingExpiry) {
        list = list.filter(d => d.missing_expiry)
      }
      return list
    },
    filteredSalesProducts(): SalesProduct[] {
      let list = this.salesProducts
      if (this.salesProductsFilter === 'unset') list = list.filter(p => !(p.applicable_regulations ?? []).length && !(p.inferred_regulations ?? []).length)
      else if (this.salesProductsFilter) list = list.filter(p =>
        (p.applicable_regulations ?? []).includes(this.salesProductsFilter) ||
        (p.inferred_regulations ?? []).includes(this.salesProductsFilter)
      )
      if (this.salesProductsSearch.trim()) {
        const kw = this.salesProductsSearch.trim().toLowerCase()
        list = list.filter(p => p.name.toLowerCase().includes(kw) || (p.product_code ?? '').toLowerCase().includes(kw))
      }
      return list
    },
  },

  mounted() { this.loadData(); this.loadPendingDocs(); this.loadSalesProducts() },

  methods: {
    async loadData() {
      this.isLoading = true
      try {
        const sRes = await complianceDashboardApi.getSupplierDashboard()
        this.supplierData = sRes.data.data
      } finally { this.isLoading = false }
    },

    async loadSalesProducts() {
      this.salesProductsLoading = true
      try {
        const res = await salesProductApi.list()
        this.salesProducts = Array.isArray(res.data.data) ? res.data.data : []
      } catch {} finally {
        this.salesProductsLoading = false
      }
    },

    async switchToSalesProducts() {
      this.activeTab = 'sales-products'
      this.drillOpen = false
      this.dppDrawerOpen = false
      if (!this.salesProducts.length) await this.loadSalesProducts()
    },

    async switchToMatrix() {
      this.activeTab = 'matrix'
      this.dppDrawerOpen = false
      if (!this.matrixData) {
        await this.loadMatrixData()
      }
      if (this.supplierGroups.length === 0) {
        try {
          const res = await settingsApi.groups.list()
          this.supplierGroups = res.data.data
        } catch {}
      }
    },

    async loadMatrixData() {
      this.matrixLoading = true
      try {
        const params: Record<string, string> = {}
        if (this.selectedMatGroupId) params.supplier_group_id = this.selectedMatGroupId
        if (this.selectedTier) params.tier = this.selectedTier
        if (this.riskScoreMin !== '') params.risk_score_min = this.riskScoreMin
        const res = await complianceDashboardApi.matrix(Object.keys(params).length ? params : undefined)
        this.matrixData = res.data.data
      } finally {
        this.matrixLoading = false
      }
    },

    onRiskScoreInput() {
      if (this.riskDebounceTimer) clearTimeout(this.riskDebounceTimer)
      this.riskDebounceTimer = setTimeout(() => this.loadMatrixData(), 300)
    },

    async openDrill(materialGroupId: string, materialGroupName: string, docType: string) {
      this.drillTitle = `${materialGroupName} × ${this.docTypeLabel(docType)}`
      this.drillOpen = true
      this.drillLoading = true
      this.drillData = null
      try {
        const params: Record<string, string> = { material_group_id: materialGroupId, doc_type: docType }
        if (this.selectedMatGroupId) params.supplier_group_id = this.selectedMatGroupId
        if (this.selectedTier) params.tier = this.selectedTier
        if (this.riskScoreMin !== '') params.risk_score_min = this.riskScoreMin
        const res = await complianceDashboardApi.matrixDrill(params as any)
        this.drillData = res.data.data
      } finally {
        this.drillLoading = false
      }
    },

    cellClass(cell: MatrixCell | null) {
      if (!cell) return 'cell-na'
      if (cell.pct >= 90) return 'cell-green'
      if (cell.pct >= 50) return 'cell-yellow'
      return 'cell-red'
    },

    toggleRegInfo(dt: string) {
      this.activeRegInfo = this.activeRegInfo === dt ? '' : dt
    },

    docTypeLabel(dt: string) {
      return ({ EUDR_DDS: 'EUDR', UFLPA_DECLARATION: 'UFLPA', CMRT: 'CMRT', SDS: 'SDS', CE_DOC: 'CE' })[dt] ?? dt
    },

    async switchToDpp() {
      this.activeTab = 'dpp'
      this.drillOpen = false
      if (this.dppData.length === 0) {
        await this.loadDppData()
      }
    },

    async loadDppData() {
      this.dppLoading = true
      try {
        const res = await complianceDashboardApi.dppReadiness()
        this.dppData = res.data.data
      } finally {
        this.dppLoading = false
      }
    },

    async openDppDrawer(productId: string, productName: string) {
      this.dppDrawerTitle = `${productName} — DPP 明細`
      this.dppDrawerOpen = true
      this.dppDrawerLoading = true
      this.dppDrawerData = null
      try {
        const res = await complianceDashboardApi.dppReadinessDetail(productId)
        this.dppDrawerData = res.data.data
      } finally {
        this.dppDrawerLoading = false
      }
    },

    openProductEdit(p: SalesProduct) {
      this.editingProduct = p
      this.editRegulations = [...(p.applicable_regulations ?? [])]
      this.editProductModal = true
    },

    toggleEditReg(reg: string) {
      const idx = this.editRegulations.indexOf(reg)
      if (idx >= 0) this.editRegulations.splice(idx, 1)
      else this.editRegulations.push(reg)
    },

    async saveProductEdit() {
      if (!this.editingProduct) return
      this.isSubmitting = true
      try {
        await salesProductApi.update(this.editingProduct.id, {
          applicable_regulations: this.editRegulations,
        })
        this.editProductModal = false
        await this.loadSalesProducts()
        if (this.activeTab === 'dpp') await this.loadDppData()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '儲存失敗')
      } finally {
        this.isSubmitting = false
      }
    },

    async switchToPending() {
      this.activeTab = 'pending'
      this.drillOpen = false
      this.dppDrawerOpen = false
      if (!this.pendingLoaded) {
        await this.loadPendingDocs()
      }
    },

    async loadPendingDocs() {
      this.pendingLoading = true
      try {
        const res = await complianceDashboardApi.pendingVerifications()
        this.pendingDocs = res.data.data
        this.pendingLoaded = true
      } finally {
        this.pendingLoading = false
      }
    },

    openVerifyModal(doc: PendingVerificationDoc) {
      this.verifyModal.doc = doc
      this.verifyModal.expiresAt = ''
      this.verifyModal.notes = ''
      this.verifyModal.downloading = false
      this.verifyModal.submitting = false
      this.verifyModal.open = true
    },

    async downloadModalDoc() {
      if (!this.verifyModal.doc) return
      this.verifyModal.downloading = true
      try {
        const res = await complianceDocApi.download(this.verifyModal.doc.id)
        const url = URL.createObjectURL(new Blob([res.data]))
        const a = document.createElement('a')
        a.href = url
        a.download = this.verifyModal.doc.file_name || 'document'
        a.click()
        URL.revokeObjectURL(url)
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '下載失敗')
      } finally {
        this.verifyModal.downloading = false
      }
    },

    async submitVerify() {
      const doc = this.verifyModal.doc
      if (!doc) return
      if (doc.missing_expiry && !this.verifyModal.expiresAt) return
      this.verifyModal.submitting = true
      try {
        const body: { expires_at?: string; notes?: string } = {}
        if (this.verifyModal.expiresAt) body.expires_at = this.verifyModal.expiresAt
        if (this.verifyModal.notes.trim()) body.notes = this.verifyModal.notes.trim()
        await complianceDocApi.verify(doc.id, body)
        const idx = this.pendingDocs.findIndex(d => d.id === doc.id)
        if (idx >= 0) this.pendingDocs.splice(idx, 1)
        this.verifyModal.open = false
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '審核失敗')
      } finally {
        this.verifyModal.submitting = false
      }
    },

    dppStatusClass(status: string) {
      return ({ ready: 'status-pill--green', partial: 'status-pill--orange', not_started: 'status-pill--gray' })[status] ?? 'status-pill--gray'
    },

    dppStatusLabel(status: string) {
      return ({ ready: '就緒', partial: '部分完成', not_started: '尚未開始' })[status] ?? status
    },

    docStatusClass(status: string) {
      return ({ valid: 'status-pill--green', expiring_soon: 'status-pill--orange', expired: 'status-pill--red', missing: 'status-pill--gray' })[status] ?? 'status-pill--gray'
    },

    docStatusLabel(status: string) {
      return ({ valid: '合規', expiring_soon: '即將到期', expired: '已過期', missing: '缺件' })[status] ?? status
    },

    supplierStatusClass(s: SupplierComplianceSummary) {
      if (s.expired_count > 0 || s.missing_required_types.length > 0) return 'status-pill--red'
      if (s.expiring_soon_count > 0) return 'status-pill--orange'
      if (s.total_docs === 0) return 'status-pill--gray'
      return 'status-pill--green'
    },

    supplierStatusLabel(s: SupplierComplianceSummary) {
      if (s.expired_count > 0) return '已過期'
      if (s.missing_required_types.length > 0) return '文件缺漏'
      if (s.expiring_soon_count > 0) return '即將到期'
      if (s.total_docs === 0) return '尚無文件'
      return '合規'
    },

    productStatusClass(s: string) {
      return ({ valid: 'status-pill--green', expiring_soon: 'status-pill--orange', expired: 'status-pill--red', pending: 'status-pill--gray', unconfigured: 'status-pill--gray' })[s] ?? 'status-pill--gray'
    },

    productStatusLabel(s: string) {
      return ({ valid: '合規', expiring_soon: '即將到期', expired: '已過期', pending: '待補件', unconfigured: '未設定' })[s] ?? s
    },
  },
})
</script>

<style scoped>
/* Tab */
.tab-bar { display: flex; gap: 2px; margin-bottom: 24px; border-bottom: 1px solid var(--border); }
.tab-btn {
  background: none; border: none; cursor: pointer;
  padding: 10px 18px; font-size: 14px; color: var(--text-secondary);
  border-bottom: 2px solid transparent; margin-bottom: -1px; transition: color 0.15s;
}
.tab-btn:hover { color: var(--text-primary); }
.tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); font-weight: 600; }
.tab-count {
  display: inline-flex; align-items: center; justify-content: center;
  background: #fff; border: 1px solid #fca5a5; border-radius: 99px;
  font-size: 11px; padding: 0 7px; min-width: 22px; margin-left: 6px; font-weight: 600;
  color: #dc2626;
}

/* KPI cards */
.kpi-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-bottom: 20px; }
.kpi-card {
  background: var(--surface); border: 1px solid var(--border); border-radius: 10px;
  padding: 16px 20px; display: flex; flex-direction: column; gap: 4px;
}
.kpi-card--green { border-left: 3px solid #16a34a; }
.kpi-card--orange { border-left: 3px solid #d97706; }
.kpi-card--red { border-left: 3px solid #dc2626; }
.kpi-card--gray { border-left: 3px solid #9ca3af; }
.kpi-value { font-size: 28px; font-weight: 700; font-family: 'Fira Code', monospace; color: var(--text-primary); line-height: 1; }
.kpi-value--green { color: #16a34a; }
.kpi-value--orange { color: #d97706; }
.kpi-value--red { color: #dc2626; }
.kpi-value--gray { color: #9ca3af; }
.kpi-label { font-size: 12px; color: var(--text-secondary); font-weight: 500; margin-top: 2px; }

/* Filter row */
.filter-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; gap: 12px; }
.filter-chips { display: flex; gap: 6px; flex-wrap: wrap; }
.filter-chip {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 5px 13px; border-radius: 99px; border: 1px solid var(--border);
  background: var(--surface); font-size: 13px; color: var(--text-secondary);
  cursor: pointer; transition: all 0.15s; font-weight: 500;
}
.filter-chip:hover { border-color: var(--accent); color: var(--accent); }
.filter-chip.active { background: var(--accent); border-color: var(--accent); color: #fff; }
.filter-chip--red.active { background: #dc2626; border-color: #dc2626; }
.filter-chip--orange.active { background: #d97706; border-color: #d97706; }
.filter-chip--gray.active { background: #6b7280; border-color: #6b7280; }
.chip-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.chip-dot--red { background: #dc2626; }
.chip-dot--orange { background: #d97706; }
.chip-count { font-size: 11px; font-weight: 700; }
.search-input {
  padding: 6px 12px; border: 1px solid var(--border); border-radius: 6px;
  font-size: 13px; width: 200px; background: var(--surface); color: var(--text-primary);
  outline: none; transition: border-color 0.15s;
}
.search-input:focus { border-color: var(--accent); }

/* Table */
.clickable-row { cursor: pointer; transition: background 0.1s; }
.clickable-row:hover td { background: #f7f5f0; }
.supplier-name { font-weight: 600; font-size: 14px; color: var(--text-primary); }
.empty-row { text-align: center; color: var(--text-secondary); padding: 48px !important; font-size: 14px; }
.none-indicator { color: var(--text-secondary); font-size: 13px; }
.row-arrow { color: var(--text-secondary); font-size: 18px; padding-right: 12px; }

/* Stat badges in cells */
.stat-badge {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 28px; height: 26px; border-radius: 6px;
  font-size: 13px; font-weight: 700; font-family: 'Fira Code', monospace; padding: 0 8px;
}
.stat-badge--green { background: #dcfce7; color: #15803d; }
.stat-badge--orange { background: #fef3c7; color: #b45309; }
.stat-badge--red { background: #fee2e2; color: #b91c1c; }
.stat-zero { color: #d1d5db; font-size: 13px; font-family: 'Fira Code', monospace; }

/* Missing doc tags */
.missing-tags { display: flex; flex-wrap: wrap; gap: 4px; }
.missing-tag {
  font-size: 11px; font-weight: 600; padding: 2px 7px; border-radius: 4px;
  background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;
}

/* Regulation tags */
.reg-tags { display: flex; flex-wrap: wrap; gap: 4px; }
.reg-tag {
  font-size: 11px; font-weight: 600; padding: 2px 7px; border-radius: 4px;
  background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe;
  display: inline-flex; align-items: center; gap: 3px;
}
.reg-tag--manual { opacity: 0.75; }
.reg-src-chip {
  font-size: 9px; font-weight: 600; padding: 1px 4px; border-radius: 3px;
  background: rgba(0,0,0,0.12); color: inherit;
}
.reg-src-chip--manual { background: rgba(0,0,0,0.07); }

/* DPP readiness bars */
.dpp-bar-row { display: flex; align-items: center; gap: 8px; }
.dpp-bar-label { font-size: 12px; font-family: 'Fira Code', monospace; color: var(--text-secondary); white-space: nowrap; width: 36px; text-align: right; }
.dpp-readiness-bar-wrap {
  height: 6px; background: var(--surface-2); border-radius: 3px; overflow: hidden;
  width: 100%;
}
.dpp-readiness-bar {
  height: 100%; background: #16a34a; border-radius: 3px; transition: width 0.3s;
}
.dpp-readiness-bar--yellow { background: #d97706; }
.dpp-readiness-bar--red { background: #dc2626; }

/* DPP status badges */
.dpp-status-ready { background: #dcfce7; color: #15803d; }
.dpp-status-partial { background: #fef3c7; color: #b45309; }
.dpp-status-not-started { background: var(--surface-2); color: var(--text-secondary); }

/* DPP Drawer sections */
.dpp-section {
  padding: 14px 20px; border-bottom: 1px solid var(--border);
}
.dpp-section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.dpp-section-title { font-size: 13px; font-weight: 600; color: var(--text-primary); }
.dpp-section-pct { font-size: 12px; font-family: 'Fira Code', monospace; color: var(--text-secondary); }
.dpp-list-row { display: flex; align-items: flex-start; gap: 10px; padding: 6px 0; border-bottom: 1px solid var(--border); }
.dpp-list-row:last-child { border-bottom: none; }
.dpp-check { font-size: 14px; font-weight: 700; flex-shrink: 0; margin-top: 1px; }
.dpp-check--ok { color: #16a34a; }
.dpp-check--fail { color: #dc2626; }
.dpp-list-info { flex: 1; min-width: 0; }
.dpp-list-name { font-size: 13px; font-weight: 500; color: var(--text-primary); }
.dpp-list-sub { font-size: 11px; color: var(--text-secondary); margin-top: 1px; }
.dpp-empty { font-size: 13px; color: var(--text-secondary); padding: 8px 0; text-align: center; }

/* ESPR regulation tag */
.reg-tag--espr { background: #d1fae5; color: #065f46; border-color: #6ee7b7; font-weight: 700; }

/* Regulation info popover */
.matrix-th-inner { display: flex; align-items: center; justify-content: center; gap: 5px; }
.reg-help-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 16px; height: 16px; border-radius: 50%;
  background: var(--surface-2); border: 1px solid var(--border);
  font-size: 10px; font-weight: 700; color: var(--text-secondary);
  cursor: pointer; flex-shrink: 0; line-height: 1;
  transition: background 0.15s, color 0.15s;
}
.reg-help-btn:hover { background: var(--accent); color: #fff; border-color: var(--accent); }
.reg-info-popover {
  position: absolute; top: calc(100% + 4px); left: 50%; transform: translateX(-50%);
  width: 260px; background: #fff; border: 1px solid var(--border);
  border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.12);
  padding: 12px 14px; z-index: 50; text-align: left;
  font-weight: 400;
}
.reg-info-popover::before {
  content: ''; position: absolute; top: -6px; left: 50%; transform: translateX(-50%);
  width: 10px; height: 10px; background: #fff; border-left: 1px solid var(--border);
  border-top: 1px solid var(--border); rotate: 45deg;
}
.reg-info-title { font-size: 12px; font-weight: 700; color: var(--accent); margin-bottom: 6px; }
.reg-info-body { font-size: 12px; color: var(--text-primary); line-height: 1.5; margin-bottom: 6px; }
.reg-info-link { font-size: 11px; color: var(--text-secondary); line-height: 1.4; }

/* Matrix table */
.matrix-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.matrix-th-row {
  text-align: left; padding: 10px 16px; font-size: 12px; font-weight: 600;
  color: var(--text-secondary); background: var(--surface-2); border-bottom: 1px solid var(--border);
  min-width: 180px; position: sticky; left: 0; z-index: 2;
}
.matrix-th-col {
  text-align: center; padding: 10px 12px; font-size: 12px; font-weight: 600;
  color: var(--text-secondary); background: var(--surface-2); border-bottom: 1px solid var(--border);
  min-width: 100px; position: relative;
}
.matrix-td-label {
  padding: 10px 16px; font-weight: 500; font-size: 13px; color: var(--text-primary);
  border-bottom: 1px solid var(--border); background: var(--surface);
  position: sticky; left: 0; z-index: 1;
}
.matrix-td-cell {
  text-align: center; padding: 8px 12px; border-bottom: 1px solid var(--border);
  cursor: pointer; transition: filter 0.1s;
}
.matrix-td-cell:hover:not(.cell-na) { filter: brightness(0.93); }
.cell-green { background: #dcfce7; }
.cell-yellow { background: #fef9c3; }
.cell-red { background: #fee2e2; }
.cell-na { background: var(--surface-2); cursor: default; }
.cell-value { font-size: 13px; font-weight: 700; font-family: 'Fira Code', monospace; color: var(--text-primary); }
.cell-pct { font-size: 11px; color: var(--text-secondary); margin-top: 2px; }
.cell-na-text { color: #d1d5db; font-size: 15px; }

/* Drawer */
.drawer-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.25); z-index: 100;
}
.drawer {
  position: fixed; top: 0; right: 0; width: 360px; height: 100vh;
  background: var(--surface); border-left: 1px solid var(--border);
  z-index: 101; display: flex; flex-direction: column; box-shadow: -4px 0 20px rgba(0,0,0,0.12);
}
.drawer-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 20px; border-bottom: 1px solid var(--border);
}
.drawer-title { font-size: 14px; font-weight: 600; color: var(--text-primary); }
.drawer-close {
  background: none; border: none; font-size: 20px; cursor: pointer;
  color: var(--text-secondary); line-height: 1; padding: 0 4px;
}
.drawer-body { flex: 1; overflow-y: auto; padding: 12px 0; }
.drill-supplier-row {
  display: flex; align-items: flex-start; justify-content: space-between;
  padding: 10px 20px; border-bottom: 1px solid var(--border); gap: 8px;
}
.drill-supplier-info { flex: 1; min-width: 0; }
.drill-supplier-name { font-size: 13px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 5px; flex-wrap: wrap; }
.drill-supplier-meta { display: flex; align-items: center; gap: 8px; margin-top: 3px; }
.drill-supplier-group { font-size: 11px; color: var(--text-secondary); }
.drill-risk-score { font-size: 11px; color: var(--text-secondary); }
.tier-badge {
  font-size: 10px; font-weight: 600; padding: 1px 5px;
  background: var(--surface-2); border: 1px solid var(--border);
  border-radius: 3px; color: var(--text-secondary);
}
.onboarding-chip {
  font-size: 10px; padding: 1px 6px; border-radius: 10px; font-weight: 500;
}
.onboarding-chip--certified { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
.onboarding-chip--other { background: var(--surface-2); color: var(--text-secondary); border: 1px solid var(--border); }
.drill-supplier-right { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex-shrink: 0; }
.drill-expires { font-size: 11px; color: var(--text-secondary); font-family: 'Fira Code', monospace; }
.drawer-footer {
  padding: 12px 20px; border-top: 1px solid var(--border);
  display: flex; flex-direction: column; gap: 6px; max-height: 160px; overflow-y: auto;
}

/* Status pill */
.status-pill {
  display: inline-flex; align-items: center; justify-content: center;
  padding: 4px 12px; border-radius: 99px; font-size: 12px; font-weight: 600; white-space: nowrap;
}
.status-pill--green { background: #dcfce7; color: #15803d; }
.status-pill--orange { background: #fef3c7; color: #b45309; }
.status-pill--red { background: #fee2e2; color: #b91c1c; }
.status-pill--gray { background: var(--surface-2); color: var(--text-secondary); }
/* Verify Modal summary */
.verify-summary { background: var(--surface-2); border-radius: 8px; padding: 12px 16px; margin-bottom: 12px; display: flex; flex-direction: column; gap: 6px; }
.verify-row { display: flex; align-items: baseline; gap: 8px; }
.verify-label { font-size: 12px; color: var(--text-secondary); width: 72px; flex-shrink: 0; }
.verify-value { font-size: 13px; color: var(--text-primary); font-weight: 500; }
</style>
