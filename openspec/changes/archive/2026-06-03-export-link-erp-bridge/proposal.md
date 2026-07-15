## Why

Phase 2 計劃在 ESG·Chain 引入出口批次（ProductionBatch / Shipment）管理，ERP 系統將透過 Webhook 推送生產批號資料。推送的 payload 包含 ERP 自身的料號（`erp_product_code`），但目前 `buyer_product_trade_goods` 型號對應表只有 UUID，無法讓 ERP Webhook 快速匹配到正確的出口連結。

此變更在 Phase 1 提前「留門」，以最低成本為 Phase 2 的 ERP 整合預留匹配橋接欄位，避免 Phase 2 上線時需要回頭修改已有的型號對應資料。

## What Changes

- `buyer_product_trade_goods` 新增 `erp_product_code`（nullable string）欄位
- `BuyerProductTradeGood` Model fillable 更新
- 前端「新增出口商品連結」Modal 新增 ERP 料號選填輸入
- API `store()` / `index()` 支援 `erp_product_code` 欄位的讀寫

## Capabilities

### Modified Capabilities

- `buyer-product-registry`：BuyerProduct 出口連結 M:N 關係，新增 `erp_product_code` 橋接欄位供 ERP Webhook 匹配用

## Impact

- **資料庫**：`buyer_product_trade_goods` 加一個 nullable 欄位，migration only
- **後端**：`BuyerProductTradeGood` Model、`BuyerProductExportLinkController` store/index 小幅更新
- **前端**：Modal 新增一個選填輸入欄位，ExportLink interface 新增欄位
- **無破壞性變更**：欄位 nullable，不影響現有資料與既有 API 呼叫
