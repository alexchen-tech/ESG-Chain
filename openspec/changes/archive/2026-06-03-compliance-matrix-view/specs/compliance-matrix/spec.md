## ADDED Requirements

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

**What**: 矩陣視角頂部提供「供應商群組」單選下拉，預設「全部供應商群組」。

**Behavior**:
- 切換供應商群組時重新呼叫 matrix API，帶 `supplier_group_id` 參數
- 選「全部」時不帶 `supplier_group_id`

### Requirement: Cell Drill-Down Drawer

**What**: 點擊有效格子（非灰底）時，右側展開 Drawer 顯示供應商明細。

**Behavior**:
- Drawer 寬 360px，固定右側，有半透明 overlay
- 標題：「[物料群組名] × [文件類型]」
- 清單依狀態排序：missing → expired → expiring_soon → valid
- 每列顯示：供應商名稱、供應商群組名稱、文件狀態（badge）、到期日（若有）
- 底部顯示「查看 CAP」按鈕（連結至 `/cap?supplier_id=xxx`）
- 點擊 overlay 或右上角 × 關閉

### Requirement: Matrix API

**What**: `GET /api/v1/compliance/matrix` 回傳矩陣聚合資料。

**Behavior**:
- Query params: `supplier_group_id`（optional）
- 回傳格式見 design.md
- 只包含有 `required_doc_types` 的 MaterialGroup 行
- pct = round(compliant / total * 100)，total=0 時 pct=0

### Requirement: Drill API

**What**: `GET /api/v1/compliance/matrix/drill` 回傳格子明細。

**Behavior**:
- Query params: `material_group_id`（required）、`doc_type`（required）、`supplier_group_id`（optional）
- 回傳該格子所有供應商及其對應文件的狀態與到期日
- status 值：`valid` / `expiring_soon` / `expired` / `missing`
