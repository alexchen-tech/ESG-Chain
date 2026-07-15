## 1. 確認 TradeGood 現況

- [x] 1.1 確認 `app/Models/TradeGood.php` 現況：實測 `TradeGood::count()` 正常回傳 15，確認原因是 `class TradeGood extends SalesProduct`（標記 `@deprecated`，向後相容別名，繼承 `sales_products` 表）。**修正先前的誤判**：這代表 `AppServiceProvider`、`PortalTradeGoodController` 等 8 個 Shipment 模組以外的檔案並未壞掉，不需要另開 change。但本次 Shipment 修復仍然必要——問題是 `ShipmentLine` 沒有 `tradeGood()` 關聯方法（與 `TradeGood` class 是否可用無關），且 `exists:trade_goods,id` 驗證規則直接查詢已刪除的資料表字串（不經過 Eloquent model）
- [x] 1.2 確認 `shipment_lines` 資料表欄位：`DESCRIBE shipment_lines` 顯示只有 `sales_product_id`，無 `trade_good_id`/`buyer_product_id`，確認修正方向正確

## 2. 修復 ShipmentLineController

- [x] 2.1 `ShipmentLineController::store()` 驗證規則：移除 `'trade_good_id' => [...'exists:trade_goods,id']`，改為 `'sales_product_id' => ['required','uuid','exists:sales_products,id']`
- [x] 2.2 同方法移除 `'buyer_product_id' => ['nullable','uuid','exists:buyer_products,id']`（`shipment_lines` 表本身沒有這個欄位，純屬殘留）
- [x] 2.3 `$line->load('tradeGood:...', 'buyerProduct:...')` 改為 `$line->load('salesProduct:id,name,product_code,hs_code,is_eudr_applicable')`
- [x] 2.4 實測 `POST /api/v1/shipments/{id}/lines`，帶 `sales_product_id`：第一次仍 500，發現 `shipment_lines.pcf_snapshot_id` 欄位根本不存在於資料庫（migration 檔案存在但從未執行，`migrations` 表確認缺漏），與使用者討論後選擇方案 1（補跑遺漏的 migration，已修正其 `->after('buyer_product_id')` 為 `->after('sales_product_id')` 後執行）。修復後實測回 201，`sales_product_id`/`pcf_snapshot_id` 皆正確寫入，測試資料已清除

## 3. 修復 ShipmentController

- [x] 3.1 `index()`（第 17 行）`with(['lines.tradeGood:...'])` 改為 `with(['lines.salesProduct:...'])`
- [x] 3.2 `show()`（第 50 行）同樣修正 eager load，並移除 `lines.buyerProduct` 這個一併殘留的死關聯
- [x] 3.3 確認前端 `ShipmentDetailView.vue` 大量依賴 `trade_good_name`/`trade_good_code` 顯示欄位名（不依賴 `trade_good_id` 本身），決定保留這些 response key 名稱不變（最小破壞），只改其資料來源為 `$l->sales_product_id`、`$l->salesProduct?->name`/`?->product_code`/`?->hs_code`/`?->is_eudr_applicable`；移除已死的 `buyer_product_id`/`buyer_product_name` 回應欄位
- [x] 3.4 實測 `GET /api/v1/shipments` → 200；`GET /api/v1/shipments/{id}` → 200，detail 內容正確含 trade_good_name/code/hs_code/is_eudr 與 line_batches

## 4. 修復 ShipmentService

- [x] 4.1 第 77、79、81 行 `load('tradeGood')`/`$line->tradeGood` 改為 `salesProduct`。實測時發現新的型別問題：`MarketComplianceChecker::check()` 型別提示為 `TradeGood`（不是 `SalesProduct`），由於 `TradeGood extends SalesProduct`（繼承方向），傳入 `SalesProduct` 實例會 TypeError。採最小改動修法：在呼叫處改用 `TradeGood::find($line->sales_product_id)` 重新取得同一筆資料（同一張 `sales_products` 表）餵給 checker，不更動 `MarketComplianceChecker` 本身（它在 `TradeGoodController` 等其他既有正常運作的呼叫端維持不變）。同時把 `addLine()` 的 PCF 快照鎖定邏輯（`buyer_product_id`→`BuyerProduct::find`）一併改為 `sales_product_id`→`SalesProduct::find()->latestPcfSnapshot()`（`SalesProduct` 沒有 `latest_pcf_snapshot_id` 屬性欄位，改用既有的 `latestPcfSnapshot()` method）
- [x] 4.2 `generateDdsDraft()`（原第 158-166 行）的 `'lines.tradeGood'`/`'lines.buyerProduct'` eager load 與 `$tg = $line->tradeGood` 改為 `'lines.salesProduct'` 與 `$line->salesProduct`，移除死的 buyerProduct 引用
- [x] 4.3 實測新增 EUDR 適用商品（FAB-LYO-003）至 `eudr_dds_status=not_required` 的測試出貨單：API 回 201（不再 TypeError），但 `eudr_dds_status` 維持 `not_required` 未變——查證後確認這是正確行為，非 bug：該商品掛載的供應商物料群組要求 `UFLPA_DECLARATION`/`ORIGIN_CERT`，不要求 `EUDR_DDS`，系統依 `market-compliance-rules` spec 已改用 `MarketComplianceChecker` 動態判定取代舊的 `is_eudr_applicable` 靜態旗標，此次驗證只能確認「不再崩潰」，無法用現有測試資料驗證「狀態確實會變成 draft」這個分支，測試資料已清除
- [x] 4.4 實測 `GET /api/v1/shipments/{id}/dds-draft` → 200，commodities/production_batches/raw_material_origins 結構正確

