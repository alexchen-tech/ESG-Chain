## ADDED Requirements

### Requirement: 路徑風險分計算（esgchain-ai）

系統 SHALL 提供 `POST /ai/v1/path-risk`，計算特定商品出口至特定市場的綜合路徑風險分。

計算公式：
1. 供應鏈碳排風險基分：`Chain_Risk = Σ(axis1_score_i / 100 × carbon_share_i)`
   - `axis1_score_i`：供應商 i 的 ESG 暴露分（來自三軸雷達軸1）
   - `carbon_share_i`：供應商 i 碳排 / 商品總碳排（來自最新 PcfSnapshot）
   - 若供應商碳排缺失：使用 `emission_factor` 行業均值 fallback，並標記 `data_gap: true`
2. 市場法規放大係數：`Amplifier = 1 + (缺失強制義務數 / 總強制義務數)`
3. `Path_Risk_Score = Chain_Risk × Amplifier`（正規化至 0~2，映射至 very_low/low/medium/high/extreme）

結果快取於 `trade_good_path_risks`（TTL 24 小時，PcfSnapshot 更新時失效）。

#### Scenario: 正常路徑風險計算

- **WHEN** `POST /ai/v1/path-risk` 收到 `{trade_good_id, market, supplier_emissions: [{supplier_id, co2_kg, axis1_score}]}`
- **THEN** 系統 SHALL 回傳 `{path_risk_score, risk_level, amplifier, chain_risk, contributors: [{supplier_id, axis1_score, carbon_share, contribution, data_gap}], has_data_gap}`

#### Scenario: 供應商碳排缺失使用 fallback

- **WHEN** 某供應商在 PcfSnapshot 無碳排明細，但有 emission_factor 行業均值
- **THEN** 計算 SHALL 使用行業均值作為碳排，該供應商 `data_gap: true`，整體結果 `has_data_gap: true`

#### Scenario: 無強制義務時放大係數為 1

- **WHEN** 目標市場對此商品無強制義務（market_compliance_rules 為空）
- **THEN** Amplifier = 1.0，Path_Risk_Score = Chain_Risk

### Requirement: 路徑風險快取 API

系統 SHALL 提供 `GET /api/v1/trade-goods/export-risk-matrix`，回傳當前使用者有權存取的所有商品 × 所有市場的路徑風險矩陣。

- 請求支援 `?market=EU` 過濾單一市場，`?trade_good_id=<id>` 過濾單一商品
- 優先從 `trade_good_path_risks` 快取讀取；快取缺失時觸發非同步計算，回傳 `status: "calculating"`
- 單次最多回傳 200 筆商品 × 市場組合

#### Scenario: 熱力圖初始載入

- **WHEN** 使用者開啟出口合規 Dashboard，前端呼叫 `GET /api/v1/trade-goods/export-risk-matrix`
- **THEN** 系統 SHALL 回傳各（商品 × 市場）的 `risk_level`、`has_data_gap`；快取缺失的格子回傳 `status: "calculating"`

#### Scenario: 快取失效後重算

- **WHEN** PcfSnapshot 更新觸發 `trade_good_path_risks` 失效
- **THEN** 下次請求 SHALL 觸發非同步重算，中間期間回傳上一次快取值並標記 `stale: true`

### Requirement: 出口合規 Dashboard 熱力圖 UI

Dashboard B SHALL 提供商品 × 市場熱力圖，支援雙入口：
- **商品入口**：選定商品後，橫列顯示各市場的路徑風險等級
- **市場入口**：選定市場後，縱列顯示各商品的路徑風險等級

熱力格顏色：very_low（綠）、low（黃綠）、medium（黃）、high（橘）、extreme（紅）。有 `has_data_gap` 時加橘色邊框警示。

#### Scenario: 從商品入口查看

- **WHEN** 使用者在商品入口選定商品「鋼結構件」
- **THEN** 系統 SHALL 顯示此商品在 EU / US / APAC / GB / JP 各市場的路徑風險色塊列

#### Scenario: 從市場入口查看

- **WHEN** 使用者在市場入口選定「EU」
- **THEN** 系統 SHALL 顯示所有商品在 EU 市場的路徑風險色塊欄，依風險等級降序排列

#### Scenario: 點擊格子展開義務缺口明細

- **WHEN** 使用者點擊熱力圖中的（鋼結構件 × EU）格子
- **THEN** 系統 SHALL 在右側面板展開：法規義務清單（含狀態 valid/expiring_soon/missing）、責任供應商（含軸1 ESG暴露分）、[補文件] 與 [換供應商] 動作按鈕

### Requirement: 義務缺口補文件動作

點擊義務缺口明細面板的 [補文件] SHALL 建立對應的 CAP（矯正行動），類型為 `compliance_doc_gap`，並連結至該義務的 doc_type。

#### Scenario: 建立文件缺口 CAP

- **WHEN** 使用者在義務缺口面板點擊「補文件」（doc_type = CBAM_REPORT）
- **THEN** 系統 SHALL 建立 CAP，`source_type = "compliance_doc_gap"`，`doc_type = "CBAM_REPORT"`，關聯至對應供應商，並導向 CAP 詳情頁

#### Scenario: 文件補齊後重算合規狀態

- **WHEN** SupplierComplianceDoc 新增有效 CBAM_REPORT 後
- **THEN** MarketComplianceChecker SHALL 重新計算，該項義務狀態更新為 valid，路徑風險快取失效並重算
