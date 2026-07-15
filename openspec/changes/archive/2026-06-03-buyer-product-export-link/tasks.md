## 1. 資料庫 Migration

- [x] 1.1 新增 migration：建立 `buyer_product_trade_goods` table（id uuid PK, buyer_product_id uuid FK, trade_good_id uuid FK, relation_type enum('finished_good','component','equivalent'), bom_line_id uuid FK nullable → product_bom_lines, note text nullable, timestamps）
- [x] 1.2 在 (buyer_product_id, trade_good_id) 加 UNIQUE constraint，防止重複連結
- [x] 1.3 執行 migration 並確認表結構正確

## 2. 後端 Model 與關聯

- [x] 2.1 新增 `BuyerProductTradeGood` Model（HasUuids, fillable, casts, BelongsTo BuyerProduct / TradeGood / ProductBomLine）
- [x] 2.2 `BuyerProduct` 新增 `exportLinks()` hasMany 關聯、`exportedTradeGoods()` hasManyThrough（透過 BuyerProductTradeGood）
- [x] 2.3 `TradeGood` 新增 `importedByProducts()` hasManyThrough（透過 BuyerProductTradeGood）

## 3. 後端 Service — ExportLinkSyncService

- [x] 3.1 新增 `ExportLinkSyncService::syncFromPcf(BuyerProduct, PcfSnapshot)` — 查詢所有 relation_type=finished_good 的連結，將 total_pcf 寫入對應 TradeGood.embedded_emissions
- [x] 3.2 新增 `ExportLinkSyncService::syncEudrFromRegulations(BuyerProduct)` — 若 inferred_regulations 含 EUDR，設定 finished_good 連結的 TradeGood.is_eudr_applicable = true
- [x] 3.3 在 `PcfCalculationService::snapshot()` 完成後呼叫 `ExportLinkSyncService::syncFromPcf()`
- [x] 3.4 在 `BuyerProduct::syncApplicableRegulations()` 完成後呼叫 `ExportLinkSyncService::syncEudrFromRegulations()`

## 4. 後端 Controller 與路由

- [x] 4.1 新增 `BuyerProductExportLinkController`：`index(buyerProductId)`、`store(buyerProductId)`、`destroy(buyerProductId, linkId)` — 含 422 重複連結防護
- [x] 4.2 `store()` 驗證：trade_good_id required|uuid|exists, relation_type required|in:finished_good,component,equivalent, bom_line_id nullable|uuid|exists（且 bom_line_id 的 buyer_product_id 須與路由參數一致）
- [x] 4.3 新增路由：`GET/POST /api/v1/buyer-products/{id}/export-links`、`DELETE /api/v1/buyer-products/{id}/export-links/{linkId}`
- [x] 4.4 新增 TradeGood 搜尋端點：`GET /api/v1/trade-goods/search?q=keyword`（回傳 id, name, product_code, hs_code，limit 20）
- [x] 4.5 同步所有後端檔案至 Docker 並 `docker restart esgchain-api`

## 5. 前端 API 模組

- [x] 5.1 在 `api/modules/compliance.ts`（或新建 `api/modules/exportLinks.ts`）新增 interface `ExportLink`（id, buyer_product_id, trade_good_id, trade_good_name, trade_good_hs_code, relation_type, bom_line_id, note）
- [x] 5.2 新增 `exportLinkApi.list(buyerProductId)`、`exportLinkApi.create(buyerProductId, payload)`、`exportLinkApi.destroy(buyerProductId, linkId)`
- [x] 5.3 在 `tradeGoodApi` 新增 `search(keyword)` 方法，呼叫 `/api/v1/trade-goods/search`

## 6. 前端 BuyerProductsView — 出口商品 Tab

- [x] 6.1 在 BOM panel 旁新增「出口商品」Tab（與現有 BomLine 展開區並列），切換後 lazy load
- [x] 6.2 Tab 顯示 ExportLink 列表：TradeGood 名稱、HS Code、relation_type badge（finished_good=綠/component=藍/equivalent=灰）、BomLine 料名（若有）、移除按鈕
- [x] 6.3 「新增出口商品」按鈕開啟 Modal：輸入關鍵字即時搜尋 TradeGood（300ms debounce）、選取後選擇 relation_type（下拉）、component 類型顯示 BomLine 選取下拉（載入該產品的 BomLine 清單）
- [x] 6.4 移除連結：點擊「移除」確認後呼叫 `exportLinkApi.destroy()`，刷新清單
- [x] 6.5 空狀態提示：「尚未設定出口商品，點擊新增以建立連結」
- [x] 6.6 同步 Vue 檔案至 Docker 並 touch 觸發 HMR

## 7. Seeder 補充 Demo 資料

- [x] 7.1 在 `BuyerProductSeeder` 或新建 `ExportLinkSeeder` 中，建立以下示範 mapping（firstOrCreate）：
  - TEX-001 基本棉 T 恤 → GMN-TEE-002 有機棉 T-Shirt（finished_good）
  - TEX-002 機能運動長褲 → GMN-PNT-003 再生聚酯機能褲（finished_good）
  - TEX-007 戶外機能夾克 → GMN-JKT-001 機能運動夾克（finished_good）、FAB-WPB-002 防水透氣外層布（component）
  - TEX-006 快乾機能衫 → FAB-DRI-001 機能性吸濕排汗布（component）
- [x] 7.2 將 `ExportLinkSeeder` 加入 `DatabaseSeeder` 呼叫順序（在 BuyerProductSeeder 和 TradeGoodSeeder 之後）
- [x] 7.3 執行 Seeder 並確認 mapping 資料正確寫入

## 8. 驗收

- [x] 8.1 BuyerProductsView「出口商品」Tab 列出連結，relation_type badge 顏色正確
- [x] 8.2 新增連結：搜尋 TradeGood、選 relation_type、送出後清單更新
- [x] 8.3 component 類型可選填 BomLine，清單顯示對應料名
- [x] 8.4 移除連結後清單即時更新，TradeGood 本身資料不受影響
- [x] 8.5 PCF 重算後，finished_good 關聯的貿易商品「內含碳排量」自動更新
- [x] 8.6 BuyerProduct inferred_regulations 含 EUDR 時，finished_good 的 TradeGood is_eudr_applicable 自動設為 true
