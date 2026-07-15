## MODIFIED Requirements

### Requirement: EUDR DDS 狀態自動判定依市場計算
Shipment addLine() SHALL 改呼叫 MarketComplianceChecker，取代現有 `trade_good.is_eudr_applicable` 靜態判定。當加入商品行後，若 MarketComplianceChecker 判定 shipment.target_market 市場要求 EUDR_DDS 且對應文件未滿足，系統 SHALL 將 `eudr_dds_status` 從 "not_required" 更新為 "draft"。

#### Scenario: 加入 EUDR 商品至 EU 出口申報
- **WHEN** Shipment.target_market = "EU"，加入上游含 EUDR_DDS 物料的 TradeGood
- **THEN** 系統呼叫 MarketComplianceChecker，判定 EUDR_DDS 為義務，eudr_dds_status 自動設為 "draft"

#### Scenario: 加入相同商品至 US 出口申報
- **WHEN** Shipment.target_market = "US"，加入相同 TradeGood
- **THEN** MarketComplianceChecker 判定 EUDR_DDS 非 US 義務，eudr_dds_status 維持 "not_required"
