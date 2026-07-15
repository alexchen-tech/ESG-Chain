## Context

目前 ESG-Chain 的合規追蹤使用 `BuyerProductSupplier`（產品→供應商）作為關聯基礎，物料群組（`material_group_id`）是選填的附屬屬性。這個設計對於「一個供應商供應整個產品」的場景足夠，但無法表達：同一供應商在同一產品中提供多種不同物料（每種物料需要不同合規文件），也無法從企業 ERP 的 BOM 表匯入並保留 ERP 的識別碼。

ESG-Chain 定位為 ERP 外的永續風險評估平台，不取代 ERP，僅在 ERP 結構之上加入永續/合規維度。

## Goals / Non-Goals

**Goals:**
- 建立 `ProductBomLine` 作為物料主體，精確對應 BOM 結構
- 支援從 ERP 系統匯入（JSON API + CSV），以 `erp_line_id` 實現冪等 upsert
- 追蹤物料群組與指定供應商的資料來源（3-C source tracking）
- 合規計算引擎支援雙軌：BomLine 優先，無資料時退回 `BuyerProductSupplier`
- 保留所有現有 `BuyerProductSupplier` 功能不受影響

**Non-Goals:**
- 不取代或刪除 `BuyerProductSupplier` 模型
- 不同步 ERP 的庫存、訂單、財務資料（僅 BOM 結構）
- 不實作前端 UI（由 product-bom-line-frontend 變更負責）
- 不修改 esgchain-ai 的計分引擎

## Decisions

### D1：ProductBomLine 作為獨立模型（非 BuyerProductSupplier 擴展）

**決定**：新建 `product_bom_lines` 資料表，不修改 `buyer_product_suppliers`。

**理由**：BomLine 的主體是「物料」，而 `BuyerProductSupplier` 的主體是「供應商關係」。兩者語意不同，強行合併會造成 nullable 欄位爆炸。雙軌並行讓現有使用者（無 BOM 資料）不受影響。

### D2：erp_line_id 冪等 upsert

**決定**：匯入時以 `(buyer_product_id, erp_line_id)` 為唯一鍵做 `updateOrCreate`。

**理由**：ERP 可能多次匯出相同資料。冪等設計讓重複匯入安全，不產生重複紀錄。ESG 標註欄位（`material_group_id`、`notes` 等人工修改的欄位）在重複匯入時**不覆蓋**，僅更新 ERP 控制的欄位（數量、單價、HS code）。

### D3：material_group_source 三值 enum

**決定**：`material_group_source` 為 `'erp_imported' | 'hs_inferred' | 'manual'`，優先級 manual > erp_imported > hs_inferred。

**理由**：讓使用者和系統都知道目前物料群組的「置信度」，手動設定的不應被自動推斷覆蓋。

### D4：合規計算雙軌路徑

**決定**：`SupplierComplianceStatusService` 先嘗試 BomLine 路徑，若該供應商無任何 BomLine 則退回 `BuyerProductSupplier` 路徑。

```
合規計算邏輯：
  if 有 ProductBomLine（指定此供應商）
    → 以 BomLine.materialGroup.required_doc_types 計算
  else if 有 BuyerProductSupplier（含 material_group_id）
    → 現有邏輯
  else
    → status: 'unconfigured'
```

### D5：CSV 匯入格式

**決定**：使用 `league/csv`（輕量，Laravel 無需額外 Excel 相依）。欄位對應：`erp_line_id`, `material_name`, `hs_code`, `quantity`, `unit`, `unit_price`, `currency`, `supplier_code`（optional，對應 `suppliers.code`）。

## Risks / Trade-offs

- **[風險] 雙軌計算邏輯複雜度**：兩條路徑可能產生不一致的結果 → 緩解：為每個計算路徑加清楚的 log，並在 API response 中回傳 `compliance_basis: 'bom_line' | 'product_supplier' | 'unconfigured'`
- **[風險] ERP 匯入時 supplier_code 不存在**：供應商在 ESG-Chain 中尚未建立 → 緩解：匯入時對未能解析的 supplier_code 記錄 warning，但不 fail 整批匯入；`designated_supplier_id` 保持 null，由使用者後續手動補齊
- **[Trade-off] 不修改 BuyerProductSupplier**：兩張表同時存在會造成一定的資料維護負擔 → 接受，因為短期內不是所有客戶都有 BOM 資料，雙軌是必要的過渡設計

## Migration Plan

1. 新增 migration：建立 `product_bom_lines` 資料表
2. 部署 API 層（不影響現有功能）
3. 更新 `SupplierComplianceStatusService`（雙軌邏輯，預設走舊路徑）
4. 無需資料遷移，`BuyerProductSupplier` 保持原樣
5. Rollback：刪除新增 migration 即可回退，現有資料不受影響

## Open Questions

- `league/csv` 是否已在 composer.json？若無需加入相依
- 未來是否需要 BomLine 的版本歷史（versioning）？目前設計不含，後續可加 `bom_line_histories` 表
