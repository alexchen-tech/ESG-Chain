## 1. 資料庫 Migration

- [x] 1.1 建立 `create_bom_line_suppliers_table` migration（id UUID, bom_line_id, supplier_id, role ENUM, source ENUM, sort_order）
- [x] 1.2 建立 `migrate_designated_supplier_to_bom_line_suppliers` migration（將現有 designated_supplier_id 資料插入 bom_line_suppliers 作為 role=primary）
- [x] 1.3 建立 `alter_product_bom_lines_add_type_drop_designated` migration（新增 bom_line_type DEFAULT 'material'，DROP COLUMN designated_supplier_id）
- [x] 1.4 建立 `alter_material_groups_add_group_type` migration（新增 group_type ENUM DEFAULT 'material'）
- [x] 1.5 建立 `alter_buyer_product_suppliers_drop_material_group` migration（DROP COLUMN material_group_id, material_group_source）
- [x] 1.6 執行所有 migration 並驗證 schema 正確

## 2. 後端 Model 與關聯

- [x] 2.1 建立 `BomLineSupplier` Model（HasUuids，belongsTo BomLine, belongsTo Supplier，fillable: role, source, sort_order）
- [x] 2.2 更新 `ProductBomLine` Model：移除 designated_supplier_id 屬性，新增 bom_line_type，新增 `bomLineSuppliers()` hasMany 關聯
- [x] 2.3 更新 `BuyerProductSupplier` Model：移除 material_group_id, material_group_source 屬性
- [x] 2.4 更新 `MaterialGroup` Model：新增 group_type 屬性，更新 fillable

## 3. Seeder 更新

- [x] 3.1 更新 `MaterialGroupSeeder`：新增 group_type 欄位，新增服務類型群組（成衣縫製服務、染整加工服務、木製包材服務）
- [x] 3.2 更新 `ProductBomLineSeeder`：移除 designated_supplier_id，改為在 bom_line_suppliers 中建立 primary 記錄；為成衣/染整 BomLine 設定 bom_line_type=service
- [x] 3.3 更新 `BuyerProductSeeder`/`SupplierSeeder` 移除 material_group_id 相關欄位（如有）
- [x] 3.4 執行完整 `db:seed` 驗證資料正確性

## 4. 合規計算引擎重寫

- [x] 4.1 重寫 `SupplierComplianceStatusService::getProductCompliance()`：廢棄 ProductSupplier 路徑，改為迭代 BomLine → BomLineSupplier
- [x] 4.2 重寫合規狀態聚合邏輯：按 (BomLine, Supplier) 組合產生結果，向上聚合至產品層級
- [x] 4.3 重寫 `SupplierService::syncApplicableRegulations()`：從 bom_line_suppliers JOIN bom_lines.material_group 聚合
- [x] 4.4 更新 `getSupplierBomRequirements()`：從 bom_line_suppliers 查詢而非 ProductSupplier
- [x] 4.5 更新 API response 結構：包含 BomLine 維度的合規詳情（bom_line_id, material_name, bom_line_type, suppliers[]{role, doc_status, docs}）

## 5. Portal 採購需求 API

- [x] 5.1 更新 `PortalController::procurementRequirements()`：呼叫重寫後的 `getSupplierBomRequirements()` 並確認 response 格式正確
- [x] 5.2 驗證供應商 JWT 登入後取得自己的 BomLine 採購需求（含 role、bom_line_type、required_doc_types）

## 6. 前端更新

- [x] 6.1 更新 `BuyerProductsView.vue` BOM 表格：顯示 BomLineSupplier 多供應商（primary badge + +N 替代），移除 ProductSupplier material_group 欄位
- [x] 6.2 更新 `MaterialComplianceView.vue`：合規狀態從 BomLine 維度展示（每條 BomLine 顯示所有供應商文件狀態）
- [x] 6.3 更新 `SupplierComplianceDetailView.vue`：細節頁顯示每條 BomLine 對應的文件需求與狀態
- [x] 6.4 更新供應商入口採購需求頁：顯示 bom_line_type（原物料/服務）、role（主要/替代）

## 7. 驗證與收尾

- [x] 7.1 執行 audit query：確認所有 BomLine 均有至少一個 BomLineSupplier（primary），列出例外項目
- [x] 7.2 比對新舊合規計算結果，確認已消除靜默缺口（TEX-007/Prym Germany 案例驗證）
- [x] 7.3 驗證 BomLine 無 MaterialGroup 時合規計算正確跳過（不產生錯誤）
- [x] 7.4 驗證 `syncApplicableRegulations` 執行後 supplier.applicable_regulations 正確更新
- [x] 7.5 移除合規計算中所有 ProductSupplier 路徑的殘留程式碼
