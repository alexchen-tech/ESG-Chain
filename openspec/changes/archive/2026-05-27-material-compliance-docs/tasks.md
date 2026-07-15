# Tasks: material-compliance-docs

## 1. 資料庫 Migrations

- [x] 1.1 建立 `material_groups` migration
  - 欄位：id (uuid), name, description, hs_code_prefixes (json), required_doc_types (json), is_system (bool default true), timestamps
- [x] 1.2 建立 `buyer_products` migration
  - 欄位：id (uuid), name, product_code (varchar nullable), description (text nullable), applicable_regulations (json default []), timestamps, softDeletes
- [x] 1.3 建立 `buyer_product_suppliers` migration（樞紐表）
  - 欄位：id (uuid), buyer_product_id (uuid FK), supplier_id (uuid FK), material_group_id (uuid FK nullable), timestamps
  - index: (buyer_product_id, supplier_id, material_group_id)
- [x] 1.4 建立 `supplier_compliance_docs` migration
  - 欄位：id (uuid), supplier_id (uuid FK), trade_good_id (uuid FK nullable), doc_type (varchar 50), file_path (varchar), issued_at (date nullable), expires_at (date nullable), verified_at (timestamp nullable), verified_by (uuid nullable), notes (text nullable), timestamps, softDeletes
- [x] 1.5 Alter `trade_goods`：新增 `material_group_id` (uuid nullable FK)
- [x] 1.6 建立 Seeder：預載 5 個標準物料群組（棉紡/木質/電子五金/化工塑料/機電終端）含 HS Code 前綴規則與 required_doc_types

## 2. Models

- [x] 2.1 建立 `MaterialGroup` Model（HasUuids, fillable, hasMany supplierComplianceDocs）
- [x] 2.2 建立 `BuyerProduct` Model
  - HasUuids, fillable, softDeletes
  - `suppliers()` BelongsToMany via buyer_product_suppliers（withPivot material_group_id）
  - `productSuppliers()` HasMany BuyerProductSupplier
- [x] 2.3 建立 `BuyerProductSupplier` Model（HasUuids, belongsTo BuyerProduct/Supplier/MaterialGroup）
- [x] 2.4 建立 `SupplierComplianceDoc` Model
  - HasUuids, fillable, softDeletes, belongsTo Supplier/TradeGood
  - Appended accessor `status`：依 expires_at 動態計算 valid/expiring_soon/expired/pending
- [x] 2.5 更新 `TradeGood` Model：`belongsTo MaterialGroup`（nullable）

## 3. 後端 API — 物料群組

- [x] 3.1 建立 `MaterialGroupController`
  - `index()`：GET /api/v1/material-groups（所有採購商角色可讀）
  - `store()`：POST /api/v1/material-groups（admin only）
  - `update()`：PUT /api/v1/material-groups/{group}（admin only）
- [x] 3.2 HS Code 自動推導：`TradeGoodObserver@saving` 比對 hs_code 前綴填入 material_group_id（若無手動指定）
- [x] 3.3 更新 `TradeGoodController`：store/update 接受 material_group_id（手動覆蓋自動推導）
- [x] 3.4 注冊物料群組路由

## 4. 後端 API — 採購商產品

- [x] 4.1 建立 `BuyerProductController`
  - `index()`：GET /api/v1/buyer-products（含 supplier 關聯與 applicable_regulations）
  - `store()`：POST /api/v1/buyer-products
  - `update()`：PUT /api/v1/buyer-products/{product}
  - `destroy()`：DELETE /api/v1/buyer-products/{product}（admin only，軟刪除）
- [x] 4.2 建立 `BuyerProductSupplierController`
  - `store(BuyerProduct)`：POST /api/v1/buyer-products/{product}/suppliers（新增關聯）
  - `destroy(BuyerProduct, BuyerProductSupplier)`：DELETE（移除關聯）
- [x] 4.3 建立 `BuyerProductImportController`
  - `store()`：POST /api/v1/buyer-products/import（CSV 上傳）
  - 解析 CSV 欄位：name, product_code, description, supplier_tax_id_or_name, material_group_name
  - 寬容模式：supplier 找不到 → 加入 warnings，不阻斷整批
  - 回傳：{ created_count, skipped_count, warnings[] }
- [x] 4.4 建立 `BuyerProductService`
  - `syncApplicableRegulations(BuyerProduct)`：重新計算並更新 applicable_regulations（依所有關聯 material_group 的 required_doc_types 聯集）
  - 在新增/移除 BuyerProductSupplier 後呼叫此方法
