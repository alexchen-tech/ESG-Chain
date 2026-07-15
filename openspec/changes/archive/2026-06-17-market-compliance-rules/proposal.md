## Why

出口商品的合規義務（CBAM、EUDR、UFLPA 等）取決於**目標市場**，但現有系統將合規旗標靜態標記在商品層級，導致不分市場都顯示警告（假陽性），無法支援「選擇目標市場 → 檢核該批貨合規狀態」的出口申報工作流程。

## What Changes

- **新增** `market_compliance_rules` 資料表：定義各目標市場觸發哪些文件義務（doc_type × market × effective_from）
- **新增** 市場合規規則管理 UI（`/settings/market-rules`，admin）：列表、新增、編輯、停用
- **新增** `MarketComplianceChecker` 服務：輸入 TradeGood + target_market，輸出合規清單（通過 / 缺少 / 過期）
- **新增** API `GET /api/v1/trade-goods/{id}/market-compliance?market=EU`
- **修改** `TradeGoodsView`：加入 target_market 單選篩選器；各商品列依市場顯示合規檢核結果（✅ / ⚠ / ❌）
- **修改** `trade-goods` spec：`is_eudr_applicable` / `is_cbam_applicable` 靜態 flag 的語意改為「市場無關的潛力標記（eligible）」，實際義務由 MarketComplianceChecker 計算

## Capabilities

### New Capabilities

- `market-compliance-rules`: 市場合規規則主檔 — 管理 market × doc_type 映射表，含生效日期、強制性、備註；提供 CRUD API 與 admin 管理 UI，內建 EU / US / APAC 預設種子資料
- `trade-good-market-compliance`: 出口商品市場合規檢核 — MarketComplianceChecker 服務依 TradeGood 上游物料 required_doc_types 與市場規則交集，回傳各文件項目的通過 / 缺少 / 過期狀態；TradeGoodsView 整合目標市場篩選與合規結果展示

### Modified Capabilities

- `trade-goods`: `is_eudr_applicable` 靜態 flag 移除，改由 MarketComplianceChecker 動態計算；`is_cbam_applicable` 語意調整為 `cbam_eligible`（僅表示 HS code 屬於 CBAM 品類，不代表實際申報義務）
- `export-shipment-management`: Shipment 建立時的 `eudr_dds_status` 自動判定邏輯改為呼叫 MarketComplianceChecker，取代現有的靜態 `is_eudr_applicable` 判斷

## Impact

- **新增**：`esgchain-api/database/migrations/` — `create_market_compliance_rules_table`
- **新增**：`app/Models/MarketComplianceRule.php`
- **新增**：`app/Services/Compliance/MarketComplianceChecker.php`
- **新增**：`app/Http/Controllers/Api/Compliance/MarketComplianceRuleController.php`
- **新增**：`app/Http/Requests/Compliance/StoreMarketComplianceRuleRequest.php`
- **新增**：`database/seeders/MarketComplianceRuleSeeder.php`（EU/US/APAC 預設規則）
- **新增**：`esgchain-web/src/views/settings/MarketComplianceRulesView.vue`
- **修改**：`app/Services/Shipment/ShipmentService.php` — addLine() 改用 Checker
- **修改**：`app/Services/Compliance/SupplierComplianceStatusService.php` — EUDR 判定邏輯
- **修改**：`esgchain-web/src/views/trade-goods/TradeGoodsView.vue` — 市場篩選 + 合規狀態欄
- **修改**：`routes/api.php` — 新增 market-compliance-rules 路由 + trade-good market-compliance 查詢路由
- 無 breaking API 變更（新增欄位，舊 response 欄位保留向後相容）
