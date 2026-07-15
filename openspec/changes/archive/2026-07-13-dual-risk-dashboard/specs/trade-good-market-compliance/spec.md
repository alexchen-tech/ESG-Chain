## MODIFIED Requirements

### Requirement: 出口商品市場合規檢核服務

`MarketComplianceChecker::check(TradeGood, market)` SHALL 依以下步驟計算合規狀態，並額外輸出路徑風險所需資料：

1. 收集 TradeGood 所有上游供應商（TradeGoodSupplier）的物料群組 required_doc_types，取聯集
2. 若 market = "EU" 且 trade_good.cbam_eligible = true，加入 CBAM_REPORT 至需求集合
3. 從 market_compliance_rules 取 market 當前有效規則，與需求集合取交集，得出「實際義務文件清單」
4. 逐一比對 SupplierComplianceDoc，判定各文件狀態：valid / expiring_soon / expired / missing
5. 回傳 overall（pass / warning / fail）及各文件明細
6. **新增** 同時回傳各責任供應商的 `axis1_score`（ESG 暴露分，來自最新 RiskAssessment）與 `latest_pcf_emission_kg`（最新 PcfSnapshot 中該供應商碳排貢獻）

esgchain-api SHALL 在合規明細 API 回傳中包含 `supplier_risk_context: [{supplier_id, name, axis1_score, has_data_gap, emission_kg}]`，供前端展示義務缺口面板的責任供應商 ESG 風險資訊。

#### Scenario: EU 市場木材商品合規檢核（含供應商風險資訊）

- **WHEN** TradeGood 上游物料含 EUDR_DDS，market = "EU"，無有效 EUDR_DDS 文件
- **THEN** overall = "fail"，results 包含 `{ doc_type: "EUDR_DDS", status: "missing" }`，且 `supplier_risk_context` 包含各責任供應商的 axis1_score 與碳排資訊

#### Scenario: US 市場木材商品不觸發 EUDR

- **WHEN** TradeGood 上游物料含 EUDR_DDS，market = "US"
- **THEN** EUDR_DDS 不在 US 規則中，overall 計算不包含 EUDR_DDS

#### Scenario: 上游無受管制物料

- **WHEN** TradeGood 無上游 required_doc_types 且 cbam_eligible = false
- **THEN** overall = "pass"，required = []，supplier_risk_context 仍回傳供應商 ESG 資訊

#### Scenario: 供應商無 axis1_score 時標記 data_gap

- **WHEN** 責任供應商尚未完成 Multi-tag SAQ 評分，axis1_score 為 null
- **THEN** `supplier_risk_context` 中該供應商 `has_data_gap: true`，axis1_score = null
