## ADDED Requirements

### Requirement: ESPR/DPP Tab in Compliance Dashboard

**What**: `MaterialComplianceView` 新增第四個 Tab「ESPR/DPP」，顯示每個產品的 Digital Product Passport 就緒度。

**Behavior**:
- Tab 不自動載入，點擊後才觸發 API 呼叫
- 列表顯示所有 BuyerProduct，每筆顯示：產品名稱、ESPR 法規標記（是否）、整體就緒狀態（badge）、材料完整度（%）、供應商合規覆蓋率（%）
- 點擊列表任一列展開右側 Drawer，顯示三個區塊詳情

#### Scenario: 載入 DPP 視角
- **WHEN** 使用者點擊「ESPR/DPP」Tab
- **THEN** 呼叫 `GET /api/v1/compliance/dpp-readiness`，顯示載入狀態，完成後渲染產品列表

#### Scenario: DPP 就緒狀態顯示
- **WHEN** 產品三個區塊全部滿足（ESPR 已標記 + 材料 100% + 供應商 ≥ 80%）
- **THEN** 顯示 `ready`（綠色 badge）
- **WHEN** 部分區塊完成
- **THEN** 顯示 `partial`（黃色 badge）
- **WHEN** BomLine 為空或無 primary 供應商
- **THEN** 顯示 `not_started`（灰色 badge）

---

### Requirement: DPP Readiness API

**What**: `GET /api/v1/compliance/dpp-readiness` 回傳所有產品的 DPP 就緒度彙整；`GET /api/v1/compliance/dpp-readiness/{productId}` 回傳單一產品的三區塊明細。

**Behavior**:
- 列表 API 每筆包含：`product_id`、`product_name`、`has_espr_regulation`、`readiness_status`（ready/partial/not_started）、`material_completeness_pct`、`supplier_compliance_pct`、`bom_line_count`、`issues[]`
- 明細 API 回傳三個 section：`material_list`（BomLine 完整性）、`supplier_compliance`（文件覆蓋）、`regulations`（法規標記）
- 材料完整性 = material 類型的 BomLine 中，有 material_group_id 者 / 總數
- 供應商合規覆蓋率 = primary 供應商已提交的 required doc（valid 或 expiring_soon）/ 所有 required doc 總數
- ESPR 標記 = `applicable_regulations` 陣列包含 `'ESPR'`

#### Scenario: 就緒度計算 - 材料完整性
- **WHEN** 所有 material 類型 BomLine 均有 material_group_id
- **THEN** material_completeness_pct = 100

#### Scenario: 就緒度計算 - 供應商合規
- **WHEN** primary 供應商對每個 required_doc_type 均有 valid 或 expiring_soon 文件
- **THEN** supplier_compliance_pct = 100

---

### Requirement: DPP Detail Drawer

**What**: 點擊產品列表任一列，右側展開 Drawer 顯示三個區塊的完整性明細。

**Behavior**:
- Drawer 寬 420px，固定右側，有半透明 overlay
- 標題：「[產品名稱] — DPP 明細」
- 三個 section：
  - **材料清單**：條目列表（材料名稱、HS Code、物料群組是否已設定），有/無群組用圖示區分
  - **供應商合規聲明**：依 supplier 分組，列出每個 required doc type 的狀態
  - **法規標記**：顯示 applicable_regulations 清單，ESPR 是否已標記
- 點擊 overlay 或 × 關閉
