## 1. 資料層：RiskAssessment 三軸欄位 Migration

- [x] 1.1 建立 migration：`risk_assessments` 新增 `axis1_score`（float nullable）、`axis2_score`（float nullable）、`axis3_score`（float nullable）、`axis1_source_saq_id`（uuid nullable）、`axis2_source_saq_id`（uuid nullable）
- [x] 1.2 更新 `RiskAssessment` model：新增 fillable 欄位與 casts
- [x] 1.3 建立 migration：新增 `trade_good_path_risks` 快取 table（`id, trade_good_id, market, path_risk_score, risk_level, amplifier, chain_risk, has_data_gap, contributors JSON, calculated_at, expires_at`）

## 2. 計分引擎：Multi-framework 多軸輸出（esgchain-ai）

- [x] 2.1 `scoring_service.py`：新增 `FRAMEWORK_FILTER_MAP` 支援 `"multi-framework"` 觸發三次 filter
- [x] 2.2 `SAQScoringResultResponse` schema 新增欄位：`iso26000_total, iso26000_category_scores, iso20400_total, iso20400_category_scores, geo_risk_total, axis1_score, axis2_score`
- [x] 2.3 `calculate_saq_score()` 實作：multi-framework 時執行三組 filter，各自 pillar 分組計算，填入新欄位
- [x] 2.4 `axis1_score = 100 - iso26000_total`、`axis2_score = 100 - iso20400_total` 換算邏輯

## 3. SAQ 自動推導：三軸 scoreCallback 擴展（esgchain-api）

- [x] 3.1 `scoreCallback()` 識別 `scoring_framework = "multi-framework"`，讀取 `axis1_score`、`axis2_score`
- [x] 3.2 自動建立 RiskAssessment 時寫入 axis1/2_score 與 source_saq_id；同時保留舊 E/S/G probability 換算（向後相容）
- [x] 3.3 iso26000_total 與 iso20400_total 均為 null 時跳過建立

## 4. 路徑風險計算端點（esgchain-ai）

- [x] 4.1 新增 `POST /ai/v1/path-risk` 端點：`PathRiskRequest`（trade_good_id, market, supplier_emissions[]）、`PathRiskResponse`（path_risk_score, risk_level, amplifier, chain_risk, has_data_gap, contributors[]）
- [x] 4.2 實作路徑風險計算邏輯：碳排占比加權、emission_factor fallback（data_gap 標記）、市場法規放大係數
- [x] 4.3 放大係數計算：呼叫 esgchain-api 取得 MarketComplianceChecker 缺口數據，或接受前端傳入缺口比例

## 5. 路徑風險快取與 API（esgchain-api）

- [x] 5.1 `ExportRiskMatrixController`：`GET /api/v1/trade-goods/export-risk-matrix`，讀取 `trade_good_path_risks` 快取，快取缺失回傳 `status: "calculating"` 並排程非同步計算
- [x] 5.2 非同步計算 Job：組裝 supplier_emissions[]（含 axis1_score + PcfSnapshot 碳排），呼叫 `/ai/v1/path-risk`，結果寫入快取
- [x] 5.3 PcfSnapshot Observer：snapshot 更新時清除對應 `trade_good_path_risks` 快取紀錄
- [x] 5.4 SupplierComplianceDoc Observer：文件新增/更新時清除相關快取

## 6. MarketComplianceChecker 擴展（esgchain-api）

- [x] 6.1 `check()` 回傳新增 `supplier_risk_context[]`：含各責任供應商 axis1_score（來自最新 RiskAssessment）、emission_kg（最新 PcfSnapshot 貢獻）、has_data_gap
- [x] 6.2 合規明細 API response 加入 `supplier_risk_context` 欄位

## 7. 替換供應商推薦端點（esgchain-ai）

- [x] 7.1 新增 `POST /ai/v1/supplier-replacement-candidates` 端點：輸入 `{trade_good_id, market, replace_supplier_id}`
- [x] 7.2 候選查詢：HS Code 交集、異來源國、有 axis1_score（呼叫 esgchain-api 取供應商清單）
- [x] 7.3 模擬計算：以候選 axis1_score 替換被換供應商貢獻，重算 chain_risk，計算 improvement_pct
- [x] 7.4 `already_in_supply_chain` 標記：候選已在 BOM 中時設為 true，排序置後

## 8. Multi-framework SAQ 範本

- [x] 8.1 `saq_templates.scoring_framework` 新增枚舉值 `"multi-framework"`（migration + validation）
- [x] 8.2 範本發布驗證：multi-framework 範本需包含 ≥1 題 iso26k.* slug 且 ≥1 題 iso20400.* slug，否則 422
- [x] 8.3 停用現有獨立框架範本（`is_active = false`）的管理工具（admin 介面或 artisan command）
- [x] 8.4 建立正式 Multi-tag 混合範本 seed（含 iso26k.* + iso20400.* 雙框架題目，從現有題庫挑選並補標 slug）

## 9. Dashboard A：永續風險三軸雷達（esgchain-web）

- [x] 9.1 新增 `SustainabilityRiskView.vue`（sustain/analyst/admin 角色可見，掛在 dashboard 模組下）
- [x] 9.2 供應商列表加入三軸色塊欄位（very_low/low/medium/high/extreme 顏色對應），支援依最高等級排序
- [x] 9.3 `SupplierRiskRadarChart.vue`：三軸雷達圖元件，虛線處理缺少資料軸，點擊軸展開 pillar 明細
- [x] 9.4 供應商詳情頁 Risk tab 整合三軸雷達 + 軸3 手動填報表單（0~100 輸入）
- [x] 9.5 供應商詳情頁 overview tab 底部新增「出口路徑風險清單」（商品 × 市場 + 風險等級，連結熱力圖）

## 10. Dashboard B：出口合規熱力圖（esgchain-web）

- [x] 10.1 新增 `ExportRiskDashboardView.vue`（comply/buyer/admin 角色可見，掛在 tradegoods 模組下）
- [x] 10.2 `ExportRiskHeatmap.vue`：商品 × 市場熱力格元件，雙入口切換（商品優先 / 市場優先），顏色 + data_gap 橘框
- [x] 10.3 義務缺口明細面板：法規義務清單（valid/expiring_soon/missing）、責任供應商 + axis1_score 色塊、[補文件] 按鈕
- [x] 10.4 [補文件] 動作：呼叫 CAP 建立 API（source_type = "compliance_doc_gap"），帶入 doc_type 與 supplier_id
- [x] 10.5 替換供應商推薦子面板：展開候選清單（名稱、來源國、ESG暴露等級、改善幅度 ↓XX%）、免責聲明、點擊開啟供應商詳情

## 11. 整合測試

- [ ] 11.1 Multi-framework SAQ 完整流程：填答 → 計分 → 三軸分 → RiskAssessment 自動建立 → 雷達圖顯示
- [ ] 11.2 路徑風險計算驗證：碳排 fallback 標記、放大係數計算、快取失效觸發
- [ ] 11.3 替換推薦流程：從義務缺口面板觸發 → 推薦出現 → 改善幅度正確計算
- [ ] 11.4 舊範本停用後，歷史 SaqProject 分數仍可正常查閱，三軸雷達顯示「舊版評估」提示
