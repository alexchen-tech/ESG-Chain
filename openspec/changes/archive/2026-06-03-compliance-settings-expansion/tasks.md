## 1. 資料庫 Migrations

- [x] 1.1 建立 `alter_supplier_groups_add_required_doc_types` migration，新增 `required_doc_types` JSON 欄位（nullable，預設 `[]`）
- [x] 1.2 建立 `create_material_items_table` migration，欄位：id(uuid)、item_code(unique)、name、hs_code(nullable)、unit(nullable)、material_group_id(nullable FK)、description(nullable)、is_active(bool,default true)、timestamps
- [x] 1.3 建立 `alter_product_bom_lines_add_material_item_id` migration，新增 nullable `material_item_id` FK → material_items(id) nullOnDelete
- [x] 1.4 建立 `create_market_definitions_table` migration，欄位：id(uuid)、code(unique,大寫底線格式)、label、description(nullable)、is_system(bool,default false)、timestamps
- [x] 1.5 執行 migrations（`php artisan migrate`）

## 2. 後端 Models 與 Seeders

- [x] 2.1 建立 `MaterialItem` Model（use HasUuids、SoftDeletes），fillable 含 item_code/name/hs_code/unit/material_group_id/description/is_active，加 `materialGroup()` belongsTo 關聯
- [x] 2.2 建立 `MarketDefinition` Model（use HasUuids），fillable 含 code/label/description/is_system
- [x] 2.3 更新 `SupplierGroup` Model：fillable 加入 `required_doc_types`，casts 加入 `'required_doc_types' => 'array'`
- [x] 2.4 更新 `ProductBomLine` Model：fillable 加入 `material_item_id`，新增 `materialItem()` belongsTo 關聯；更新 `withCount/eager load` 預設包含 `materialItem.materialGroup`
- [x] 2.5 建立 `MarketDefinitionSeeder`，預載：US_MARKET、EU_MARKET、JP_MARKET、UK_MARKET、GLOBAL（is_system=true）
- [x] 2.6 在 `DatabaseSeeder` 中加入 `MarketDefinitionSeeder`，執行 `php artisan db:seed --class=MarketDefinitionSeeder`

## 3. 後端 Controllers 與 Routes

- [x] 3.1 建立 `MaterialItemController`（index/store/update/destroy/import），destroy 需檢查 BomLine 參照，import 處理 CSV upsert + warnings
- [x] 3.2 建立 `MarketDefinitionController`（index/store/update/destroy），destroy 需檢查 is_system
- [x] 3.3 更新 `MaterialGroupController`：新增 `destroy` action，檢查 MaterialItem 和 BomLine 參照後再刪除
- [x] 3.4 更新 `SupplierGroupController.store/update`：加入 `required_doc_types` 驗證（array of strings）
- [x] 3.5 更新 `ProductBomLineController.index`：eager load `materialItem.materialGroup`，response 加入 `effective_material_name`、`effective_hs_code`、`effective_material_group` 計算欄位
- [x] 3.6 在 `routes/api.php` 新增 Routes：`apiResource('material-items', MaterialItemController::class)`、`POST material-items/import`、`apiResource('market-definitions', MarketDefinitionController::class)`；補齊 `DELETE material-groups/{materialGroup}`

## 4. 前端 API 型別與 HTTP 模組

- [x] 4.1 在 `compliance.ts` 新增 `MaterialItem` interface（含 material_group 巢狀）；更新 `ProductBomLine` interface 加入 `material_item_id`、`material_item`、`effective_material_name`、`effective_hs_code`、`effective_material_group` 欄位
- [x] 4.2 在 `compliance.ts` 新增 `materialItemApi`（list/create/update/destroy/import）
- [x] 4.3 在 `settings.ts` 新增 `MarketDefinition` interface 和 `marketDefinitionApi`（list/create/update/destroy）
- [x] 4.4 在 `settings.ts` 更新 `SupplierGroup` interface 加入 `required_doc_types: string[]`；更新 `settingsApi.supplierGroups.update` 傳遞 required_doc_types

