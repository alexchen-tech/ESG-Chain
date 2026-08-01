## 1. 資料庫

- [x] 1.1 Migration：`suppliers` 新增 `organization_unit_id`（nullable, FK → `organization_units.id`, `nullOnDelete`）
- [x] 1.2 Migration：新增 `supplier_organization_unit_histories` 表（`id`, `supplier_id`, `from_organization_unit_id` nullable, `to_organization_unit_id` nullable, `changed_by`, `created_at`）

## 2. 後端 — Model 與範圍查詢

- [x] 2.1 `Supplier` model：新增 `organizationUnit()` belongsTo、`organizationUnitHistories()` hasMany
- [x] 2.2 新增 `SupplierOrganizationUnitHistory` model
- [x] 2.3 新增 `App\Services\OrganizationUnit\OrganizationUnitScopeService::visibleUnitIds(User $user): array`：以 `$user->organization_unit_id` 為根，用 `WITH RECURSIVE` 撈出自己＋所有子孫單位 id；`organization_unit_id` 為 null 時回傳特殊值或由呼叫端另外判斷跳過過濾
- [x] 2.4 `Supplier` model 新增 `scopeVisibleTo(Builder $query, User $user)` local scope：`organization_unit_id` 為 null 的使用者不套用過濾；否則套用「`organization_unit_id` 為 null OR 屬於可視子樹」的過濾條件

## 3. 後端 — Controller 與稽核

- [x] 3.1 `SupplierController@index` 套用 `Supplier::visibleTo($request->user())`
- [x] 3.2 新增指派/變更供應商組織單位的 API（如 `PATCH suppliers/{supplier}/organization-unit`），寫入 `SupplierOrganizationUnitHistory`，掛既有權限 middleware（比照供應商管理現有權限字串）
- [x] 3.3 `SupplierController` 回傳的供應商資料（index/show）需附帶 `organization_unit`（含 null 情況）與是否「未指派單位」的判斷欄位

## 4. 前端

- [x] 4.1 `SuppliersView.vue`：清單新增組織單位欄位顯示、未指派單位的標示樣式（沿用 `--accent-soft` 或既有 badge 樣式，不新增強調色）、依組織單位篩選（下拉選單，資料來源為 `organization-units` API）
- [x] 4.2 `SupplierDetailView.vue`：新增指派/變更組織單位的操作介面，顯示目前歸屬與變更歷程
- [x] 4.3 `vue-tsc --noEmit` 確認零錯誤

## 5. 部署與驗證

- [x] 5.1 執行 migration，部署後端
- [x] 5.2 部署前端
- [x] 5.3 驗證：指派某組織單位 A 給供應商 X，A 底下的子單位 B 使用者查詢清單看不到 X（因為 X 屬於 A 本身而非 B），A 自己與 A 的上層單位（若可視子樹涵蓋 A）能看到 X
- [x] 5.4 驗證：未指派單位的供應商，任何組織單位的使用者都看得到
- [x] 5.5 驗證：`organization_unit_id` 為 null 的使用者（如 admin）能看到全部供應商，不受過濾
- [x] 5.6 驗證：組織單位範圍過濾造成清單為零筆時，回傳空清單而非 403
- [x] 5.7 驗證：指派/變更組織單位會正確寫入稽核歷程，且 ERP 同步流程執行後不會覆蓋既有組織單位歸屬
