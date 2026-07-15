## Context

BomLine supplier 現有兩個 source：
- `erp_designated`：由 ERP BOM 匯入時帶入，是事實來源，不應受 AVL 約束
- `manual`：使用者在 UI 手動新增，應受 AVL 約束

AVL 儲存於 `buyer_product_suppliers` 表，欄位 `buyer_product_id` + `supplier_id`。

## Goals / Non-Goals

**Goals:**
- 後端：manual BomLineSupplier 新增時驗證 supplier_id 在該產品 AVL 中
- 前端：sub-row 下拉只顯示 AVL 成員；AVL 為空時顯示引導提示

**Non-Goals:**
- 不回溯驗證已存在的 erp_designated BomLineSuppliers
- 不限制 AVL 新增（仍可從全部 MDM 供應商選）
- 不改動合規計算邏輯

## Decisions

### D1：後端驗證位置
**決策**：在 `BomLineSupplierController::store()` 加驗證，而非 Model 層。

**理由**：ERP 匯入路徑（`BomLineImportService`）傳入的是 `erp_designated`，Controller 層可依 `source` 欄位決定是否驗證，不影響匯入流程。Model 層驗證會對所有寫入路徑生效，過於嚴格。

### D2：前端候選池資料來源
**決策**：BomLine sub-row 的供應商下拉直接從 `p.product_suppliers`（已存在於前端 product 物件）過濾，不新增 API 呼叫。

**理由**：`product_suppliers` 含 `supplier` 關聯（name、id），可直接使用。展開 BOM Panel 時 product 物件已在記憶體中。

### D3：AVL 空狀態處理
**決策**：當 `p.product_suppliers.length === 0` 時，隱藏新增供應商 form，改顯示文字提示，引導使用者先到 AVL 區塊新增。

**理由**：下拉空清單讓使用者困惑，明確提示比空下拉更好的 UX。

## Risks / Trade-offs

- **[ERP 匯入的供應商不在 AVL]** ERP 帶入的廠商初始不在 AVL，屬正常狀態（採購商事後認可）。這些廠商的 BomLineSupplier 已存在，合規計算不受影響。→ 接受，說明文字已解釋此流程。
- **[前端 AVL 候選池資料過期]** 若使用者開著 BOM Panel 同時在另一個 tab 新增 AVL，sub-row 候選池不會即時更新。→ 接受，重新展開 Panel 即可更新。

## Migration Plan

1. 後端：修改 `BomLineSupplierController`，無 migration
2. 前端：修改 `BuyerProductsView.vue`，docker cp 即可
