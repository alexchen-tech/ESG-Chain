## 1. 資料庫 Migration

- [x] 1.1 確認 `league/csv` 已在 composer.json，若無則執行 `composer require league/csv`
- [x] 1.2 建立 migration：`create_product_bom_lines_table`（含所有欄位、FK、唯一索引 `buyer_product_id + erp_line_id`）
- [x] 1.3 執行 `php artisan migrate` 並確認資料表結構正確

## 2. Eloquent Model

- [x] 2.1 建立 `app/Models/ProductBomLine.php`（use HasUuids，fillable，belongsTo: buyerProduct、materialGroup、designatedSupplier）
- [x] 2.2 在 `BuyerProduct` 模型新增 `hasMany(ProductBomLine::class)` 關聯方法 `bomLines()`
- [x] 2.3 在 `Supplier` 模型新增 `hasMany(ProductBomLine::class, 'designated_supplier_id')` 關聯方法 `designatedBomLines()`

## 3. BomLine CRUD API

- [x] 3.1 建立 `ProductBomLineController`（index, store, update, destroy）
- [x] 3.2 新增路由：`/api/v1/buyer-products/{buyerProduct}/bom-lines`（resource）
- [x] 3.3 store 驗證：`buyer_product_id` 從路由取，`material_name` required，其餘 optional；`material_group_source` 預設 `'manual'`
- [x] 3.4 update 驗證：所有欄位 sometimes；手動修改 `material_group_id` 時自動設 `material_group_source = 'manual'`
- [x] 3.5 index 回傳含 `materialGroup`、`designatedSupplier` 關聯

## 4. ERP BOM 匯入（JSON）

- [x] 4.1 建立 `BomLineImportService`，實作 `importFromArray(BuyerProduct, array): array` 方法
- [x] 4.2 `importFromArray` 對每筆資料以 `(buyer_product_id, erp_line_id)` 做 `updateOrCreate`
- [x] 4.3 upsert 時：ERP 欄位（`material_name`, `hs_code`, `quantity`, `unit`, `unit_price`, `currency`）總是更新；ESG 標註欄位（`notes`, `material_group_id` when source='manual'）不覆蓋
- [x] 4.4 `supplier_code` 解析：查 `suppliers.code`，找到則設 `designated_supplier_id` 和 `supplier_source='erp_designated'`；找不到則 null 並加入 warnings
- [x] 4.5 在 `ProductBomLineController` 新增 `import` action，處理 JSON body，呼叫 `BomLineImportService`，回傳 `{ created, updated, warnings }`

## 5. ERP BOM 匯入（CSV/Excel）

- [x] 5.1 在 `import` action 支援 multipart/form-data 上傳，偵測 Content-Type 決定解析方式
- [x] 5.2 使用 `league/csv` 解析 CSV，將 header 列對應欄位名稱，轉為陣列後呼叫 `BomLineImportService::importFromArray()`
- [x] 5.3 缺少必填欄位（`erp_line_id` 或 `material_name`）時回傳 422 並說明原因
- [x] 5.4 新增路由：`POST /api/v1/buyer-products/{buyerProduct}/bom-lines/import`

## 6. 合規計算引擎更新

- [x] 6.1 更新 `SupplierComplianceStatusService::getProductCompliance()`：對每個供應商先查 BomLines（`designated_supplier_id = supplier.id`），有則走 BomLine 路徑，無則走 BuyerProductSupplier 路徑
- [x] 6.2 BomLine 路徑：取所有相關 BomLines 的 `materialGroup.required_doc_types` 聯集作為 required_doc_types
- [x] 6.3 在 supplier_results 各項加入 `compliance_basis: 'bom_line'|'product_supplier'|'unconfigured'` 欄位
- [x] 6.4 更新 `getSupplierSummary()`：缺漏文件計算加入 ProductBomLine 路徑（取 TradeGoods 路徑與 BomLine 路徑的 required_doc_types 聯集）
- [x] 6.5 確認現有 `BuyerProductSupplier` 路徑在無 BomLines 時行為不變（regression test 心智驗證）

## 7. 驗收確認

- [x] 7.1 手動測試：建立 BomLine → 查詢產品合規狀態，確認 `compliance_basis = 'bom_line'`
- [x] 7.2 手動測試：CSV 匯入 3 筆，重複匯入，確認冪等（created=0, updated=3）
- [x] 7.3 手動測試：supplier_code 不存在時，warnings 有紀錄，其餘欄位正常匯入
- [x] 7.4 手動測試：手動設定 `material_group_id` 後重複 ERP 匯入，確認 manual 值未被覆蓋
- [x] 7.5 docker cp 所有新增/修改的 PHP 檔案至 api container
