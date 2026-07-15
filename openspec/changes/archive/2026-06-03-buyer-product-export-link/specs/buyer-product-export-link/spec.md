## ADDED Requirements

### Requirement: BuyerProduct 與 TradeGood M:N 關聯建立

系統 SHALL 允許使用者為 BuyerProduct 建立與一個或多個 TradeGood 的出口連結（ExportLink），每條連結必須指定 `relation_type`（`finished_good` / `component` / `equivalent`）。`component` 類型可選填 `bom_line_id` 以精確對應 BomLine 中的某一行原料。

#### Scenario: 新增 finished_good 連結
- **WHEN** 使用者在 BuyerProductsView「出口商品」Tab 選擇一個 TradeGood 並設定 relation_type = finished_good
- **THEN** 系統建立 `buyer_product_trade_goods` 記錄，relation_type = finished_good，bom_line_id = null

#### Scenario: 新增 component 連結並指定 BomLine
- **WHEN** 使用者選擇 relation_type = component 並選填 bom_line_id
- **THEN** 系統建立記錄，bom_line_id 指向對應的 ProductBomLine

#### Scenario: 重複連結防止
- **WHEN** 使用者嘗試對同一組 (buyer_product_id, trade_good_id) 再次建立連結
- **THEN** 系統回傳 422，提示該連結已存在

#### Scenario: 移除連結
- **WHEN** 使用者點擊「移除」並確認
- **THEN** 系統刪除對應的 `buyer_product_trade_goods` 記錄，不影響 BuyerProduct 或 TradeGood 本身資料

### Requirement: 出口商品 Tab UI

系統 SHALL 在 `BuyerProductsView` 的產品展開區（BOM panel 旁）提供「出口商品」Tab，列出該 BuyerProduct 所有已連結的 TradeGood，並提供新增與移除操作。

#### Scenario: 載入出口連結清單
- **WHEN** 使用者展開 BuyerProduct 並切換到「出口商品」Tab
- **THEN** 系統呼叫 `GET /api/v1/buyer-products/{id}/export-links`，顯示已連結的 TradeGood 名稱、HS Code、relation_type badge

#### Scenario: 新增連結（搜尋選取）
- **WHEN** 使用者點擊「新增出口商品」，輸入關鍵字搜尋 TradeGood
- **THEN** 系統顯示搜尋結果下拉，使用者選取後選擇 relation_type，確認後送出

#### Scenario: 無連結狀態
- **WHEN** BuyerProduct 尚無任何出口連結
- **THEN** Tab 顯示空狀態提示「尚未設定出口商品，點擊新增以建立連結」

### Requirement: PCF → embedded_emissions 自動同步

當 BuyerProduct 的 PCF 快照更新時，系統 SHALL 自動將 `pcf_snapshots.total_pcf` 寫入所有 `relation_type = finished_good` 的關聯 TradeGood 之 `embedded_emissions` 欄位。

#### Scenario: PCF 快照觸發同步
- **WHEN** `PcfCalculationService::snapshot()` 成功建立新的 PcfSnapshot
- **THEN** 系統查詢該 BuyerProduct 所有 relation_type = finished_good 的 ExportLink，將 total_pcf 值更新到對應 TradeGood.embedded_emissions

#### Scenario: 無 finished_good 連結時不同步
- **WHEN** BuyerProduct 無任何 relation_type = finished_good 的出口連結
- **THEN** PCF 快照建立後不執行任何 TradeGood 更新

### Requirement: EUDR 合規狀態自動同步

當 BuyerProduct 的 `inferred_regulations` 包含 `EUDR` 時，系統 SHALL 自動設定所有 `relation_type = finished_good` 的關聯 TradeGood 之 `is_eudr_applicable = true`。

#### Scenario: BuyerProduct 推算出 EUDR 適用
- **WHEN** `BuyerProduct::syncApplicableRegulations()` 執行後，`inferred_regulations` 包含 EUDR
- **THEN** 系統更新所有 finished_good 關聯的 TradeGood，設定 is_eudr_applicable = true

#### Scenario: BuyerProduct EUDR 適用移除
- **WHEN** BuyerProduct 的 inferred_regulations 不再包含 EUDR
- **THEN** 不自動將 TradeGood.is_eudr_applicable 改回 false（避免覆寫使用者手動聲明）
