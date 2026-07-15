## Why

Phase 2a 建立了生產批號（ProductionBatch）作為溯源的原子單位。但 EUDR DDS 申報的單位不是生產批號，而是「出口申報批次」（Shipment）——一份出口報單可能集合來自多個工廠的生產批號，申報對象是目標市場（EU）主管機關。

現有系統缺少這個彙整層：無法把多個生產批號組成一次出口申報、無法計算出口 PCF 加權平均、無法產出 EUDR DDS 草稿。Phase 2b 填補這個缺口，讓採購主管能在 ESG·Chain 內完成出口申報草稿的建立與管理，而無需手動整理多個批次的原料溯源資料。

## What Changes

- 新增 `shipments`（出口申報批次）、`shipment_lines`（出口商品項目）、`shipment_line_batches`（批號分配中間表，含 allocated_quantity）三張資料表
- 新增出口申報管理 UI：新增 Shipment、指派商品項目、將生產批號分配到項目、查看 EUDR DDS 草稿
- 新增 EUDR DDS 草稿生成邏輯：彙整所有 ShipmentLine → ProductionBatch → RawMaterialOrigin，輸出結構化草稿
- 新增出口 PCF 加權平均計算：依各批號的 lot_pcf × allocated_quantity 計算本次申報的加權碳排

## Capabilities

### New Capabilities

- `export-shipment-management`：出口申報管理 — 建立 Shipment、指派 ShipmentLine（出口商品）、分配 ProductionBatch（含 allocated_quantity）、產出 EUDR DDS 草稿、計算出口 PCF 加權平均

## Impact

- **資料庫**：新增 3 張表（shipments、shipment_lines、shipment_line_batches）
- **後端**：新增 Shipment / ShipmentLine / ShipmentLineBatch Model；新增 ShipmentController、ShipmentService（含 DDS 草稿生成、PCF 加權計算）
- **前端**：新增 `ShipmentsView.vue` + `ShipmentDetailView.vue`；sidebar 加入「出口申報」入口；新增 `api/modules/shipment.ts`
- **依賴**：需先完成 `export-lot-phase-2a`（production_batches + raw_material_origins）與 `export-link-erp-bridge`（erp_product_code）
