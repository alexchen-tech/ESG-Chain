## 1. 後端：BomLineSupplierController AVL 驗證

- [x] 1.1 在 `BomLineSupplierController::store()` 新增 AVL 驗證：當 request 未帶 `source` 或 `source=manual` 時，檢查 `supplier_id` 是否存在於 `$buyerProduct->productSuppliers()->pluck('supplier_id')`，若不在則回傳 422
- [x] 1.2 docker cp `BomLineSupplierController.php` 至容器並測試：手動新增非 AVL 供應商應得到 422

## 2. 前端：BomLine sub-row 候選池改為 AVL 成員

- [x] 2.1 在 `BuyerProductsView.vue` 的 BomLine sub-row 新增供應商 form 中，將供應商下拉的資料來源從 `suppliers`（全部 MDM）改為 `p.product_suppliers`（AVL 成員），顯示 `ps.supplier.name`，value 為 `ps.supplier_id`
- [x] 2.2 新增 computed helper 或 method `avlSuppliers(productId)`，回傳該產品 `product_suppliers` 中有 `supplier` 關聯的成員清單
- [x] 2.3 當 `p.product_suppliers.length === 0` 時，隱藏新增供應商 form，改顯示提示文字「請先在下方新增已認可供應商（AVL）後，再指定 BomLine 供應商」

## 3. Docker 部署與驗證

- [x] 3.1 docker cp `BuyerProductsView.vue` 至容器
- [x] 3.2 驗證：BomLine sub-row 供應商下拉只顯示 AVL 成員
- [x] 3.3 驗證：AVL 為空時顯示引導提示，無新增 form
- [x] 3.4 驗證：後端拒絕手動新增非 AVL 供應商（422）；ERP 匯入不受影響
