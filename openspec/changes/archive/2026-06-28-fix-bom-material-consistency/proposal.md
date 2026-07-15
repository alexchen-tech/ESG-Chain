## Why

ProductBomLine 的資料讀取路徑在不同 Controller 之間不一致，導致快照（snapshot）與物料主檔（MaterialItem）的 name/hs_code/material_group 在部分查詢路徑中以錯誤優先順序被讀取；同時 store()/update() 未自動同步 material_group_id，且缺少對「未連結物料主檔」的 BomLine 前置守衛，使得碳排請求在事後才以 422 靜默失敗。

## What Changes

- **修正讀取路徑**：`BomLineSupplierController` 的 hs_code / material_name 讀取順序改為主檔優先（`materialItem?.hs_code ?? bomLine->hs_code`），與 `ProductBomLineController::index()` 的 effective 邏輯保持一致
- **自動同步 material_group_id**：`ProductBomLineController::store()` 與 `update()` 在收到 `material_item_id` 時，若呼叫端未明確傳入 `material_group_id`，自動從 MaterialItem 帶入 `material_group_id` 與 `material_group_source='erp_imported'`
- **新增 linkage_status 欄位**：ProductBomLine 新增 `linkage_status` enum（`linked` / `unlinked`），在 store()/update() 時依 `material_item_id` 是否存在自動設定；前端 BOM 列表對 `unlinked` 行顯示警告標籤

## Capabilities

### New Capabilities

- `bom-line-linkage-status`: BomLine 連結狀態欄位與前端警告顯示——記錄每條 BOM 明細是否已關聯物料主檔，並在列表頁提示待完善項目

### Modified Capabilities

- `product-bom-line`: 新增 linkage_status 欄位、store()/update() 自動同步 material_group_id 的行為規格變更
- `bom-line-supplier-management`: hs_code / material_name 讀取優先順序修正（主檔優先）

## Impact

- **API**：`ProductBomLineController::store()` / `update()`、`BomLineSupplierController`
- **Migration**：`product_bom_lines` 新增 `linkage_status` enum 欄位
- **前端**：`SalesProductDetailView.vue`（BOM 列表區塊）新增 unlinked 警告標籤
- **無 breaking change**：`linkage_status` 預設值為 `linked`（有 material_item_id）/ `unlinked`（無），舊資料透過 migration 回填
