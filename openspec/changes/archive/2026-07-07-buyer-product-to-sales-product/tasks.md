## Tasks

### Phase 1：資料庫 Migration

- [x] 建立 migration：`trade_goods` 改名為 `sales_products`，新增 `applicable_regulations`、`inferred_regulations` 欄位
- [x] 建立 migration：`product_bom_lines.buyer_product_id` 改名為 `sales_product_id`，新增 `child_sales_product_id` nullable FK
- [x] 建立 migration：`pcf_snapshots.buyer_product_id` 改名為 `sales_product_id`
- [x] 建立 migration：`shipment_lines` 移除 `buyer_product_id`，`trade_good_id` 改名為 `sales_product_id`
- [x] 建立資料遷移 Seeder/Migration：將有 `finished_good` 連結的 BuyerProduct 資料合併至對應 SalesProduct
- [x] 建立資料遷移：無連結的 4 筆 BuyerProduct 在 `sales_products` 建立新記錄
- [x] 建立資料遷移：8 筆 `component` 關係轉換為 BomLine 型態 B（`child_sales_product_id`）
- [x] 建立 migration：廢棄 `buyer_product_trade_goods`、`buyer_product_suppliers`、`buyer_products` 表

### Phase 2：後端 Model 與 Service

- [x] 將 `TradeGood` model 改名為 `SalesProduct`，更新 `$table = 'sales_products'`，新增 `applicable_regulations`、`inferred_regulations` fillable 與 cast
- [x] 將 `ProductBomLine` model 的 `buyerProduct()` 關聯改為 `salesProduct()`，新增 `childSalesProduct()` 關聯
- [x] 將 `PcfSnapshot` model 的 `buyer_product_id` FK 更新為 `sales_product_id`
- [x] 將 `ShipmentLine` model 移除 `buyerProduct()` 關聯，`tradeGood()` 改為 `salesProduct()`
- [x] 建立 `ProductBomLineService`：實作 `assertNoCycle()` 循環參照保護邏輯
- [x] 更新 `PcfCalculationService`：接受 `SalesProduct`，BomLine 型態 B 從 `child_sales_product.latestPcfSnapshot` 取碳強度
- [x] 移植 `BuyerProduct::syncInferredRegulations()` 至 `SalesProduct` model
- [x] 廢棄 `ExportLinkSyncService`（PcfCalculationService 已移除依賴）
- [x] 廢棄 `BuyerProduct`、`BuyerProductTradeGood`、`BuyerProductSupplier` model（DB 已刪除）

### Phase 3：後端 Controller 與路由

- [x] 建立 `SalesProductController`（整合原 `TradeGoodController` + BOM CRUD 能力）
- [x] 更新 `ProductBomLineController`：路由前綴改為 `/sales-products/{id}/bom-lines`，支援型態 B（`child_sales_product_id`）
- [x] 更新 `PcfRecalcController`、`PcfSnapshotController`：路由改為 `/sales-products/{id}/pcf-*`
- [x] 更新 `ShipmentLineController`：移除 `buyer_product_id` 邏輯
- [x] 廢棄 `BuyerProductController`、`BuyerProductImportController`、`BuyerProductExportLinkController`、`BuyerProductSupplierController`
- [x] 更新 `routes/api.php`：新增 `/sales-products/*` 路由群組

### Phase 4：前端

- [x] 新增 `api/modules/salesProducts.ts`
- [x] 廢棄 `views/compliance/BuyerProductsView.vue`
- [x] 建立 `views/sales-products/SalesProductsView.vue`，含 BOM 面板、PCF 顯示、法規標籤
- [x] BomLine 新增表單支援型態 B 結構（`child_sales_product_id`）
- [x] 更新 `AppSidebar.vue`：移除「採購品合規」，「出口商品合規」改為「銷售產品」
- [x] 更新 Vue Router：新增 `/sales-products` 路由

### Phase 5：收尾

- [x] 更新 `CLAUDE.md` 欄位歸屬表（新增 SalesProduct 欄位歸屬說明、循環參照禁止事項）
- [x] 更新 Seeder（`TradeGoodSeeder` 改名為 `SalesProductSeeder`，補充測試資料）
- [x] 驗證：`GET /api/v1/sales-products` 正常（15 筆）
- [x] 驗證：`GET /api/v1/sales-products/{id}/bom-lines` 正常
- [x] 驗證：`GET /api/v1/sales-products/{id}/pcf-latest` 正常
- [x] 驗證：`GET /api/v1/sales-products/{id}/suppliers` 正常
- [x] 驗證：PCF 計算流程（型態 A + 型態 B BomLine）
- [x] 驗證：循環參照保護（嘗試建立 A→B→A 應拒絕）
- [x] 驗證：ShipmentLine 出口申報流程正常
- [x] 驗證：側邊欄導航、頁面跳轉無死連結
