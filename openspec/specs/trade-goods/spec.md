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

---

<!-- delta: market-compliance-rules -->
## MODIFIED Requirements

### Requirement: cbam_eligible 取代 is_cbam_applicable 語意

`TradeGood.is_cbam_applicable` 欄位 SHALL 重新定義為「cbam_eligible」語意：僅表示 HS code 屬於 CBAM 品類，**不代表實際出口申報義務**。實際義務由 MarketComplianceChecker 依目標市場計算。API response 在過渡期同時回傳 `is_cbam_applicable`（向後相容）與 `cbam_eligible` 兩個欄位，值相同。

#### Scenario: CBAM eligible 商品出口 US

- **WHEN** TradeGood.cbam_eligible = true，target_market = "US"
- **THEN** MarketComplianceChecker 不將 CBAM_REPORT 列入義務，UI 不顯示 CBAM 警告

## REMOVED Requirements

### Requirement: is_eudr_applicable 靜態旗標

- **Reason**: 靜態旗標不考慮目標市場，導致假陽性。EUDR 適用性改由 MarketComplianceChecker 動態計算（market="EU" 且上游物料含 EUDR_DDS 受管制物料）。
- **Migration**: DB 欄位 `is_eudr_applicable` 保留（nullable）但停止寫入。API response 移除此欄位。前端改用 MarketComplianceChecker 結果。
