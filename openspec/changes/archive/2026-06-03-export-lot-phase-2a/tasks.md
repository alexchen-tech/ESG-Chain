## 1. 資料庫 Migration

- [x] 1.1 新增 migration：建立 `production_batches` 表（id uuid PK, erp_batch_no varchar UNIQUE, erp_order_no nullable, buyer_product_trade_good_id uuid FK nullable → buyer_product_trade_goods, supplier_id uuid FK → suppliers, production_date date nullable, quantity decimal, unit varchar, lot_pcf decimal nullable, lot_pcf_source enum(calculated,reported,estimated) nullable, source enum(webhook,csv,manual), erp_synced_at timestamp nullable, timestamps, softDeletes）
- [x] 1.2 新增 migration：建立 `raw_material_origins` 表（id uuid PK, production_batch_id uuid FK, bom_line_id uuid FK nullable → product_bom_lines, material_name varchar, origin_country char(2), facility_name nullable, gps_lat decimal(9,6) nullable, gps_lng decimal(9,6) nullable, harvest_year smallint nullable, certification_ref nullable, timestamps）
- [x] 1.3 執行 migration 確認表結構正確

## 2. 後端 Model 與關聯

- [x] 2.1 新增 `ProductionBatch` Model（HasUuids, fillable, casts, BelongsTo Supplier / BuyerProductTradeGood, HasMany RawMaterialOrigin）
- [x] 2.2 新增 `RawMaterialOrigin` Model（HasUuids, fillable, casts, BelongsTo ProductionBatch / ProductBomLine）
- [x] 2.3 `Supplier` 新增 `productionBatches()` hasMany 關聯
- [x] 2.4 `BuyerProductTradeGood` 新增 `productionBatches()` hasMany 關聯

## 3. 後端 Service

- [x] 3.1 新增 `ProductionBatchService::upsertFromPayload(array $data): ProductionBatch` — 依 erp_batch_no upsert，匹配 erp_product_code → buyer_product_trade_good_id，匹配 supplier_code → supplier_id
- [x] 3.2 新增 `ProductionBatchService::importFromCsv(array $rows): array` — 回傳 `{ imported, errors }`
- [x] 3.3 新增 `ProductionBatchService::list(array $filters): Collection` — 支援 matched_status / supplier_id 篩選

## 4. 後端 Controller 與路由

- [x] 4.1 新增 `ErpWebhookController::productionBatch(Request)` — 驗證 HMAC / API Key，呼叫 Service upsert，回傳 201
- [x] 4.2 新增 `ProductionBatchController`：`index()`, `show()`, `update()`（手動補匹配），`destroy()`
- [x] 4.3 新增 `RawMaterialOriginController`：`store()`, `update()`, `destroy()`（掛在 production-batches/{id}/origins）
- [x] 4.4 新增 `ProductionBatchImportController::store()` — 接受 CSV multipart，呼叫 importFromCsv
- [x] 4.5 `.env` 加入 `ERP_AUTH_MODE=hmac`、`ERP_WEBHOOK_SECRET`、`ERP_API_KEY`（與 `.env.example` 同步）
- [x] 4.6 新增路由：
  - `POST /api/v1/erp/webhook/production-batches`（無 JWT auth，改用 Webhook 驗證 middleware）
  - `POST /api/v1/erp/import/production-batches`（需 JWT auth，admin 角色）
  - `GET/PUT/DELETE /api/v1/production-batches/{id}`
  - `GET /api/v1/production-batches`
  - `POST/PUT/DELETE /api/v1/production-batches/{id}/origins`
- [x] 4.7 同步後端至 Docker 並 `docker restart esgchain-api`

## 5. 前端 API 模組

- [x] 5.1 新增 `api/modules/productionBatch.ts`：interface `ProductionBatch`、`RawMaterialOrigin`；`productionBatchApi.list(filters)`, `get(id)`, `update(id, payload)`, `destroy(id)`；`rawMaterialOriginApi.create(batchId, payload)`, `update(id, payload)`, `destroy(id)`

## 6. 前端頁面

- [x] 6.1 新增 `ProductionBatchesView.vue`：列表含批號、工廠、匹配狀態 badge（matched=綠/待匹配=橙）、數量、生產日期、批次 PCF；篩選 bar（匹配狀態 / 工廠下拉）
- [x] 6.2 右側 Drawer（420px）：批號詳情區塊 + 原料溯源清單（含新增表單）
- [x] 6.3 待匹配批號的 Drawer：顯示採購品連結下拉，送出後呼叫 `update()` 寫入 `buyer_product_trade_good_id`
- [x] 6.4 GPS 座標顯示：`{lat}°N/S {lng}°E/W` + 「在地圖查看」連結（Google Maps）
- [x] 6.5 新增路由 `/compliance/production-batches`，側邊欄「商品合規管理」群組加入「生產批號」項目（角色：admin, buyer, comply）
- [x] 6.6 同步前端至 Docker 並 touch 觸發 HMR

## 7. Seeder 補充 Demo 資料

- [x] 7.1 新增 `ProductionBatchSeeder`：建立 5 筆示範批號（3 筆已匹配 / 2 筆待匹配），含 raw_material_origins（農場 GPS / 認證號）
- [x] 7.2 將 `ProductionBatchSeeder` 加入 `DatabaseSeeder`（在 ExportLinkSeeder 之後）
- [x] 7.3 執行 Seeder 確認資料正確

## 8. 驗收

- [x] 8.1 Webhook `POST /api/v1/erp/webhook/production-batches` HMAC 驗證正確，批號建立 / upsert 正常
- [x] 8.2 CSV import 部分列錯誤時，其他列正常匯入，錯誤列回傳訊息
- [x] 8.3 前端列表顯示匹配/待匹配 badge，篩選功能正常
- [x] 8.4 Drawer 原料溯源新增 / 刪除，GPS 座標正確顯示
- [x] 8.5 待匹配批號可透過 Drawer 手動選擇採購品連結完成匹配
