## 1. API Module 更新

- [x] 1.1 在 `compliance.ts` 新增 `ProductBomLine` interface（含所有欄位：id, buyer_product_id, erp_line_id, material_name, hs_code, material_group_id, material_group_source, designated_supplier_id, supplier_source, quantity, unit, unit_price, currency, notes，以及 materialGroup/designatedSupplier 關聯）
- [x] 1.2 新增 `bomLineApi`：`list(productId)`, `create(productId, data)`, `update(productId, lineId, data)`, `destroy(productId, lineId)`, `import(productId, formData)`
- [x] 1.3 新增 `portalProcurementApi`：`getRequirements()` — 供 Portal 使用，呼叫後端供應商採購需求 endpoint

## 2. BuyerProductsView — BOM Panel

- [x] 2.1 在 data 新增：`bomPanelOpen: {}（productId → bool）`、`bomLines: {}（productId → array）`、`bomLoading: {}`、`editingBomLine: null`、`newBomLineRow: null`
- [x] 2.2 新增 `toggleBomPanel(productId)` method：切換展開狀態，首次展開時呼叫 `loadBomLines(productId)`
- [x] 2.3 新增 `loadBomLines(productId)` method：呼叫 `bomLineApi.list()`，設入 `bomLines[productId]`
- [x] 2.4 在產品卡片 HTML 新增「BOM 明細（N 筆）」標籤按鈕，點擊觸發 `toggleBomPanel`
- [x] 2.5 新增 BOM panel 展開區塊：含 toolbar（ERP 匯入按鈕、新增按鈕）與 bom-table
- [x] 2.6 BOM table 欄位：物料名稱、HS Code、物料群組（下拉 / 顯示 chip）、指定供應商（下拉 / 顯示名稱）、數量、單價/幣別、來源標記（`material_group_source` badge）、操作（編輯/刪除）
- [x] 2.7 實作「+ 新增物料」：在 table 頂部插入 input row，各欄對應 input/select
- [x] 2.8 實作 inline edit：點擊編輯圖示，該列切換為 input 模式，儲存呼叫 `bomLineApi.update()`
- [x] 2.9 實作刪除：確認後呼叫 `bomLineApi.destroy()`，重新載入列表
- [x] 2.10 物料群組下拉 options 來自 `materialGroupApi.list()`（mounted 時已載入）
- [x] 2.11 指定供應商下拉 options 來自該產品現有 `product.product_suppliers` 的供應商

## 3. BuyerProductsView — CSV 匯入

- [x] 3.1 「ERP 匯入」按鈕觸發隱藏 `<input type="file" accept=".csv,.xlsx">` 的點擊
- [x] 3.2 選檔後顯示預覽 modal：上傳 FormData 至 `/bom-lines/import?dry_run=true`（若後端支援），或直接顯示檔案名稱與行數
- [x] 3.3 確認後呼叫 `bomLineApi.import(productId, formData)`，完成後顯示結果摘要（created/updated）與 warnings（黃色列表）
- [x] 3.4 匯入完成後重新載入 `loadBomLines(productId)`

## 4. SupplierComplianceDetailView — 關聯採購產品 Section

- [x] 4.1 在 data 新增：`bomRequirements: []`（依產品分組的 BomLine 合規需求）、`bomReqLoading: false`
- [x] 4.2 在 `loadData()` 中呼叫取得供應商被指定的 BomLines API（後端需提供 `/api/v1/suppliers/{id}/bom-requirements`），存入 `bomRequirements`
- [x] 4.3 在頁面新增「關聯採購產品」section，依 buyer_product 分組渲染：產品名稱、法規標籤、各 BomLine 的物料名稱 + required_doc_types 清單
- [x] 4.4 各 doc_type 依提交狀態顯示：已提交（綠色）、缺漏（紅色「待補件」標籤）
- [x] 4.5 無關聯產品時顯示空狀態文字

## 5. Portal 採購需求頁

- [x] 5.1 建立 `esgchain-web/src/views/portal/PortalProcurementView.vue`（Options API）
- [x] 5.2 頁面頂部顯示缺口摘要 KPI：「待補件文件 N 個」、「已提交 M 個」
- [x] 5.3 依匿名化客戶產品（「客戶產品 #1」「客戶產品 #2」）分組顯示 BomLines
- [x] 5.4 每個 BomLine 顯示：物料名稱、物料群組、required_doc_types（各附提交狀態 badge）
- [x] 5.5 已提交且 valid 的 doc_type 顯示綠色 + 到期日；待補件顯示紅色；已過期顯示橙色
- [x] 5.6 無採購需求時顯示空狀態

## 6. 路由與導覽

- [x] 6.1 在 `router/index.ts` 新增路由 `{ path: '/supplier/portal/procurement', component: PortalProcurementView, meta: { requiresAuth: true, roles: ['supplier', 'sup_esg'] } }`
- [x] 6.2 在 Portal 頂部導覽列（PortalView 快速入口）新增「採購需求」連結，`router-link` 指向 `/supplier/portal/procurement`

## 7. 驗收確認

- [x] 7.1 手動測試：在 BuyerProductsView 展開 BOM panel → 新增 BomLine → 確認顯示正確
- [x] 7.2 手動測試：CSV 上傳 → 確認 created/updated 摘要顯示
- [x] 7.3 手動測試：SupplierComplianceDetailView「關聯採購產品」section 顯示（需先有 BomLine 指定此供應商）
- [x] 7.4 手動測試：以 supplier 帳號登入 Portal → 進入「採購需求」頁 → 確認匿名顯示
- [x] 7.5 docker cp 所有新增/修改的 Vue 檔案至 web container（若使用 Docker）
