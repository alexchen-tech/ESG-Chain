## Context

一個 Shipment（出口申報批次）對應一份出口報單，包含一或多個 ShipmentLine（出口商品項目）。每個 ShipmentLine 代表這次出口的某個貿易商品（TradeGood），並透過 `shipment_line_batches` 中間表指定由哪些 ProductionBatch 提供貨量（allocated_quantity）。同一個 ProductionBatch 可以出現在多個 ShipmentLine（不同出口批次集貨），不做硬性超額驗證，採軟性警告。

EUDR DDS 草稿的產出邏輯：遍歷 Shipment → ShipmentLines → ProductionBatches → RawMaterialOrigins，彙整所有農場 GPS 與認證資訊，輸出結構化 JSON 草稿（不直接送 EU TRACES NT，Phase 3 範疇）。

## Goals / Non-Goals

**Goals:**
- `shipments` / `shipment_lines` / `shipment_line_batches` 資料模型
- Shipment CRUD：建立、新增商品項目、分配批號
- EUDR DDS 草稿生成（JSON 輸出）
- 出口 PCF 加權平均計算
- 前端 Shipment 列表 + 詳情頁

**Non-Goals:**
- 與 EU TRACES NT 直接介接（Phase 3）
- CBAM 季度彙整申報（獨立 change）
- 海關 EDI 報文產出（Phase 3）
- allocated_quantity 硬性超額驗證（採軟性警告）

## Decisions

**資料模型**

```
shipments
  id uuid PK
  shipment_no varchar(100) UNIQUE     ← 使用者自訂或系統產生（SHIP-YYYYMM-NNN）
  target_market varchar(10)           ← ISO 市場代碼，如 EU、UK、US
  export_date date nullable
  eudr_dds_status enum(not_required, draft, submitted, approved) default(draft)
  eudr_dds_ref varchar(200) nullable  ← 申報成功後的官方編號
  eudr_submitted_at timestamp nullable
  created_by uuid FK → users
  notes text nullable
  timestamps + softDeletes

shipment_lines
  id uuid PK
  shipment_id uuid FK
  trade_good_id uuid FK → trade_goods
  buyer_product_id uuid FK nullable → buyer_products
  total_quantity decimal(15,4)
  unit varchar(20)
  hs_code_override varchar(10) nullable  ← 出口用 HS Code 若與商品主檔不同
  weighted_pcf decimal(15,4) nullable    ← 由 Service 計算後寫入
  timestamps

shipment_line_batches
  id uuid PK
  shipment_line_id uuid FK
  production_batch_id uuid FK → production_batches
  allocated_quantity decimal(15,4)
  timestamps
  UNIQUE (shipment_line_id, production_batch_id)
```

**EUDR DDS 草稿生成邏輯**

`ShipmentService::generateDdsDraft(Shipment): array`

```
{
  "shipment_no": "SHIP-202606-001",
  "target_market": "EU",
  "export_date": "2026-06-20",
  "operator": { "name": "...", "country": "TW" },
  "commodities": [
    {
      "trade_good": "GMN-TEE-002",
      "hs_code": "6109.10",
      "total_quantity": 5000,
      "unit": "PCS",
      "weighted_pcf": 2.43,         ← 加權平均
      "production_batches": [
        {
          "batch_no": "PRD-A-0601",
          "supplier": "GMN-001 越南廠",
          "quantity": 3000,
          "raw_material_origins": [
            {
              "material": "棉花",
              "country": "BR",
              "gps": "-15.2, 47.3",
              "harvest_year": 2025,
              "certification": "GOTS-12345"
            }
          ]
        }
      ]
    }
  ],
  "eudr_risk_assessment": "pending"   ← Phase 3 才做風險評估 API
}
```

**出口 PCF 加權平均計算**

```
ShipmentLine.weighted_pcf =
  Σ(batch.lot_pcf × batch.allocated_quantity) / Σ(batch.allocated_quantity)

若任一批號 lot_pcf = null：weighted_pcf = null（不估算）
```

**allocated_quantity 超額警告**

當 `Σ(shipment_line_batches.allocated_quantity for this batch) > production_batch.quantity`，API 回傳 `warnings: ["批號 PRD-A-0601 已超額分配 X 件"]`，不擋寫入。

**EUDR 適用判斷**

若 Shipment 的任一 ShipmentLine 的 TradeGood.is_eudr_applicable = true，則 Shipment.eudr_dds_status 預設為 `draft`（需申報）；否則為 `not_required`。

**前端架構**

- `ShipmentsView.vue`：列表，含 shipment_no、target_market、export_date、EUDR 狀態 badge、商品項目數、出口日期
- `ShipmentDetailView.vue`：詳情頁（路由 `/compliance/shipments/:id`）
  - 上方：Shipment 基本資訊 + EUDR DDS 狀態操作
  - 中間：ShipmentLine 列表，每行展開顯示已分配 ProductionBatch
  - 右側面板：DDS 草稿預覽（JSON tree 或結構化顯示）
  - 「分配批號」Modal：搜尋 ProductionBatch（依商品匹配），填入 allocated_quantity

## Risks / Trade-offs

- `weighted_pcf` 即時計算成本低（記憶體加總），在 store/update 批號分配時同步更新 ShipmentLine，不需非同步任務。
- DDS 草稿為 JSON 輸出，不直接送 EU TRACES NT。Phase 3 再評估是否需要 XML 格式（TRACES NT 使用 XML）。
- `shipment_no` 允許使用者自訂，系統同時提供自動產生（`SHIP-YYYYMM-NNN`）作為預設值。