- [x] 4.5 注冊產品路由

## 5. 後端 API — 合規文件

- [x] 5.1 建立 `SupplierComplianceDocController`
  - `index(Supplier)`：GET /api/v1/suppliers/{supplier}/compliance-docs（含 ?status= 篩選）
  - `store(Supplier)`：POST /api/v1/suppliers/{supplier}/compliance-docs（採購商 + 供應商均可）
  - `destroy(SupplierComplianceDoc)`：DELETE（僅上傳者或 admin）
  - `verify(SupplierComplianceDoc)`：POST .../verify（採購商設定 verified_at）
  - `unverify(SupplierComplianceDoc)`：DELETE .../verify
- [x] 5.2 File upload：`storeAs` 至 local disk，validate 10MB 上限（`max:10240`）
- [x] 5.3 確認 supplier/sup_esg 角色可透過 Portal API 存取自己的 compliance-docs
- [x] 5.4 注冊合規文件路由

## 6. 後端 API — 合規健康度

- [x] 6.1 建立 `SupplierComplianceStatusService`
  - `getSupplierSummary(Supplier)`：計算 total/valid/expiring_soon/expired/pending counts + missing_required_types[]
  - `getProductCompliance(BuyerProduct)`：計算產品層級健康度，回傳 overall_status + supplier_results[]
  - `getDashboard()`：所有供應商健康度清單（for 採購商看板）
  - `getProductDashboard()`：所有產品健康度清單（for 產品看板）
- [x] 6.2 新增端點至 controller 或獨立 `ComplianceDashboardController`
  - GET /api/v1/suppliers/{supplier}/compliance-docs/summary
  - GET /api/v1/buyer-products/{product}/compliance-status
  - GET /api/v1/compliance/dashboard（供應商視角）
  - GET /api/v1/compliance/product-dashboard（產品視角）

## 7. 前端 — API 模組

- [x] 7.1 建立 `esgchain-web/src/api/modules/compliance.ts`
  - materialGroupApi：list
  - buyerProductApi：list, create, update, destroy, addSupplier, removeSupplier, import, getProductCompliance
  - complianceDocApi：list, create, destroy, verify, unverify, getSupplierSummary
  - complianceDashboardApi：getSupplierDashboard, getProductDashboard

## 8. 前端 — 採購商產品管理

- [x] 8.1 建立 `BuyerProductsView.vue`（路由：/compliance/products）
  - 產品清單 table（name, product_code, applicable_regulations badges, supplier 數量）
  - 建立產品 Modal（name, product_code, description）
  - 點擊產品 → 展開關聯供應商列表（含 material_group）+ 新增/移除關聯
  - CSV 匯入按鈕 + 匯入結果 Modal（顯示 warnings）

## 9. 前端 — 合規看板

- [x] 9.1 建立 `MaterialComplianceView.vue`（路由：/compliance）
  - Tab 切換：供應商視角 / 產品視角
  - 供應商視角：供應商清單 + 合規健康度 badge + 篩選「有問題」
  - 產品視角：產品清單 + applicable_regulations badges + overall_status 色彩 + 篩選特定法規
- [x] 9.2 建立 `SupplierComplianceDetailView.vue`（路由：/compliance/suppliers/:id）
  - 文件清單 table（doc_type, expires_at, status badge, verified badge）
  - 審核按鈕（採購商）
  - 上傳新文件 Modal

## 10. 前端 — 供應商 Portal

- [x] 10.1 建立 `SupplierCompliancePortalView.vue`（路由：/supplier/compliance）
  - 我的合規文件清單（status badge, expires_at）
  - 上傳新文件表單（doc_type 下拉, issued_at, expires_at, file）
- [x] 10.2 Portal 導覽加入「合規文件」入口

## 11. 側邊欄 & 路由

- [x] 11.1 `AppSidebar.vue`：新增「物料合規」群組（icon: ◑，roles: admin/buyer/sustain/comply）
  - 子項目：合規看板（/compliance）、產品清單（/compliance/products）、貿易商品（/trade-goods）
- [x] 11.2 `router/index.ts`：注冊所有新路由，RBAC 設定
  - /compliance → admin/buyer/sustain/comply
  - /compliance/products → admin/buyer/sustain/comply
  - /compliance/suppliers/:id → admin/buyer/sustain/comply
  - /supplier/compliance → supplier/sup_esg
