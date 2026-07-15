## Why

實測驗證發現三個層級的問題，皆源於 SalesProduct 重構（2026-06-17 將 BuyerProduct 合併進 SalesProduct）未完全收尾：

1. **P0 / 現役功能已壞**：UI 上「BOM 匯入」功能（`POST /api/v1/sales-products/{id}/bom-lines/import`）實測直接回 500。Laravel log 顯示 `BomLineImportService::importFromArray(): Argument #1 ($product) must be of type App\Models\BuyerProduct, App\Models\SalesProduct given`——`ProductBomLineController::import()` 已改傳 `SalesProduct`，但 `BomLineImportService` 的方法簽章與內部欄位（`buyer_product_id`）從未跟進更新，任何人現在透過 UI 匯入 BOM 都會失敗。
2. **P1 / 同步閘道死路徑**：`ErpSyncService::syncBomLines()`（可由 `POST /api/v1/erp/webhook/bom-lines` 觸發）查詢的 `BuyerProduct`/`buyer_products` 表已被 migration `2026_06_17_200006_drop_buyer_product_tables.php` 刪除，以 tinker 驗證會直接丟 `SQLSTATE[42S02]: Table 'esgchain.buyer_products' doesn't exist`。
3. **P2 / 系統邊界違規**：`MaterialItemController::store/update` 允許前端直接建立或修改 `item_code`，違反 CLAUDE.md「不可手動建立 ERP 已管理的實體（MaterialItem item_code）」的明文禁止事項，與既有 `material-item-master` spec 的「建立新料號」需求互相矛盾。

三者皆是「物料主檔／銷售產品 BOM 資料一致性」的根本缺口，且第一項是現役功能完全不可用，須立即修正。

## What Changes

- **修復 `BomLineImportService::importFromArray()` / `importFromCsv()`**：型別提示由 `BuyerProduct` 改為 `SalesProduct`，內部查詢/建立欄位 `buyer_product_id` 改為 `sales_product_id`，使現行 BOM 匯入功能恢復可用（此為本次最高優先修復項）
- 修復 `ErpSyncService::syncBomLines()`：改查詢 `SalesProduct`／`ProductBomLine`（`sales_product_id`），不再引用已刪除的 `BuyerProduct`／`buyer_products`
- 為 `ErpSyncService` 的 BOM 同步比照 Supplier 同步模式，新增顯式 `ERP_OWNED_BOM_FIELDS` 常數，並保護 `material_group_source = 'manual'` 的行（邏輯與 `BomLineImportService` 既有保護一致，避免兩條同步路徑行為不一致）
- 為 `ErpSyncService::syncMaterials()` 新增顯式 `ERP_OWNED_MATERIAL_FIELDS` 常數（`item_code`、`name`、`hs_code`、`unit`），明確排除 ESG 擁有欄位（`net_weight`、`pcr_percentage`），避免未來新增同步欄位時誤覆寫
- **BREAKING**：`POST /api/v1/material-items`、`PUT /api/v1/material-items/{id}` 移除前端可自由帶入 `item_code` 建立/修改的能力；`item_code` 改為唯讀欄位，僅可透過 ERP 同步（webhook/排程）或標記為 `import_source=admin_csv` 的 CSV 批次匯入建立
- 前端 `MaterialItemsView.vue`／`MaterialSettingsView.vue` 的「新增料號」流程，改為導向 CSV 匯入或唯讀提示，移除自由輸入 `item_code` 的建立表單欄位

## Capabilities

### New Capabilities
（無）

### Modified Capabilities
- `material-item-master`：「料號主檔 CRUD」需求修改為 `item_code` 僅可透過 ERP 同步或標記匯入來源的 CSV 匯入建立，一般管理員 CRUD 表單不可自由輸入 `item_code`
- `erp-bom-import`：修正 `BomLineImportService` 的目標 entity 由已刪除的 `buyer_products`／`BuyerProduct` 改為 `sales_products`／`SalesProduct`，使現行 JSON/CSV BOM 匯入需求重新可被滿足
- `erp-sync-gateway`：「ERP 同步欄位歸屬保護」需求擴充，新增 MaterialItem 的顯式 ERP/ESG 欄位清單；修正 BOM 同步的目標 entity 由已刪除的 `buyer_products` 改為 `sales_products`/`product_bom_lines`

## Impact

- 後端：`BomLineImportService.php`（最高優先，現役功能修復）、`MaterialItemController.php`（store/update 驗證規則）、`ErpSyncService.php`（syncBomLines、syncMaterials）
- 前端：`MaterialItemsView.vue`、`MaterialSettingsView.vue`（新增料號 UI 流程）；BOM 匯入前端介面無需改動（修復後即可恢復原有行為）
- 受影響整合：`POST /api/v1/sales-products/{id}/bom-lines/import`（現役、目前壞掉）、`POST /api/v1/erp/webhook/bom-lines`、`POST /api/v1/erp/webhook/materials`（webhook 觸發路徑）、未來排程拉取（`ErpAdapterInterface::fetchBomLines`）
- 不在本次範圍：舊版 `buyer-products` 路由群組（`BuyerProductController`、`BuyerProductImportController`、`buyer-products/{buyerProduct}/bom-lines/import` 等，同樣引用已刪除的 `buyer_products` 表，且其路由參數名與 Controller 方法簽章 `$salesProduct` 不一致，推測同樣無法正常運作）的全面移除——這是更大範圍的死代碼清理，於 design.md 的 Open Questions 中列為待決議事項
