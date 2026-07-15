## Context

生產批號（ProductionBatch）是溯源的原子單位，資料來自 ERP，不在 ESG·Chain 內自行建立。一個批號只屬於一個工廠（supplier），但同一個批號的數量可以分配到多個出口申報（Phase 2b 的 shipment_line_batches）。原料溯源（RawMaterialOrigin）掛在批號下，記錄農場級別的地理與認證資訊，是 EUDR DDS 草稿生成的直接資料來源。

## Goals / Non-Goals

**Goals:**
- `production_batches` + `raw_material_origins` 資料模型
- ERP Webhook 接入（HMAC-SHA256 驗證）+ CSV import 備援
- 批號與型號對應（透過 `erp_product_code` 匹配 `buyer_product_trade_goods`）
- 前端批號列表 + 原料溯源 Drawer 編輯

**Non-Goals:**
- 出口申報（Shipment）彙整 — Phase 2b 範疇
- EUDR DDS 草稿自動產出 — Phase 2b 範疇
- 批次 PCF 計算引擎 — Phase 3 範疇
- EU TRACES NT 直接介接 — Phase 3 範疇

## Decisions

**資料模型**

```
production_batches
  id uuid PK
  erp_batch_no varchar(100) UNIQUE   ← ERP 唯一鍵，重複時 upsert
  erp_order_no varchar(100) nullable ← 採購單/出貨單號
  buyer_product_trade_good_id uuid FK nullable  ← 匹配後寫入，初始可 null
  supplier_id uuid FK → suppliers    ← 生產工廠
  production_date date nullable
  quantity decimal(15,4)
  unit varchar(20)
  lot_pcf decimal(15,4) nullable     ← 批次碳排，supplier 回報或估算
  lot_pcf_source enum(calculated, reported, estimated) nullable
  source enum(webhook, csv, manual)
  erp_synced_at timestamp nullable
  timestamps + softDeletes

raw_material_origins
  id uuid PK
  production_batch_id uuid FK
  bom_line_id uuid FK nullable       ← 對應 BOM 哪條物料（可選）
  material_name varchar(200)
  origin_country char(2)             ← ISO 3166
  facility_name varchar(200) nullable
  gps_lat decimal(9,6) nullable
  gps_lng decimal(9,6) nullable
  harvest_year smallint nullable     ← EUDR 農產品
  certification_ref varchar(200) nullable  ← GOTS/GRS/有機認證號
  timestamps
```

**Webhook 匹配邏輯**

```
1. 驗證 X-ERP-Signature header（HMAC-SHA256）
2. 依 erp_batch_no upsert production_batches
3. 依 erp_product_code 查 buyer_product_trade_goods
   → 找到一筆：寫入 buyer_product_trade_good_id
   → 找到多筆：寫入第一筆，log warning
   → 找不到：batch 建立但 buyer_product_trade_good_id = null，UI 顯示「未匹配」
4. 依 supplier_code 查 suppliers
```

**Webhook 安全**

- 優先：`X-ERP-Signature: sha256=<HMAC-SHA256(secret, raw_body)>`
- `.env` 加 `ERP_WEBHOOK_SECRET`
- 若 ERP 不支援 HMAC，提供 `ERP_API_KEY` 備援（`Authorization: Bearer <key>`）
- 兩種驗證方式透過 `ERP_AUTH_MODE=hmac|api_key` 切換

**CSV 格式**

```
erp_batch_no, erp_order_no, erp_product_code, supplier_code,
production_date, quantity, unit, lot_pcf
```

**超額分配（Phase 2b 前置考慮）**

ProductionBatch 不做 allocated_quantity 總量驗證，採軟性警告（Phase 2b 的 UI 顯示）。

**前端架構**

- 新頁面 `ProductionBatchesView.vue`，路由 `/compliance/production-batches`
- 加入側邊欄「商品合規管理」群組，顯示名稱「生產批號」
- 列表含：批號、工廠、採購品連結狀態（matched / unmatched）、數量、日期、批次 PCF
- 右側 Drawer：原料溯源清單 + 新增/編輯/刪除

## Risks / Trade-offs

- `erp_batch_no` 設 UNIQUE，ERP 重送同一批號時 upsert（不重複建立）。接受此設計。
- `buyer_product_trade_good_id` 允許 null，未匹配的批號仍可建立並在前端標示「待匹配」，讓採購主管手動選擇。
- lot_pcf 目前為手動填入或 supplier 回報，不接計算引擎（Phase 3）。
