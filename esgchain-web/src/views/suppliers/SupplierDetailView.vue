<template>
  <div class="page-container">
    <!-- 頁頭 -->
    <div class="page-header">
      <div style="display:flex; align-items:center; gap:12px;">
        <button class="btn btn-secondary btn-sm" @click="router.push('/suppliers')">← 返回列表</button>
        <div v-if="supplier">
          <h1 class="page-title">{{ isEditing ? editForm.name : maskSupplierName(supplier.name) }}</h1>
          <div class="supplier-meta-chips">
            <span class="meta-chip meta-chip-mono">{{ supplier.code }}</span>
            <span class="meta-chip">{{ supplier.country_code }}</span>
            <span class="meta-chip">Tier {{ supplier.tier }}</span>
          </div>
        </div>
      </div>
      <div v-if="supplier" style="display:flex;gap:8px;align-items:center;">
        <span class="badge" :class="erpStatusBadgeClass(supplier.status)" title="ERP 匯入狀態，唯讀">{{ erpStatusLabel(supplier.status) }}</span>
        <template v-if="!isEditing">
          <button class="btn btn-secondary btn-sm" @click="openTransitionModal">變更狀態</button>
          <button class="btn btn-primary btn-sm" @click="enterEditMode">編輯</button>
        </template>
        <template v-else>
          <button class="btn btn-secondary btn-sm" @click="cancelEdit">取消</button>
          <button class="btn btn-primary btn-sm" :disabled="isSubmitting" @click="saveEdit">
            {{ isSubmitting ? '儲存中...' : '儲存' }}
          </button>
        </template>
      </div>
    </div>

    <div v-if="isLoading" class="loading-mask">載入中...</div>
    <div v-else-if="!supplier" class="empty-state"><p>供應商不存在</p></div>
    <div v-else class="detail-layout">
      <div class="detail-main">

        <!-- ══ Tab 導覽 ══ -->
        <div class="detail-tabs">
          <button
            v-for="t in INFO_TABS" :key="t.key"
            class="detail-tab"
            :class="{ active: activeInfoTab === t.key }"
            @click="switchTab(t.key)"
          >{{ t.label }}</button>
        </div>

        <!-- ══════════════════════════════════════════════════ -->
        <!-- Tab 1：概況（預設）                                -->
        <!-- ══════════════════════════════════════════════════ -->
        <div v-show="activeInfoTab === 'overview'" class="tab-panel-wrap">

          <!-- 識別資訊 + 產業分類 + 管理歸屬（合一）-->
          <div class="detail-section">
            <div class="section-title">識別資訊</div>
            <div class="detail-grid">
              <div class="detail-item">
                <span class="detail-label">名稱</span>
                <span class="detail-value">{{ maskSupplierName(supplier.name) }}</span>
                <span v-if="isEditing" style="display:block;font-size:11px;color:var(--text-secondary);">僅可透過 ERP 同步建立，不可修改</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">代碼</span>
                <span class="detail-value font-mono">{{ supplier.code || '—' }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">國家/地區碼</span>
                <span class="detail-value">{{ supplier.country_code || '—' }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">供應商層級</span>
                <span class="detail-value">Tier {{ supplier.tier }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">統編 / VAT</span>
                <span class="detail-value font-mono">{{ supplier.vat_number || '—' }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">ERP 廠商代碼</span>
                <span class="detail-value font-mono">{{ supplier.erp_vendor_codes || '—' }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">年採購金額（USD）</span>
                <span class="detail-value font-mono">{{ supplier.spend_amount != null ? Number(supplier.spend_amount).toLocaleString() : '—' }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">建立時間</span>
                <span class="detail-value">{{ formatDate(supplier.created_at) }}</span>
              </div>

              <!-- 產業分類 -->
              <div class="detail-item">
                <span class="detail-label">產業</span>
                <span class="detail-value">{{ supplier.industry || '—' }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">ESG 產業分類</span>
                <select v-if="isEditing" v-model="editForm.industry_group" class="form-select">
                  <option value="">— 未設定 —</option>
                  <option v-for="ig in INDUSTRY_GROUP_OPTIONS" :key="ig" :value="ig">{{ ig }}</option>
                </select>
                <span v-else class="detail-value">{{ supplier.industry_group || '—' }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">SASB 分類</span>
                <template v-if="isEditing">
                  <select v-model="editForm.sasb_industry_id" class="form-select" :disabled="auxLoading">
                    <option value="">— 不指定 —</option>
                    <optgroup v-for="(industries, sector) in groupedSasb" :key="sector" :label="sector">
                      <option v-for="ind in industries" :key="ind.id" :value="ind.id">
                        {{ ind.code }} — {{ ind.industry }}
                      </option>
                    </optgroup>
                  </select>
                </template>
                <span v-else class="detail-value">
                  {{ supplier.sasb_industry ? `${supplier.sasb_industry.code} — ${supplier.sasb_industry.industry}` : '—' }}
                </span>
              </div>

              <!-- 管理歸屬 -->
              <div class="detail-item">
                <span class="detail-label">供應商分組</span>
                <template v-if="isEditing">
                  <select v-model="editForm.group_id" class="form-select" :disabled="auxLoading">
                    <option value="">— 未分組 —</option>
                    <option v-for="g in groupOptions" :key="g.id" :value="g.id">{{ g.name }}</option>
                  </select>
                </template>
                <span v-else class="detail-value">{{ supplier.group?.name || '未分組' }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">活躍狀態</span>
                <span>
                  <span class="badge" :class="statusBadgeClass(supplier.onboarding_stage)">
                    {{ statusLabel(supplier.onboarding_stage) }}
                  </span>
                </span>
              </div>
            </div>
          </div>


        </div><!-- /overview -->

        <!-- ══════════════════════════════════════════════════ -->
        <!-- Tab 2：風險歷史                                     -->
        <!-- ══════════════════════════════════════════════════ -->
        <div v-show="activeInfoTab === 'risk'" class="tab-panel-wrap">
          <div class="risk-grid">

            <!-- ┌─────────────────┐ ┌──────────────────────────────────┐ -->
            <!-- │ 左上：雷達圖      │ │ 右上：年度問卷資料表格              │ -->
            <!-- └─────────────────┘ └──────────────────────────────────┘ -->
            <!-- ┌─────────────────┐ ┌──────────────────────────────────┐ -->
            <!-- │ 左下：AI 建議    │ │ 右下：趨勢比較圖                   │ -->
            <!-- └─────────────────┘ └──────────────────────────────────┘ -->

            <!-- 左上：六維雷達圖 -->
            <div class="rg-cell rg-top-left">
              <div v-if="riskSummary">
                <div class="rg-cell-title">六維風險雷達</div>
                <!-- Chart.js Radar -->
                <div class="rg-radar-chartjs-wrap">
                  <Radar :data="radarChartData" :options="radarChartOptions" />
                </div>
                <!-- 維度得分 legend -->
                <div class="rg-radar-legend" style="margin-top:12px;">
                  <div v-for="(dim,i) in radarDims" :key="dim.key" class="rg-radar-row">
                    <span class="rg-radar-dot" :style="{ background: radarDotColor(radarDimScores[i]) }"></span>
                    <span class="rg-radar-key">{{ dim.key }}</span>
                    <span class="rg-radar-label">{{ dim.label }}</span>
                    <span :class="['rg-radar-score font-mono', radarDimScores[i] == null ? 'rg-radar-null' : '']">
                      {{ radarDimScores[i] != null ? radarDimScores[i].toFixed(1) : '—' }}
                    </span>
                  </div>
                </div>
              </div>
              <div v-else class="empty-inline">尚無風險資料</div>
            </div>

            <!-- 左下：AI 風險建議 -->
            <div class="rg-cell rg-bottom-left">
              <div class="rg-cell-title">風險評估建議</div>
              <AiRiskSuggestionPanel
                v-if="supplier"
                :supplier-id="supplier.id"
                :has-ra="!!riskSummary"
              />
            </div>

            <!-- 右欄：年度評估表 + 趨勢圖（合併單卡）-->
            <div class="rg-cell rg-right">

              <!-- ── 歷年評估紀錄 ── -->
              <div class="rg-cell-title-row" style="margin-bottom:14px;">
                <span class="rg-cell-title" style="margin-bottom:0;">歷年評估紀錄</span>
                <a @click.prevent="router.push('/risk')" href="#" class="rg-cell-link">前往永續風險概覽 →</a>
              </div>
              <div v-if="timelineLoading" class="empty-inline">載入中...</div>
              <div v-else-if="!trendRows.length" class="empty-inline">尚無評估記錄</div>
              <div v-else class="rg-year-table-wrap">
                <table class="rg-year-table">
                  <thead>
                    <tr>
                      <th>年度</th>
                      <th>總分</th>
                      <th>Grade</th>
                      <th>E1</th><th>E2</th><th>E3</th><th>E4</th><th>E5</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in trendRows" :key="row.year">
                      <td class="rg-yt-period">
                        <span class="rg-yt-year">{{ row.year }}</span>
                        <span v-if="row.ra?.linked_saq?.submitted_at" class="rg-yt-date">{{ formatDate(row.ra.linked_saq.submitted_at) }}</span>
                      </td>
                      <td class="rg-yt-score font-mono">{{ row.ra?.linked_saq?.score != null ? Number(row.ra.linked_saq.score).toFixed(1) : '—' }}</td>
                      <td>
                        <span v-if="row.ra?.linked_saq?.grade" class="tl-grade-chip" :class="`grade-${row.ra.linked_saq.grade.toLowerCase()}`">{{ row.ra.linked_saq.grade }}</span>
                        <span v-else class="rg-yt-muted">—</span>
                      </td>
                      <td v-for="dim in ['E1','E2','E3','E4','E5']" :key="dim" class="rg-yt-dim">
                        <span :class="['rg-dim-chip', row.ra?.risk?.six_dims?.[dim] != null ? trendCellClass(row.ra.risk.six_dims[dim]) : 'none']">
                          {{ row.ra?.risk?.six_dims?.[dim] != null ? Number(row.ra.risk.six_dims[dim]).toFixed(0) : '—' }}
                        </span>
                      </td>
                      <td>
                        <router-link v-if="row.ra?.risk?.source_saq_id" :to="`/questionnaires/review/${row.ra.risk.source_saq_id}`" class="rg-yt-link">查看問卷</router-link>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Pending SAQ -->
              <div v-if="timeline?.pending_saq" class="tl-card tl-pending" style="margin-top:10px;">
                <div class="tl-pending-header">
                  <span class="tl-spin">⟳</span>
                  <span class="tl-pending-label">問卷已提交，等待計分</span>
                  <span class="badge badge-yellow tl-status-chip">{{ timeline.pending_saq.status === 'under_review' ? '審核中' : '待計分' }}</span>
                </div>
                <div class="tl-pending-meta">提交於 {{ formatDate(timeline.pending_saq.submitted_at) }}</div>
              </div>

              <!-- ── 分隔線 ── -->
              <div class="rg-section-divider"></div>

              <!-- ── 六維度趨勢比較 ── -->
              <div v-if="trendRows.length">
                <div class="rtc-header">
                  <span class="rg-cell-title" style="margin-bottom:0;">六維度趨勢比較</span>
                  <div class="rtc-tabs">
                    <button :class="['rtc-tab', trendChartTab==='bar'&&'active']" @click="trendChartTab='bar'">年度比較</button>
                    <button :class="['rtc-tab', trendChartTab==='delta'&&'active']" @click="trendChartTab='delta'">變化量</button>
                  </div>
                </div>

                <!-- 年度比較：Chart.js Bar -->
                <div v-if="trendChartTab==='bar'" class="rtc-chartjs-wrap">
                  <div class="rtc-legend">
                    <span v-for="(row,i) in [...trendRows].reverse()" :key="row.year" class="rtc-legend-item">
                      <i :style="{background: i===0?'#4a86b0':'#1a4d3e'}"></i>{{ row.year }}
                    </span>
                  </div>
                  <Bar :data="barChartData" :options="barChartOptions" />
                </div>

                <!-- 變化量：Chart.js 水平 Bar -->
                <div v-if="trendChartTab==='delta' && trendRows.length>=2" class="rtc-delta-wrap">
                  <div class="rtc-delta-subtitle">{{ trendRows[trendRows.length-1].year }} → {{ trendRows[0].year }} 各維度分數變化</div>
                  <div class="rtc-delta-chartjs-wrap">
                    <Bar :data="deltaChartData" :options="deltaChartOptions" />
                  </div>
                </div>
                <div v-if="trendChartTab==='delta' && trendRows.length<2" class="rtc-no-delta">需至少兩年資料才能顯示變化量</div>
              </div>
              <div v-else class="empty-inline" style="margin-top:8px;">尚無趨勢資料</div>

            </div><!-- /rg-right -->

          </div><!-- /risk-grid -->
        </div><!-- /risk -->

        <!-- ══════════════════════════════════════════════════ -->
        <!-- Tab 2：永續績效                                    -->
        <!-- ══════════════════════════════════════════════════ -->
        <div v-show="activeInfoTab === 'sustain'" class="tab-panel-wrap">

          <!-- 現況摘要 header -->
          <div class="sustain-summary-bar" v-if="questionnaires.length">
            <div class="ss-stat">
              <span class="ss-num">{{ sustainSummary.passed }}</span>
              <span class="ss-label">已通過</span>
            </div>
            <div class="ss-divider"></div>
            <div class="ss-stat" v-if="sustainSummary.reviewing > 0">
              <span class="ss-num ss-num--warn">{{ sustainSummary.reviewing }}</span>
              <span class="ss-label">審核中</span>
            </div>
            <div class="ss-divider" v-if="sustainSummary.reviewing > 0"></div>
            <div class="ss-stat" v-if="sustainSummary.sent > 0">
              <span class="ss-num ss-num--muted">{{ sustainSummary.sent }}</span>
              <span class="ss-label">待填報</span>
            </div>
            <div class="ss-divider" v-if="sustainSummary.sent > 0 && sustainSummary.latest"></div>
            <div class="ss-latest" v-if="sustainSummary.latest">
              <span class="ss-label">最近活動</span>
              <span class="ss-latest-name">{{ sustainSummary.latest.template?.name || '問卷' }}</span>
              <span class="badge" :class="qBadgeClass(sustainSummary.latest.status)" style="font-size:11px;">{{ qStatusLabel(sustainSummary.latest.status) }}</span>
              <span class="ss-latest-date" v-if="sustainSummary.latest.submitted_at">{{ formatDate(sustainSummary.latest.submitted_at) }}</span>
            </div>
          </div>

          <!-- 問卷記錄 -->
          <div class="detail-section">
            <div class="section-title-row">
              <span class="section-title">問卷記錄</span>
              <button class="btn btn-primary btn-sm" @click="router.push('/questionnaires/send?supplier_id=' + supplier?.id)">+ 發起問卷</button>
            </div>
            <div v-if="!questionnaires.length" class="empty-inline">尚無問卷記錄</div>
            <table v-else class="data-table">
              <thead><tr><th>範本</th><th>狀態</th><th style="white-space:nowrap;">提交日</th><th>分數</th><th>操作</th></tr></thead>
              <tbody>
                <tr v-for="q in questionnaires" :key="q.id">
                  <td>{{ q.template?.name || q.template_id?.slice(0,8) }}</td>
                  <td><span class="badge" :class="qBadgeClass(q.status)">{{ qStatusLabel(q.status) }}</span></td>
                  <td style="font-size:12px;white-space:nowrap;">{{ q.submitted_at ? formatDate(q.submitted_at) : '—' }}</td>
                  <td class="num">{{ q.score != null ? q.score.toFixed(1) : '—' }}</td>
                  <td><button class="btn btn-ghost-sm" @click.stop="router.push(`/questionnaires/review/${q.id}`)">查看 →</button></td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- 揭露資料時間序列（依 KPI 類型分組） -->
          <div class="detail-section">
            <div class="section-title-row">
              <span class="section-title">揭露資料時間序列</span>
            </div>
            <div v-if="disclosureLoading" class="empty-inline">載入中…</div>
            <div v-else-if="!disclosureGroups.length" class="empty-inline">尚無填報資料</div>
            <template v-else v-for="group in disclosureGroups" :key="group.prefix">
              <div class="disclosure-group-header">{{ group.label }}</div>
              <table class="data-table" style="margin-bottom:16px;">
                <thead>
                  <tr>
                    <th>KPI 欄位</th>
                    <th style="white-space:nowrap;">年度</th>
                    <th style="text-align:right;">數值</th>
                    <th>來源問卷</th>
                    <th style="white-space:nowrap;">驗證</th>
                  </tr>
                </thead>
                <tbody>
                  <template v-for="entry in group.entries" :key="entry.slug + entry.period_year">
                    <tr>
                      <td>
                        <span class="font-mono" style="font-size:11px;color:var(--text-secondary);">{{ entry.slug }}</span>
                        <div style="font-size:13px;">{{ entry.label }}</div>
                      </td>
                      <td class="font-mono">{{ entry.period_year }}</td>
                      <td style="text-align:right;" class="font-mono">
                        <span v-if="entry.data_type === 'boolean'">
                          {{ entry.boolean_value ? '✓ 是' : '✗ 否' }}
                        </span>
                        <span v-else-if="entry.data_type === 'numeric'">
                          {{ entry.numeric_value != null ? entry.numeric_value.toLocaleString() : '—' }}
                          <span v-if="entry.unit" style="font-size:11px;color:var(--text-secondary);margin-left:3px;">{{ entry.unit }}</span>
                        </span>
                        <span v-else>{{ entry.text_value || '—' }}</span>
                        <span v-if="entry.yoy != null" :style="{ marginLeft:'6px', fontSize:'11px', color: entry.yoy >= 0 ? 'var(--success)' : 'var(--danger)' }">
                          {{ entry.yoy >= 0 ? '+' : '' }}{{ entry.yoy.toFixed(1) }}%
                        </span>
                      </td>
                      <td>
                        <router-link
                          v-if="entry.source === 'saq_sync' && entry.source_saq_id"
                          :to="`/questionnaires/review/${entry.source_saq_id}`"
                          style="font-size:12px;color:var(--accent);"
                        >查看問卷</router-link>
                        <span v-else-if="entry.source === 'manual'" style="color:var(--text-secondary);font-size:12px;">手動填報</span>
                        <span v-else-if="entry.source === 'erp_sync'" style="color:var(--text-secondary);font-size:12px;">ERP 同步</span>
                        <span v-else style="color:var(--text-secondary);">—</span>
                      </td>
                      <td>
                        <span v-if="entry.verified_at" class="badge badge-green" style="font-size:11px;">已驗證</span>
                        <span v-else-if="entry.source === 'saq_sync'" class="badge badge-gray" style="font-size:11px;">未驗證</span>
                        <span v-else style="color:var(--text-secondary);font-size:12px;">—</span>
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </template>
          </div>

        </div><!-- /sustain -->

        <!-- ══════════════════════════════════════════════════ -->
        <!-- Tab 3：合規管理                                    -->
        <!-- ══════════════════════════════════════════════════ -->
        <div v-show="activeInfoTab === 'comply'" class="tab-panel-wrap">

          <!-- 供應材料清單（BOM → required_doc_types，因果鏈上方）-->
          <div class="detail-section">
            <div class="section-title-row">
              <span class="section-title">供應材料清單</span>
              <span v-if="bomReqLoading" class="loading-hint">載入中…</span>
            </div>
            <div v-if="!bomReqLoading && flatBomLines.length === 0" class="empty-inline">尚無供應物料記錄</div>
            <table v-else-if="flatBomLines.length > 0" class="data-table">
              <thead>
                <tr>
                  <th>所屬產品</th>
                  <th>物料名稱</th>
                  <th>HS Code</th>
                  <th>物料群組</th>
                  <th>所需文件類型</th>
                  <th>合規文件狀態</th>
                  <th>PCF 申報狀態</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="line in flatBomLines" :key="line.bom_line_id">
                  <td><span class="product-tag">{{ line.product_name || '—' }}</span></td>
                  <td class="bom-material-name">{{ line.material_name || '—' }}</td>
                  <td class="font-mono bom-hs-code">{{ line.hs_code || '—' }}</td>
                  <td class="bom-group">{{ line.material_group || '—' }}</td>
                  <td>
                    <span
                      v-for="dt in (line.required_doc_types || [])"
                      :key="dt"
                      class="doc-type-badge"
                      style="margin-right:4px;"
                    >{{ dt }}</span>
                    <span v-if="!line.required_doc_types || line.required_doc_types.length === 0" class="text-secondary" style="font-size:13px;">無要求</span>
                  </td>
                  <td>
                    <template v-if="line.submitted_docs && line.submitted_docs.length > 0">
                      <div v-for="sd in line.submitted_docs" :key="sd.doc_type" class="comply-status-row">
                        <span class="comply-doc-type">{{ sd.doc_type }}</span>
                        <span class="comply-status-chip" :class="complyStatusClass(sd.status)">
                          {{ complyStatusLabel(sd.status) }}
                        </span>
                        <span v-if="sd.expires_at" class="comply-expires font-mono">{{ sd.expires_at?.slice(0,10) }}</span>
                      </div>
                    </template>
                    <span v-else class="text-secondary" style="font-size:12px;">無合規要求</span>
                  </td>
                  <td>
                    <span class="badge" :class="pcfStatusBadge(line.pcf_status)">
                      {{ pcfStatusLabel(line.pcf_status) }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- 合規文件（actual doc status，因果鏈下方）-->
          <div class="detail-section">
            <div class="section-title-row">
              <span class="section-title">合規文件</span>
            </div>
            <div v-if="complianceDocs.length === 0" class="empty-inline">尚無合規文件記錄</div>
            <table v-else class="data-table">
              <thead>
                <tr>
                  <th>文件類型</th>
                  <th>出口商品</th>
                  <th style="white-space:nowrap;">發行日</th>
                  <th style="white-space:nowrap;">有效期限</th>
                  <th>驗證狀態</th>
                  <th>佐證文件</th>
                  <th>備註</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="doc in complianceDocs" :key="doc.id" :class="{ 'doc-expired': isExpired(doc.expires_at) }">
                  <td><span class="doc-type-badge">{{ doc.doc_type }}</span></td>
                  <td class="text-secondary">{{ doc.trade_good?.name || '—' }}</td>
                  <td class="font-mono" style="font-size:12px;white-space:nowrap;">{{ doc.issued_at?.slice(0,10) || '—' }}</td>
                  <td style="white-space:nowrap;">
                    <span class="font-mono" style="font-size:12px;" :class="{ 'text-danger': isExpired(doc.expires_at), 'text-warn': isExpiringSoon(doc.expires_at) }">
                      {{ doc.expires_at?.slice(0,10) || '—' }}
                    </span>
                    <span v-if="isExpired(doc.expires_at)" class="comply-status-chip comply-expired" style="margin-left:4px;">已逾期</span>
                    <span v-else-if="isExpiringSoon(doc.expires_at)" class="comply-status-chip comply-expiring" style="margin-left:4px;">即將到期</span>
                  </td>
                  <td>
                    <span class="badge" :class="doc.verified_at ? 'badge-green' : 'badge-gray'">
                      {{ doc.verified_at ? '✓ 已驗證' : '待驗證' }}
                    </span>
                  </td>
                  <td>
                    <a v-if="doc.file_name" :href="`/storage/${doc.file_path}`" target="_blank" class="file-link">
                      📄 {{ doc.file_name }}
                    </a>
                    <span v-else class="text-secondary" style="font-size:12px;">—</span>
                  </td>
                  <td class="text-secondary" style="font-size:12px;">{{ doc.notes || '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>

        </div><!-- /comply -->

        <!-- ══════════════════════════════════════════════════ -->
        <!-- Tab 4：設施 & 聯絡                                 -->
        <!-- ══════════════════════════════════════════════════ -->
        <div v-show="activeInfoTab === 'facility'" class="tab-panel-wrap">

          <!-- 廠區 -->
          <div class="detail-section">
            <div class="section-subtitle-row">
              <span class="section-subtitle">廠區（製程/地點）</span>
              <button class="btn btn-secondary btn-xs" @click="openAddFacilityModal">＋ 新增廠區</button>
            </div>
            <div v-if="facilitiesLoading" class="empty-inline">載入中…</div>
            <div v-else-if="!facilities.length" class="empty-inline">尚無廠區資料</div>
            <div v-else class="contact-list">
              <div v-for="f in facilities" :key="f.id" class="contact-row">
                <div class="contact-info">
                  <span class="contact-name">{{ f.name }}</span>
                  <span class="contact-meta">{{ FACILITY_TYPE_LABELS[f.facility_type] ?? f.facility_type }}</span>
                  <span v-if="f.country" class="contact-meta">{{ f.country }}</span>
                </div>
                <div class="contact-actions">
                  <span v-if="!f.is_active" class="badge badge-gray">已停用</span>
                  <button class="icon-btn" title="編輯" @click="openEditFacilityModal(f)">✎</button>
                </div>
              </div>
            </div>
          </div>

          <!-- 地址 / 網站 + 聯絡人 -->
          <div class="detail-section">
            <div class="detail-grid" style="margin-bottom:16px;">
              <div class="detail-item">
                <span class="detail-label">地址</span>
                <span class="detail-value">{{ supplier.address || '—' }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">網站</span>
                <a v-if="supplier.website" :href="supplier.website" target="_blank" class="detail-link">{{ supplier.website }}</a>
                <span v-else class="detail-value">—</span>
              </div>
            </div>
            <div class="section-subtitle-row">
              <span class="section-subtitle">聯絡人</span>
              <button class="btn btn-secondary btn-xs" @click="openAddContactModal">＋ 新增聯絡人</button>
            </div>
            <div v-if="!supplier.contacts?.length" class="empty-inline">尚無聯絡人</div>
            <div v-else class="contact-list">
              <div v-for="c in supplier.contacts" :key="c.id" class="contact-row">
                <div class="contact-info">
                  <span class="contact-name">{{ c.name }}</span>
                  <span v-if="c.title" class="contact-meta">{{ c.title }}</span>
                  <span v-if="c.email" class="contact-meta">📧 {{ c.email }}</span>
                  <span v-if="c.phone" class="contact-meta">📞 {{ c.phone }}</span>
                </div>
                <div class="contact-actions">
                  <span v-if="c.is_primary" class="badge badge-green contact-primary">主要</span>
                  <button class="icon-btn" title="刪除" @click="deleteContact(c)">✕</button>
                </div>
              </div>
            </div>
          </div>

          <!-- 狀態歷程 Timeline -->
          <div class="detail-section">
            <div class="section-title">狀態歷程</div>
            <div v-if="!supplier.status_histories?.length" class="empty-inline">尚無狀態變更記錄</div>
            <div v-else class="timeline">
              <div v-for="h in supplier.status_histories" :key="h.id" class="timeline-item">
                <div class="timeline-dot" :class="historyBadgeClass(h, h.to_status)" />
                <div class="timeline-content">
                  <div class="timeline-title">
                    <span v-if="h.from_status" class="badge" :class="historyBadgeClass(h, h.from_status)">{{ historyLabel(h, h.from_status) }}</span>
                    <span v-if="h.from_status" class="timeline-arrow">→</span>
                    <span class="badge" :class="historyBadgeClass(h, h.to_status)">{{ historyLabel(h, h.to_status) }}</span>
                  </div>
                  <div v-if="h.reason" class="timeline-reason">{{ h.reason }}</div>
                  <div class="timeline-time">{{ formatDateTime(h.created_at) }}</div>
                </div>
              </div>
            </div>
          </div>

        </div><!-- /facility -->

      </div><!-- /detail-main -->
    </div><!-- /detail-layout -->

    <!-- 新增/編輯廠區 Modal -->
    <div v-if="showFacilityModal" class="modal-overlay" @click.self="showFacilityModal=false">
      <div class="modal" style="min-width:360px;">
        <div class="modal-header">
          <span class="modal-title">{{ facilityForm.id ? '編輯廠區' : '新增廠區' }}</span>
          <button class="modal-close" @click="showFacilityModal=false">×</button>
        </div>
        <div class="form-group">
          <label class="form-label">廠區名稱 <span style="color:#ef4444;">*</span></label>
          <input v-model="facilityForm.name" class="form-input" placeholder="如：越南染整廠" />
        </div>
        <div class="form-group">
          <label class="form-label">製程類型</label>
          <select v-model="facilityForm.facility_type" class="form-select">
            <option value="manufacturing">一般製造</option>
            <option value="weaving">織布</option>
            <option value="knitting">針織</option>
            <option value="dyeing">染整</option>
            <option value="printing">印花</option>
            <option value="wet_processing">濕製程</option>
            <option value="garment_assembly">成衣製造</option>
            <option value="warehouse">倉儲</option>
            <option value="office">辦公室</option>
            <option value="other">其他</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">國家（ISO 2 碼）</label>
          <input v-model="facilityForm.country" class="form-input" placeholder="VN" maxlength="2" style="text-transform:uppercase;" />
        </div>
        <div class="form-group">
          <label class="form-label">地址</label>
          <textarea v-model="facilityForm.address" class="form-textarea" rows="2" placeholder="廠區地址" />
        </div>
        <div v-if="facilityForm.id" class="form-group" style="display:flex;align-items:center;gap:8px;">
          <input id="facility-active" v-model="facilityForm.is_active" type="checkbox" />
          <label for="facility-active" class="form-label" style="margin:0;cursor:pointer;">啟用中</label>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showFacilityModal=false">取消</button>
          <button class="btn btn-primary" :disabled="facilitySaving || !facilityForm.name.trim()" @click="saveFacility">
            {{ facilitySaving ? '儲存中...' : '儲存' }}
          </button>
        </div>
      </div>
    </div>

    <!-- 新增聯絡人 Modal -->
    <div v-if="showContactModal" class="modal-overlay" @click.self="showContactModal=false">
      <div class="modal" style="min-width:360px;">
        <div class="modal-header">
          <span class="modal-title">新增聯絡人</span>
          <button class="modal-close" @click="showContactModal=false">×</button>
        </div>
        <div class="form-group">
          <label class="form-label">姓名 <span style="color:#ef4444;">*</span></label>
          <input v-model="contactForm.name" class="form-input" placeholder="聯絡人姓名" />
        </div>
        <div class="form-group">
          <label class="form-label">職稱</label>
          <input v-model="contactForm.title" class="form-input" placeholder="如：ESG 經理" />
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input v-model="contactForm.email" class="form-input" type="email" placeholder="example@company.com" />
        </div>
        <div class="form-group">
          <label class="form-label">電話</label>
          <input v-model="contactForm.phone" class="form-input" placeholder="+886-..." />
        </div>
        <div class="form-group" style="display:flex;align-items:center;gap:8px;">
          <input id="is-primary" v-model="contactForm.is_primary" type="checkbox" />
          <label for="is-primary" class="form-label" style="margin:0;cursor:pointer;">設為主要聯絡人</label>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showContactModal=false">取消</button>
          <button class="btn btn-primary" :disabled="contactSaving || !contactForm.name.trim()" @click="saveContact">
            {{ contactSaving ? '儲存中...' : '新增' }}
          </button>
        </div>
      </div>
    </div>

    <!-- 入選階段流轉 Modal -->
    <div v-if="showTransitionModal" class="modal-overlay" @click.self="showTransitionModal=false">
      <div class="modal" style="min-width:380px;">
        <div class="modal-header">
          <span class="modal-title">變更供應商入選階段</span>
          <button class="modal-close" @click="showTransitionModal=false">×</button>
        </div>
        <div class="form-group">
          <label class="form-label">目前階段</label>
          <div style="padding:6px 0;font-size:0.9rem;color:var(--text-secondary);">{{ onboardingStageLabel(supplier && supplier.onboarding_stage) }}</div>
        </div>
        <div class="form-group">
          <label class="form-label">變更至</label>
          <select v-model="transitionForm.onboarding_stage" class="form-select">
            <option v-for="s in allowedNextStages" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
          <p v-if="allowedNextStages.length === 0" class="form-hint" style="color:#ef4444;">此階段無可轉換的下一步</p>
        </div>
        <div class="form-group">
          <label class="form-label">原因</label>
          <textarea v-model="transitionForm.reason" class="form-textarea" placeholder="選填：說明變更原因"></textarea>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showTransitionModal=false">取消</button>
          <button class="btn btn-primary" :disabled="isSubmitting || allowedNextStages.length === 0" @click="doTransition">確認</button>
        </div>
      </div>
    </div>

  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { Bar, Radar } from 'vue-chartjs'
import {
  Chart as ChartJS, CategoryScale, LinearScale, BarElement,
  RadialLinearScale, PointElement, LineElement, Filler,
  Title, Tooltip, Legend
} from 'chart.js'
ChartJS.register(
  CategoryScale, LinearScale, BarElement,
  RadialLinearScale, PointElement, LineElement, Filler,
  Title, Tooltip, Legend
)
import { suppliersApi, type Supplier, facilityApi, type SupplierFacility } from '@/api/modules/suppliers'
import { DISCLOSURE_PREFIX_LABELS } from '@/constants/disclosureDomains'
import { questionnaireApi, type Questionnaire } from '@/api/modules/questionnaire'
import { riskApi } from '@/api/modules/risk'
import { settingsApi, type SasbIndustry, type SupplierGroup } from '@/api/modules/settings'
import { supplierBomRequirementsApi, type BomRequirementProduct } from '@/api/modules/compliance'
import { maskSupplierName } from '@/utils/maskName'
import CountrySelect from '@/components/common/CountrySelect.vue'
import SupplierRiskRadarChart from '@/components/risk/SupplierRiskRadarChart.vue'
import AiRiskSuggestionPanel from '@/components/suppliers/AiRiskSuggestionPanel.vue'
import api from '@/api/http'

const INFO_TABS = [
  { key: 'overview',  label: '概況' },
  { key: 'risk',      label: '風險歷史' },
  { key: 'sustain',   label: '永續績效' },
  { key: 'comply',    label: '合規管理' },
  { key: 'facility',  label: '聯絡/廠區資訊' },
]

const AXIS_DEFS = {
  axis1: { label: 'ESG 揭露（軸1）' },
  axis2: { label: '治理成熟度（軸2）' },
  axis3: { label: '地緣產業（軸3）' },
}
const LEVEL_LABEL: Record<string, string> = {
  extreme: '極高', high: '高', medium: '中', low: '低', very_low: '極低',
}
const AXIS_CHIP_CLASS: Record<string, string> = {
  extreme: 'axis-chip-extreme', high: 'axis-chip-high', medium: 'axis-chip-medium',
  low: 'axis-chip-low', very_low: 'axis-chip-very-low',
}
const LEVEL_ZH: Record<string, string> = {
  extreme: '極高', high: '高', medium: '中', low: '低', very_low: '極低',
}

const STATUS_LABELS: Record<string, string> = { active: '啟用', suspended: '暫停', terminated: '終止' }
// ERP 擁有的 status 欄位（依匯入資料決定，僅 active/inactive 兩種），與上面 onboarding_stage 的 3 種值語彙分開，避免混用
const ERP_STATUS_LABELS: Record<string, string> = { active: '生效', inactive: '停用' }
const ERP_STATUS_BADGE_CLASS: Record<string, string> = { active: 'badge-green', inactive: 'badge-gray' }
const FACILITY_TYPE_LABELS: Record<string, string> = {
  manufacturing: '一般製造', weaving: '織布', knitting: '針織', dyeing: '染整', printing: '印花',
  wet_processing: '濕製程', garment_assembly: '成衣製造', warehouse: '倉儲', office: '辦公室', other: '其他',
}
const Q_STATUS_LABELS: Record<string, string> = {
  not_started: '未開始', in_progress: '填寫中', submitted: '已提交',
  under_review: '審核中', review_returned: '已退回', completed: '已通過', reviewed: '已複核',
}
const DIM_LABELS: Record<string, string> = { e: 'E 環境', s: 'S 社會', g: 'G 治理', gp: 'GP 地緣政治' }

export default defineComponent({
  name: 'SupplierDetailView',
  components: { CountrySelect, SupplierRiskRadarChart, AiRiskSuggestionPanel, Bar, Radar },
  setup() { return { router: useRouter(), route: useRoute() } },

  data() {
    return {
      INFO_TABS,
      activeInfoTab: 'overview',
      loadedTabs: new Set<string>(['overview']),
      disclosureLoading: false,
      disclosureRaw: {} as Record<string, any[]>,
      isLoading: false,
      isSubmitting: false,
      supplier: null as Supplier | null,
      riskSummary: null as any,
      axis3Input: null as number | null,
      savingAxis3: false,
      AXIS_DEFS,
      LEVEL_LABEL,
      LEVEL_ZH,
      INDUSTRY_GROUP_OPTIONS: ['製造業', '勞動密集製造', '農林漁業', '科技電子', '物流倉儲', '原物料化工', '服務業'],
      questionnaires: [] as Questionnaire[],
      showTransitionModal: false,
      transitionForm: { onboarding_stage: 'invited', reason: '' },

      // Edit mode
      isEditing: false,
      // 僅 ESG-Chain 自有分類欄位可編輯，其餘供應商資料一律唯讀、保留從 ERP 匯入的值
      editForm: {
        industry_group: '' as string | null,
        sasb_industry_id: '' as string | null,
        group_id: '' as string | null,
      },

      // 輔助資料（SASB + 分組）
      auxLoaded: false,
      auxLoading: false,
      sasbOptions: [] as SasbIndustry[],
      groupOptions: [] as SupplierGroup[],

      // 聯絡人 Modal
      showContactModal: false,
      contactForm: { name: '', title: '', email: '', phone: '', is_primary: false },
      contactSaving: false,

      // 廠區
      facilities: [] as SupplierFacility[],
      facilitiesLoading: false,
      showFacilityModal: false,
      facilityForm: {
        id: '' as string,
        name: '',
        facility_type: 'manufacturing' as SupplierFacility['facility_type'],
        country: '',
        address: '',
        is_active: true,
      },
      facilitySaving: false,
      FACILITY_TYPE_LABELS,

      // 合規文件
      complianceDocs: [] as any[],

      // 供應材料清單
      bomRequirements: [] as BomRequirementProduct[],
      bomReqLoading: false,

      // 風險評估歷史時間軸
      riskHistory: [] as any[],
      riskHistoryLoading: false,
      timeline: null as { events: any[]; pending_saq: any | null } | null,
      trendChartTab: 'bar' as 'bar' | 'delta' | 'trend',
      timelineLoading: false,
    }
  },

  computed: {
    flatBomLines(): Array<{ bom_line_id: string; product_name: string; material_name: string; hs_code: string; material_group: string; required_doc_types: string[]; pcf_status: string }> {
      const result: any[] = []
      for (const product of this.bomRequirements) {
        for (const line of product.bom_lines || []) {
          result.push({ ...line, product_name: product.product_name })
        }
      }
      return result
    },
    disclosureEntries(): any[] {
      const entries: any[] = []
      for (const [slug, rows] of Object.entries(this.disclosureRaw)) {
        const sorted = [...rows].sort((a, b) => a.period_year - b.period_year)
        sorted.forEach((row, i) => {
          let yoy: number | null = null
          if (i > 0 && row.data_type === 'numeric' && row.numeric_value != null && sorted[i - 1].numeric_value != null) {
            const prev = sorted[i - 1].numeric_value
            if (prev !== 0) yoy = ((row.numeric_value - prev) / Math.abs(prev)) * 100
          }
          entries.push({ slug, ...row, yoy })
        })
      }
      return entries.sort((a, b) => a.slug.localeCompare(b.slug) || a.period_year - b.period_year)
    },
    disclosureGroups(): Array<{ prefix: string; label: string; entries: any[] }> {
      const groups: Record<string, any[]> = {}
      for (const entry of this.disclosureEntries) {
        const prefix = entry.slug.split('.')[0]
        if (!groups[prefix]) groups[prefix] = []
        groups[prefix].push(entry)
      }
      return Object.entries(groups).map(([prefix, entries]) => ({
        prefix,
        label: DISCLOSURE_PREFIX_LABELS[prefix] ?? prefix,
        entries,
      }))
    },
    sustainSummary(): { passed: number; reviewing: number; sent: number; pending: number; latest: any | null } {
      const qs = this.questionnaires
      return {
        passed:    qs.filter(q => q.status === 'completed' || q.status === 'reviewed').length,
        reviewing: qs.filter(q => q.status === 'under_review').length,
        sent:      qs.filter(q => q.status === 'sent' || q.status === 'not_started').length,
        pending:   qs.filter(q => q.status === 'submitted' || q.status === 'in_progress' || q.status === 'review_returned').length,
        latest:    qs.length ? qs[0] : null,
      }
    },
    groupedSasb(): Record<string, SasbIndustry[]> {
      return this.sasbOptions.reduce((acc, ind) => {
        if (!acc[ind.sector]) acc[ind.sector] = []
        acc[ind.sector].push(ind)
        return acc
      }, {} as Record<string, SasbIndustry[]>)
    },
    // 廢棄：pairedRows 已被 groupedTimeline 取代，保留供參考
    pairedRows(): Array<{ saq: any | null; ra: any | null }> { return [] },

    groupedTimeline(): Array<{ year: number; events: any[] }> {
      if (!this.timeline) return []
      // RA 為主，SAQ-only（無 linked_ra）作次要事件
      const raLinkedSaqIds = new Set(
        this.timeline.events
          .filter((e: any) => e.type === 'risk_assessment' && e.risk?.source_saq_id)
          .map((e: any) => e.risk.source_saq_id)
      )
      const mainEvents = this.timeline.events.filter((e: any) =>
        e.type === 'risk_assessment' ||
        (e.type === 'saq_scored' && !raLinkedSaqIds.has(e.saq?.id))
      )
      const sorted = [...mainEvents].sort((a, b) => (b.date ?? '').localeCompare(a.date ?? ''))
      const groups: Record<number, any[]> = {}
      for (const ev of sorted) {
        const yr = ev.year ?? new Date(ev.date).getFullYear()
        if (!groups[yr]) groups[yr] = []
        groups[yr].push(ev)
      }
      return Object.entries(groups)
        .sort(([a], [b]) => Number(b) - Number(a))
        .map(([year, events]) => ({ year: Number(year), events }))
    },

    trendRows(): Array<{ year: number; ra: any; delta: Record<string, number | null> }> {
      if (!this.timeline) return []
      // 每年取 assessed_at 最大的 RA
      const byYear: Record<number, any> = {}
      for (const ev of this.timeline.events) {
        if (ev.type !== 'risk_assessment') continue
        const yr = ev.year ?? new Date(ev.date).getFullYear()
        if (!byYear[yr] || ev.date > byYear[yr].date) byYear[yr] = ev
      }
      const rows = Object.entries(byYear)
        .sort(([a], [b]) => Number(b) - Number(a))
        .map(([year, ra]) => ({ year: Number(year), ra, delta: {} as Record<string, number | null> }))
      // 計算 delta（與下一行比較，基於 six_dims）
      for (let i = 0; i < rows.length - 1; i++) {
        const cur = rows[i].ra; const prev = rows[i + 1].ra
        for (const dim of ['E1', 'E2', 'E3', 'E4', 'E5', 'E6']) {
          const c = cur.risk?.six_dims?.[dim]; const p = prev.risk?.six_dims?.[dim]
          rows[i].delta[dim] = (c != null && p != null) ? c - p : null
        }
      }
      return rows
    },
    radarChartData(): any {
      const FONT = "'Noto Sans TC', system-ui, sans-serif"
      return {
        labels: this.radarDims.map(d => d.key),
        datasets: [{
          data: this.radarDimScores.map(s => s ?? 0),
          backgroundColor: 'rgba(26,77,62,0.10)',
          borderColor: '#1a4d3e',
          borderWidth: 2,
          pointBackgroundColor: this.radarDimScores.map(s => this.radarDotColor(s)),
          pointBorderColor: '#fff',
          pointBorderWidth: 1.5,
          pointRadius: 5,
          pointHoverRadius: 6,
        }]
      }
    },
    radarChartOptions(): any {
      const FONT = "'Noto Sans TC', system-ui, sans-serif"
      return {
        responsive: true,
        maintainAspectRatio: true,
        animation: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (ctx: any) => {
                const dim = this.radarDims[ctx.dataIndex]
                const score = this.radarDimScores[ctx.dataIndex]
                return ` ${dim?.label ?? ''}: ${score?.toFixed(1) ?? '—'}`
              },
            },
          },
        },
        scales: {
          r: {
            min: 0, max: 100,
            ticks: { stepSize: 20, display: false, backdropColor: 'transparent' },
            grid: { color: 'rgba(0,0,0,0.07)' },
            angleLines: { color: 'rgba(0,0,0,0.07)' },
            pointLabels: {
              font: { family: FONT, size: 12, weight: '700' },
              color: '#3a3530',
            },
          },
        },
      }
    },
    deltaChartData(): any {
      if (this.trendRows.length < 2) return { labels: [], datasets: [] }
      const dims = ['E1', 'E2', 'E3', 'E4', 'E5']
      const dimLabels: Record<string, string> = { E1: 'ESG整體', E2: '採購永續', E3: '社會責任', E4: '地緣風險', E5: '供應鏈安全' }
      const deltas = dims.map(d => this.trendRows[0].delta?.[d] ?? null)
      return {
        labels: dims.map(k => `${k}  ${dimLabels[k]}`),
        datasets: [{
          data: deltas,
          backgroundColor: deltas.map((v: number | null) => (v ?? 0) >= 0 ? '#2d7a5f' : '#b83232'),
          borderRadius: 4,
          borderSkipped: false,
        }],
      }
    },
    deltaChartOptions(): any {
      const FONT = "'Noto Sans TC', system-ui, sans-serif"
      return {
        indexAxis: 'y' as const,
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        font: { family: FONT },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (ctx: any) => {
                const v = ctx.raw as number
                return ` ${v > 0 ? '+' : ''}${v}`
              },
            },
          },
        },
        scales: {
          x: {
            min: -40, max: 40,
            grid: { color: '#e8e4dc' },
            border: { display: false },
            ticks: {
              stepSize: 20,
              font: { family: FONT, size: 10 },
              color: '#b0aca4',
              callback: (v: number) => (v > 0 ? '+' : '') + v,
            },
          },
          y: {
            grid: { display: false },
            border: { display: false },
            ticks: {
              font: { family: FONT, size: 12, weight: '700' },
              color: '#3a3530',
            },
          },
        },
      }
    },
    barChartData(): any {
      const dims = ['E1', 'E2', 'E3', 'E4', 'E5']
      const dimLabels = { E1: 'ESG整體', E2: '採購永續', E3: '社會責任', E4: '地緣風險', E5: '供應鏈安全' } as Record<string, string>
      const labels = dims.map(k => [k, dimLabels[k]])
      const years = [...this.trendRows].reverse() // 舊→新
      const colors = ['#4a86b0', '#1a4d3e', '#7eb8d4', '#3d7a60']
      return {
        labels,
        datasets: years.map((row, i) => ({
          label: String(row.year),
          data: dims.map(d => row.ra?.risk?.six_dims?.[d] ?? null),
          backgroundColor: colors[i] ?? '#999',
          borderRadius: 4,
          borderSkipped: false,
          barPercentage: 0.7,
          categoryPercentage: 0.65,
        })),
      }
    },
    barChartOptions(): any {
      return {
        responsive: true,
        maintainAspectRatio: true,
        aspectRatio: 2.8,
        animation: false,
        font: { family: "'Noto Sans TC', system-ui, sans-serif" },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              title: (items: any[]) => items[0]?.label?.replace(',', ' '),
            },
          },
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: {
              font: { family: "'Noto Sans TC', system-ui, sans-serif", size: 11, weight: '600' },
              color: '#4a4540',
              callback(_: any, index: number) {
                const raw = (this as any).getLabelForValue(index)
                return Array.isArray(raw) ? raw : [raw]
              },
            },
          },
          y: {
            min: 0, max: 100,
            grid: { color: '#e8e4dc' },
            border: { display: false },
            ticks: {
              stepSize: 25,
              font: { family: "'Noto Sans TC', system-ui, sans-serif", size: 9 },
              color: '#b0aca4',
            },
          },
        },
      }
    },
    matrixPoints(): Array<{ cx: number; cy: number; r: number; fill: string; year: number }> {
      // 從最舊到最新，用 trendRows（已按年降序），需反轉
      const rows = [...this.trendRows].reverse()
      return rows
        .filter(r => r.ra.risk?.axis1_score != null && r.ra.risk?.axis2_score != null)
        .map(r => {
          const a1 = r.ra.risk.axis1_score   // x：高=右
          const a2 = r.ra.risk.axis2_score   // y：高=上
          const a3 = r.ra.risk.axis3_score ?? 50
          // SVG 座標：x[40,300], y[10,230]（y反轉）
          const cx = 40 + (a1 / 100) * 260
          const cy = 230 - (a2 / 100) * 220
          // 泡泡半徑 8~14
          const r2 = 7 + (a3 / 100) * 7
          // 顏色按軸3風險
          const fill = a3 >= 70 ? '#ef4444' : a3 >= 50 ? '#f59e0b' : a3 >= 30 ? '#84cc16' : '#22c55e'
          return { cx: Math.round(cx * 10) / 10, cy: Math.round(cy * 10) / 10, r: r2, fill, year: r.year }
        })
    },
    // ── Radar Chart ──
    radarDims(): Array<{ key: string; label: string; field: string }> {
      return [
        { key: 'E1', label: 'ESG整體',   field: 'dim_e1' },
        { key: 'E2', label: '永續採購',   field: 'dim_e2' },
        { key: 'E3', label: '社會責任',   field: 'dim_e3' },
        { key: 'E4', label: '地緣政治',   field: 'dim_e4' },
        { key: 'E5', label: '供應鏈安全', field: 'dim_e5' },
        { key: 'E6', label: '產品合規',   field: 'dim_e6' },
      ]
    },
    radarDimKeys(): string[] { return this.radarDims.map(d => d.key) },
    radarDimScores(): (number | null)[] {
      if (!this.riskSummary) return Array(6).fill(null)
      const sixDims = this.riskSummary.six_dims ?? {}
      return this.radarDims.map(d => sixDims[d.key] ?? null)
    },
    radarAxisEndpoints(): Array<{ x: number; y: number }> {
      // 6 axes, starting from top (E1), going clockwise
      const cx = 130, cy = 115, r = 90
      return Array.from({ length: 6 }, (_, i) => {
        const angle = (Math.PI * 2 * i) / 6 - Math.PI / 2
        return { x: cx + r * Math.cos(angle), y: cy + r * Math.sin(angle) }
      })
    },
    radarDataCoords(): Array<{ x: number; y: number }> {
      const cx = 130, cy = 115, r = 90
      return this.radarDimScores.map((score, i) => {
        const angle = (Math.PI * 2 * i) / 6 - Math.PI / 2
        const ratio = (score ?? 0) / 100
        return { x: cx + r * ratio * Math.cos(angle), y: cy + r * ratio * Math.sin(angle) }
      })
    },
    radarDataPoints(): string {
      return this.radarDataCoords.map(p => `${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' ')
    },
    radarLabelPositions(): Array<{ x: number; y: number; lx: number; ly: number }> {
      const cx = 130, cy = 115, r = 105, sr = 118
      return Array.from({ length: 6 }, (_, i) => {
        const angle = (Math.PI * 2 * i) / 6 - Math.PI / 2
        return {
          x: cx + r * Math.cos(angle),
          y: cy + r * Math.sin(angle),
          lx: cx + sr * Math.cos(angle),
          ly: cy + sr * Math.sin(angle) + 11,
        }
      })
    },
    allowedNextStages(): Array<{ value: string; label: string }> {
      const TRANSITIONS: Record<string, string[]> = {
        active:     ['suspended'],
        suspended:  ['active', 'terminated'],
        terminated: [],
      }
      const LABELS: Record<string, string> = {
        active: '啟用', suspended: '暫停', terminated: '終止',
      }
      const current = this.supplier?.onboarding_stage ?? ''
      return (TRANSITIONS[current] ?? []).map(s => ({ value: s, label: LABELS[s] ?? s }))
    },
  },

  mounted() {
    this.loadData()
    window.addEventListener('resize', this._onTrendResize = () => {
      if (this.trendChartTab !== 'delta') this.drawTrendChart()
    })
  },
  beforeUnmount() { window.removeEventListener('resize', (this as any)._onTrendResize) },

  methods: {
    maskSupplierName,
    setTrendTab(tab: 'bar' | 'delta' | 'trend') {
      this.trendChartTab = tab
      if (tab !== 'delta') {
        this.$nextTick(() => this.drawTrendChart())
      }
    },
    drawTrendChart() {
      const canvas = this.$refs.trendCanvas as HTMLCanvasElement | undefined
      if (!canvas) return
      const rows = [...this.trendRows].reverse() // oldest first
      if (!rows.length) return
      // 若 layout 尚未完成，延遲一幀後再試
      const rawW = canvas.parentElement?.offsetWidth ?? 0
      if (rawW === 0) { requestAnimationFrame(() => this.drawTrendChart()); return }
      const dpr = window.devicePixelRatio || 1
      const W = rawW
      const H = 220
      canvas.width = W * dpr; canvas.height = H * dpr
      canvas.style.width = W + 'px'; canvas.style.height = H + 'px'
      const ctx = canvas.getContext('2d')!
      ctx.scale(dpr, dpr)
      const s = getComputedStyle(document.documentElement)
      const cv = (n: string) => s.getPropertyValue(n).trim()
      const padL = 34, padR = 18, padT = 18, padB = 38
      const chartW = W - padL - padR, chartH = H - padT - padB
      const dims = ['E1','E2','E3','E4','E5']
      const yearColors = ['#2d6a8f', '#1a4d3e', '#6b3a8f', '#8b4513', '#8f6b1a']
      const goodColor = '#2d7a5f', warnColor = '#c47d0a', riskColor = '#b83232'
      const dimColor = (v: number) => v >= 70 ? goodColor : v >= 40 ? warnColor : riskColor

      // Grid lines
      ctx.strokeStyle = cv('--border') || '#e0dcd4'
      ctx.lineWidth = 1
      ;[0, 25, 50, 75, 100].forEach(v => {
        const y = padT + chartH - (v / 100) * chartH
        ctx.beginPath(); ctx.moveTo(padL, y); ctx.lineTo(padL + chartW, y); ctx.stroke()
        ctx.fillStyle = cv('--text-muted') || '#9e9890'
        ctx.font = '10px -apple-system,system-ui,sans-serif'
        ctx.textAlign = 'right'
        ctx.fillText(String(v), padL - 5, y + 3.5)
      })

      if (this.trendChartTab === 'bar') {
        // Grouped bar chart
        const years = rows.map(r => r.year)
        const groupW = chartW / dims.length
        const barW = Math.min(22, groupW * 0.28)
        const gap = 3
        const totalBarW = years.length * barW + (years.length - 1) * gap
        dims.forEach((dim, di) => {
          const cx = padL + groupW * di + groupW / 2
          years.forEach((yr, yi) => {
            const v = rows[yi]?.ra?.risk?.six_dims?.[dim]
            if (v == null) return
            const barH = (v / 100) * chartH
            const x = cx - totalBarW / 2 + yi * (barW + gap)
            const y = padT + chartH - barH
            ctx.fillStyle = yearColors[yi] + (yi === 0 && years.length > 1 ? 'bb' : '')
            const r = 2
            ctx.beginPath()
            ctx.moveTo(x + r, y); ctx.lineTo(x + barW - r, y)
            ctx.arcTo(x + barW, y, x + barW, y + r, r)
            ctx.lineTo(x + barW, y + barH); ctx.lineTo(x, y + barH)
            ctx.arcTo(x, y, x + r, y, r); ctx.closePath(); ctx.fill()
            ctx.fillStyle = cv('--text-primary') || '#1c1917'
            ctx.font = '600 9px -apple-system,system-ui,sans-serif'
            ctx.textAlign = 'center'
            ctx.fillText(String(v), x + barW / 2, y - 3)
          })
          // Dim label
          ctx.fillStyle = cv('--text-secondary') || '#6b6560'
          ctx.font = '600 11px -apple-system,system-ui,sans-serif'
          ctx.textAlign = 'center'
          ctx.fillText(dim, cx, padT + chartH + 14)
          ctx.fillStyle = cv('--text-muted') || '#9e9890'
          ctx.font = '10px -apple-system,system-ui,sans-serif'
          const shortLabels: Record<string, string> = { E1:'ESG整體', E2:'採購永續', E3:'社會責任', E4:'地緣風險', E5:'供應鏈安全' }
          ctx.fillText(shortLabels[dim] || '', cx, padT + chartH + 26)
        })
      } else {
        // Trend line chart
        const years = rows.map(r => r.year)
        const xOf = (i: number) => padL + (rows.length === 1 ? chartW / 2 : (i / (rows.length - 1)) * chartW)
        years.forEach((yr, i) => {
          ctx.fillStyle = cv('--text-secondary') || '#6b6560'
          ctx.font = '600 11px -apple-system,system-ui,sans-serif'
          ctx.textAlign = 'center'
          ctx.fillText(String(yr), xOf(i), padT + chartH + 18)
        })
        const dimColors5 = ['#2d6a8f','#1a4d3e','#8b4513','#6b3a8f','#8f6b1a']
        dims.forEach((dim, di) => {
          const pts = rows.map((r, i) => ({
            x: xOf(i),
            y: padT + chartH - ((r.ra?.risk?.six_dims?.[dim] ?? 0) / 100) * chartH,
            v: r.ra?.risk?.six_dims?.[dim]
          })).filter(p => p.v != null)
          if (!pts.length) return
          const color = dimColors5[di]
          ctx.strokeStyle = color; ctx.lineWidth = 2; ctx.setLineDash([])
          ctx.beginPath(); pts.forEach((p, i) => i === 0 ? ctx.moveTo(p.x, p.y) : ctx.lineTo(p.x, p.y)); ctx.stroke()
          pts.forEach(p => {
            ctx.fillStyle = cv('--surface') || '#fff'
            ctx.beginPath(); ctx.arc(p.x, p.y, 4, 0, Math.PI * 2); ctx.fill()
            ctx.fillStyle = color
            ctx.beginPath(); ctx.arc(p.x, p.y, 3, 0, Math.PI * 2); ctx.fill()
          })
          const last = pts[pts.length - 1]
          ctx.fillStyle = color
          ctx.font = '600 10px -apple-system,system-ui,sans-serif'
          ctx.textAlign = 'left'
          ctx.fillText(`${dim} ${last.v}`, last.x + 6, last.y + 3.5)
        })
      }
    },
    switchTab(key: string) {
      this.activeInfoTab = key
      if (!this.loadedTabs.has(key)) {
        this.loadedTabs.add(key)
        if (key === 'sustain') this.loadDisclosureProfile()
      }
    },

    async loadData() {
      this.isLoading = true
      const id = this.route.params.id as string
      try {
        const [sRes, rRes, qRes, docRes, bomRes] = await Promise.allSettled([
          suppliersApi.get(id),
          suppliersApi.riskSummary(id),
          questionnaireApi.list({ supplier_id: id, per_page: 10 }),
          suppliersApi.complianceDocs(id),
          supplierBomRequirementsApi.get(id),
        ])
        if (sRes.status === 'fulfilled') this.supplier = sRes.value.data.data
        if (rRes.status === 'fulfilled') this.riskSummary = (rRes.value.data as any).data
        if (qRes.status === 'fulfilled') this.questionnaires = qRes.value.data.data
        if (docRes.status === 'fulfilled') this.complianceDocs = (docRes.value.data as any).data ?? []
        if (bomRes.status === 'fulfilled') this.bomRequirements = (bomRes.value.data as any).data ?? []
        this.loadRiskHistory(id)
        this.loadFacilities()
      } finally { this.isLoading = false }
    },

    async loadRiskHistory(supplierId: string) {
      this.timelineLoading = true
      try {
        const res = await suppliersApi.riskTimeline(supplierId)
        this.timeline = (res.data as any).data ?? { events: [], pending_saq: null }
        setTimeout(() => this.drawTrendChart(), 100)
      } catch { this.timeline = { events: [], pending_saq: null } } finally { this.timelineLoading = false }
    },

    // ── Edit mode ──
    async enterEditMode() {
      if (!this.supplier) return
      this.editForm = {
        industry_group: this.supplier.industry_group ?? '',
        sasb_industry_id: this.supplier.sasb_industry_id,
        group_id: this.supplier.group_id,
      }
      this.isEditing = true
      if (!this.auxLoaded) {
        this.auxLoading = true
        try {
          const [sasbRes, groupsRes] = await Promise.all([
            settingsApi.sasb.list(),
            settingsApi.groups.list(),
          ])
          this.sasbOptions = sasbRes.data.data
          this.groupOptions = groupsRes.data.data
          this.auxLoaded = true
        } finally { this.auxLoading = false }
      }
    },

    cancelEdit() {
      this.isEditing = false
    },

    async saveEdit() {
      if (!this.supplier) return
      this.isSubmitting = true
      try {
        await suppliersApi.update(this.supplier.id, {
          industry_group: this.editForm.industry_group || null,
          sasb_industry_id: this.editForm.sasb_industry_id || null,
          group_id: this.editForm.group_id || null,
        })
        this.isEditing = false
        await this.loadData()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '儲存失敗，請稍後再試')
      } finally { this.isSubmitting = false }
    },

    // ── 廠區管理 ──
    async loadFacilities() {
      if (!this.supplier) return
      this.facilitiesLoading = true
      try {
        const { data } = await facilityApi.list(this.supplier.id)
        this.facilities = data.data
      } catch { /* silent */ }
      finally { this.facilitiesLoading = false }
    },
    openAddFacilityModal() {
      this.facilityForm = { id: '', name: '', facility_type: 'manufacturing', country: '', address: '', is_active: true }
      this.showFacilityModal = true
    },
    openEditFacilityModal(f: SupplierFacility) {
      this.facilityForm = {
        id: f.id,
        name: f.name,
        facility_type: f.facility_type,
        country: f.country ?? '',
        address: f.address ?? '',
        is_active: f.is_active,
      }
      this.showFacilityModal = true
    },
    async saveFacility() {
      if (!this.supplier || !this.facilityForm.name.trim()) return
      this.facilitySaving = true
      try {
        const payload = {
          name: this.facilityForm.name,
          facility_type: this.facilityForm.facility_type,
          country: this.facilityForm.country || null,
          address: this.facilityForm.address || null,
          is_active: this.facilityForm.is_active,
        }
        if (this.facilityForm.id) {
          await facilityApi.update(this.supplier.id, this.facilityForm.id, payload)
        } else {
          await facilityApi.create(this.supplier.id, payload)
        }
        this.showFacilityModal = false
        await this.loadFacilities()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '儲存失敗')
      } finally { this.facilitySaving = false }
    },

    // ── 聯絡人管理 ──
    openAddContactModal() {
      this.contactForm = { name: '', title: '', email: '', phone: '', is_primary: false }
      this.showContactModal = true
    },
    async saveContact() {
      if (!this.supplier || !this.contactForm.name.trim()) return
      this.contactSaving = true
      try {
        await suppliersApi.addContact(this.supplier.id, this.contactForm)
        this.showContactModal = false
        await this.loadData()
      } catch (e: any) {
        alert(e?.response?.data?.message ?? '新增失敗')
      } finally { this.contactSaving = false }
    },
    async deleteContact(c: any) {
      if (!this.supplier || !confirm(`確認刪除聯絡人「${c.name}」？`)) return
      try {
        await suppliersApi.deleteContact(this.supplier.id, c.id)
        await this.loadData()
      } catch {
        alert('刪除失敗')
      }
    },

    // ── 合規文件 ──
    isExpired(date: string | null) {
      if (!date) return false
      return new Date(date) < new Date()
    },
    isExpiringSoon(date: string | null) {
      if (!date) return false
      const d = new Date(date)
      const now = new Date()
      const days30 = new Date(now.getTime() + 30 * 86400000)
      return d >= now && d <= days30
    },

    // ── 入選階段流轉 ──
    openTransitionModal() {
      const allowed = this.allowedNextStages
      this.transitionForm.onboarding_stage = allowed.length > 0 ? allowed[0].value : ''
      this.transitionForm.reason = ''
      this.showTransitionModal = true
    },
    async doTransition() {
      if (!this.supplier || !this.transitionForm.onboarding_stage) return
      this.isSubmitting = true
      try {
        await suppliersApi.transitionOnboarding(this.supplier.id, this.transitionForm.onboarding_stage, this.transitionForm.reason)
        this.showTransitionModal = false
        await this.loadData()
      } finally { this.isSubmitting = false }
    },
    onboardingStageLabel(stage: string | null | undefined): string {
      const map: Record<string, string> = {
        active: '啟用', suspended: '暫停', terminated: '終止',
      }
      return stage ? (map[stage] ?? stage) : '—'
    },

    // ── 格式化 ──
    formatDate: (s: string) => s ? new Date(s).toLocaleDateString('zh-TW') : '—',
    formatDateTime: (s: string) => s ? new Date(s).toLocaleString('zh-TW', { dateStyle: 'short', timeStyle: 'short' }) : '—',
    statusLabel: (s: string) => STATUS_LABELS[s] ?? s,
    statusBadgeClass: (s: string) => ({ active: 'badge-green', suspended: 'badge-yellow', terminated: 'badge-red' }[s] ?? 'badge-gray'),
    erpStatusLabel: (s: string | null) => (s == null ? '—' : ERP_STATUS_LABELS[s] ?? s),
    erpStatusBadgeClass: (s: string | null) => (s == null ? 'badge-gray' : ERP_STATUS_BADGE_CLASS[s] ?? 'badge-gray'),
    // 狀態歷程時間軸：erp_status 型別用 生效/停用 語彙，onboarding 型別維持既有 啟用/暫停/終止 語彙
    historyLabel(h: { type?: string }, status: string | null) {
      return h.type === 'erp_status' ? this.erpStatusLabel(status) : this.statusLabel(status as string)
    },
    historyBadgeClass(h: { type?: string }, status: string | null) {
      return h.type === 'erp_status' ? this.erpStatusBadgeClass(status) : this.statusBadgeClass(status as string)
    },
    dimLabel: (d: string) => DIM_LABELS[d] ?? d,
    levelBadgeClass: (l?: string) => ({ very_low: 'badge-green', low: 'badge-green', medium: 'badge-yellow', high: 'badge-red', extreme: 'badge-red' }[l ?? ''] ?? 'badge-gray'),
    qStatusLabel: (s: string) => Q_STATUS_LABELS[s] ?? s,
    qBadgeClass: (s: string) => ({ completed: 'badge-green', under_review: 'badge-purple', submitted: 'badge-blue', review_returned: 'badge-yellow', reviewed: 'badge-green', not_started: 'badge-gray', in_progress: 'badge-yellow' }[s] ?? 'badge-gray'),
    pcfStatusLabel: (s: string) => ({ none: '未申報', pending: '待填寫', submitted: '已提交', verified: '已驗證' }[s] ?? s),
    pcfStatusBadge: (s: string) => ({ none: 'badge-gray', pending: 'badge-yellow', submitted: 'badge-blue', verified: 'badge-green' }[s] ?? 'badge-gray'),
    complyStatusLabel: (s: string) => ({ valid: '有效', expired: '已逾期', expiring_soon: '即將到期', missing: '缺少' }[s] ?? s),
    complyStatusClass: (s: string) => ({ valid: 'comply-valid', expired: 'comply-expired', expiring_soon: 'comply-expiring', missing: 'comply-missing' }[s] ?? 'comply-missing'),

    axisBarFillClass(axisKey: string, level: string | null): string {
      // axis3 是風險分數（高分=高風險），用紅色系；axis1/axis2 是成熟度（高分=低風險），用綠色系
      if (!level) return 'axis-fill-empty'
      if (axisKey === 'axis3') {
        const map: Record<string, string> = { extreme: 'axis-fill-danger', high: 'axis-fill-warn', medium: 'axis-fill-neutral', low: 'axis-fill-good', very_low: 'axis-fill-good' }
        return map[level] ?? 'axis-fill-neutral'
      }
      const map: Record<string, string> = { very_low: 'axis-fill-danger', low: 'axis-fill-warn', medium: 'axis-fill-neutral', high: 'axis-fill-good', extreme: 'axis-fill-good' }
      return map[level] ?? 'axis-fill-neutral'
    },
    axisChipClass(level: string | null): string {
      return level ? (AXIS_CHIP_CLASS[level] ?? '') : ''
    },
    axisScoreToLevel(axisKey: string, score: number): string {
      if (axisKey === 'axis3') {
        if (score >= 70) return 'high'
        if (score >= 50) return 'medium'
        if (score >= 30) return 'low'
        return 'very_low'
      }
      if (score >= 80) return 'very_low'
      if (score >= 65) return 'low'
      if (score >= 45) return 'medium'
      return 'high'
    },

    async saveAxis3() {
      if (this.axis3Input == null || this.axis3Input < 0 || this.axis3Input > 100) return
      this.savingAxis3 = true
      try {
        await api.post(`/suppliers/${this.supplier.id}/risk-axis3`, { score: this.axis3Input })
        const res = await suppliersApi.riskSummary(this.supplier.id)
        this.riskSummary = (res.data as any).data
        this.axis3Input = null
      } catch {
        alert('儲存失敗，請稍後再試')
      } finally {
        this.savingAxis3 = false
      }
    },

    levelAbbr(level: string): string {
      return { very_low: 'VL', low: 'L', medium: 'M', high: 'H', extreme: 'EXT' }[level] ?? level
    },

    sixDimLevel(score: number): string {
      if (score >= 80) return 'very_low'
      if (score >= 60) return 'low'
      if (score >= 40) return 'medium'
      if (score >= 20) return 'high'
      return 'extreme'
    },
    trendCellClass(score: number | null | undefined): string {
      if (score == null) return ''
      if (score >= 70) return 'trend-cell-ok'
      if (score >= 40) return 'trend-cell-warn'
      return 'trend-cell-danger'
    },
    radarDotColor(score: number | null): string {
      if (score == null) return '#ccc'
      if (score >= 70) return '#48bb78'
      if (score >= 40) return '#ed8936'
      return '#e53e3e'
    },
    hasSixDims(sixDims: Record<string, number | null> | undefined): boolean {
      if (!sixDims) return false
      return Object.values(sixDims).some(v => v != null)
    },
    radarGridPoints(pct: number): string {
      const cx = 130, cy = 115, r = 90 * pct / 100
      return Array.from({ length: 6 }, (_, i) => {
        const angle = (Math.PI * 2 * i) / 6 - Math.PI / 2
        return `${(cx + r * Math.cos(angle)).toFixed(1)},${(cy + r * Math.sin(angle)).toFixed(1)}`
      }).join(' ')
    },

    riskLevelClass(score: number): string {
      if (score >= 20) return 'risk-extreme'
      if (score >= 12) return 'risk-high'
      if (score >= 6)  return 'risk-medium'
      if (score >= 3)  return 'risk-low'
      return 'risk-very-low'
    },
    riskDelta(idx: number, dim: string): number | null {
      const list = this.riskHistory
      if (idx >= list.length - 1) return null
      const pKey = `${dim}_probability`, iKey = `${dim}_impact`
      const curr = list[idx][pKey] * list[idx][iKey]
      const prev = list[idx + 1][pKey] * list[idx + 1][iKey]
      const diff = curr - prev
      return diff === 0 ? null : diff
    },

    async loadDisclosureProfile() {
      if (!this.supplier) return
      this.disclosureLoading = true
      try {
        const res = await api.get(`/api/v1/suppliers/${this.supplier.id}/disclosure-profile`)
        this.disclosureRaw = res.data?.data?.disclosures ?? {}
      } catch {
        this.disclosureRaw = {}
      } finally {
        this.disclosureLoading = false
      }
    },
  },
})
</script>

<style scoped>
/* ── 佈局 ── */
.detail-layout { display: flex; flex-direction: column; gap: 0; }
.detail-main { display: flex; flex-direction: column; gap: 0; }

/* Tabs / Section / Detail Grid 樣式已移至 components.css 共用（.detail-tabs/.detail-tab/
   .tab-panel-wrap/.detail-section/.section-title/.detail-grid/.detail-item/.detail-label/
   .detail-value/.detail-link），供應商/物料/銷售產品三個詳情頁統一套用同一套，不再各自維護 */

/* 頁頭 meta chips */
.supplier-meta-chips { display: flex; align-items: center; gap: 6px; margin-top: 5px; flex-wrap: wrap; }
.meta-chip {
  font-size: 11.5px;
  font-weight: 500;
  color: var(--text-secondary);
  background: #f0ede6;
  border: 1px solid #e4e0d9;
  border-radius: 5px;
  padding: 2px 8px;
  line-height: 1.6;
}
.meta-chip-mono { font-family: var(--font-mono); }

.empty-inline { font-size: 13px; color: var(--text-secondary); padding: 8px 0; }

/* ── 聯絡人 ── */
.contact-list { display: flex; flex-direction: column; gap: 8px; }
.contact-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 10px 12px; background: var(--surface-2); border-radius: 6px;
}
.contact-info { display: flex; flex-direction: column; gap: 2px; }
.contact-name { font-size: 14px; font-weight: 500; }
.contact-meta { font-size: 12px; color: var(--text-secondary); }
.contact-primary { font-size: 11px; }

/* ── 風險評估 ── */
.risk-row { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; }
.risk-dim-label { font-size: 13px; font-weight: 500; min-width: 90px; }
.risk-bar-wrap { flex: 1; height: 8px; background: var(--surface-2); border-radius: 4px; overflow: hidden; }
.risk-bar { height: 100%; border-radius: 4px; transition: width 0.4s; }
.risk-bar.very_low { background: var(--risk-very-low-color); }
.risk-bar.low     { background: var(--risk-low-color); }
.risk-bar.medium  { background: var(--risk-medium-color); }
.risk-bar.high    { background: var(--risk-high-color); }
.risk-bar.extreme { background: var(--risk-extreme-color); }

/* ── Timeline ── */
.timeline { position: relative; padding-left: 24px; }
.timeline::before {
  content: ''; position: absolute; left: 7px; top: 8px; bottom: 8px;
  width: 2px; background: var(--border);
}

.timeline-item {
  position: relative;
  display: flex; gap: 14px;
  padding-bottom: 20px;
}
.timeline-item:last-child { padding-bottom: 0; }

.timeline-dot {
  position: absolute; left: -21px; top: 4px;
  width: 12px; height: 12px; border-radius: 50%;
  border: 2px solid var(--surface); flex-shrink: 0;
}
.timeline-dot.badge-green { background: #22c55e; }
.timeline-dot.badge-gray  { background: #94a3b8; }
.timeline-dot.badge-red   { background: #ef4444; }

.timeline-content { flex: 1; }
.timeline-title { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.timeline-arrow { color: var(--text-secondary); font-size: 13px; }
.timeline-reason { font-size: 13px; color: var(--text-secondary); margin-top: 4px; }
.timeline-time { font-size: 11px; color: var(--text-secondary); margin-top: 4px; }

/* ── Misc ── */
.badge-yellow { background: #fef9c3; color: #854d0e; }
.badge-blue   { background: #dbeafe; color: #1e40af; }

/* ── 聯絡人操作 ── */
.section-subtitle-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.contact-actions { display: flex; align-items: center; gap: 6px; }
.icon-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 24px; height: 24px; border: none; background: none; cursor: pointer;
  color: var(--text-secondary); border-radius: 4px; font-size: 13px;
  transition: background 0.15s, color 0.15s;
}
.icon-btn:hover { background: #fee2e2; color: #dc2626; }
.btn-xs { padding: 3px 10px; font-size: 12px; }

/* ── 合規文件 ── */
.doc-type-badge { font-size: 11px; font-weight: 600; background: #f0f4ff; color: #3730a3; border: 1px solid #c7d2fe; padding: 2px 7px; border-radius: 4px; font-family: 'Fira Code', monospace; letter-spacing: 0.02em; }

/* 供應材料清單 table 欄位 */
.product-tag { display: inline-block; font-size: 13px; font-weight: 400; color: var(--text-secondary); white-space: nowrap; }
.bom-material-name { font-size: 13px; font-weight: 400; color: var(--text-primary); }
.bom-hs-code { font-size: 12.5px; color: var(--text-primary); letter-spacing: 0.03em; }
.bom-group { font-size: 13px; color: var(--text-secondary); }
.doc-expired td { opacity: 0.65; }
.comply-status-row { display: flex; align-items: center; gap: 5px; margin-bottom: 3px; }
.comply-doc-type { font-size: 10.5px; color: var(--text-secondary); font-family: monospace; flex-shrink: 0; }
.comply-status-chip { font-size: 10.5px; font-weight: 600; border-radius: 3px; padding: 1px 5px; white-space: nowrap; }
.comply-valid    { background: #dcfce7; color: #15803d; }
.comply-expired  { background: #fee2e2; color: #b91c1c; }
.comply-expiring { background: #fef9c3; color: #a16207; }
.comply-missing  { background: #f1f5f9; color: #64748b; }
.comply-expires  { font-size: 10px; color: var(--text-secondary); }
.file-link { font-size: 12px; color: var(--accent); text-decoration: none; display: flex; align-items: center; gap: 3px; white-space: nowrap; }
.file-link:hover { text-decoration: underline; }
@media (prefers-color-scheme: dark) {
  .comply-valid    { background: rgba(21,128,61,0.2); color: #4ade80; }
  .comply-expired  { background: rgba(185,28,28,0.2); color: #f87171; }
  .comply-expiring { background: rgba(161,98,7,0.2); color: #fbbf24; }
  .comply-missing  { background: rgba(100,116,139,0.15); color: #94a3b8; }
}
.text-danger { color: #dc2626 !important; }
.text-warn { color: #d97706 !important; }
.text-secondary { color: var(--text-secondary); }
.risk-cell-badge { display:inline-block; min-width:28px; padding:1px 6px; border-radius:4px; font-size:12px; font-weight:600; text-align:center; font-variant-numeric:tabular-nums; }
.risk-extreme  { background:#fee2e2; color:#991b1b; }
.risk-high     { background:#fed7aa; color:#9a3412; }
.risk-medium   { background:#fef9c3; color:#854d0e; }
.risk-low      { background:#dcfce7; color:#166534; }
.risk-very-low { background:#f1f5f9; color:#64748b; }
.delta-mark { font-size:11px; font-weight:600; margin-left:3px; }
.delta-up   { color:#dc2626; }
.delta-down { color:#16a34a; }
.badge-auto   { background:#ede9fe; color:#6d28d9; font-size:11px; }
.badge-manual { background:#f1f5f9; color:#64748b; font-size:11px; }

/* ── 時間軸事件流 ── */
.tl-list { display: flex; flex-direction: column; gap: 8px; }

/* 雙欄佈局 */
.tl-cols { display: flex; flex-direction: column; gap: 8px; }
.tl-col-header-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.tl-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  align-items: stretch;
}
.tl-row > .tl-card { height: 100%; box-sizing: border-box; }
.tl-cell-empty {
  background: var(--color-surface, #f9f8f6);
  border: 1px dashed var(--color-border, #e0ddd8);
  border-radius: 8px;
  min-height: 60px;
  opacity: 0.4;
}
.tl-col-header {
  font-size: 12px;
  font-weight: 600;
  color: var(--text-secondary);
  letter-spacing: 0.03em;
  padding: 4px 0 6px;
  border-bottom: 1px solid var(--border);
  margin-bottom: 2px;
  display: flex;
  align-items: center;
  gap: 5px;
}
.tl-col-icon { font-size: 13px; }
.tl-empty-col {
  font-size: 12px;
  color: var(--text-secondary);
  padding: 12px 0;
  opacity: 0.6;
}
.tl-card {
  border-radius: 10px;
  border: 1px solid var(--border);
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  background: #fff;
}
/* 左邊框顏色 */
.tl-risk_assessment { border-left: 3px solid #f97316; background: #fffcf9; }
.tl-saq_scored      { border-left: 3px solid #3b82f6; background: #f8faff; }
.tl-pending         { border: 1.5px dashed #d97706; border-radius: 10px; background: #fffbeb; }

/* 卡片頂部 header */
.tl-card-header { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; margin-bottom: 2px; }
.tl-date {
  font-size: 11.5px;
  color: var(--text-secondary);
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}
.tl-chip {
  font-size: 10.5px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 99px;
  letter-spacing: 0.02em;
}
.chip-auto   { background: #ede9fe; color: #5b21b6; }
.chip-manual { background: #f1f0ee; color: #57534e; }
.chip-saq    { background: #dbeafe; color: #1d4ed8; }
.tl-cap-badge {
  font-size: 10.5px; font-weight: 700;
  color: #991b1b; background: #fee2e2;
  padding: 2px 8px; border-radius: 99px;
}

/* 4 維度 bar */
.tl-dims-grid { display: flex; flex-direction: column; gap: 7px; margin-top: 4px; }
.tl-dim { display: grid; grid-template-columns: 26px 1fr 34px 52px; align-items: center; gap: 8px; }
.tl-dim-label {
  font-size: 10.5px;
  font-weight: 800;
  color: #b5ada4;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}
.tl-bar-wrap { height: 8px; background: #ede9e4; border-radius: 99px; overflow: hidden; }
.tl-bar { height: 100%; border-radius: 99px; transition: width 0.4s ease; }
.tl-bar.very_low { background: #4ade80; }
.tl-bar.low      { background: #4ade80; }
.tl-bar.medium   { background: #fbbf24; }
.tl-bar.high     { background: #f97316; }
.tl-bar.extreme  { background: #ef4444; }
.tl-dim-score { font-size: 12.5px; font-weight: 700; text-align: right; font-variant-numeric: tabular-nums; }
.tl-very_low { color: #15803d; }
.tl-low      { color: #15803d; }
.tl-medium   { color: #92400e; }
.tl-high     { color: #c2410c; }
.tl-extreme  { color: #991b1b; }
.tl-level-badge {
  font-size: 10px;
  padding: 1px 6px;
  border-radius: 4px;
  font-weight: 700;
  text-align: center;
}

/* Linked SAQ */
.tl-linked-saq {
  display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
  padding: 7px 10px;
  background: rgba(249,115,22,0.06);
  border-radius: 6px;
  border-left: 2px solid #f97316;
  font-size: 12.5px;
  margin-top: 2px;
}
.tl-linked-label { color: var(--text-secondary); font-size: 11.5px; }
.tl-linked-score { font-weight: 700; color: var(--text-primary); }
.tl-linked-sub { color: var(--text-secondary); font-family: var(--font-mono); font-size: 11.5px; }
.tl-linked-link { color: var(--accent); font-size: 11.5px; margin-left: auto; font-weight: 500; }

/* SAQ 事件卡 */
.tl-saq-body {
  display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-top: 4px;
}
.tl-grade-chip {
  font-size: 14px;
  font-weight: 800;
  padding: 3px 10px;
  border-radius: 6px;
  min-width: 32px;
  text-align: center;
}
.grade-a { background: #dcfce7; color: #14532d; }
.grade-b { background: #bbf7d0; color: #14532d; }
.grade-c { background: #fef9c3; color: #713f12; }
.grade-d { background: #fed7aa; color: #7c2d12; }
.grade-e { background: #fecaca; color: #7f1d1d; }
.tl-saq-score {
  font-size: 22px;
  font-weight: 800;
  color: var(--text-primary);
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.02em;
}
.tl-saq-dims { display: flex; gap: 10px; align-items: center; }
.tl-saq-dim {
  font-size: 12px;
  color: var(--text-secondary);
  font-family: var(--font-mono);
  background: #f5f3ee;
  padding: 2px 6px;
  border-radius: 4px;
}
.tl-saq-meta { font-size: 12px; color: var(--text-secondary); margin-top: 3px; }
.tl-card-title { font-size: 13px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; line-height: 1.3; }
.tl-saq-footer { display: flex; align-items: center; gap: 8px; margin-top: 6px; }
.tl-saq-ra-tag { font-size: 11px; color: #7c3aed; background: #ede9fe; border-radius: 4px; padding: 1px 6px; font-weight: 500; }
@media (prefers-color-scheme: dark) {
  .tl-saq-ra-tag { background: rgba(124,58,237,0.2); color: #c4b5fd; }
}

/* Pending 卡 */
.tl-pending-header { display: flex; align-items: center; gap: 8px; }
.tl-spin { font-size: 16px; color: #d97706; animation: spin 2s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.tl-pending-label { font-size: 13.5px; font-weight: 600; color: #92400e; }
.tl-status-chip { font-size: 11px; }
.tl-pending-meta { font-size: 12px; color: #a16207; margin-top: 4px; }

/* 三軸 + AI 建議左右分欄 */
/* Row 1：三軸評估 + 風險矩陣 兩欄 */
/* Row 1：風險矩陣 + 時間軸 兩欄 */
/* ── 風險歷史 2×2 Grid ── */
.risk-grid {
  display: grid;
  grid-template-columns: 4fr 6fr;
  gap: 14px;
  align-items: start;
  padding: 4px 0;
}
.rg-cell {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 18px 20px;
  min-width: 0;
}
.rg-top-left    { grid-column: 1; grid-row: 1; }
.rg-bottom-left { grid-column: 1; grid-row: 2; }
.rg-right       { grid-column: 2; grid-row: 1 / 3; }

.rg-section-divider {
  border: none; border-top: 1px solid var(--border);
  margin: 22px 0;
}

.rg-cell-title {
  font-size: 13px; font-weight: 700; color: var(--text-secondary);
  letter-spacing: .03em;
  margin-bottom: 14px; display: flex; align-items: center; gap: 7px;
}
.rg-cell-title::before {
  content: ''; display: inline-block; width: 3px; height: 12px;
  background: var(--accent, #1a4d3e); border-radius: 2px; flex-shrink: 0;
}
.rg-cell-title-row {
  display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;
}
.rg-cell-link {
  font-size: 11px; color: var(--accent); text-decoration: none;
  padding: 3px 8px; border: 1px solid var(--accent,#1a4d3e);
  border-radius: 4px; opacity: 0.75; transition: opacity .15s;
}
.rg-cell-link:hover { opacity: 1; }

/* 右上：年度問卷資料表格 */
.rg-year-table-wrap { overflow-x: auto; }
.rg-year-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.rg-year-table thead tr { border-bottom: 2px solid var(--border); }
.rg-year-table th {
  font-size: 10.5px; font-weight: 600; color: var(--text-muted);
  text-align: center; padding: 0 10px 10px; white-space: nowrap; letter-spacing: .03em;
}
.rg-year-table th:first-child { text-align: left; padding-left: 0; }
.rg-year-table th:last-child { padding-right: 0; }
.rg-year-table tbody tr { transition: background .1s; }
.rg-year-table tbody tr:hover { background: var(--surface-2, #f7f5f0); }
.rg-year-table td { padding: 11px 10px; text-align: center; border-bottom: 1px solid var(--border); vertical-align: middle; }
.rg-year-table tbody tr:last-child td { border-bottom: none; }
.rg-year-table td:first-child { padding-left: 0; }
.rg-year-table td:last-child { padding-right: 0; }

.rg-yt-period { text-align: left !important; }
.rg-yt-year { font-size: 13px; font-weight: 700; color: var(--text-primary); font-variant-numeric: tabular-nums; }
.rg-yt-date { display: block; font-size: 10px; color: var(--text-muted); margin-top: 2px; }
.rg-yt-score { font-size: 15px; font-weight: 800; color: var(--text-primary); font-variant-numeric: tabular-nums; }

/* dim 分數 chip */
.rg-yt-dim { padding: 3px 0; }
.rg-dim-chip {
  display: inline-block; font-size: 12px; font-weight: 700;
  font-variant-numeric: tabular-nums; padding: 2px 7px;
  border-radius: 5px; min-width: 36px; text-align: center;
}
.rg-dim-chip.very_low, .rg-dim-chip.low   { background: #e8f5ee; color: #2d7a5f; }
.rg-dim-chip.medium                        { background: #fdf3e0; color: #c47d0a; }
.rg-dim-chip.high, .rg-dim-chip.extreme   { background: #fce8e8; color: #b83232; }
.rg-dim-chip.none                          { color: var(--text-muted); background: transparent; }

.rg-yt-muted { color: var(--text-muted); font-size: 12px; }
.rg-yt-link {
  display: inline-block; font-size: 11px; color: var(--accent);
  text-decoration: none; padding: 3px 8px;
  background: var(--surface-2, #f0ede6); border-radius: 4px;
  white-space: nowrap; transition: background .12s;
}
.rg-yt-link:hover { background: var(--accent-light, #e8f2ee); }

@media (max-width: 1000px) {
  .risk-grid { grid-template-columns: 1fr; }
  .rg-top-left,.rg-bottom-left,.rg-right { grid-column: 1; grid-row: auto; }
}

/* 舊 risk-row1 保留（其他地方可能引用） */
.risk-row1 { display: none; }

/* 三軸 compact bar panel */
.axis-compact-panel { display: flex; flex-direction: column; gap: 10px; }
.axis-bar-row { display: flex; align-items: center; gap: 10px; }
.axis-bar-label { width: 120px; flex-shrink: 0; font-size: 12.5px; color: var(--text-secondary); }
.axis-bar-track {
  flex: 1; height: 8px; background: var(--border); border-radius: 99px; overflow: hidden; max-width: 240px;
}
.axis-bar-fill { height: 100%; border-radius: 99px; transition: width 0.4s ease; min-width: 0; }
.axis-fill-good    { background: #22c55e; }
.axis-fill-neutral { background: #eab308; }
.axis-fill-warn    { background: #f97316; }
.axis-fill-danger  { background: #ef4444; }
.axis-fill-empty   { background: transparent; }
.axis-bar-score { font-size: 13px; font-weight: 700; color: var(--text-primary); min-width: 36px; font-variant-numeric: tabular-nums; }
.axis-bar-empty { font-size: 12px; color: var(--text-secondary); min-width: 36px; }
.axis-level-chip { font-size: 0.7rem; padding: 2px 8px; border-radius: 9999px; font-weight: 500; }
.axis-chip-extreme { background: #fee2e2; color: #b91c1c; }
.axis-chip-high    { background: #ffedd5; color: #c2410c; }
.axis-chip-medium  { background: #fef9c3; color: #a16207; }
.axis-chip-low     { background: #dcfce7; color: #15803d; }
.axis-chip-very-low{ background: #f0fdf4; color: #166534; }

.axis3-form { margin-top: 0.75rem; padding: 0.75rem; background: #f9fafb; border-radius: 0.5rem; border: 1px solid var(--color-border, #e5e7eb); }
.axis3-form-label { font-size: 0.75rem; color: #6b7280; margin-bottom: 0.5rem; }
.axis3-input-row { display: flex; gap: 0.5rem; }
.axis3-input { width: 80px; padding: 0.35rem 0.5rem; border: 1px solid var(--color-border, #e5e7eb); border-radius: 0.375rem; font-size: 0.875rem; }
.btn-save-axis3 { padding: 0.35rem 0.75rem; background: var(--accent, #1a4d3e); color: white; border: none; border-radius: 0.375rem; cursor: pointer; font-size: 0.8rem; }
.btn-save-axis3:disabled { opacity: 0.5; cursor: not-allowed; }
.axis3-current { font-size: 0.75rem; color: #6b7280; margin-top: 0.375rem; }
.old-assessment-hint { font-size: 0.72rem; color: #d97706; margin-top: 0.5rem; background: #fffbeb; padding: 0.4rem 0.5rem; border-radius: 0.375rem; }
.axis3-standalone { padding: 4px 0; }

.mkt-badge { font-size: 0.75rem; padding: 2px 8px; background: #e5e7eb; border-radius: 4px; font-weight: 500; }

/* ── 六維度 SVG 趨勢圖表 ── */
.rtc-wrap { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 14px 16px 16px; margin-bottom: 20px; }
.rtc-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; gap: 10px; }
.rtc-title { font-size: 12px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .05em; }
.rtc-tabs { display: flex; gap: 2px; background: var(--surface-2, #f0ede6); border-radius: 6px; padding: 2px; }
.rtc-tab { padding: 4px 12px; border-radius: 4px; font-size: 11px; font-weight: 500; color: var(--text-secondary); cursor: pointer; border: none; background: none; }
.rtc-tab.active { background: var(--surface); color: var(--text-primary); box-shadow: 0 1px 2px rgba(0,0,0,.1); }
.rg-radar-chartjs-wrap { width: 100%; max-width: 280px; margin: 0 auto; }
.rtc-chartjs-wrap { width: 100%; }
.rtc-delta-chartjs-wrap { width: 100%; height: 220px; }
.rtc-svg-wrap { overflow-x: auto; }
.rtc-svg { width: 100%; display: block; overflow: visible; font-family: var(--font-sans); }
.rtc-svg-delta { width: 100%; display: block; font-family: var(--font-sans); }
.rtc-legend { display: flex; gap: 12px; margin-bottom: 8px; }
.rtc-legend-item { display: flex; align-items: center; gap: 5px; font-size: 11px; color: var(--text-secondary); }
.rtc-legend-item i { display: inline-block; width: 10px; height: 10px; border-radius: 2px; }
/* HTML 維度標籤 row：對齊 SVG 條組 */
.rtc-dim-labels {
  display: flex;
  padding-left: 7.09%; /* 40/564 */
  margin-top: 6px;
}
.rtc-dim-label {
  flex: 0 0 18.44%; /* 104/564 */
  display: flex; flex-direction: column; align-items: center; gap: 2px;
}
.rtc-dim-key  { font-size: 11.5px; font-weight: 700; color: var(--text-primary); }
.rtc-dim-name { font-size: 10px; color: var(--text-secondary); }
.rtc-delta-wrap { padding: 4px 0; }
.rtc-delta-subtitle { font-size: 11px; color: var(--text-muted); margin-bottom: 6px; }
.rtc-no-delta { font-size: 12px; color: var(--text-muted); padding: 16px 0; text-align: center; }

/* trendCellClass 用到的舊類 */
.trend-score.very_low { color: #15803d; }
.trend-score.low      { color: #16a34a; }
.trend-score.medium   { color: #ca8a04; }
.trend-score.high     { color: #ea580c; }
.trend-score.extreme  { color: #dc2626; }

/* ── 單欄事件流年度分組 ── */
.tl-year-group { margin-bottom: 8px; }
.tl-year-divider {
  font-size: 11px; font-weight: 700; color: var(--text-secondary);
  text-transform: uppercase; letter-spacing: 0.08em;
  padding: 6px 0 8px;
  border-bottom: 1px solid var(--border);
  margin-bottom: 12px;
}
.tl-cap-closed { background: #dcfce7; color: #15803d; font-size: 11px; padding: 2px 6px; border-radius: 4px; font-weight: 500; }
.tl-saq-inline {
  display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
  padding: 8px 12px; margin-bottom: 8px;
  background: var(--surface); border: 1px solid var(--border); border-radius: 8px;
  font-size: 12.5px;
}

/* ── 永續績效 summary bar ── */
.sustain-summary-bar {
  display: flex; align-items: center; gap: 0; flex-wrap: wrap;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 14px 20px;
  margin-bottom: 16px;
  gap: 6px;
}
.ss-stat { display: flex; align-items: baseline; gap: 6px; }
.ss-num { font-size: 22px; font-weight: 800; color: var(--accent); font-variant-numeric: tabular-nums; }
.ss-num--warn { color: #d97706; }
.ss-num--muted { color: var(--text-secondary); }
.ss-label { font-size: 12px; color: var(--text-secondary); }
.ss-divider { width: 1px; height: 24px; background: var(--border); margin: 0 12px; }
.ss-latest { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.ss-latest-name { font-size: 13px; font-weight: 500; color: var(--text-primary); }
.ss-latest-date { font-size: 12px; color: var(--text-secondary); }

/* ── 揭露資料分組標題 ── */
.disclosure-group-header {
  font-size: 12px; font-weight: 600; color: var(--text-secondary);
  text-transform: uppercase; letter-spacing: 0.05em;
  padding: 8px 0 4px;
  border-bottom: 1px solid var(--border);
  margin-bottom: 0;
}

/* ── 評估週期卡 ── */
.tl-period-card {
  padding: 0;
  overflow: hidden;
}
.tl-period-saq {
  padding: 10px 14px 10px;
  background: rgba(26,77,62,0.04);
}
.tl-period-saq-header {
  display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
}
.tl-period-icon { font-size: 13px; }
.tl-period-label { font-size: 12px; font-weight: 600; color: var(--text-secondary); }
.tl-period-score { font-size: 15px; font-weight: 700; color: var(--text-primary); }
.tl-period-divider {
  display: flex; align-items: center; gap: 8px;
  padding: 6px 14px;
  background: var(--border);
  font-size: 11.5px;
  border-top: 1px solid var(--border);
  border-bottom: 1px solid var(--border);
}
.tl-period-derive-badge {
  font-size: 11px; font-weight: 600;
  color: #7c3aed;
  background: rgba(124,58,237,0.08);
  padding: 2px 8px; border-radius: 4px;
}
.tl-period-risk { padding: 10px 14px 12px; }
.tl-saq-standalone { padding: 10px 14px; }
.tl-saq-no-ra-hint {
  margin-top: 6px; font-size: 11.5px; color: var(--text-secondary);
  padding-left: 2px;
}

/* bar 限寬，讓比例感正確 */
.tl-bar-wrap { flex: 1; max-width: 280px; }

/* ── 評估週期：風險兩欄 ── */
.tl-period-risk-cols {
  display: flex;
  gap: 20px;
  align-items: flex-start;
}
.tl-period-risk-cols > .tl-dims-grid { flex: 0 0 360px; }
.tl-axis-col {
  flex: 1;
  display: flex;
  flex-direction: row;
  gap: 24px;
  flex-wrap: wrap;
  align-items: flex-start;
  padding: 4px 0;
}
.tl-axis-col-row {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 80px;
}
.tl-axis-col-label { font-size: 11px; color: var(--text-secondary); }
.tl-axis-col-score {
  font-size: 20px;
  font-weight: 800;
  color: var(--accent);
  font-variant-numeric: tabular-nums;
}
.tl-axis-score-row {
  display: flex;
  align-items: baseline;
  gap: 6px;
}
.tl-axis-level-chip {
  font-size: 11px;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: 4px;
  line-height: 1.6;
}
.tl-axis-level-chip.axis-chip-extreme { background: #fee2e2; color: #b91c1c; }
.tl-axis-level-chip.axis-chip-high    { background: #ffedd5; color: #c2410c; }
.tl-axis-level-chip.axis-chip-medium  { background: #fefce8; color: #92400e; }
.tl-axis-level-chip.axis-chip-low     { background: #dcfce7; color: #15803d; }
.tl-axis-level-chip.axis-chip-very-low { background: #d1fae5; color: #065f46; }

/* 六維架構版本 badge */
.tl-v2-badge {
  font-size: 10px;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 4px;
  background: rgba(26,77,62,0.12);
  color: var(--accent);
  margin-left: 6px;
}
/* 六維分數展示區 */
.tl-six-dims {
  padding: 8px 14px 12px;
  border-top: 1px solid var(--border);
}
.tl-six-dims-title {
  font-size: 11px;
  font-weight: 600;
  color: var(--text-secondary);
  margin-bottom: 6px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.tl-six-dims-grid { display: flex; flex-direction: column; gap: 5px; }
.tl-six-dim-row {
  display: flex;
  align-items: center;
  gap: 8px;
}
.tl-six-dim-label {
  font-size: 11px;
  color: var(--text-secondary);
  width: 96px;
  flex-shrink: 0;
}
.tl-six-dim-score {
  font-size: 12px;
  font-weight: 600;
  width: 36px;
  text-align: right;
  color: var(--text-primary);
}
.tl-six-dim-mixed-badge {
  font-size: 10px;
  font-weight: 600;
  padding: 0 5px;
  border-radius: 3px;
  background: rgba(99,102,241,0.1);
  color: #4f46e5;
}
@media (prefers-color-scheme: dark) {
  .tl-six-dim-mixed-badge { background: rgba(99,102,241,0.2); color: #818cf8; }
  .tl-v2-badge { background: rgba(52,211,153,0.15); }
}

@media (prefers-color-scheme: dark) {
  .tl-axis-level-chip.axis-chip-extreme { background: rgba(239,68,68,0.18); color: #fca5a5; }
  .tl-axis-level-chip.axis-chip-high    { background: rgba(249,115,22,0.18); color: #fdba74; }
  .tl-axis-level-chip.axis-chip-medium  { background: rgba(234,179,8,0.18);  color: #fde68a; }
  .tl-axis-level-chip.axis-chip-low     { background: rgba(34,197,94,0.15);  color: #86efac; }
  .tl-axis-level-chip.axis-chip-very-low { background: rgba(16,185,129,0.15); color: #6ee7b7; }
}

/* ── 風險矩陣定位 ── */
.risk-matrix-wrap {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  flex-wrap: wrap;
}
.risk-matrix-svg {
  width: 100%;
  max-width: 360px;
  min-width: 260px;
  flex: 0 0 auto;
  overflow: visible;
}
.rm-quad-label { font-size: 9px; font-family: inherit; }
.rm-tick       { font-size: 9px; font-family: inherit; fill: var(--text-secondary); }
.rm-axis-label { font-size: 9.5px; font-family: inherit; fill: var(--text-secondary); }
.rm-point-label { font-size: 10px; font-weight: 700; font-family: inherit; }
.rm-legend {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 10px 0;
}
.rm-legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--text-secondary);
}
.rm-legend-sep { height: 8px; }
.rm-dot {
  width: 10px; height: 10px;
  border-radius: 50%;
  display: inline-block;
  flex-shrink: 0;
}
.rm-dot-low     { background: #22c55e; }
.rm-dot-mid     { background: #f59e0b; }
.rm-dot-high    { background: #ef4444; }
.rm-dot-current { background: transparent; border: 2px solid var(--accent); }

/* ── Radar Chart ── */
/* 雷達圖橫排 layout */
.rg-radar-layout { display: flex; align-items: center; gap: 12px; }
.rg-radar-svg { flex: 0 0 48%; width: 48%; height: auto; font-family: var(--font-sans); }
.rg-radar-legend { flex: 1; display: grid; grid-template-columns: 1fr 1fr; gap: 6px 8px; min-width: 0; align-content: center; }
.rg-radar-row { display: flex; align-items: center; gap: 5px; background: var(--surface-2,#f7f4ee); border-radius: 6px; padding: 5px 8px; }
.rg-radar-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.rg-radar-key { font-size: 10px; font-weight: 700; color: var(--text-muted); flex-shrink: 0; }
.rg-radar-label { font-size: 10px; color: var(--text-secondary); flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rg-radar-score { font-size: 13px; font-weight: 800; color: var(--text-primary); flex-shrink: 0; font-variant-numeric: tabular-nums; }
.rg-radar-null { color: var(--text-muted); font-weight: 400; font-size: 12px; }

/* 舊 radar（保留相容） */
.radar-wrap { display: flex; flex-direction: column; align-items: center; gap: 12px; }
.radar-svg { width: 100%; max-width: 260px; height: auto; }
.radar-legend { width: 100%; display: flex; flex-direction: column; gap: 4px; }
.radar-legend-item { display: flex; align-items: center; gap: 6px; font-size: 12px; }
.radar-legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.radar-legend-key { font-weight: 700; width: 20px; flex-shrink: 0; color: var(--text); }
.radar-legend-label { flex: 1; color: var(--text-secondary, #666); }
.radar-legend-score { font-family: monospace; font-size: 12px; color: var(--text); }

/* ── 趨勢表色票 ── */
.trend-cell-ok { background: rgba(72,187,120,0.15); }
.trend-cell-warn { background: rgba(237,137,54,0.15); }
.trend-cell-danger { background: rgba(229,62,62,0.18); font-weight: 600; color: #c53030; }
.rm-axis-desc {
  margin-top: 12px;
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.rm-desc-row {
  display: flex;
  align-items: baseline;
  gap: 6px;
  font-size: 11.5px;
  color: var(--text-secondary);
  line-height: 1.5;
}
.rm-desc-tag {
  flex-shrink: 0;
  font-size: 10.5px;
  font-weight: 700;
  color: var(--accent);
  background: rgba(26,77,62,0.08);
  padding: 1px 5px;
  border-radius: 3px;
  letter-spacing: 0.03em;
}
.section-hint {
  font-size: 11.5px;
  color: var(--text-secondary);
  margin-left: 8px;
}

/* ── 時間軸 三軸分數列 ── */
.tl-axis-row {
  display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
  padding: 5px 10px;
  background: rgba(26,77,62,0.05);
  border-radius: 6px;
  border-left: 2px solid var(--accent);
  font-size: 12px;
  margin-top: 4px;
  margin-bottom: 2px;
}
.tl-axis-label { color: var(--text-secondary); font-size: 11.5px; margin-right: 2px; }
.tl-axis-chip {
  display: inline-flex; align-items: center; gap: 4px;
  background: rgba(26,77,62,0.08); color: var(--accent);
  padding: 2px 8px; border-radius: 4px; font-size: 11.5px; font-weight: 500;
}

/* ── 時間軸 SAQ 精簡卡 ── */
.tl-saq-compact { padding: 10px 12px; }
.tl-saq-compact-header {
  display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
}
.tl-grade-sm {
  font-size: 12px; font-weight: 800;
  padding: 2px 8px; border-radius: 5px; min-width: 24px; text-align: center;
}
.tl-saq-compact-score {
  font-size: 15px; font-weight: 700; color: var(--text-primary);
  font-variant-numeric: tabular-nums;
}
.tl-saq-compact-dims {
  font-size: 11.5px; color: var(--text-secondary);
  background: #f5f3ee; padding: 2px 7px; border-radius: 4px;
  letter-spacing: 0.03em;
}
.tl-saq-compact-meta {
  display: flex; align-items: center; gap: 8px; margin-top: 6px; flex-wrap: wrap;
}
</style>
