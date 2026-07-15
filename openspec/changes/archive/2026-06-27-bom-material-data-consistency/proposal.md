## Why

BOM 明細（`ProductBomLine`）同時保有「快照欄位」（`material_name`、`hs_code`）與「關聯欄位」（`material_item_id`、`material_group_id`），但兩者的同步規則不一致：ERP 匯入路徑會自動 upsert 並連結 `MaterialItem`，手動建立路徑卻不會。這導致同一張 BOM 表中，部分明細的顯示名稱/HS Code/法規判斷會跟物料主檔脫鉤，且前端目前未使用後端已經算好的 `effective_*` 欄位。此外，碳排填報要求 `material_item_id` 必填，卻未在建立 BOM 明細時強制檢查，造成使用者建立後才發現無法填報。三個問題同屬「BOM ↔ 物料主檔 ↔ 供應商主檔」資料一致性範疇，一併處理。

## What Changes

- 前端 BOM 明細 Tab（`SalesProductDetailView.vue`）改用後端既有的 `effective_material_name` / `effective_hs_code` / `effective_material_group` 欄位顯示，而非快照欄位
- 後端手動建立 BOM 明細時（`ProductBomLineController::store()`），若提供 `material_item_id`，自動帶入該物料主檔的 `name`/`hs_code` 至快照欄位，保持快照與關聯一致
- `SalesProduct::syncInferredRegulations()` 法規推算邏輯改為優先使用 `materialItem->materialGroup`（effective 來源），其次才 fallback 至 BomLine 自身的 `material_group_id`，與 `ProductBomLineController::index()` 的判斷邏輯統一
- 新增「未綁定物料主檔」的視覺提示：BOM 明細清單若 `material_item_id` 為空，顯示警示標籤，提醒使用者此明細無法用於碳排填報
- **BREAKING**：`requestEmission()` 的 422 錯誤訊息維持不變，但新增前端提示時機提前至明細列表，使用者不需等到點擊「申請填報」才發現問題

## Capabilities

### Modified Capabilities

- `product-bom-line`：新增「effective 欄位優先顯示」與「手動建立時快照自動回填」兩項行為
- `product-regulation-inference`：法規推算來源統一改為 effective material group（優先 `materialItem->materialGroup`，fallback `material_group_id`）

## Impact

- **前端**：`esgchain-web/src/views/sales-products/SalesProductDetailView.vue`（BOM Tab 顯示邏輯）
- **後端**：
  - `esgchain-api/app/Http/Controllers/Api/Compliance/ProductBomLineController.php`（`store()` 自動回填快照）
  - `esgchain-api/app/Models/SalesProduct.php`（`syncInferredRegulations()` 來源統一）
- **無 API 路由變更**：純行為修正，回傳資料結構不變（`effective_*` 欄位已存在於 API response）
- **無資料庫遷移**：不新增欄位，僅修正讀寫邏輯
