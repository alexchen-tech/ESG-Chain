## Context

2026-06-17 的一批 migration（`2026_06_17_200002` ~ `200006`）將 `BuyerProduct` 合併進 `SalesProduct`：`product_bom_lines.buyer_product_id` 重新命名為 `sales_product_id`，`pcf_snapshots`、`shipment_lines` 的 FK 也同步改向，最終 `200006` 直接 `Schema::dropIfExists('buyer_products')`。但這次重構並未同步更新所有引用 `BuyerProduct` model 的服務層程式碼：

- `BomLineImportService::importFromArray()` / `importFromCsv()` 仍型別提示 `BuyerProduct $product`，內部仍寫 `buyer_product_id`。`ProductBomLineController::import()` 早已改為注入 `SalesProduct $salesProduct`（對應新路由 `sales-products/{salesProduct}/bom-lines/import`），呼叫服務時傳入 `SalesProduct` 實例給型別不符的參數，PHP 直接丟 `TypeError`。已用 admin 帳號實際呼叫 API 並從 `storage/logs/laravel.log` 取得完整堆疊確認。
- `ErpSyncService::syncBomLines()` 仍查詢 `BuyerProduct::where('product_code', ...)`，但 `buyer_products` 表已被刪除，實測 tinker 直接得到 `SQLSTATE[42S02]`。此函式由 `ErpWebhookController::receive()` 的 `entity = 'bom-lines'` 分支觸發，是對外可達的 API。
- 舊版 `routes/api.php` 中仍掛著一整組 `buyer-products/...` 路由（`BuyerProductController`、`BuyerProductImportController`、`BomLineSupplierController` 等共用），其中 `buyer-products/{buyerProduct}/bom-lines/import` 仍指向同一個 `ProductBomLineController::import`方法，但該方法簽章命名的是 `$salesProduct`，路由參數名 `buyerProduct` 與其不一致，隱含同樣會繫結失敗或行為異常——這組路由及其專屬 Controller 的去留是更大範圍的清理工作，列為 Open Question，本次不處理。

另外，`MaterialItemController::store/update` 目前允許任何呼叫端自由指定 `item_code`，這與 CLAUDE.md 的系統邊界規則（ERP 是 MaterialItem item_code 的唯一主檔來源）直接衝突，且與 `material-item-master` spec 現有「建立新料號」需求矛盾——需要同時修正 spec 與實作。

## Goals / Non-Goals

**Goals:**
- 立即修復現役 BOM 匯入功能（`BomLineImportService`），讓 `POST /api/v1/sales-products/{id}/bom-lines/import`（JSON 與 CSV 兩種模式）恢復可用，且維持既有 `material_group_source = 'manual'` 保護行為不變
- 修復 `ErpSyncService::syncBomLines()` 指向已刪除表的問題，改用 `SalesProduct`/`ProductBomLine`，並補上與 `BomLineImportService` 一致的 ERP/ESG 欄位保護
- 為 `ErpSyncService::syncMaterials()` 補上顯式 `ERP_OWNED_MATERIAL_FIELDS`，比照 `ERP_OWNED_SUPPLIER_FIELDS` 既有模式
- 收回 `MaterialItemController` 對 `item_code` 自由建立/修改的權限，使其僅能透過 ERP 同步或標記為管理員 CSV 匯入的路徑寫入

**Non-Goals:**
- 不處理舊版 `buyer-products` 路由群組與其專屬 Controller（`BuyerProductController`、`BuyerProductImportController` 等）的移除或修復——這組路由可能本來就已经是死代碼，需要先確認是否還有任何前端在呼叫，再決定是整組刪除還是修復，列為 Open Question
- 不新增 ERP adapter 的具體實作（`ErpAdapterInterface` 的廠商對接），維持現有抽象介面不變
- 不改變 `material-item-master` spec 既有的 CSV 批次匯入需求（upsert 行為不變），只調整「自由建立」這一條 scenario

## Decisions

1. **BomLineImportService 修復方式：直接替換型別與欄位名，不做相容層**。`BuyerProduct $product` → `SalesProduct $product`，`'buyer_product_id'` → `'sales_product_id'`（共 2 處：查詢條件 line 83、建立欄位 line 104）。
   - 替代方案：保留 `BuyerProduct` 型別、在方法內部用 duck-typing 取 `->id`——放棄，因為這只是掩蓋問題，且 `BuyerProduct` model 本身查詢任何欄位都會因表不存在而炸掉，沒有保留價值。

