## Why

手動在 BomLine 指定供應商時，目前可從 MDM 全部供應商選擇，導致出現「BomLine 供應商不在產品 AVL 中」的幽靈供應商，造成語意混亂。業務流程為：ERP 匯入 BOM → 採購商審核後將廠商加進 AVL（正式認可）→ 未來手動指派 BomLine 供應商只能從 AVL 候選池選擇，確保 AVL 成為手動操作的門禁。

## What Changes

- **後端約束**：`BomLineSupplierController::store()` 當 `source=manual` 時，驗證 `supplier_id` 必須存在於該產品的 `buyer_product_suppliers`（AVL）；ERP 匯入路徑（`source=erp_designated`）跳過此驗證
- **前端下拉改為 AVL 候選池**：BomLine sub-row 新增供應商表單，下拉選單從「全部 MDM 供應商」改為「此產品的 AVL 成員」
- **AVL 空狀態提示**：當產品 AVL 為空時，sub-row 新增表單顯示提示「請先在下方新增已認可供應商（AVL）後，再指定 BomLine 供應商」

## Capabilities

### New Capabilities

（無新 capability，皆為現有行為修改）

### Modified Capabilities

- `bom-line-supplier-management`：手動新增 BomLineSupplier 需通過 AVL 成員驗證；前端候選池改為 AVL
- `bom-line-supplier-avl`：AVL 的語意從「純認可清單」升格為「手動 BomLine 指派的門禁候選池」

## Impact

- `esgchain-api/app/Http/Controllers/Api/Compliance/BomLineSupplierController.php`：store() 新增 AVL 驗證邏輯
- `esgchain-web/src/views/compliance/BuyerProductsView.vue`：BomLine sub-row 供應商下拉改為 AVL 篩選；空 AVL 提示
- 無 DB migration、無新路由
