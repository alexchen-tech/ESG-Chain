## Why

實測 `GET /api/v1/shipments` 回 500，Laravel log 確認 `Call to undefined relationship [tradeGood] on model [App\Models\ShipmentLine]`。這與 `fix-erp-material-bom-sync-integrity` change 修復的 BuyerProduct→SalesProduct 問題同根同源：2026-06-17 那批 SalesProduct 重構（`trade_goods`／`buyer_products` 合併為 `sales_products`）只改了資料表結構與 Model 關聯（`ShipmentLine` 現在只有 `salesProduct()`，沒有 `tradeGood()`；`trade_goods` 表已被刪除），但 Shipment 模組的 Controller/Service/Seeder/前端程式碼沒有跟進更新，導致整個模組（列表、詳情、新增/編輯出貨明細、PCF 加權計算、種子資料）全部壞掉，而不只是先前修過的 BOM 模組。

## What Changes

- `ShipmentController::index/show`：`with(['lines.tradeGood:...'])` 改為 `with(['lines.salesProduct:...'])`
- `ShipmentLineController::store/update`：驗證規則 `'trade_good_id' => [...'exists:trade_goods,id']` 改為 `'sales_product_id' => [...'exists:sales_products,id']`；`load('tradeGood:...')` 改為 `load('salesProduct:...')`
- `ShipmentService`：所有 `load('tradeGood')`、`$line->tradeGood` 改為 `salesProduct`，含建立 Shipment 時的 EUDR 適用性判斷與 PCF 加權計算邏輯
- `ShipmentSeeder`：`'trade_good_id' => $eudrTg->id` 改為 `'sales_product_id' => $eudrTg->id`
- 前端 `esgchain-web/src/api/modules/shipment.ts`：請求欄位 `trade_good_id` 改為 `sales_product_id`（回應欄位 `trade_good_name`/`trade_good_code` 是否一併改名留給 design.md 決定，避免不必要的前端顯示邏輯連動修改）
- 更新 `openspec/specs/shipment-management`、`openspec/specs/export-shipment-management` 中仍寫著 TradeGood/trade_good_id 的需求文字，改為 SalesProduct 對應命名
- 新增一個專案層級的掃描任務（`grep -rn 'tradeGood\|trade_good_id\|TradeGood'`），確認沒有第三個模組漏改

## Capabilities

### New Capabilities
（無）

### Modified Capabilities
- `export-shipment-management`：「出口申報批次資料模型」「批號分配與出口 PCF 計算」「出口申報管理 UI」三個既有需求中 TradeGood 相關敘述改為 SalesProduct

（`shipment-management` 的「Shipment 客戶綁定」需求本身不涉及 TradeGood，行為不變，不需要 delta spec）

## Impact

- 後端：`ShipmentController.php`、`ShipmentLineController.php`、`ShipmentService.php`、`ShipmentSeeder.php`
- 前端：`esgchain-web/src/api/modules/shipment.ts`，及任何呼叫該 API 模組的 View（`ShipmentsView.vue`、`ShipmentDetailView.vue`）
- 受影響功能：出口申報列表/詳情查詢、新增/編輯出貨明細、批號分配與 PCF 加權計算、EUDR DDS 草稿生成（依賴 ShipmentLine→SalesProduct 關聯）
- 不在本次範圍：若掃描發現第三個模組也有同類殘留引用，另開新的 change 處理，不在本次一併修，避免單次變更範圍失控
