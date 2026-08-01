## Context

`OrganizationUnit`（`esgchain-api/app/Models/OrganizationUnit.php`）已是完整樹狀結構：`parent_id`/`depth`/`sort_order`/`type`/`country_code`，具備 `parent()`/`children()` 自關聯。`User` 已有 `organization_unit_id`（`2024_06_01_000002_add_organization_unit_id_to_users_table.php`），並在 `User::getJWTCustomClaims()` 塞進 JWT 的 `ouId`——但目前全專案 grep 只有這一處寫入，沒有任何地方讀取，是死欄位。`Supplier` 目前完全沒有跟 `OrganizationUnit`的關聯欄位。

現有唯一的資料列範圍限制是供應商入口帳號被限制在自己的 `supplier_id`，但這是每個 Portal Controller 各自手寫 `$user->supplier_id` 過濾，沒有共用的 scope/trait 機制。這次要新增的是完全不同性質的範圍控制（樹狀組織單位可視範圍，而非單一供應商綁定），不應該沿用 portal 那套一對一比對的寫法。

## Goals / Non-Goals

**Goals:**
- `Supplier` 可被指派單一組織單位，未指派時對所有人可見
- 內部使用者（非供應商入口帳號）查詢供應商清單時，依自己組織單位的可視子樹自動過濾
- 組織單位範圍過濾與既有角色權限（`permission:xxx`）完全正交、互不干擾——拿掉某角色的 `caps.create` 不影響其能看到的供應商範圍，反之亦然
- 指派/變更供應商組織單位需要留稽核紀錄

**Non-Goals:**
- 不做供應商多組織單位共用（多對多）
- 不擴充商品合規管理相關實體（TradeGoods/SalesProduct/BOM/生產批號/出口審查）的範圍過濾
- 不擴充 SAQ/CAP 等其他實體的組織單位範圍過濾
- 不改變 `EnsureSupplierPortalScope` 既有機制
- 不處理「供應商所屬組織單位」以外的其他資料列範圍需求（如依國家、依採購負責人等），僅處理組織單位這一個維度

## Decisions

### Decision 1：範圍過濾用 `WITH RECURSIVE`，不建 closure table
以 `User.organization_unit_id` 為根，查詢時用 MySQL 8.4 原生 `WITH RECURSIVE` CTE 即時算出可視子樹 id 清單，不另外維護 closure table 或 materialized path。
- 替代方案（closure table，每次組織架構變動時重算並持久化祖先-子孫關係表）：否決，YAGNI——`organization_units` 表資料量級是「公司內部組織架構」（數十到數百筆），遞迴查詢的效能成本可忽略，不值得為此多維護一張額外的表與同步邏輯。若未來實測發現效能問題，再回頭考慮。

### Decision 2：範圍過濾實作為 Eloquent Local Scope，不做成 Global Scope
在 `Supplier` model 新增一個 local scope，例如 `scopeVisibleTo(Builder $query, User $user)`，內部呼叫 `OrganizationUnitScopeService::visibleUnitIds($user)` 取得子樹 id 清單，組出 `where(fn($q) => $q->whereNull('organization_unit_id')->orWhereIn('organization_unit_id', $visibleIds))`（未指派單位的供應商一律納入，見 Decision 3）。`SupplierController@index` 明確呼叫 `Supplier::visibleTo($request->user())->...`。
- 替代方案（Eloquent Global Scope，自動套用在所有查詢上）：否決，因為 (a) 內部管理操作（如 admin 後台的稽核查詢、報表匯出）可能需要繞過範圍限制，Global Scope 會讓「刻意查全部」變得需要額外寫 `withoutGlobalScope()`，維護上更容易忘記或誤用；(b) 這次明確只影響 `SupplierController@index` 這一個查詢入口，用 local scope 更清楚地標示「這裡才有範圍限制」，不會有隱性套用在其他不該套用的查詢（如 ERP 同步邏輯內部查詢）上的風險。

