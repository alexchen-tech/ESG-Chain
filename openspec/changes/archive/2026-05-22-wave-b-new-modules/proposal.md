# Change Proposal: Wave B — New Modules

## 前置條件

Wave A 完成後執行。

## 動機

Spec v2.1.0 定義了三個現有程式碼完全缺少的模組：
- M3 風險評估（採用 5×5 矩陣法，與現有加權平均模型完全不同）
- M9 系統設定（問卷範本、供應商分組、SASB 分類管理）
- 問卷 KPI 計數（已在 Wave A 加入，此處確認完成）

## 範圍

### B1 — M3 Risk 完整重設計

**模型從加權平均 → probability × impact 矩陣**

新的風險評估結構：
- 四個獨立維度：E（環境）/ S（社會）/ G（治理）/ GP（地緣政治）
- 每個維度各有 probability(1-5) 和 impact(1-5)
- score = probability × impact（範圍 1–25）
- level = very_low(<5) / low(<10) / medium(<15) / high(<20) / extreme(≥20)

**新 API 端點**
- `GET /api/v1/risk/matrix?dimension=E|S|G|GP`（5×5 矩陣 + 摘要）
- `GET /api/v1/risk/matrix/suppliers?dimension=&probability=&impact=`（格子下鑽）
- `GET /api/v1/risk/assessments`（列表，支援 supplier_id 篩選）
- `POST /api/v1/risk/assessments`（建立，含四維度 probability/impact）
- `GET /api/v1/suppliers/{id}/risk-summary`（最新四維度評分，Wave A 預留）

**架構決策**
- 風險評估存於 **Laravel MySQL**（source of truth）
- FastAPI risk service 更新：改為輔助角色（計算複雜批量分析用）
- risk_assessments 表重設計，移除舊的 score_environment 等欄位

### B2 — M9 Settings

**B2a 問卷範本管理**
- `GET /settings/questionnaire-templates`（支援 is_active、sasb_industry_id 篩選）
- `POST /settings/questionnaire-templates`
- `GET /settings/questionnaire-templates/{id}`
- `PUT /settings/questionnaire-templates/{id}`
- `DELETE /settings/questionnaire-templates/{id}`

**B2b 供應商分組管理**
- `GET /settings/supplier-groups`
- `POST /settings/supplier-groups`
- `PUT /settings/supplier-groups/{id}`
- `DELETE /settings/supplier-groups/{id}`

**B2c SASB 產業分類**
- `GET /settings/sasb-industries`（唯讀，支援 sector 篩選）
- Seeder：植入完整 SASB 產業分類資料（約 77 個產業）

## 不在範圍

- PCF 非同步化（Wave C）
- Reports（Wave C）
- 前端頁面（維持現有，後續 Phase 4 更新）

## 成功條件

- [ ] `POST /risk/assessments` 接受四維度 probability/impact，正確計算 score 和 level
- [ ] `GET /risk/matrix?dimension=GP` 回傳正確 5×5 矩陣資料
- [ ] `GET /risk/matrix/suppliers?dimension=E&probability=5&impact=5` 回傳 extreme 供應商
- [ ] `GET /suppliers/{id}/risk-summary` 回傳最新四維度評分
- [ ] Settings 三個子模組 CRUD 全部正常
- [ ] SASB 產業分類 Seeder 植入，GET 可正確篩選
