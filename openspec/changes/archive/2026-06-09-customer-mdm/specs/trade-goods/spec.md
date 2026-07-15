## MODIFIED Requirements

### Requirement: TradeGood 客戶歸屬
出口商品的外部料號（product_code）須與客戶綁定，確保料號唯一性在客戶維度成立。

#### Scenario: 建立客戶專屬出口商品
- **WHEN** 建立 TradeGood 時提供 customer_id
- **THEN** 在同一客戶下 product_code 不得重複（UNIQUE customer_id + product_code）

#### Scenario: 重複外部料號
- **WHEN** 同一 customer_id 下提交相同 product_code
- **THEN** 返回 422，錯誤訊息指出該客戶下料號已存在

#### Scenario: 通用出口商品（無客戶歸屬）
- **WHEN** 建立 TradeGood 時 customer_id 為空
- **THEN** 允許建立，product_code 無全域唯一性約束（多筆 NULL 不衝突）
