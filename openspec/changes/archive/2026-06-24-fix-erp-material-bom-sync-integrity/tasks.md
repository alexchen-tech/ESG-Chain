## 1. 修復現役 BOM 匯入功能（最高優先）

- [x] 1.1 `BomLineImportService::importFromArray()` 型別提示由 `BuyerProduct $product` 改為 `SalesProduct $product`，`use App\Models\BuyerProduct` import 移除，改為 `use App\Models\SalesProduct`
- [x] 1.2 同步修改 `BomLineImportService::importFromCsv()` 的型別提示
- [x] 1.3 將查詢條件 `->where('buyer_product_id', $product->id)`（line 83）與建立欄位 `'buyer_product_id' => $product->id`（line 104）改為 `sales_product_id`
- [x] 1.4 以 admin 帳號實際呼叫 `POST /api/v1/sales-products/{id}/bom-lines/import`（JSON 模式）驗證：修復後第一次呼叫遇到容器內缺少 `App\Jobs\PcfEmissionGapScanJob`（host 有檔案但未同步進容器，與本次改動無關的既有部署落差），`docker cp` 補同步後重試 → HTTP 200，`created/updated` 計數正確，且確認建立的 BomLine `sales_product_id` 正確指向呼叫路徑上的 SalesProduct（已清除測試資料）
- [x] 1.5 實際以 CSV 檔案呼叫同一端點驗證 `importFromCsv` 路徑同樣修復。過程中發現 `league/csv` 其實已宣告在 Dockerfile 的 build 步驟中，問題是執行中的容器是舊 build（未包含此套件與 `PcfEmissionGapScanJob`），於是執行 `docker compose build esgchain-api` 重建映像檔。重建後發現另一個與本次修復無關的既有 bug：`app/Services/PCF/PcfRequestService.php` 的 namespace 誤寫成 `App\Services\Pcf`（與資料夾實際大小寫 `PCF` 不符），在 macOS（大小寫不敏感檔案系統）上開發時不會發現，但 Linux 容器（大小寫敏感）下 autoload 失敗導致整個 API 服務無法啟動。已比對同目錄其餘 4 個檔案皆使用 `App\Services\PCF`，確認是單一檔案的拼字錯誤，修正該檔案與其 2 個呼叫端的 `use` 陳述式後重建成功。重建後以 admin 帳號重新跑過 7 個關鍵端點（sales-products、suppliers、tag-library、material-items、scoring-models、sasb-required-topics、framework-default-weights）確認皆正常回應，再重新驗證 JSON 與 CSV 兩種模式的 BOM 匯入皆回 200，建立的 BomLine 欄位（hs_code/quantity/unit）正確解析，已清除所有測試資料

## 2. 修復 ErpSyncService 的 BOM/Material 同步

- [x] 2.1 `ErpSyncService::syncBomLines()` 改查詢 `SalesProduct::where('product_code', $row['product_code'])`，不再使用 `BuyerProduct`
- [x] 2.2 確認既有 `ERP_OWNED_BOM_FIELDS` 常數內容符合需求（`material_name`、`hs_code`、`quantity`、`unit`、`unit_price`、`currency`，外加 `erp_line_id` 作為查詢鍵），upsert 僅更新這些欄位
- [x] 2.3 在 `syncBomLines()` 新增保護：upsert 前先查詢既有行，若 `material_group_source === 'manual'` 則 unset `material_group_id`/`material_group_source`，邏輯與 `BomLineImportService` 一致
- [x] 2.4 新增 `ERP_OWNED_MATERIAL_FIELDS` 常數（`item_code`、`name`、`hs_code`、`unit`），`syncMaterials()` 改用 `array_intersect_key` 明確限定 upsert 欄位範圍，排除 `net_weight`、`pcr_percentage`
- [x] 2.5 以 tinker 撰寫 `QaStubAdapter` 注入真實資料，實測 `syncMaterials()`：ERP 更新 name，但 `net_weight`(12.34)/`pcr_percentage`(56.78) 保持不變；實測 `syncBomLines()`：正確解析 `SalesProduct`（依 product_code）、寫入 `sales_product_id`/`material_item_id`/`quantity`，且二次同步時 `material_group_source='manual'` 不被覆蓋。測試資料已於腳本內清除

