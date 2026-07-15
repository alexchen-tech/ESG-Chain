## Why

出口商品（TradeGood）使用客戶的外部料號（客戶料號），但目前系統缺乏 Customer 主檔實體，導致：
1. 無法追蹤外部料號歸屬於哪個客戶，product_code 在多客戶情境下無法唯一識別
2. 出貨單（Shipment）的 `target_market` 僅為地區字串（"EU"），缺少具體進口商資訊，無法滿足 CBAM 申報所需的 EORI Number
3. 客戶訂單號（customer_po_no）無處記錄，造成跨系統對帳困難
4. 三角貿易情境（Scenario B）下，同一批號分撥給不同客戶的出貨單，缺乏明確的客戶對應關係

## What Changes

1. **新增 Customer 主檔**：`customers` + `customer_contacts` 資料表，含 EORI Number 欄位與歐盟成員國條件驗證
2. **TradeGood 加入 customer_id**：外部料號歸屬明確化，加 UNIQUE(customer_id, product_code) 約束
3. **Shipment 加入 customer_id 與 customer_po_no**：target_market 改由 Customer.country_code 自動帶入
4. **前端 UI**：Customer 管理頁面（CRUD）、TradeGood 與 Shipment 表單加入客戶選擇

## Capabilities

### New Capabilities
- `customer-mdm`: 客戶主檔管理——Customer 實體 CRUD、customer_contacts、EORI 驗證、customer_type 分類

### Modified Capabilities
- `trade-goods`: TradeGood 加入 customer_id，product_code 唯一性約束調整
- `shipment-management`: Shipment 加入 customer_id、customer_po_no，target_market 改為自動帶入

## Impact

- **esgchain-api**：新增 Migration、Model、Controller、Service、FormRequest（Customer）；修改 TradeGood、Shipment migration 與 Model
- **esgchain-web**：新增 CustomerMdmView.vue；修改 TradeGoodsView、ShipmentsView 的表單
- **既有資料**：TradeGood.customer_id nullable，既有資料不需遷移，漸進補填
- **CBAM 合規**：Shipment → Customer.eori_number 可直接用於 CBAM 申報單生成
