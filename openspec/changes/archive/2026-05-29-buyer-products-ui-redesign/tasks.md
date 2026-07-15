## 1. 後端：BomLineSupplierController

- [x] 1.1 新增 `app/Http/Controllers/Api/Compliance/BomLineSupplierController.php`，實作 `store`（POST，驗證 supplier_id/role，防止重複 primary）與 `destroy`（DELETE）
- [x] 1.2 在 `routes/api.php` 新增 nested 路由：`products/{buyerProduct}/bom-lines/{bomLine}/suppliers`，掛 store + destroy
- [x] 1.3 docker cp Controller 和 routes 至容器並驗證路由（`php artisan route:list | grep bom-line`）

## 2. 前端：API 模組

- [x] 2.1 在 `esgchain-web/src/api/modules/compliance.ts` 新增 `bomLineSupplierApi`，包含 `store(productId, bomLineId, data)` 與 `destroy(productId, bomLineId, supplierId)` 函數

## 3. 前端：BuyerProductsView 重構展開邏輯

- [x] 3.1 移除 `expandedId` 狀態，點主列改為 toggle `bomPanelOpen[p.id]`
- [x] 3.2 移除展開 AVL panel 的 `v-if="expandedId === p.id"` 區塊（供應商關聯 panel）

## 4. 前端：BOM 表格供應商 sub-row

- [x] 4.1 新增 `expandedBomLineId` 狀態（string | null），新增 `toggleBomLineSuppliers(lineId)` method
- [x] 4.2 在 BOM 表格每列顯示模式新增「供應商 N」badge（點擊 toggle sub-row）；N 從 `line.bom_line_suppliers?.length ?? 0` 取得
- [x] 4.3 在每列後插入 sub-row `<tr>`（`v-if="expandedBomLineId === line.id"`），colspan 覆蓋所有欄，顯示現有 BomLineSupplier 清單（名稱、role badge、來源 badge、移除按鈕）
- [x] 4.4 sub-row 底部加「+ 新增供應商」inline form：下拉選擇 supplier（從 suppliers list）、下拉選擇 role（primary/alternate）、確認按鈕
- [x] 4.5 實作 `addBomLineSupplier(productId, bomLineId)` method：呼叫 API，成功後更新 `bomLines[productId]` 中對應 line 的 `bom_line_suppliers`
- [x] 4.6 實作 `removeBomLineSupplier(productId, bomLineId, bomLineSupplierId)` method：呼叫 API，成功後從清單移除

## 5. 前端：AVL 移至 BOM Panel 底部

- [x] 5.1 在 BOM Panel 底部加分隔線與「已認可供應商（AVL）」區塊，含說明文字「AVL 廠商需在 BOM 明細中指定為供應商，才會納入合規計算」
- [x] 5.2 將原 AVL 供應商列表（`p.product_suppliers`）與「新增供應商」按鈕移至此區塊（保留原有新增/移除功能）
- [x] 5.3 產品摘要列保留「供應商 N」數量顯示（bubble），移除點擊展開 AVL 的行為（或改為 scroll to AVL 區塊）

## 6. 前端：樣式

- [x] 6.1 新增 `.bom-line-supplier-sub-row`、`.bom-supplier-badge`、`.role-badge--primary`、`.role-badge--alternate`、`.avl-section` 等 CSS class

## 7. Docker 部署與驗證

- [x] 7.1 docker cp 前端更新後的檔案（BuyerProductsView.vue、compliance.ts）至容器
- [x] 7.2 驗證：展開產品直接顯示 BOM panel；BOM 筆數在展開前已顯示正確數字
- [x] 7.3 驗證：點 BomLine 供應商 badge 展開 sub-row，可新增 primary/alternate 供應商，可移除
- [x] 7.4 驗證：AVL 管理出現在 BOM Panel 底部，功能正常