## 3. 收回 MaterialItem item_code 自由建立/修改權限

- [x] 3.1 `MaterialItemController::store()` 改為一律回 422（料號代碼為必填 DB 欄位但已禁止接受，一般 API 無法再建立任何料號），訊息提示「料號代碼僅可透過 ERP 同步或 CSV 匯入建立...請使用「匯入」功能」。實測 `POST /api/v1/material-items` 帶 item_code → 422 確認
- [x] 3.2 `MaterialItemController::update()` 新增 guard：若 request 帶有 `item_code` 一律回 422；驗證規則移除 `item_code` 的 `sometimes` 規則。實測帶 item_code 的 PUT → 422，不帶 item_code 的 PUT（更新 description）→ 200 確認
- [x] 3.3 確認 `MaterialItemController::import()`（CSV 匯入）不受影響，仍可依 item_code upsert。實測上傳 CSV 建立新料號 → 200 成功，測試資料已刪除
- [x] 3.4 前端 `MaterialItemsView.vue`：發現此檔案實際未被任何路由使用（`router/index.ts` 沒有指向它），但仍一併修正以保持一致性 — 移除「新增料號」create modal 分支（store() 已無法成功），改為「+ 新增料號（CSV 匯入）」按鈕直接觸發既有隱藏的 CSV file input；Modal 僅保留編輯模式，`item_code` 欄位改為唯讀並提示「僅可透過 CSV 匯入或 ERP 同步建立/變更」；`saveItem()` 不再送出 `item_code`
- [x] 3.5 前端 `MaterialSettingsView.vue`（實際路由 `/settings/material-settings`，採購物料設定頁面，真正在使用中）做相同調整：「+ 新增料號（CSV 匯入）」按鈕、Modal 唯讀 item_code、`saveItem()` 不送 item_code。已用 Playwright 實測：編輯既有料號（ACC-MET-001）成功儲存（modal 正確關閉、後端回 200），確認移除 item_code 後 update 流程未被自己加的 422 guard 誤擋；測試資料已還原為原始值

## 4. 驗證

- [x] 4.1 實測 BOM 匯入冪等性：同一 `erp_line_id` 先建立（quantity=1），手動標記 `material_group_source='manual'`，再以不同 quantity（99）與 material_name 重新匯入 → `quantity`/`material_name` 正確更新為新值，但 `material_group_id`/`material_group_source='manual'` 維持不變，符合 `erp-bom-import` spec 既有 scenario。測試資料已清除
- [x] 4.2 實測：`POST /api/v1/material-items` 帶 item_code → 422；`PUT /api/v1/material-items/{id}` 帶 item_code → 422；不帶 item_code 的 PUT（更新 description）→ 200 正常更新
- [x] 4.3 已用 Playwright 實測 `/settings/material-settings`（真正在用的路由）：「+ 新增料號（CSV 匯入）」按鈕文字正確、編輯 Modal 的 item_code 欄位唯讀且有提示文字、儲存變更流程正常運作（200 回應、modal 正確關閉）
- [x] 4.4 確認 `ErpSyncService` 新增的 `ERP_OWNED_BOM_FIELDS`/`ERP_OWNED_MATERIAL_FIELDS` 與既有 `ERP_OWNED_SUPPLIER_FIELDS`/`ESG_OWNED_SUPPLIER_FIELDS` 命名風格一致，皆採 `array_intersect_key`+`array_flip` 模式；`syncSuppliers()` 本身未被改動，實測 `GET /api/v1/suppliers` 仍正常回應，無回歸
