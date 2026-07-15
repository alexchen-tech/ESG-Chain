## 1. 修復 PcfEmissionGapScanService

- [x] 1.1 第 35 行 `->with(['bomLine.materialItem', 'bomLine.buyerProduct'])` 改為 `->with(['bomLine.materialItem', 'bomLine.salesProduct'])`，並移除已不再使用的 `use App\Models\BuyerProduct;` import
- [x] 1.2 第 42 行 `$q->where('buyer_product_id', $buyerProductId)` 改為 `$q->where('sales_product_id', $salesProductId)`
- [x] 1.3 方法簽章 `scan(?string $buyerProductId = null, ...)` 改為 `scan(?string $salesProductId = null, ...)`，docblock 同步更新（第 19、24 行）
- [x] 1.4 第 92-93 行（建立完成的 log）`'buyer_product_id' => $buyerProductId` 改為 `'sales_product_id' => $salesProductId`，確認方法內其餘程式碼無其他殘留引用

## 2. 修復 BomLineSupplierObserver

- [x] 2.1 `dispatchScan()` 第 32 行 `$bomLineSupplier->bomLine?->buyer_product_id` 改為 `$bomLineSupplier->bomLine?->sales_product_id`
- [x] 2.2 已於任務 4.3 實測確認：修正後 `$salesProductId` 正確解析出實際的 SalesProduct id（先前因屬性不存在靜默回傳 null，等同全域掃描），語意修正生效

## 3. 修復呼叫端參數名稱

- [x] 3.1 `PcfEmissionGapScanJob`：建構子參數 `$buyerProductId` 改為 `$salesProductId`，docblock 同步更新，log 欄位 `'buyer_product_id'` 改為 `'sales_product_id'`
- [x] 3.2 `PcfEmissionGapScanJob::handle()` 呼叫 `$service->scan(buyerProductId: ...)` 改為具名引數 `salesProductId: ...`
- [x] 3.3 `ProductBomLineController::requestEmission()` 呼叫 `$this->gapScanService->scan(buyerProductId: $salesProduct->id, ...)` 改為 `salesProductId: $salesProduct->id`；確認 `BomLineImportService` 對同一個 Job 的 dispatch 是用位置參數（`PcfEmissionGapScanJob::dispatch($product->id, null, 'system_bom_import')`），不受建構子參數改名影響，無需修改

## 4. 驗證

- [x] 4.1 實測過程中發現現有種子資料的 (material_item × supplier) 全部都已有 MaterialItemEmission 記錄（無真實缺口可測），故建立全新的測試用 MaterialItem + ProductBomLine + BomLineSupplier(primary) 製造真實缺口。第一次呼叫 `POST /api/v1/sales-products/{id}/bom-lines/{lineId}/request-emission` 仍回 500，發現連鎖的第二、第三個遺漏 migration：`pcf_requests.trigger_source`（migration `2026_06_17_000002`）與 `pcf_request_lines.material_item_id`/`fulfilled_emission_id`（migration `2026_06_17_000003`）皆從未執行過。比照前次「補跑遺漏 migration」的處理方式，確認兩者皆為自包含、安全的 migration 後執行。修復後實測回 **200**（非預期的 201，原 controller 本來就沒有用 201，這是既有行為非本次改動範圍）且 `created:1`，確認 PcfRequest + PcfRequestLine 首次成功被建立
- [x] 4.2 實測重複觸發同一筆 BOM 行：回 200 且 `skipped:1`（沒有重複建立第二筆 PcfRequestLine，去重邏輯正確）。**發現 spec 與實作不一致**：`pcf-emission-gap-scan` spec 寫的是「回傳 409」，但 `ProductBomLineController::requestEmission()` 实際上沒有任何 409 邏輯，永遠回 200 並把 created/skipped 計數放在 body 裡。這是與本次 BuyerProduct/SalesProduct 修復**無關**的既有 spec/實作落差（從未被實作過，不是本次改動造成的回歸），不在本次範圍修正，記錄供後續參考
- [x] 4.3 以 tinker 直接呼叫 `BomLineSupplierObserver::created()` 確認 dispatch 不拋例外；因實際 `config('queue.default')` 解析為 `redis`（與 .env 寫的 `database` 不一致，且整個 docker-compose 沒有任何 Laravel queue worker 容器，`jobs` 表恆為空），佇列任務不會被自動處理。改用 tinker 直接建構並同步執行 `PcfEmissionGapScanJob::handle()`，確認 `sales_product_id` 正確解析、`scan()` 完整跑完不報錯。Queue worker 缺失是另一個與本次修復無關的既有環境問題，記錄但不在本次處理
- [x] 4.4 已清除所有測試資料：PcfRequestLine、PcfRequest、BomLineSupplier、ProductBomLine、MaterialItem（皆為本次驗證新建立，已逐一 forceDelete 確認移除）

## 5. 更新 openspec spec 文字與收尾

- [x] 5.1 `openspec/specs/pcf-emission-gap-scan/spec.md`「採購商手動觸發填報請求」需求的範例端點路徑已於本次 specs/pcf-emission-gap-scan/spec.md 的 MODIFIED 區塊準備好，待 archive 時套用
- [x] 5.2 核對 `fix-shipment-sales-product-migration` design.md 留下的已知清單（TradeGood 別名生態系、舊版 buyer-products 路由群組死代碼）：狀態無變化，本次未觸碰相關檔案，確認不需要在本次一併處理；未重新執行全專案 grep 掃描
- [x] 5.3 本次驗證過程中**確實發現了「未被任何先前清單記錄」的新項目**，但不是第四個程式碼模組，而是兩個額外的孤兒 migration（`2026_06_17_000002`、`2026_06_17_000003`，與先前已知的 000001/000004/000005/000006 同批，皆從未執行過）。已記錄並依使用者先前對同類情境的指示（補跑遺漏 migration）處理；另外發現一個與 BuyerProduct/SalesProduct 完全無關的環境問題（queue.default 實際解析為 redis 但無任何 worker 容器，jobs 永遠不會被處理）與一個 spec/實作落差（409 從未被實作），皆記錄但不在本次處理範圍
