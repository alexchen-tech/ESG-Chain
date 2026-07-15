## Why

現有合規看板只有「供應商視角」與「產品視角」，無法回答「哪個物料群組的 EUDR 文件覆蓋率最低？」或「Tier 1 供應商群組整體缺哪些文件？」等跨維度問題。採購商需要一個能同時從物料群組、供應商群組、文件類型三個維度切入的矩陣視圖，才能在稽核前快速定位風險集中點。

## What Changes

- `MaterialComplianceView` 新增第三個 Tab：**矩陣視角**
- 矩陣以物料群組為行、文件類型（EUDR / UFLPA / CMRT / SDS / CE）為列，格子顯示合規覆蓋率（合規供應商數 / 總供應商數）
- 頂部篩選：供應商群組下拉（單選）+ 合規狀態 chip
- 點擊格子 → 右側 Drawer 展開該物料群組 × 文件類型的供應商明細清單
- Drawer 底部提供「發送提醒」（未來）與「查看 CAP」連結
- 後端新增兩支 API：`GET /api/v1/compliance/matrix`（矩陣聚合資料）與 `GET /api/v1/compliance/matrix/drill`（格子明細）

## Capabilities

### New Capabilities

- `compliance-matrix`: 合規矩陣視圖 — 物料群組 × 文件類型的交叉分析，含供應商群組篩選與格子 drill-down

### Modified Capabilities

（無 spec 層級的行為變更）

## Impact

- **前端**：`MaterialComplianceView.vue`（新增 tab + 矩陣元件 + Drawer）；`api/modules/compliance.ts`（新增 2 個 API 方法與型別）
- **後端**：`ComplianceDashboardController`（新增 2 個 action）；`SupplierComplianceStatusService`（新增 `getMatrixData()` 和 `getMatrixDrill()` 方法）；`routes/api.php`（新增 2 條路由）
- **資料庫**：不需要 migration，現有資料關係已足夠（SupplierGroup → Supplier → BomLineSupplier → ProductBomLine → MaterialGroup → required_doc_types）
