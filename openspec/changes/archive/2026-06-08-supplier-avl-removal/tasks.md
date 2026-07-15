## 1. 後端：Bug 修復（先行，無副作用）

- [x] 1.1 `BuyerProductSupplierController.php` 兩處 `syncApplicableRegulations()` → `syncInferredRegulations()`
- [x] 1.2 `BuyerProductImportController.php` 一處 `syncApplicableRegulations()` → `syncInferredRegulations()`
- [x] 1.3 `BuyerProductSeeder.php` 一處 `syncApplicableRegulations()` → `syncInferredRegulations()`

## 2. 後端：移除 AVL Controller 與路由

- [x] 2.1 刪除 `app/Http/Controllers/Api/Compliance/BuyerProductSupplierController.php`
- [x] 2.2 `routes/api.php` 移除 `buyer-products/{product}/suppliers` 的 GET / POST / DELETE 路由（共 2 行）
- [x] 2.3 `BuyerProductImportController.php` 移除建立 AVL 記錄的邏輯（`productSuppliers()->create(...)` 區塊）

## 3. 後端：修改 BomLineSupplierController 驗證邏輯

- [x] 3.1 移除 `BomLineSupplierController::store()` 中的 AVL 驗證區塊（`$isManual` / `$inAvl` 邏輯）
- [x] 3.2 新增 certified 狀態驗證：手動新增（`source !== 'erp_designated'`）時，驗證 `supplier.status === 'certified'`，否則回傳 422

## 4. 後端：移除 BuyerProduct model 關聯

- [x] 4.1 `app/Models/BuyerProduct.php` 刪除 `productSuppliers()` HasMany 關聯方法
- [x] 4.2 確認其他 model 無殘留引用 `productSuppliers`（grep 確認）

## 5. 後端：Drop migration

- [x] 5.1 建立新 migration：`drop_buyer_product_suppliers_table`，執行 `Schema::dropIfExists('buyer_product_suppliers')`
- [x] 5.2 本機執行 `php artisan migrate` 確認 migration 成功

## 6. 後端：Seeder 清理

- [x] 6.1 `BuyerProductSeeder.php` 移除 AVL 填充邏輯（`productSuppliers()->create(...)` 區塊）

## 7. 前端：移除 AVL 管理 UI

- [x] 7.1 `BuyerProductsView.vue` 移除 AVL 區塊 template（「已認可供應商」清單、新增/移除按鈕、AVL 說明文字，約 lines 370-465）
- [x] 7.2 移除 AVL 相關 data 屬性（`showAvlModal`、`avlSupplierId` 等）與 methods（`addToAvl`、`removeFromAvl`）
- [x] 7.3 移除供應商選單對 `p.product_suppliers` 的依賴（AVL 成員過濾邏輯）
- [x] 7.4 `src/api/modules/compliance.ts`（或相關 api 模組）移除 `addSupplier`、`removeSupplier` AVL 端點呼叫

## 8. 前端：實作開放供應商 Combobox

- [x] 8.1 建立 `SupplierCombobox.vue` 元件：輸入框 + 動態下拉，呼叫 `GET /api/v1/suppliers?onboarding_stage=certified&q=&per_page=20`
- [x] 8.2 Combobox 每筆供應商條目顯示：名稱、代碼（font-mono）、Tier badge
- [x] 8.3 加入 Tier 篩選下拉（Tier 1 / 2 / 3 / 全部），對應 `tier` query param
- [x] 8.4 無結果時顯示「找不到符合的認證供應商」空狀態
- [x] 8.5 當 BomLine 有 `material_group_id` 時，Combobox 條目顯示合規文件需求標籤（`required_doc_types`）
- [x] 8.6 在 BomLine sub-row 供應商新增區塊引入 `SupplierCombobox`，取代舊的 AVL 下拉選單

## 9. 同步 Docker 容器並驗證

- [x] 9.1 `docker cp esgchain-api/app/. esgchain-api:/app/app/ && docker restart esgchain-api`
- [x] 9.2 `docker cp esgchain-web/src/. esgchain-web:/app/src/`
- [x] 9.3 確認 `GET /api/v1/suppliers?onboarding_stage=certified` 正常回傳 5 筆（curl 驗證通過）
- [x] 9.4 確認 ERP 匯入 BomLine 供應商正常（不受 certified 驗證影響）
- [x] 9.5 確認手動新增 non-certified 供應商回傳 422（onboarding_stage: reviewing → 拒絕）
- [x] 9.6 確認手動新增 certified 供應商成功建立 BomLineSupplier（success: true）
- [ ] 9.7 前端瀏覽器確認：BOM 供應商 sub-row 顯示 Combobox，AVL 區塊已消失