## 5. 修復 ShipmentSeeder

- [x] 5.1 依任務 1.1 的結論修正：`TradeGood::where('is_eudr_applicable', ...)` 本身運作正常（向後相容別名），**不需要改成** `SalesProduct::where(...)`，維持原樣
- [x] 5.2 `'trade_good_id' => $eudrTg->id` 改為 `'sales_product_id' => $eudrTg->id`
- [x] 5.3 因 Seeder 本身不是 idempotent（`shipment_no` 唯一約束會與既有種子資料衝突），改用 tinker 在獨立的暫時測試資料上覆現 Seeder 同一段邏輯（同樣的 `ShipmentLine::create([...'sales_product_id'=>$eudrTg->id...])`），確認 `sales_product_id` 正確寫入且與 `$eudrTg->id` 一致，測試資料已清除

## 6. 前端調整

- [x] 6.1 `esgchain-web/src/api/modules/shipment.ts`：`shipmentLineApi.create()` payload 型別的 `trade_good_id` 改為 `sales_product_id`
- [x] 6.2 `ShipmentDetailView.vue`：`lineForm.trade_good_id`（本地表單狀態+送出 payload 的 key）全部改名為 `sales_product_id`；確認顯示用的 `trade_good_name`/`trade_good_code` 等保留原樣（不受影響，因為後端 response key 沒有改名）；`ShipmentsView.vue` 未使用這些欄位，不需調整
- [x] 6.3 以 Playwright 實測：登入 → 開啟 SHIP-202606-001 詳情頁（畫面正確顯示既有商品項目與批號分配）→ 點「新增商品項目」→ 搜尋「機能性」→ 選取結果 → 填數量 7 → 送出 → 畫面即時顯示新項目「機能性吸濕排汗布 FAB-DRI-001 7.0000 pcs」，過程無 console error / 無 4xx-5xx response，測試資料已清除

## 7. 更新 openspec spec 文字

- [x] 7.1 `specs/export-shipment-management/spec.md` 的 MODIFIED 區塊已於 proposal 階段準備好（三個需求的 TradeGood 敘述改為 SalesProduct），待 archive 時套用，內容與本次實作一致
- [x] 7.2 確認 `openspec/specs/shipment-management/spec.md` 的「Shipment 客戶綁定」需求不受影響，無需修改

## 8. 全專案掃描收尾

- [x] 8.1 執行掃描，扣除本次已修正的 Shipment/前端檔案後，命中分布於 49 個檔案，已逐類檢視（見 8.2/8.3）
- [x] 8.2 分類結果：
  - **正常運作，無需處理**：`TradeGood`/`TradeGoodSupplier`/`TradeGoodSupplierEmission` 生態系（`AppServiceProvider`、`TradeGoodObserver`、`TradeGoodController`、`TradeGoodService`、`PortalTradeGoodController`、`TradeGoodMarketComplianceController`、`MarketComplianceChecker`、`DashboardService`、`SalesProductController`、`Customer.php`、`MaterialGroup.php`、`Supplier.php`、`SupplierGroup.php`、`SupplierComplianceDoc*`、`BomLineSupplier*`、`ProductionBatch*`、`CustomerService`、`ExportLinkSyncService`/`ExportLinkSeeder` 等）——這些都是針對真實存在的 `trade_goods`(別名)/`trade_good_suppliers`/`trade_good_supplier_emissions` 表的正常操作，先前誤判的「8 個壞掉的檔案」已在 1.1 修正為「實際上都正常」
  - **已知死代碼，沿用前次 change 的判斷**：舊版 `buyer-products` 路由群組與其專屬 Controller/Model/Seeder（`BuyerProductController`、`BuyerProductImportController`、`BuyerProductExportLinkController`、`BuyerProductSupplier`、`BuyerProductTradeGood`、`BuyerProductSeeder`、`routes/api.php` 的 buyer-products 路由區塊、前端 `BuyerProductsView.vue`——已確認未被任何路由引用）。這在 `fix-erp-material-bom-sync-integrity` change 的 design.md 已列為 Open Question，本次不重複處理
- [x] 8.3 **發現一個全新、未被任何先前清單記錄的命中**：`esgchain-api/app/Services/PCF/PcfEmissionGapScanService.php` 第 35 行 `->with(['bomLine.materialItem', 'bomLine.buyerProduct'])`——`ProductBomLine` model 沒有 `buyerProduct()` 關聯（只有 `salesProduct()`/`childSalesProduct()`），這會在 `scan()` 執行時拋 `RelationNotFoundException`。此服務由 `PcfEmissionGapScanJob`（佇列非同步）呼叫，先前測試 BOM 匯入時因 Job 是 dispatch 而非同步執行，沒有當場暴露此問題。**這是第三個受同類遷移遺留問題影響的模組（PCF 缺口掃描），不在本次範圍處理**，記錄下來回報使用者，建議另開 change