### Decision 3：未指派組織單位的供應商永遠可見，不因為使用者有明確組織單位就被排除
`visibleTo` scope 的邏輯是「未指派 OR 屬於可視子樹」而非只有「屬於可視子樹」，任何使用者（只要角色權限本身允許看供應商列表）都能看到所有未歸戶供應商。
- 替代方案（未歸戶供應商只有 admin 看得到）：否決，使用者已明確選擇「對所有人可見」策略（避免資料在 migration 當下突然消失、造成使用中斷），沒有例外。

### Decision 4：組織單位為 null 的使用者（如 admin）跳過範圍過濾，視為全域可見；有組織單位的使用者若剛好在樹根，透過遞迴子樹自然涵蓋全部，不需要額外的「全域角色」旗標
不新增「全域可視角色」這種特殊標記。`organization_unit_id IS NULL` 的使用者（目前現實中主要是 admin，因為 admin 帳號建立時通常不會被指派特定部門）直接跳過範圍過濾；若集團永續辦公室的使用者被指派在組織樹的根節點，遞迴子樹查詢自然就涵蓋全公司所有單位，不需要另外設計旗標。
- 替代方案（額外加一個 `is_global_scope` 布林欄位或角色層級的例外清單）：否決，organization_unit_id 為 null 或指派在根節點，已經能自然表達「全域」語意，不需要疊加另一套判斷邏輯。

### Decision 5：指派/變更組織單位的稽核比照 `SupplierStatusHistory` 模式，新增 `SupplierOrganizationUnitHistory`
新增 `supplier_organization_unit_histories` 表（`supplier_id`, `from_organization_unit_id` nullable, `to_organization_unit_id` nullable, `changed_by`, `created_at`），每次指派/變更/清空組織單位都寫一筆。
- 替代方案（沿用通用 activity log 機制）：否決，專案裡沒有通用 activity log 基礎設施，`SupplierStatusHistory`/`UserRoleHistory`/`UserStatusHistory` 這種「每個實體各自一張專用歷程表」已經是本專案貫穿多次的既有慣例（見 `user-role-management`/`role-permission-management` 的稽核設計），沿用一致風格比引入新機制更好。

## Risks / Trade-offs

- [風險] `WITH RECURSIVE` 在每次供應商清單查詢時都即時計算，若組織架構層級極深或使用者所在單位子樹極大，可能有效能疑慮 → 緩解：目前組織單位資料量級小（公司內部組織架構），且 `SupplierController@index` 本身已經有 pagination，可視子樹 id 清單只是一個 `whereIn` 的輸入，不是逐筆供應商遞迴，效能風險低；先上線觀察，不預先過度設計
- [風險] 「未指派單位供應商對所有人可見」是刻意選擇，但長期若永續團隊沒有落實歸戶，範圍過濾會形同虛設（大部分供應商永遠可被所有人看到）→ 緩解：畫面明顯標示「未指派單位」提醒歸戶，但本次不做強制歸戶機制（如封鎖某些操作直到歸戶），這是使用者知情選擇的政策風險，非技術風險
- [風險] Local scope 而非 Global Scope 意味著往後任何新增查詢供應商清單的入口（如報表、匯出）都需要開發者自己記得呼叫 `visibleTo()`，容易遺漏 → 緩解：這次僅涵蓋 `SupplierController@index`，若未來新增其他供應商清單查詢入口，需要在該次變更的 code review 中提醒套用同一個 scope，此風險記錄於此供未來參考

## Migration Plan

1. Migration：`suppliers` 新增 `organization_unit_id`（nullable FK, nullOnDelete）；新增 `supplier_organization_unit_histories` 表
2. `Supplier` model：新增 `organizationUnit()` belongsTo、`organizationUnitHistories()` hasMany、`scopeVisibleTo()`
3. 新增 `OrganizationUnitScopeService::visibleUnitIds(User $user): array`
4. `SupplierController@index` 套用 `visibleTo()` scope；新增指派組織單位的 action（含稽核寫入）
5. 前端 `SuppliersView.vue`/`SupplierDetailView.vue` 補上顯示/篩選/指派 UI
6. 部署後用不同組織單位的測試帳號驗證可視範圍正確（含「未指派單位供應商對所有人可見」與「admin 看全部」兩個邊界情況）
