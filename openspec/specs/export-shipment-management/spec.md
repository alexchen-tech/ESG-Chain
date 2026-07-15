## ADDED Requirements

### Requirement: 出口申報批次資料模型

**What**: 建立 `shipments`、`shipment_lines`、`shipment_line_batches` 三張資料表，表達「一份出口申報 → 多個出口商品項目 → 各項目由多個生產批號集貨」的三層結構。

**Behavior**:
- `shipment_no` 唯一，可使用者自訂或系統自動產生（`SHIP-YYYYMM-NNN`）
- 建立 Shipment 時，依 ShipmentLine 的 SalesProduct.is_eudr_applicable 自動決定 `eudr_dds_status`：有任一 EUDR 適用商品則為 `draft`，否則為 `not_required`
- `shipment_line_batches` 的 (shipment_line_id, production_batch_id) 為 UNIQUE

#### Scenario: 自動 EUDR 狀態判斷
- **WHEN** 新增 ShipmentLine 且其 SalesProduct.is_eudr_applicable = true
- **THEN** Shipment.eudr_dds_status 自動設為 `draft`（若原本是 not_required）

---

### Requirement: 批號分配與出口 PCF 計算

**What**: ShipmentLine 透過 `shipment_line_batches` 指定由哪些 ProductionBatch 提供貨量（allocated_quantity），系統自動計算加權平均 PCF 寫入 `shipment_lines.weighted_pcf`。

**Behavior**:
- 分配批號時填入 `allocated_quantity`（必填，> 0）
- 若批號的 `allocated_quantity` 合計超過 `production_batch.quantity`，回傳 `warnings` 陣列但不擋寫入
- `weighted_pcf = Σ(lot_pcf × allocated_qty) / Σ(allocated_qty)`；若任一批號 lot_pcf = null，weighted_pcf = null

#### Scenario: 批號超額分配警告
- **WHEN** 新增批號分配後，該 production_batch 的 allocated_quantity 合計 > production_batch.quantity
- **THEN** API 回傳 `{ data: ..., warnings: ["批號 PRD-A-0601 累計分配 6,000 件，超過生產量 5,000 件"] }`，分配記錄仍建立

#### Scenario: PCF 計算含 null 批號
- **WHEN** 任一已分配批號的 lot_pcf = null
- **THEN** shipment_line.weighted_pcf = null，不估算

---

### Requirement: EUDR DDS 草稿生成

**What**: `GET /api/v1/shipments/{id}/dds-draft` 回傳結構化 JSON，彙整所有 ShipmentLine → ProductionBatch → RawMaterialOrigin。

**Behavior**:
- 回傳結構含：shipment 基本資訊、commodities 陣列（每個 ShipmentLine）、每個 commodity 含 production_batches 陣列、每個 batch 含 raw_material_origins
- 若某 ProductionBatch 無 RawMaterialOrigin，該批號在草稿中標記 `"origins_missing": true`
- `eudr_risk_assessment` 欄位固定為 `"pending"`（Phase 3 再接風險評估 API）

#### Scenario: 草稿含缺漏溯源
- **WHEN** 某 ProductionBatch 無任何 RawMaterialOrigin
- **THEN** DDS 草稿該批號區塊標記 `origins_missing: true`，前端顯示警告 badge

#### Scenario: 無 EUDR 適用商品
- **WHEN** Shipment.eudr_dds_status = not_required
- **THEN** `GET /api/v1/shipments/{id}/dds-draft` 回傳 404 with message「此申報批次無 EUDR 適用商品，不需產出 DDS」

---

### Requirement: 出口申報管理 UI

**What**: `ShipmentsView.vue`（列表）與 `ShipmentDetailView.vue`（詳情）提供出口申報的完整操作介面。

**Behavior**:
- 列表：shipment_no、target_market、export_date、eudr_dds_status badge（not_required=灰/draft=黃/submitted=藍/approved=綠）、商品項目數
- 詳情頁（`/compliance/shipments/:id`）：
  - 上方卡片：基本資訊 + EUDR DDS 狀態與操作按鈕（「產出草稿」「標記已送出」）
  - ShipmentLine 列表，展開顯示已分配 ProductionBatch 及各批號 allocated_quantity
  - 「分配批號」Modal：依 sales_product_id 篩選可用 ProductionBatch，輸入 allocated_quantity
  - DDS 草稿預覽面板（右側，可收合）：結構化顯示草稿內容，缺漏溯源標橙色警告

#### Scenario: 產出 DDS 草稿
- **WHEN** 使用者點擊「產出草稿」
- **THEN** 呼叫 `GET /api/v1/shipments/{id}/dds-draft`，結果顯示在右側預覽面板

#### Scenario: 標記已送出
- **WHEN** 使用者點擊「標記已送出」並填入申報編號
- **THEN** `PATCH /api/v1/shipments/{id}`（eudr_dds_status=submitted, eudr_dds_ref=編號, eudr_submitted_at=now），badge 更新為藍色

---

<!-- delta: market-compliance-rules -->
## MODIFIED Requirements

### Requirement: EUDR DDS 狀態自動判定依市場計算

Shipment addLine() SHALL 改呼叫 MarketComplianceChecker，取代現有 `trade_good.is_eudr_applicable` 靜態判定。當加入商品行後，若 MarketComplianceChecker 判定 shipment.target_market 市場要求 EUDR_DDS 且對應文件未滿足，系統 SHALL 將 `eudr_dds_status` 從 "not_required" 更新為 "draft"。

#### Scenario: 加入 EUDR 商品至 EU 出口申報

- **WHEN** Shipment.target_market = "EU"，加入上游含 EUDR_DDS 物料的 TradeGood
- **THEN** 系統呼叫 MarketComplianceChecker，判定 EUDR_DDS 為義務，eudr_dds_status 自動設為 "draft"

#### Scenario: 加入相同商品至 US 出口申報

- **WHEN** Shipment.target_market = "US"，加入相同 TradeGood
- **THEN** MarketComplianceChecker 判定 EUDR_DDS 非 US 義務，eudr_dds_status 維持 "not_required"