## 5. 設定頁擴充（SettingsView.vue）

- [x] 5.1 在 `TABS` 陣列新增：`{ key: 'material-groups', label: '物料群組' }`、`{ key: 'market', label: '目標市場' }`、`{ key: 'material-items', label: '料號主檔', link: '/settings/material-items' }`
- [x] 5.2 更新「供應商分組」Tab UI：在新增/編輯 modal 加入廠商文件類型多選欄位（值域：SMETA_AUDIT、ISO_9001、FACTORY_AUDIT、HIGG_FEM、OEKO_TEX、BSCI、ZDHC_MRSL），以 chip 顯示已選項目
- [x] 5.3 建立「物料群組」Tab 區塊（v-show='material-groups'）：清單顯示名稱、類型、HS Code 前綴數、文件類型數、是否系統；新增/編輯 modal 含名稱、類型（material/service）、HS Code 前綴（tag 輸入）、文件類型（多選）、說明；is_system=true 隱藏刪除按鈕
- [x] 5.4 建立「目標市場」Tab 區塊（v-show='market'）：清單顯示代碼、標籤、說明、是否系統；新增/編輯 modal 含 code（大寫底線格式 input）、標籤、說明；is_system=true 隱藏刪除按鈕
- [x] 5.5 更新 SettingsView 的 data()、methods、mounted() 加入物料群組和目標市場的資料載入與 CRUD 操作

## 6. 料號主檔子頁（MaterialItemsView.vue）

- [x] 6.1 建立 `src/views/settings/MaterialItemsView.vue`，包含：搜尋欄（item_code/name 模糊搜尋）、分頁清單（料號代碼、品名、HS Code、物料群組、狀態）、新增/編輯 modal
- [x] 6.2 新增/編輯 modal 欄位：料號代碼 *、品名 *、HS Code（輸入後自動推薦物料群組）、物料群組 *（下拉）、計量單位、說明、啟用狀態
- [x] 6.3 實作 CSV 匯入：上傳按鈕 → 解析回傳的 created/updated/warnings → 顯示匯入結果摘要
- [x] 6.4 實作停用料號：刪除失敗（有 BomLine 參照）時改提示「停用」操作
- [x] 6.5 在 `router/index.ts` 新增路由 `{ path: '/settings/material-items', component: MaterialItemsView }`，meta.roles 同現有設定頁（admin）

## 7. BomLine 顯示更新（BuyerProductsView.vue）

- [x] 7.1 BomLine 清單的物料名稱欄：有 material_item 時顯示 effective_material_name 加料號代碼標籤；無時顯示原 material_name 加「自由文字」灰色標示
- [x] 7.2 BomLine 清單的物料群組欄：有 material_item 時使用 effective_material_group；無時使用現有 line.material_group（行為不變）
- [x] 7.3 BomLine 新增/編輯 row：加入料號選取下拉（從 materialItemApi.list 取得，僅顯示 is_active=true），選取後 hs_code 和 material_group_id 自動填入，可手動覆蓋

## 8. Docker 同步與驗收

- [x] 8.1 將後端變更檔案 docker cp 至 esgchain-api 容器，執行 migrations 和 seeder
- [x] 8.2 將前端變更檔案 docker cp 至 esgchain-web 容器，確認 HMR 更新
- [x] 8.3 驗收：設定頁供應商分組編輯可儲存 required_doc_types
- [x] 8.4 驗收：物料群組 Tab 可 CRUD，is_system=true 的群組無刪除按鈕
- [x] 8.5 驗收：料號主檔子頁可 CRUD、CSV 匯入回傳 created/warnings
- [x] 8.6 驗收：目標市場 Tab 可 CRUD，系統預載市場無刪除按鈕
- [x] 8.7 驗收：BuyerProduct BomLine 清單顯示料號連結狀態標籤
