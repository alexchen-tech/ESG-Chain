## Why

這是第三個被「2026-06-17 SalesProduct 重構未全面收尾」問題波及的模組，前兩個（BOM 匯入路徑 `BomLineImportService`/`ErpSyncService`，與 Shipment 模組）已分別在 `fix-erp-material-bom-sync-integrity`、`fix-shipment-sales-product-migration` 兩個 change 中修復並封存。本次發現 `PcfEmissionGapScanService::scan()`（第 35 行）無條件 eager-load 一個已不存在的 `bomLine.buyerProduct` 關聯（`ProductBomLine` 現在只有 `salesProduct()`/`childSalesProduct()`），代表**這個方法每次被呼叫都會直接拋 `RelationNotFoundException`，從未成功執行過一次**。由於它是透過 `PcfEmissionGapScanJob` 非同步佇列觸發（BOM 匯入後、primary supplier 變更後），失敗不會讓使用者在前端立即看到，因此「碳排缺口掃描自動建立 PcfRequest」這個功能等同於自重構以來完全沒有正常運作。

## What Changes

- `PcfEmissionGapScanService::scan()`：`bomLine.buyerProduct` 改為 `bomLine.salesProduct`；查詢條件 `where('buyer_product_id', ...)` 改為 `where('sales_product_id', ...)`；方法參數 `$buyerProductId` 改名為 `$salesProductId`（含 docblock）
- `BomLineSupplierObserver::dispatchScan()`：`$bomLineSupplier->bomLine?->buyer_product_id` 改為 `$bomLineSupplier->bomLine?->sales_product_id`
- 呼叫端比照修正參數名稱：`PcfEmissionGapScanJob`、`ProductBomLineController::requestEmission()` 等傳入 `scan()` 的呼叫保持語義一致（已確認皆使用具名參數 `buyerProductId:`，需同步改為 `salesProductId:`）
- 更新 `openspec/specs/pcf-emission-gap-scan/spec.md` 第三個需求「採購商手動觸發填報請求」中的範例端點路徑，由 `/api/v1/buyer-products/{id}/bom-lines/{lineId}/request-emission` 改為實際路由 `/api/v1/sales-products/{id}/bom-lines/{lineId}/request-emission`
- 修復後實際驗證 `scan()` 能完整跑完不報錯，並驗證確實能建立 `PcfRequest`/`PcfRequestLine`——這是過去從未被驗證過的路徑，因為它從未成功執行過

## Capabilities

### New Capabilities
（無）

### Modified Capabilities
- `pcf-emission-gap-scan`：「採購商手動觸發填報請求」需求的範例端點路徑由 buyer-products 改為 sales-products；其餘兩個需求（BOM 匯入後自動觸發、供應商切換時自動觸發）文字本身未提及具體模型名稱，不需修改

## Impact

- 後端：`PcfEmissionGapScanService.php`（核心修復）、`BomLineSupplierObserver.php`、`PcfEmissionGapScanJob.php`、`ProductBomLineController.php`（僅參數名稱對齊，不改變對外 API 行為）
- 受影響功能：BOM 匯入後自動碳排缺口掃描、primary supplier 變更後自動掃描、採購商手動觸發填報請求——三者目前皆透過同一個 `scan()` 方法，全部一併恢復
- 不在本次範圍：先前 `fix-shipment-sales-product-migration` 的 design.md 已完成一次全專案掃描並列出已知清單（TradeGood 別名生態系確認正常、舊版 buyer-products 路由群組確認為已知死代碼）。本次不重新跑全量掃描，只需在 tasks 最後確認那份已知清單中的項目沒有狀態變化；若驗證過程中意外發現第四個模組，記錄下來但不在本次處理
