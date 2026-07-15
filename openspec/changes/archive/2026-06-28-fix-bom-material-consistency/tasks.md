## 1. Migration — linkage_status 欄位

- [x] 1.1 新增 migration：`product_bom_lines` 加入 `linkage_status` enum `('linked','unlinked')` default `'unlinked'`
- [x] 1.2 同一 migration 回填既有資料：`material_item_id IS NOT NULL → 'linked'`，否則 `'unlinked'`
- [x] 1.3 執行 migration 並確認 `php artisan migrate` 成功

## 2. 後端 A — 統一讀取路徑（BomLineSupplierController）

- [x] 2.1 修正 `BomLineSupplierController` 中 hs_code 讀取順序：改為 `$bomLine->materialItem?->hs_code ?? $bomLine->hs_code`
- [x] 2.2 修正同檔案中 material_name 讀取順序：改為 `$bomLine->materialItem?->name ?? $bomLine->material_name`

## 3. 後端 B — store()/update() 自動同步 material_group_id

- [x] 3.1 修改 `ProductBomLineController::store()`：傳入 material_item_id 且未傳 material_group_id 時，自動帶入 `materialItem->material_group_id`，設 `material_group_source='erp_imported'`
- [x] 3.2 修改 `ProductBomLineController::update()`：相同邏輯，注意不覆蓋呼叫端明確傳入的 material_group_id

## 4. 後端 C — linkage_status 自動維護

- [x] 4.1 修改 `ProductBomLineController::store()`：根據 material_item_id 是否存在自動設定 `linkage_status`
- [x] 4.2 修改 `ProductBomLineController::update()`：有 material_item_id 變動時同步更新 `linkage_status`
- [x] 4.3 更新 `ProductBomLine` Model 的 `$fillable` 加入 `linkage_status`，同時設定 `$casts`

## 5. 前端 — BOM 列表 unlinked 警告標籤

- [x] 5.1 在 `SalesProductDetailView.vue` 的 BOM 明細列表，對 `linkage_status='unlinked'` 的列在 material_name 旁顯示橘色「未連結主檔」badge
- [x] 5.2 確認 badge 樣式與現有設計系統一致（使用 `--accent` 或 warning 色系）

## 6. 驗證

- [x] 6.1 手動建立無 material_item_id 的 BomLine，確認 linkage_status='unlinked'，前端顯示警告
- [x] 6.2 更新該 BomLine 補上 material_item_id，確認 linkage_status 自動切換為 'linked'，警告消失
- [x] 6.3 確認 BomLineSupplierController 回傳的 hs_code 反映主檔值（修改 MaterialItem.hs_code 後再查詢）
- [x] 6.4 確認 store() 傳入 material_item_id 時 material_group_id 被自動帶入
