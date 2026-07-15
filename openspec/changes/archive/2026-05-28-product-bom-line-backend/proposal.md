## Why

現有的 `BuyerProductSupplier` 模型只能記錄「哪個供應商供應哪個產品」，缺乏物料層級的細節，無法對應到採購商的 BOM 表結構，導致合規文件需求無法精確對應到特定物料項目。隨著 CBAM、UFLPA、EUDR 等法規要求越來越細緻，系統需要以「物料」為主體追蹤合規需求與供應商來源。

## What Changes

- 新增 `product_bom_lines` 資料表，以物料為主體記錄產品 BOM 結構
- 每條 BOM 明細包含：物料名稱、HS Code、物料群組、指定供應商、數量/單價、合規需求
- 支援 ERP 匯入（REST API + CSV/Excel），以 `erp_line_id` 實現冪等 upsert
- `material_group_source` 欄位追蹤物料群組的來源（`erp_imported` / `hs_inferred` / `manual`），優先級：manual > erp_imported > hs_inferred
- `supplier_source` 欄位追蹤指定供應商的來源（`erp_designated` / `manual`）
- 合規狀態計算引擎更新：優先走 BomLine 路徑，無 BOM 資料時退回 `BuyerProductSupplier` 路徑（雙軌並行）
- 新增 BOM 管理 API endpoints（CRUD + ERP 匯入）

## Capabilities

### New Capabilities

- `product-bom-line`: ProductBomLine 模型、migration、CRUD API，代表產品的 BOM 明細（物料→供應商→合規需求）
- `erp-bom-import`: ERP BOM 資料匯入機制，支援 REST API（JSON）與 CSV/Excel 上傳，冪等 upsert 設計
- `bom-driven-compliance`: 以 BomLine 為基礎的合規狀態計算，更新 SupplierComplianceStatusService 支援雙軌路徑

### Modified Capabilities

- `supplier-compliance`: 合規計算邏輯新增 BomLine 優先路徑，現有 `BuyerProductSupplier` 路徑保留為 fallback

## Impact

- **新增**：`app/Models/ProductBomLine.php`、migration、Controller、Service
- **修改**：`SupplierComplianceStatusService`（新增 BomLine 計算路徑）、`BuyerProduct` 模型（新增 `bomLines()` 關聯）
- **API**：新增 `/api/v1/buyer-products/{id}/bom-lines`（CRUD）、`/api/v1/buyer-products/{id}/bom-lines/import`（ERP 匯入）
- **資料庫**：新增 `product_bom_lines` 資料表
- **相依**：`maatwebsite/excel` 或 `league/csv`（CSV 解析）
