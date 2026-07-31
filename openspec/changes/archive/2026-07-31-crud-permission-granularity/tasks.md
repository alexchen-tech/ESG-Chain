## 1. 權限目錄重新分類（依模組分批，每批改完立即用真實角色帳號 curl 回歸測試）

- [x] 1.1 彙整現行 `PermissionCatalogSeeder::CATALOG`（18 個 `module.manage`）對應回的原始 66 條路由清單（沿用 `role-permission-management` 稽核時整理的路由→角色對照表）
- [x] 1.2 第一批：使用者/角色管理模組（`users.*`、`suppliers.manage-users`）拆分為 view/create/update/delete
- [x] 1.3 第二批：SAQ / 評核系列 / 問卷專案模組拆分
- [x] 1.4 第三批：CAP 模組拆分
- [x] 1.5 第四批：商品合規／市場規則模組拆分（`market-compliance-rules`、`market-definitions`）
- [x] 1.6 第五批：系統設定模組拆分（org-units、questionnaire-templates、question-bank、tag-library、scoring-models、carbon-price、country-risk）
- [x] 1.7 更新 `PermissionCatalogSeeder::ROLE_PERMISSIONS`，把每個角色原本持有的 `module.manage` 換成對應拆分後的四個（或更多）動作權限字串，確保角色持有的權限組合等價於拆分前
- [x] 1.8 全專案 grep 確認沒有殘留寫死引用舊 `module.manage` 字串的程式碼（前端/後端）

## 2. 後端 — 路由遷移到 CRUD 權限字串

- [x] 2.1 `routes/api.php` 66 條路由的 `permission:module.manage` 逐條改為對應的 `permission:module.view/create/update/delete`
- [x] 2.2 全專案 grep 確認 `permission:.*\.manage` 已無殘留

## 3. 後端 — 個人權限覆寫

- [x] 3.1 新增 `UserPermissionOverrideHistory` model + migration（`user_id`, `permission`, `granted_by`, `created_at`），僅作稽核記錄用，不作為權限判斷的資料來源
- [x] 3.2 確認 `EnsurePermission` middleware／`User::hasPermissionTo()` 呼叫路徑已能正確合併角色權限與 spatie `model_has_permissions` 個人直接權限（多數情況免修改，僅需驗證 admin 短路邏輯在個人覆寫存在時仍正確）
- [x] 3.3 `App\Services\User\UserService`（或新 `UserPermissionOverrideService`）新增 `grantPermission(User $user, string $permission, User $grantedBy)` / `revokePermission(...)` 方法：呼叫 spatie `givePermissionTo()`/`revokePermissionTo()`，並寫入 `UserPermissionOverrideHistory`；對 admin 角色使用者呼叫時拋出例外
- [x] 3.4 `PermissionController` 新增 `userPermissions(string $userId)`（回傳角色權限與個人直接權限，分開標示來源）、`grantUserPermission`、`revokeUserPermission`
- [x] 3.5 `routes/api.php` 新增 `users/{user}/permissions`（GET）、`users/{user}/permissions/{permission}`（POST 授予 / DELETE 收回），掛既有 `role.admin`

## 4. 前端 — API 與型別

- [x] 4.1 `esgchain-web/src/api/modules/permissions.ts` 更新型別以反映拆分後的權限目錄結構（模組內動作陣列）
- [x] 4.2 新增使用者個人權限覆寫的 API 方法（查詢/授予/收回）於 `esgchain-web/src/api/modules/users.ts`

## 5. 前端 — 角色管理頁面

- [x] 5.1 `RolesView.vue` 矩陣改為模組內依動作（查看/新增/修改/刪除）展開多列，確認既有摺疊互動與樂觀更新邏輯不受影響
- [x] 5.2 `vue-tsc --noEmit` 確認乾淨

## 6. 前端 — 使用者個人權限覆寫

- [x] 6.1 `UsersView.vue` 使用者編輯 modal 新增「個人權限覆寫」子頁籤：依模組分組顯示全部權限，已透過角色取得者顯示唯讀「已透過角色取得」標籤，其餘可勾選授予/取消
- [x] 6.2 admin 角色使用者的個人權限覆寫頁籤顯示為不可操作，並說明「admin 已固定擁有全部權限」
- [x] 6.3 `vue-tsc --noEmit` 確認乾淨

## 7. 部署與驗證

- [x] 7.1 執行更新後的 `PermissionCatalogSeeder`，部署後端，`route:clear`+`config:cache`+`route:cache`
- [x] 7.2 部署前端
- [x] 7.3 針對第 1-2 節每一批拆分的模組，用對應角色的真實帳號逐一 curl 驗證行為與拆分前（`role-permission-management` 完成時的狀態）一致
- [x] 7.4 驗證個人權限覆寫：對某位 buyer 使用者額外授予角色沒有的權限，重新登入後確認可存取；收回後重新登入確認恢復原本角色權限範圍
- [x] 7.5 驗證 admin 角色與 admin 使用者的權限皆無法被角色管理頁面或個人覆寫 API 調整（API 直接拒絕）
- [x] 7.6 驗證側邊欄選單依合併後（角色+個人覆寫）的有效權限動態顯示
