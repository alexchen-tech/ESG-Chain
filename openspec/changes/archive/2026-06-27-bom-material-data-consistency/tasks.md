## 1. 後端：手動建立時自動回填快照

- [x] 1.1 在 `ProductBomLineController::store()` 中，驗證通過後若 `material_item_id` 有值，查詢對應 `MaterialItem`
- [x] 1.2 若查詢到 `MaterialItem`，以其 `name`/`hs_code` 覆蓋 `validated['material_name']`/`validated['hs_code']`
- [x] 1.3 確認 `update()` 同樣邏輯（若請求包含 `material_item_id` 變更，也需回填快照）— 檢查 `ProductBomLineController::update()` 是否已支援 `material_item_id` 欄位驗證，若無則新增
- [x] 1.4 驗證：提供 `material_item_id` 建立/更新 BomLine，回傳的 `material_name`/`hs_code` 等於物料主檔的值（透過真實 API + demo 資料驗證，建立後即刪除測試資料，未殘留）

## 2. 後端：法規推算來源統一

- [x] 2.1 修改 `SalesProduct::syncInferredRegulations()`，對每條 BomLine 計算 effective 物料群組：優先 `materialItem?->materialGroup`，fallback `materialGroup`
- [x] 2.2 確認 `bomLines()` 查詢有 eager load `materialItem.materialGroup` 與 `materialGroup`，避免 N+1
- [x] 2.3 驗證：BomLine 的 `material_item_id` 關聯物料群組與自身 `material_group_id` 不同時，採用 effective（MaterialItem）來源（透過 tinker 對既有 demo 資料暫時變更+還原驗證）
- [x] 2.4 驗證：BomLine 關聯的 MaterialItem 無物料群組時，fallback 至自身 `material_group_id`（同上方式驗證並還原）

## 3. 前端：BOM 明細顯示改用 effective 欄位

- [x] 3.1 確認 `esgchain-web/src/api/modules/salesProducts.ts` 的 `BomLine` interface 補上 `effective_material_name`、`effective_hs_code`、`effective_material_group` 欄位（optional）
- [x] 3.2 `SalesProductDetailView.vue` 的 BOM Tab 表格欄位改用 `bl.effective_material_name ?? bl.material_name`、`bl.effective_hs_code ?? bl.hs_code`
- [x] 3.3 驗證：已關聯物料主檔且主檔名稱與快照不同的 BOM 明細，API 回傳 `effective_material_name` 為主檔即時名稱（與快照 `material_name` 不同），前端已改用該欄位顯示

## 4. 前端：未綁定物料主檔視覺提示

- [x] 4.1 `SalesProductDetailView.vue` BOM Tab 表格新增條件渲染：`bom_line_type === 'material' && !material_item_id` 時顯示警示標籤
- [x] 4.2 警示標籤樣式採用既有 `badge-yellow` 或 `badge-gray` class，文字「未綁定物料主檔」
- [x] 4.3 驗證：手動建立未指定 `material_item_id` 的物料類明細，API 回傳 `material_item_id: null`（觸發前端警示條件）；建立後即刪除，未殘留測試資料

## 5. 整合驗證與資料校正

- [x] 5.1 完整 smoke test：建立一筆手動 BOM 明細並指定物料主檔，確認快照欄位自動回填、前端顯示正確（透過真實 API 驗證，見 1.4）
- [x] 5.2 完整 smoke test：檢視既有 ERP 匯入的 BOM 明細，確認 effective 欄位顯示不受影響（無 regression，見 3.3）
- [x] 5.3 執行 `php artisan sync:product-regulations` 批量重算現有 SalesProduct 的 `inferred_regulations`（已更新 18 筆）
- [x] 5.4 docker cp 同步前後端變更檔案至容器，確認 Vite HMR 與 Laravel 重啟後均正常運作
