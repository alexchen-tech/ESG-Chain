## Why

BuyerProduct（採購品）與 TradeGood（貿易商品）目前是兩個完全獨立的資料孤島，無法表達「一個採購產品可以出口成哪些貿易商品」的業務關係。當 BuyerProduct 完成 PCF 計算或合規追蹤後，其結果無法自動流向對應的 TradeGood，造成 CBAM 碳含量申報與 EUDR 合規狀態需要重複手動維護。

## What Changes

- 新增 `buyer_product_trade_goods` mapping table，支援 BuyerProduct ↔ TradeGood 的 M:N 關聯
- `relation_type` 欄位區分三種業務語義：`finished_good`（成品出口）、`component`（原料/半成品出口）、`equivalent`（跨市場不同 HS Code 報關）
- `bom_line_id`（nullable）讓 component 類型的關聯可精確對應到 BomLine 中的某一行原料
- `BuyerProductsView` 新增「出口商品」Tab，列出已連結的 TradeGood，並提供新增/移除連結的 UI
- BuyerProduct PCF 快照更新後，`finished_good` 類型的 TradeGood `embedded_emissions` 自動同步
- BuyerProduct `inferred_regulations` 更新後，`finished_good` 類型的 TradeGood `is_eudr_applicable` 自動同步

## Capabilities

### New Capabilities

- `buyer-product-export-link`: BuyerProduct 與 TradeGood 的 M:N 關聯管理，含 relation_type 語義、BomLine 精確對應、以及 PCF / 合規狀態的下游同步

### Modified Capabilities

（無既有 spec 異動）

## Impact

- **資料庫**：新增 `buyer_product_trade_goods` table（migration）
- **後端 Model**：`BuyerProduct`、`TradeGood` 新增 M:N 關聯；`PcfCalculationService` 新增 PCF 同步邏輯
- **後端 Controller/Route**：新增 CRUD 端點 `/api/v1/buyer-products/{id}/export-links`
- **前端**：`BuyerProductsView.vue` 新增「出口商品」Tab，`compliance.ts` 新增 API 型別與呼叫
- **Seeder**：`BuyerProductSeeder` 補充 mapping 資料，對齊現有 TradeGood demo 資料
