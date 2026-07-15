## Why

現有 `TradeGood` 模型以「供應商視角」設計（`supplier_id` NOT NULL），但平台實際使用者是中心廠 / 貿易商（採購商角色），他們需要管理的是「我自己出口 / 出售的品項」及其法規暴露，而非被動追蹤供應商的進口品。現有模型語意錯置，導致前端視圖無法建立，CBAM / EUDR 申報義務也對應不到正確主體。

## What Changes

- **TradeGood 語意翻轉**：從「供應商的進口品」改為「中心廠 / 貿易商自己的出口品」
- **移除 `supplier_id`**：TradeGood 不再強制隸屬單一供應商
- **新增 `trade_good_suppliers` 關聯表**：一個出口品可對應多個上游供應商（BOM 結構），記錄每個供應商提供的物料群組
- **新增前端 `TradeGoodsView.vue`**：品項清單含 CBAM / EUDR 狀態、上游合規摘要；展開面板管理上游供應商
- **`embedded_emissions` 改為供應商回報**：供應商透過 Supplier Portal 填寫對應品項的嵌入碳排（kgCO2e/unit），中心廠可在 TradeGood 詳情中查閱與確認
- **Portal 新增碳排回報欄位**：供應商在 Portal 可針對關聯的 TradeGood 填報嵌入碳排數值與計算說明

## Capabilities

### New Capabilities
- `trade-good-registry`: 中心廠出口品目錄 — 管理自有出口品項、HS Code、CBAM/EUDR 自動判定、上游供應商 BOM 關聯
- `trade-good-supplier-emissions`: 供應商嵌入碳排回報 — 供應商透過 Portal 填寫指定 TradeGood 的嵌入碳排，中心廠確認後作為 CBAM 申報依據

### Modified Capabilities
- `supplier-compliance-status`: TradeGood 改版後，合規狀態計算需支援 trade_good_suppliers 的多對多結構（原為單一 supplier_id 路徑）

## Impact

- **esgchain-api**：`TradeGood` model 移除 supplier 關聯、新增 `tradeGoodSuppliers()` 關聯；新增 `trade_good_suppliers` 資料表；`TradeGoodController` 全面改寫；新增 Portal 碳排回報 endpoint
- **esgchain-web**：新增 `TradeGoodsView.vue`（路由 `/trade-goods` 已在 router 但無 view）；Portal 新增碳排回報 UI
- **資料庫**：`trade_goods.supplier_id` 改為 nullable 後刪除；新增 `trade_good_suppliers` 表
- **BREAKING**：現有 `trade_goods` 資料需 migration 清理（`supplier_id` 欄位移除）；現有 `SupplierComplianceDoc.trade_good_id` 語意不變，保留
