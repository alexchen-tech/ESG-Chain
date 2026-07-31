## Why

`role-permission-management`（尚未 commit/archive）已把 66 條路由的角色白名單收斂成 18 個「模組.manage」統包權限，解決了「權限只能靠角色名稱寫死」的問題，但粒度仍是模組級的全有全無——同一模組底下的「查看」與「刪除」共用同一把鑰匙，且權限只能綁在角色上，無法針對單一使用者做例外授權（例如某位 buyer 需要額外看到某個模組的刪除功能，但不想把整個 buyer 角色都升級）。使用者明確要求兩件事：(1) 權限拆到 CRUD 四動作粒度，(2) 在角色繼承之上疊加個人層級的權限覆寫。

## What Changes

- `PermissionCatalogSeeder` 的 18 個 `module.manage` 權限，依 `role-permission-management` 稽核時整理的路由→角色對照表，逐條路由重新歸類為 `module.view` / `module.create` / `module.update` / `module.delete` 四動作之一（同模組同動作、角色白名單相同的路由仍合併為一個權限字串），權限總數會隨之增加（如 `caps.manage` → `caps.view` / `caps.create` / `caps.update` / `caps.delete`）
- 新增 `user_has_permissions` 資料表：使用者個人權限覆寫（`user_id`, `permission`, `granted` 布林, `granted_by`, 時間戳），使用者有效權限 = 角色權限聯集個人 `granted=true` 覆寫，再扣除個人 `granted=false` 覆寫（個人覆寫優先於角色繼承）
- `EnsurePermission` middleware 與 `User::permissionStrings()`（或等效方法）改為查詢「角色權限 + 個人覆寫」合併後的有效權限，而非只查角色權限
- `PermissionController` 新增查詢/更新單一使用者個人權限覆寫的 API
- `RolesView.vue` 矩陣改為「模組 × 動作」四欄（查看/新增/修改/刪除）× 角色列，而非目前的「模組 × 角色」四欄
- `UsersView.vue` 使用者編輯 modal 新增「個人權限覆寫」子頁籤，可針對單一使用者勾選額外授予或收回特定權限，並標示該權限是繼承自角色還是個人覆寫
- **BREAKING**：`module.manage` 這批既有權限字串會被四個新字串取代，`role_has_permissions` 需重新 seed；任何硬編寫在程式碼中引用舊 `module.manage` 字串的地方都需要同步更新
- admin 角色與被指派 admin 角色的使用者，一律不可被個人覆寫（維持既有 admin 全權限鎖定設計，見 `role-permission-management` design.md Decision 5）

## Capabilities

### New Capabilities
- `user-permission-override`：使用者個人權限覆寫機制（資料表、有效權限計算規則、管理 API 與 UI）

### Modified Capabilities
- `permission-catalog`（`role-permission-management` 未 commit 的變更）：權限目錄的粒度從「模組.manage」改為「模組.view/create/update/delete」四動作
- `role-permission-management`（`role-permission-management` 未 commit 的變更）：角色權限矩陣 UI 與 API 需支援四動作粒度的勾選/查詢

## Impact

- 後端：`PermissionCatalogSeeder`、新 migration（`user_has_permissions` 表）、新 `UserPermissionOverride` model、`EnsurePermission` middleware、`PermissionController`、`User` model（新增有效權限計算方法）
- 前端：`RolesView.vue`、`UsersView.vue`、`api/modules/permissions.ts`、`api/modules/users.ts`
- 需重新對 66 條既有遷移路由逐一核對「現況允許角色」，確保拆分後的四動作權限組合起來的行為與現況（`role-permission-management` 完成時的狀態）100% 一致，不因拆分而放寬或收緊任何路由的存取
