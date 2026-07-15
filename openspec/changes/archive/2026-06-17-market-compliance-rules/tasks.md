## 1. 資料庫 Migration

- [x] 1.1 建立 `create_market_compliance_rules_table` migration：id(UUID)/market(VARCHAR 10)/doc_type(VARCHAR 50)/is_mandatory(BOOL default true)/effective_from(DATE)/notes(TEXT NULL)/is_active(BOOL default true)/timestamps；UNIQUE(market, doc_type)；INDEX(market, is_active)
- [x] 1.2 建立 `MarketComplianceRuleSeeder`：植入 EU×EUDR_DDS(2025-01-17)、EU×CBAM_REPORT(2026-01-01)、EU×ORIGIN_CERT(2000-01-01)、US×UFLPA_DECLARATION(2022-06-21)、US×ORIGIN_CERT(2000-01-01)、US×CMRT(2010-01-01)、APAC×ORIGIN_CERT(2000-01-01)

## 2. Laravel Model & Request

- [x] 2.1 建立 `MarketComplianceRule` Model（HasUuids；fillable: market/doc_type/is_mandatory/effective_from/notes/is_active；scope: active()）
- [x] 2.2 建立 `StoreMarketComplianceRuleRequest`：market required in(EU,US,NA,APAC,GB,JP)、doc_type required string max:50、is_mandatory boolean、effective_from required date、notes nullable string；unique(market_compliance_rules, market+doc_type)
- [x] 2.3 建立 `UpdateMarketComplianceRuleRequest`：same rules，unique ignore self

## 3. MarketComplianceChecker 服務

- [x] 3.1 建立 `app/Services/Compliance/MarketComplianceChecker.php`：
      - `check(TradeGood $good, string $market): array`
      - Step 1：取 tradeGoodSuppliers → materialGroup.required_doc_types 聯集
      - Step 2：if market=EU and cbam_eligible → push CBAM_REPORT
      - Step 3：query MarketComplianceRule::active()->where(market)->whereIn(doc_type, $needed)
      - Step 4：foreach rule → query SupplierComplianceDoc → status(valid/expiring_soon/expired/missing)
      - Step 5：return { market, required[], results[], overall(pass/warning/fail) }
- [x] 3.2 建立 `checkBatch(array $goodIds, string $market): array`：單次查詢所有 suppliers/docs，避免 N+1，最多 100 筆

## 4. Controller & Routes

- [x] 4.1 建立 `app/Http/Controllers/Api/Compliance/MarketComplianceRuleController.php`：index（支援 market 篩選）、store、update、destroy（軟停：設 is_active=false）
- [x] 4.2 建立 `app/Http/Controllers/Api/TradeGoods/TradeGoodMarketComplianceController.php`：
      - `batch(Request $request)`：POST /api/v1/trade-goods/market-compliance-batch，驗證 market + trade_good_ids（array max:100）
- [x] 4.3 在 `routes/api.php` 新增：
      - `Route::apiResource('market-compliance-rules', MarketComplianceRuleController::class)`（admin 角色）
      - `Route::post('trade-goods/market-compliance-batch', [TradeGoodMarketComplianceController::class, 'batch'])`

## 5. 修改 ShipmentService

- [x] 5.1 修改 `ShipmentService::addLine()`：移除 `$tradeGood->is_eudr_applicable` 靜態判定，改呼叫 `MarketComplianceChecker::check($tradeGood, $shipment->target_market)`；若 results 含 EUDR_DDS missing/expired 且 eudr_dds_status=not_required → 更新為 draft

## 6. 前端 API 模組

- [x] 6.1 在 `esgchain-web/src/api/modules/tradeGoods.ts` 新增：
      - `marketComplianceApi.batch(market: string, tradeGoodIds: string[]): Promise<...>`
      - 介面定義：`MarketComplianceResult { trade_good_id, overall, results: DocResult[] }`
- [x] 6.2 建立 `esgchain-web/src/api/modules/marketComplianceRules.ts`：
      - 介面定義：`MarketComplianceRule { id, market, doc_type, is_mandatory, effective_from, notes, is_active }`
      - `marketComplianceRulesApi`: list/create/update/destroy

## 7. 前端頁面

- [x] 7.1 建立 `esgchain-web/src/views/settings/MarketComplianceRulesView.vue`（/settings/market-rules，admin）：規則列表（依 market 分組），新增/編輯 Modal，啟用/停用切換
- [x] 7.2 在 `AppSidebar.vue` settings-group 加入 `{ name: 'market-rules', path: '/settings/market-rules', label: '市場合規規則', roles: ['admin'] }`
- [x] 7.3 在 `router/index.ts` 加入 `/settings/market-rules` 路由
- [x] 7.4 修改 `TradeGoodsView.vue`：
      - 篩選列加 target_market 單選（全部/EU/US/APAC/其他）
      - 未選市場時顯示原有靜態 CBAM/EUDR eligible 標籤（潛力模式）
      - 選定市場後呼叫 marketComplianceApi.batch()
      - 各商品列「法規適用」欄顯示合規狀態圖示（✅⚠❌）+ 點擊展開明細

## 8. Docker 同步與驗證

- [x] 8.1 docker cp migration + seeder 至 esgchain-api，restart，執行 migrate + db:seed MarketComplianceRuleSeeder
- [x] 8.2 curl 驗證 GET /api/v1/market-compliance-rules → 回傳 7 筆種子規則
- [x] 8.3 curl 驗證 POST /api/v1/trade-goods/market-compliance-batch（market=EU）→ required=[ORIGIN_CERT]，overall=fail
- [x] 8.4 curl 驗證 market=US 相同商品 → required=[ORIGIN_CERT, UFLPA_DECLARATION]（EU 無 UFLPA，US 無 EUDR_DDS）
- [x] 8.5 docker cp esgchain-web，瀏覽器驗證 MarketComplianceRulesView（/settings/market-rules）顯示規則分組
- [x] 8.6 瀏覽器驗證 TradeGoodsView 選 EU → 商品列出現合規狀態圖示；選 US → 需求文件不同
