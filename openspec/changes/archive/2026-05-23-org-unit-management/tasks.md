## 1. 資料庫 Migration & Model

- [x] 1.1 建立 `create_organization_units_table` migration（id UUID、name、code、type enum、parent_id nullable FK self、country_code char(2)、depth tinyint、sort_order int、is_active boolean、timestamps）
- [x] 1.2 建立 `add_organization_unit_id_to_users_table` migration（nullable UUID FK → organization_units）
- [x] 1.3 建立 `OrganizationUnit` Model（HasUuids、fillable、type cast、`parent()`/`children()` 關聯、`User` hasMany）
- [x] 1.4 更新 `User` Model：新增 `organization_unit_id` fillable + `organizationUnit()` belongsTo 關聯
- [x] 1.5 建立 `OrganizationUnitSeeder`，植入預設根節點（name=ESGChain, code=HQ, type=headquarters, depth=1）並在 `DatabaseSeeder` 呼叫

## 2. 後端 API

- [x] 2.1 建立 `OrganizationUnitService`：`getTree()`（遞迴組裝 children）、`create()`（計算 depth，驗證 ≤4）、`update()`（禁止修改 parent_id/type）、`delete()`（有子節點時拋 422）
- [x] 2.2 建立 `OrganizationUnitController`（`index` 一般列表、`tree` 樹狀、`store`、`update`、`destroy`），放 `app/Http/Controllers/Api/Settings/`
- [x] 2.3 在 `routes/api.php` 的 `Settings` 區塊新增路由：`GET org-units`、`GET org-units/tree`、`POST org-units`、`PUT org-units/{unit}`、`DELETE org-units/{unit}`

## 3. 前端 API 模組

- [x] 3.1 在 `esgchain-web/src/api/modules/settings.ts` 新增 `OrgUnit` 型別與 `orgUnitsApi`（`list()`、`tree()`、`create()`、`update()`、`remove()`）

## 4. 前端元件與設定頁

- [x] 4.1 建立 `OrgUnitNode.vue`（遞迴元件）：顯示縮排節點、展開/收合、type badge、[編輯][刪除] 按鈕；有子節點時刪除 disabled + tooltip
- [x] 4.2 在 `SettingsView.vue` 將「組織架構」Tab 插入第一位（原 TABS 陣列前置），補上 `orgUnits`、`orgTree`、`ouLoading` data 及對應 methods（`loadOrgTree`、`openCreateOuModal`、`createOu`、`updateOu`、`deleteOu`）
- [x] 4.3 新增「組織架構」Tab 的 HTML 區塊：樹狀展示（呼叫 `OrgUnitNode.vue`）、空狀態、新增按鈕
- [x] 4.4 新增「新增組織單位」Modal：名稱、代碼、類型下拉（5 種）、上層單位下拉（類型非 headquarters 時顯示，列出 depth<4 節點）、國家碼輸入（預設 TW）
- [x] 4.5 新增「編輯組織單位」Modal（可更新 name、code、country_code、is_active）
- [x] 4.6 新增刪除確認 Modal（有子節點時顯示警告而非確認）

## 5. 執行 & 驗證

- [x] 5.1 執行 `php artisan migrate` 確認兩個 migration 無誤
- [x] 5.2 執行 `php artisan db:seed --class=OrganizationUnitSeeder` 確認根節點植入
- [x] 5.3 前端系統設定頁：確認「組織架構」為第一 Tab，新增/編輯/刪除 CRUD 正常
- [x] 5.4 驗證 4 層深度限制（嘗試建立 depth=5 應顯示錯誤）
- [x] 5.5 驗證刪除有子節點時按鈕 disabled 且無法刪除
