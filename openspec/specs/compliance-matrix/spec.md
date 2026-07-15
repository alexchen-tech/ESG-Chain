### Requirement: Matrix Tab in Compliance Dashboard

**What**: `MaterialComplianceView` 新增第三個 Tab「矩陣視角」，顯示物料群組 × 文件類型的合規矩陣。

**Behavior**:
- Tab 預設不自動載入，點擊後才觸發 API 呼叫
- 矩陣行 = 所有有 `required_doc_types` 的 MaterialGroup
- 矩陣列 = 固定 5 種文件類型：EUDR_DDS / UFLPA_DECLARATION / CMRT / SDS / CE_DOC
- 格子為 null 表示該物料群組不要求此文件類型，顯示「—」灰底
- 格子有值時顯示：合規數/總數（百分比）
- 顏色規則：≥90% 綠色、50–89% 黃色、<50% 紅色

### Requirement: Supplier Group Filter

**What**: 矩陣視角頂部提供「供應商群組」單選下拉、「Tier」單選下拉、「風險分數下限」數字 input，三者均預設為空（不篩選）。

**Behavior**:
- 切換供應商群組時重新呼叫 matrix API，帶 `supplier_group_id` 參數
- 選「全部」時不帶 `supplier_group_id`
- 切換 Tier 時重新呼叫 matrix API，帶 `tier` 參數（1 / 2 / 3）
- 選「全部 Tier」時不帶 `tier`
- 風險分數 input 輸入後 300ms debounce，帶 `risk_score_min` 參數（整數 0–100）
- 清空風險分數 input 時不帶 `risk_score_min`
- 三個篩選條件之間為 AND 關係（所有條件同時套用）
- Input placeholder 顯示「未評分供應商不列入」

#### Scenario: 按 Tier 篩選

- **WHEN** 使用者選擇 Tier 1
- **THEN** 矩陣重載，格子中的 compliant/total 分母僅計算 tier=1 的供應商

#### Scenario: 按風險分數下限篩選

- **WHEN** 使用者輸入 risk_score_min = 60 並等待 300ms
- **THEN** 矩陣重載，格子分母僅計算 risk_score ≥ 60 的供應商；risk_score 為 null 的供應商不計入

#### Scenario: 組合篩選

- **WHEN** 使用者同時選擇 Tier 1 且 risk_score_min = 50
- **THEN** 矩陣重載，分母為 tier=1 AND risk_score ≥ 50 的供應商

#### Scenario: 清除篩選

- **WHEN** 使用者將 Tier 改回「全部 Tier」且清空風險分數
- **THEN** 矩陣回復顯示全部供應商的合規比率

### Requirement: Cell Drill-Down Drawer

**What**: 點擊有效格子（非灰底）時，右側展開 Drawer 顯示供應商明細。

**Behavior**:
- Drawer 寬 360px，固定右側，有半透明 overlay
- 標題：「[物料群組名] × [文件類型]」
- 清單依狀態排序：missing → expired → expiring_soon → valid
- 每列顯示：供應商名稱、Tier badge、onboarding_stage chip、供應商群組名稱、risk_score（font-mono）、文件狀態（badge）、到期日（若有）
- 底部顯示「查看 CAP」按鈕（連結至 `/cap?supplier_id=xxx`）
- 點擊 overlay 或右上角 × 關閉

### Requirement: Matrix API

**What**: `GET /api/v1/compliance/matrix` 回傳矩陣聚合資料。

**Behavior**:
- Query params: `supplier_group_id`（optional）、`tier`（optional, integer 1/2/3）、`risk_score_min`（optional, float）
- 回傳格式見 design.md
- 只包含有 `required_doc_types` 的 MaterialGroup 行
- pct = round(compliant / total * 100)，total=0 時 pct=0
- 不帶篩選參數時行為與原版完全一致（向後相容）
- risk_score 為 null 的供應商：當 risk_score_min 有值時不計入分母

#### Scenario: 帶 tier 參數

- **WHEN** GET /api/v1/compliance/matrix?tier=2
- **THEN** 回傳僅包含 tier=2 供應商的矩陣數據

#### Scenario: 帶 risk_score_min 參數

- **WHEN** GET /api/v1/compliance/matrix?risk_score_min=70
- **THEN** 回傳僅包含 risk_score ≥ 70 供應商的矩陣數據；risk_score=null 的供應商不計入

#### Scenario: 不帶新參數（向後相容）

- **WHEN** GET /api/v1/compliance/matrix（無 tier 無 risk_score_min）
- **THEN** 行為與修改前完全相同

### Requirement: Drill API

**What**: `GET /api/v1/compliance/matrix/drill` 回傳格子明細。

**Behavior**:
- Query params: `material_group_id`（required）、`doc_type`（required）、`supplier_group_id`（optional）、`tier`（optional）、`risk_score_min`（optional）
- 回傳該格子所有符合篩選條件的供應商及其對應文件的狀態與到期日
- status 值：`valid` / `expiring_soon` / `expired` / `missing`
- 每筆回傳欄位含：`tier`（integer）、`risk_score`（float | null）、`onboarding_stage`（string）
- `tier` 與 `risk_score_min` 篩選條件需與 matrix API 一致（drill-down 母體與矩陣格子母體相同）

#### Scenario: Drill-down 含 Tier 資訊

- **WHEN** 點擊矩陣格子並觸發 drill API
- **THEN** 每筆供應商列包含 tier badge（T1/T2/T3）、risk_score 數值（font-mono）、onboarding_stage chip

#### Scenario: Drill-down 篩選與矩陣一致

- **WHEN** 矩陣以 tier=1 + risk_score_min=50 篩選後點擊格子
- **THEN** drill-down 清單的供應商母體與矩陣格子分母相同（tier=1 AND risk_score ≥ 50）
