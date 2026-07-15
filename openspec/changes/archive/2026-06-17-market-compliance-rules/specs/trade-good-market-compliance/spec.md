## ADDED Requirements

### Requirement: 出口商品市場合規檢核服務
`MarketComplianceChecker::check(TradeGood, market)` SHALL 依以下步驟計算合規狀態：
1. 收集 TradeGood 所有上游供應商（TradeGoodSupplier）的物料群組 required_doc_types，取聯集
2. 若 market = "EU" 且 trade_good.cbam_eligible = true，加入 CBAM_REPORT 至需求集合
3. 從 market_compliance_rules 取 market 當前有效規則，與需求集合取交集，得出「實際義務文件清單」
4. 逐一比對 SupplierComplianceDoc，判定各文件狀態：valid / expiring_soon / expired / missing
5. 回傳 overall（pass / warning / fail）及各文件明細

#### Scenario: EU 市場木材商品合規檢核
- **WHEN** TradeGood 上游物料含 EUDR_DDS，market = "EU"，無有效 EUDR_DDS 文件
- **THEN** overall = "fail"，results 包含 { doc_type: "EUDR_DDS", status: "missing" }

#### Scenario: US 市場木材商品不觸發 EUDR
- **WHEN** TradeGood 上游物料含 EUDR_DDS，market = "US"
- **THEN** EUDR_DDS 不在 US 規則中，overall 計算不包含 EUDR_DDS

#### Scenario: 上游無受管制物料
- **WHEN** TradeGood 無上游 required_doc_types 且 cbam_eligible = false
- **THEN** overall = "pass"，required = []

### Requirement: 批次合規查詢 API
系統 SHALL 提供 `POST /api/v1/trade-goods/market-compliance-batch`，接受 `{ market: string, trade_good_ids: string[] }`，回傳每筆商品的 overall 狀態，單次最多 100 筆。

#### Scenario: 批次查詢避免 N+1
- **WHEN** 前端傳入 50 筆 trade_good_ids
- **THEN** 後端以單次查詢取得所有相關 docs，不產生 N 次個別查詢

### Requirement: TradeGoodsView 市場合規整合
系統 SHALL 在 TradeGoodsView 加入 target_market 單選篩選器（EU / US / APAC / 全部）。選定市場後，每列商品顯示市場合規狀態圖示（✅ pass / ⚠ warning / ❌ fail）。點擊圖示展開明細面板，列出各文件義務狀態。

#### Scenario: 選定市場觸發合規查詢
- **WHEN** 使用者選擇 target_market = "EU"
- **THEN** 前端呼叫批次查詢 API，將結果渲染至各商品列；未選市場時顯示「— 選擇市場以查看合規」

#### Scenario: 合規明細展開
- **WHEN** 使用者點擊商品列合規狀態圖示
- **THEN** 展開顯示：文件名稱、到期日、關聯供應商、狀態（valid/missing/expired）
