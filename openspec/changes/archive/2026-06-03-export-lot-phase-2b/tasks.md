## 1. 資料庫 Migration

- [x] 1.1 新增 migration：建立 `shipments` 表（id uuid PK, shipment_no varchar UNIQUE, target_market varchar(10), export_date date nullable, eudr_dds_status enum(not_required,draft,submitted,approved) default draft, eudr_dds_ref nullable, eudr_submitted_at timestamp nullable, created_by uuid FK → users, notes text nullable, timestamps, softDeletes）
- [x] 1.2 新增 migration：建立 `shipment_lines` 表（id uuid PK, shipment_id uuid FK, trade_good_id uuid FK, buyer_product_id uuid FK nullable, total_quantity decimal, unit varchar, hs_code_override varchar(10) nullable, weighted_pcf decimal nullable, timestamps）
- [x] 1.3 新增 migration：建立 `shipment_line_batches` 表（id uuid PK, shipment_line_id uuid FK, production_batch_id uuid FK, allocated_quantity decimal, timestamps, UNIQUE(shipment_line_id, production_batch_id)）
- [x] 1.4 執行 migration 確認表結構

## 2. 後端 Model 與關聯

- [x] 2.1 新增 `Shipment` Model（HasUuids, fillable, casts, HasMany ShipmentLine, BelongsTo User（created_by））
- [x] 2.2 新增 `ShipmentLine` Model（HasUuids, fillable, casts, BelongsTo Shipment/TradeGood/BuyerProduct, HasMany ShipmentLineBatch）
- [x] 2.3 新增 `ShipmentLineBatch` Model（HasUuids, fillable, casts, BelongsTo ShipmentLine/ProductionBatch）
- [x] 2.4 `ProductionBatch` 新增 `shipmentLineBatches()` hasMany 關聯
- [x] 2.5 `TradeGood` 新增 `shipmentLines()` hasMany 關聯

## 3. 後端 Service

- [x] 3.1 新增 `ShipmentService::create(array $data, User $user): Shipment` — 建立 Shipment，自動產生 shipment_no（SHIP-YYYYMM-NNN）若未提供
- [x] 3.2 新增 `ShipmentService::addLine(Shipment, array $data): ShipmentLine` — 新增商品項目，同步更新 eudr_dds_status（若 TradeGood.is_eudr_applicable = true 且原本 not_required 則改 draft）
- [x] 3.3 新增 `ShipmentService::allocateBatch(ShipmentLine, ProductionBatch, float $qty): array` — 建立 shipment_line_batches，計算超額警告，更新 weighted_pcf
- [x] 3.4 新增 `ShipmentService::recalcWeightedPcf(ShipmentLine): void` — 重算加權平均 PCF 並寫入 shipment_lines.weighted_pcf
- [x] 3.5 新增 `ShipmentService::generateDdsDraft(Shipment): array` — 遍歷三層結構，產出 DDS 草稿 JSON，標記缺漏溯源

## 4. 後端 Controller 與路由

- [x] 4.1 新增 `ShipmentController`：`index()`, `store()`, `show()`, `update()`（含 eudr_dds_status 更新）, `destroy()`
- [x] 4.2 新增 `ShipmentLineController`：`store(shipmentId)`, `destroy(shipmentId, lineId)`
- [x] 4.3 新增 `ShipmentLineBatchController`：`store(shipmentId, lineId)`, `destroy(shipmentId, lineId, batchId)`
- [x] 4.4 新增 `ShipmentDdsController::draft(shipmentId)` — 呼叫 generateDdsDraft，not_required 回傳 404
- [x] 4.5 新增路由：
  - `GET/POST /api/v1/shipments`
  - `GET/PATCH/DELETE /api/v1/shipments/{id}`
  - `POST/DELETE /api/v1/shipments/{id}/lines/{lineId}`
  - `POST /api/v1/shipments/{id}/lines`
  - `POST/DELETE /api/v1/shipments/{id}/lines/{lineId}/batches/{batchId}`
  - `GET /api/v1/shipments/{id}/dds-draft`
- [x] 4.6 同步後端至 Docker 並 `docker restart esgchain-api`

## 5. 前端 API 模組

- [x] 5.1 新增 `api/modules/shipment.ts`：interface `Shipment`, `ShipmentLine`, `ShipmentLineBatch`, `DdsDraft`；`shipmentApi.list()`, `get(id)`, `create(payload)`, `update(id, payload)`, `destroy(id)`；`shipmentLineApi.create(shipmentId, payload)`, `destroy(shipmentId, lineId)`；`shipmentLineBatchApi.create(shipmentId, lineId, payload)`, `destroy(...)`；`ddsDraftApi.get(shipmentId)`

## 6. 前端頁面

- [x] 6.1 新增 `ShipmentsView.vue`：列表含 shipment_no、target_market、export_date、eudr_dds_status badge、商品項目數；「新增申報」按鈕開啟 Modal（填 shipment_no/target_market/export_date）
- [x] 6.2 新增 `ShipmentDetailView.vue`（路由 `/compliance/shipments/:id`）：
  - 上方卡片：基本資訊 + EUDR DDS 狀態操作按鈕（「產出草稿」「標記已送出」）
  - ShipmentLine 列表，展開顯示 ProductionBatch 分配清單（批號/工廠/分配量/lot_pcf）
  - 每個 ShipmentLine 顯示 weighted_pcf（有值顯示，null 顯示「—（部分批號無碳排資料）」）
  - 超額警告 badge：當某批號超額分配時橙色標示
- [x] 6.3 「新增商品項目」Modal：搜尋 TradeGood（含 is_eudr_applicable badge）、填 total_quantity/unit
- [x] 6.4 「分配批號」Modal：依 trade_good_id 篩選 ProductionBatch 清單（顯示批號/工廠/剩餘量），填 allocated_quantity，超額時顯示警告
- [x] 6.5 DDS 草稿預覽面板（右側可收合）：結構化顯示，origins_missing 的批號橙色警告，含「複製 JSON」按鈕
- [x] 6.6 「標記已送出」Modal：填入 eudr_dds_ref（申報編號）後送出
- [x] 6.7 新增路由 `/compliance/shipments` 與 `/compliance/shipments/:id`；sidebar「商品合規管理」群組加入「出口申報」（角色：admin, buyer, comply）
- [x] 6.8 同步前端至 Docker 並 touch 觸發 HMR

## 7. Seeder 補充 Demo 資料

- [x] 7.1 新增 `ShipmentSeeder`：建立 2 筆示範 Shipment（1 筆 EUDR draft / 1 筆 not_required），各含 ShipmentLine 與 ProductionBatch 分配
- [x] 7.2 加入 `DatabaseSeeder`（在 ProductionBatchSeeder 之後）
- [x] 7.3 執行 Seeder 確認資料正確

## 8. 驗收

- [x] 8.1 建立 Shipment + ShipmentLine（EUDR 適用商品）→ eudr_dds_status 自動設為 draft
- [x] 8.2 分配 ProductionBatch 超額 → 回傳 warnings 不擋寫入
- [x] 8.3 weighted_pcf 計算正確（含部分 lot_pcf = null 的情境）
- [x] 8.4 `GET /api/v1/shipments/{id}/dds-draft` 正確輸出草稿 JSON，缺漏溯源標記 origins_missing
- [ ] 8.5 前端詳情頁展開批號分配、DDS 草稿預覽面板可收合
- [ ] 8.6 標記已送出後 badge 更新為藍色，eudr_dds_ref 正確儲存
