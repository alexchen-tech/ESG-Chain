## Why

現有角色檢查是硬寫在 66+ 處 middleware 套用點與少數 Controller 內的字串陣列（`role.admin`、`role.any:admin,sustain,comply,analyst` 等），前端選單的角色可見性也是 `AppSidebar.vue` 裡另一份寫死的角色陣列。這兩份清單彼此獨立維護，容易漂移（例如稽核時就發現過 SAQ 三個 GET 端點漏加角色檢查）。spatie/laravel-permission 的 `permissions`/`role_has_permissions` 表架構已存在但完全未使用。使用者管理介面上線後，下一步是讓 admin 能在 UI 上直接設定「每個角色可以用哪些功能、能做哪些 CRUD 動作」，取代目前分散在程式碼裡的寫死清單，並把「使用者管理」（管帳號、指派角色）與「角色管理」（管角色本身有哪些權限）拆成兩個獨立介面，各自負責單一職責。

## What Changes

- 建立權限目錄（permission catalog）：以「模組.動作」命名（如 `suppliers.view`、`caps.create`），涵蓋現有路由表實際檢查過的範圍，並用 seeder 依現況（CLAUDE.md 模組表 + 實際路由檢查清單）初始化 `role_has_permissions`，確保上線當下行為與現況完全一致，不是重新設計權限分配
- 新增「角色管理」頁面（`/settings/roles`）：矩陣式 UI，admin 可勾選/取消每個角色對每個模組的各項動作權限
- 新增泛用權限檢查 middleware（`permission:module.action`），取代目前寫死角色清單的 `role.admin`/`role.any:...`／Controller 內硬寫檢查，路由改用權限字串而非角色字串
- 前端側邊欄選單改為依使用者實際擁有的權限動態決定顯示（`/auth/me` 回傳權限清單），取代 `AppSidebar.vue` 裡寫死的角色陣列
- 原本「使用者管理」頁面（`/settings/users`）維持現有功能不變（帳號建立/角色指派/停用/密碼重設），角色管理是新增的獨立頁面，兩者共用同一個側邊欄群組但入口分開

明確排除範圍（這次不做）：
- 不新增/移除任何角色（admin/buyer/sustain/comply/analyst/supplier/sup_esg 七種角色維持不變，只是把「角色能做什麼」從寫死改成可設定）
- 不做權限的「例外覆寫」（例如針對單一使用者而非角色的特例權限），權限一律綁在角色上
- 不重構 Portal 供應商端的 `EnsureSupplierPortalScope` 白名單機制（那是租戶隔離，跟這次的角色功能權限是不同層次的問題，繼續保留現狀）
- 不做權限變更的即時生效通知（使用者權限被改動後，下次登入/token 換發時才會反映最新權限，不強制踢掉現有 session）

## Capabilities

### New Capabilities
- `permission-catalog`：權限目錄定義與 seed 初始化，確保新機制上線時行為與現況一致
- `role-permission-management`：admin 在 UI 上管理角色對應的權限矩陣
- `dynamic-menu-permissions`：前端選單依使用者實際權限動態顯示，取代寫死角色陣列

### Modified Capabilities
- `user-management`（`openspec/changes/user-role-management/` 已完成的既有功能）：使用者管理頁面本身不變，但角色指派這件事的「角色有什麼權限」現在由角色管理頁面決定，兩者責任更明確分工——此為既有能力的說明性調整，不涉及既有 API 破壞性變更

## Impact

- 資料庫：`permissions` 表 seed 完整權限目錄（約 80-150 筆，依模組×動作估算）；`role_has_permissions` seed 對照現況
- 後端：新增 `PermissionController`（角色管理用）、`EnsurePermission` middleware；`routes/api.php` 現有 66+ 處 `role.admin`/`role.any:...` 逐步改為 `permission:xxx.xxx`；`AuthService::me()`／JWT payload 或 `/auth/me` 回應需附上使用者目前擁有的權限清單
- 前端：新增 `RolesView.vue`（角色管理頁）；`AppSidebar.vue` 選單過濾邏輯從 `roles.includes(userRole)` 改為 `permissions.includes(requiredPermission)`；`router/index.ts` 各路由的 `meta.roles` 比照調整或並存一段時間
- 不影響：既有 Portal 供應商端隔離機制、既有使用者管理功能本身的 API 介面