2. **ErpSyncService BOM 同步保護欄位比照 BomLineImportService 既有邏輯，而非重新發明**。新增 `ERP_OWNED_BOM_FIELDS = ['material_name', 'hs_code', 'quantity', 'unit', 'unit_price', 'currency']`（與 `erp-bom-import` spec 既有 JSON schema 一致），upsert 時若 `material_group_source === 'manual'`，跳過覆蓋 `material_group_id`/`material_group_source`，邏輯與 `BomLineImportService::importFromArray()` line 91-97 完全對齊。
   - 替代方案：把兩條同步路徑（webhook/排程 vs JSON/CSV 匯入）合併成一個 service——範圍過大，且 `ErpSyncService` 與 `BomLineImportService` 的呼叫情境不同（一個是被動接收 webhook/排程拉取的批次資料，一個是使用者主動上傳/貼上資料），暫不合併，僅統一欄位保護邏輯與常數命名風格。

3. **MaterialItem item_code 鎖定方式：後端驗證層擋掉，而非移除 API**。`MaterialItemController::store()` 移除 `item_code` 的一般驗證規則，改為若 request 帶有 `item_code` 一律 422 拒絕（提示「料號代碼僅可透過 ERP 同步或 CSV 匯入建立」）；`update()` 同樣移除 `item_code` 的 `sometimes` 規則。建立料號的唯一管道收斂為既有 `POST /api/v1/material-items/import`（CSV，已存在的 upsert 邏輯不變，本身就已支援以 item_code 為鍵的 upsert，不需新增任何欄位或機制）與未來的 ERP sync。確認 `material_items` 表目前沒有 `import_source` 之類欄位，本次也不新增——既有 CSV import 端點本身即是合規的建立管道，不需要額外標記來源。
   - 替代方案：直接刪除 `store`/`update` 端點——放棄，因為 `update` 仍需保留給 ESG 擁有欄位（`net_weight`、`pcr_percentage`、`material_group_id`、`description`、`is_active`）的編輯用途，只是 `item_code` 要從可編輯欄位中移除。

4. **前端「新增料號」UI 調整為導向匯入，而非完全移除按鈕**。`MaterialItemsView.vue`/`MaterialSettingsView.vue` 的新增料號 Modal 移除 `item_code` 輸入框，改顯示提示文字「料號代碼請透過 CSV 匯入或等待 ERP 同步建立」，並提供「前往 CSV 匯入」的捷徑連結。

## Risks / Trade-offs

- [風險] 修復 `BomLineImportService` 後，過去因為功能壞掉而被擱置的 BOM 匯入請求可能在修復後第一次被大量觸發，需確認系統能承受 → 緩解：沿用既有非同步 Job 派工模式（`PcfEmissionGapScanJob`、`ChemicalComplianceScanJob`），匯入本身仍是同步 HTTP request，沒有新增併發風險
- [風險] 收回 `item_code` 自由建立權限是 **BREAKING change**，若有依賴此 API 的腳本/整合會立即出錯 → 緩解：proposal 已標記 BREAKING；於 design 落地前先確認目前無其他內部服務呼叫 `POST /api/v1/material-items`（已搜尋確認僅前端 `MaterialItemsView.vue`/`MaterialSettingsView.vue` 呼叫）
- [Trade-off] `ErpSyncService` 與 `BomLineImportService` 仍是兩套獨立程式碼維護兩份相似的欄位保護邏輯（非單一 source of truth）→ 可接受，因為兩者觸發情境不同，過度抽象成共用 trait 在沒有第三個使用情境前會增加不必要的間接層

## Migration Plan

1. 修 `BomLineImportService`（型別+欄位名），本地以 admin 帳號重跑先前失敗的匯入請求，確認回 201/200 而非 500
2. 修 `ErpSyncService::syncBomLines`/`syncMaterials`，新增 `ERP_OWNED_BOM_FIELDS`/`ERP_OWNED_MATERIAL_FIELDS`，以 tinker 模擬呼叫確認不再噴 SQL exception
3. 修 `MaterialItemController::store/update` 驗證規則，前端同步調整新增料號 Modal
4. 全程不需要資料庫遷移（欄位早已存在，只是程式碼沒跟上），無需 rollback 腳本；若需回滾，直接 revert 程式碼即可
5. 部署後以 `POST /api/v1/sales-products/{id}/bom-lines/import` 實際發一筆測試資料驗證、並嘗試 `POST /api/v1/material-items` 帶 `item_code` 確認被擋下

## Open Questions

- 舊版 `buyer-products` 路由群組（`BuyerProductController`、`BuyerProductImportController`、`BomLineSupplierController` 共用路徑等）是否還有任何前端頁面在呼叫？若無人呼叫，應該整組移除而非修復，需要再確認前端路由使用狀況後另開一個 change 處理
