## Why

ESPR（Ecodesign for Sustainable Products Regulation）要求 EU 市場上的產品必須建立 Digital Product Passport（DPP），揭露材料組成、碳足跡、可回收性等永續數據。現有合規看板缺乏產品維度的 DPP 就緒度追蹤，採購商無法快速掌握哪些產品已具備 DPP 所需資料、哪些仍有資料缺口。

## What Changes

- 合規看板新增第四個 Tab「ESPR/DPP 視角」
- 以產品為主體，顯示每個 BuyerProduct 的 DPP 就緒度（必填欄位填寫率）
- DPP 所需資料來源於既有資料：BomLine（材料組成）、SupplierComplianceDoc（材料來源聲明）、BuyerProduct（基本資訊 + applicable_regulations）
- 每個產品顯示：DPP 就緒狀態、材料完整性、供應商合規覆蓋率、缺漏項目清單
- 點擊產品展開 Drawer，顯示 DPP 各區塊（材料清單 / 供應商聲明 / 一般資訊）的完整性明細

## Capabilities

### New Capabilities

- `espr-dpp-readiness`: 產品 DPP 就緒度視角 — 以 BuyerProduct 為單位，彙整 BomLine、SupplierComplianceDoc、applicable_regulations 計算 DPP 完整度，含 Drawer 明細

### Modified Capabilities

（無）

## Impact

- **前端**：`MaterialComplianceView.vue`（新增第四個 Tab + DPP 列表 + Drawer）；`api/modules/compliance.ts`（新增 DPP readiness API 方法與型別）
- **後端**：`ComplianceDashboardController`（新增 `dppReadiness()` action）；`SupplierComplianceStatusService`（新增 `getDppReadiness()` 方法）；`routes/api.php`（新增 1 條路由）
- **資料庫**：不需要 migration，現有資料已足夠（BuyerProduct → BomLine → MaterialGroup / BomLineSupplier → Supplier → SupplierComplianceDoc）
