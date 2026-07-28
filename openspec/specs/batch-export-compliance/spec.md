### Requirement: 批號×市場出口合規審查

系統 SHALL 允許為每個生產批次設定一個或多個目標出口市場，每「批次×市場」建立一筆審查紀錄（`batch_export_reviews`，unique(batch, market)）。審查依該市場規範整合資料面並輸出 `status`（pass/warning/fail）與逐項 `findings`（JSON，可稽核）。重跑審查 MUST 更新同一筆紀錄（upsert）。

審查資料面（依市場適用）：
1. **文件規則**：`market_compliance_rules` 對應市場的必備文件 × 批次產品上游供應商文件狀態（重用 MarketComplianceChecker 邏輯）。
2. **EUDR 溯源**（EU）：批次原料溯源中 EUDR 管制原料（木漿/天然橡膠）MUST 具 GPS 座標與收穫年，缺失 → fail。
3. **UFLPA 佐證**（US）：棉質原料溯源 MUST 具產地國與認證編號，產地國為 CN → fail。
4. **批次 PCF**：`lot_pcf` 缺失 → warning（EU 另計入 DPP 完備度）。
5. **DPP 欄位完備度**（EU）：產品 model_no、hs_code、embedded_emissions 缺失 → warning。

#### Scenario: 為批次執行 EU 市場審查（資料完備）
- **WHEN** 使用者對含完整木漿溯源（GPS＋收穫年）、批次 PCF、DPP 欄位齊備之批次執行 EU 審查
- **THEN** 建立/更新該批次×EU 的審查紀錄，status = pass
- **AND** findings 逐項記錄各檢核（文件/EUDR/PCF/DPP）之結果

#### Scenario: EUDR 管制原料缺 GPS
- **WHEN** 批次產品含天然橡膠但其溯源缺 GPS 座標，執行 EU 審查
- **THEN** status = fail，findings 標記 EUDR 溯源缺失項

#### Scenario: 同一批次多市場
- **WHEN** 同一批次先後執行 EU 與 US 審查
- **THEN** 存在兩筆獨立審查紀錄，各自持有 status 與 findings

#### Scenario: 重跑審查
- **WHEN** 補齊資料後重新執行同市場審查
- **THEN** 更新既有紀錄（不新增重複列），reviewed_at 刷新

### Requirement: 批次資料包對外 API（API Key）

系統 SHALL 提供對外唯讀端點 `GET /api/v1/export/batch-package/{erp_batch_no}?market=XX`，回傳批次護照 JSON：產品識別（名稱/SKU/型號/HS/客戶）、批次事實（批號/工單/日期/數量/縫製廠＋國別/批次 PCF）、供應鏈（BOM 行×主供應商×Tier）、原料溯源（GPS/收穫年/認證）、合規（文件狀態＋該市場審查結論與 findings）、meta（generated_at/market）。

認證採 `X-Api-Key` header，金鑰存於 `system_settings.export_api_key`（可換發）；金鑰錯誤或缺失 MUST 回 401，不得洩漏資料。

#### Scenario: 合法金鑰取得批次資料包
- **WHEN** 外部系統以正確 X-Api-Key 請求某批號的資料包（market=EU）
- **THEN** 回傳完整批次護照 JSON，含該批次×EU 審查結論

#### Scenario: 金鑰錯誤
- **WHEN** X-Api-Key 缺失或不符
- **THEN** 回 401，回應不含任何批次資料

#### Scenario: 批號不存在
- **WHEN** 請求不存在的批號
- **THEN** 回 404
