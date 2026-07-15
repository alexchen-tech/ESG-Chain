## Context

現有 TradeGood 狀態：
- `supplier_id` NOT NULL FK → `suppliers.id`
- `TradeGoodObserver.saving()` 自動從 HS Code 推導 `material_group_id`
- `TradeGoodObserver.saved/deleted()` 清除 SupplierGroup 的 inferred cache
- `SupplierComplianceDoc.trade_good_id` nullable FK（文件可選擇掛在特定 TradeGood）
- 前端路由 `/trade-goods` 存在於 router 與 sidebar，但無對應 Vue view

Portal 現況：
- `SupplierCompliancePortalView.vue` — 供應商查看合規文件
- 無任何碳排回報欄位

## Goals / Non-Goals

**Goals:**
- TradeGood 代表「中心廠自己的出口品」，不綁定單一供應商
- `trade_good_suppliers` 多對多關聯，記錄每個品項的上游供應商與物料群組
- 前端提供品項清單（CBAM / EUDR 法規狀態）與上游供應商面板
- 供應商透過 Portal 對指定 TradeGood 填報 `embedded_emissions`（kgCO2e/unit）
- 中心廠在 TradeGood 詳情中查閱各供應商回報值並確認採用哪個數字

**Non-Goals:**
- CBAM 正式申報書生成（PDF / XML）
- embedded_emissions 計算引擎（LCA / 排放因子自動計算）
- 多客戶出售記錄（TradeGood 不追蹤銷往哪個下游客戶）

## Decisions

### D1: supplier_id 採漸進式移除

**第一步**：`supplier_id` 改為 nullable（保留欄位）  
**第二步**：資料遷移（現有 trade_goods 資料移入 trade_good_suppliers）  
**第三步**：migration drop column

**理由**：避免因 FK 約束造成資料遺失；讓 rollback 路徑明確。

### D2: trade_good_suppliers 輕量化

```sql
trade_good_suppliers
├── id (uuid)
├── trade_good_id  FK → trade_goods
├── supplier_id    FK → suppliers
├── material_group_id (nullable) FK → material_groups
├── notes (nullable text)
└── timestamps
```

不設 unique constraint on (trade_good_id, supplier_id)，允許同一供應商提供多個物料群組。

**理由**：一個鋼製品可能同時向 A 廠買板材、向 B 廠買表面處理。

### D3: embedded_emissions 回報流程

```
供應商 Portal
  → 看到「待填報品項」列表（自己被關聯到的 TradeGood）
  → 填報 emissions_value (float) + calculation_note (text)
  → 儲存至 trade_good_supplier_emissions 表

中心廠 TradeGood 詳情
  → 查看各供應商回報值
  → 手動選擇「採用此數值」→ 寫入 trade_goods.embedded_emissions
```

新增 `trade_good_supplier_emissions` 表（非直接改 trade_good_suppliers）以保留歷史版本。

```sql
trade_good_supplier_emissions
├── id (uuid)
├── trade_good_id
├── supplier_id
├── emissions_value (decimal 15,4)  kgCO2e/unit
├── calculation_note (text nullable)
├── reported_at (timestamp)
└── confirmed_at (timestamp nullable) ← 中心廠確認時間
```

### D4: EUDR 適用性判定

`TradeGood.is_eudr_applicable` 衍生計算：

```
trade_good_suppliers
  → material_group.required_doc_types 含 'EUDR_DDS'
  → 任一上游供應商有 EUDR 管制物料 → is_eudr_applicable = true
```

不存為欄位，在 Service 層即時計算後回傳。

### D5: TradeGoodObserver 調整

原本 `clearGroupCache` 依賴 `tradeGood.supplier_id` 查 SupplierGroup。改版後改為走 `tradeGoodSuppliers` 關聯清除所有相關 SupplierGroup cache。

## Risks / Trade-offs

- **現有 trade_good 資料**：supplier_id 欄位有值的既有資料，migration 時自動建立一筆 trade_good_suppliers 記錄再 nullify supplier_id。風險低（開發階段，無正式生產資料）。
- **SupplierComplianceDoc.trade_good_id**：語意不變（文件可選擇掛在特定出口品），保留。
- **Portal UX 複雜度**：供應商需看到「哪些 TradeGood 需要我回報碳排」，需要後端 endpoint 過濾。

## Migration Plan

1. `alter trade_goods ALTER COLUMN supplier_id DROP NOT NULL`
2. `create table trade_good_suppliers`
3. `create table trade_good_supplier_emissions`
4. 資料遷移：現有 trade_goods 每筆 supplier_id → 插入 trade_good_suppliers
5. `alter trade_goods DROP COLUMN supplier_id`（確認資料遷移正確後）
6. 部署後端新 endpoint
7. 部署前端 TradeGoodsView + Portal 碳排欄位

Rollback：steps 1-4 可逆（重填 supplier_id from trade_good_suppliers）；step 5 不可逆，需確認後執行。

## Open Questions

- Portal 碳排回報是否需要審核狀態機（draft → submitted → confirmed），或直接 confirmed_at 即可？建議：先用 confirmed_at，狀態機留待 v2。
- 中心廠「採用此數值」的確認動作是否需要留稽核日誌？建議：是，寫 AuditLog。
