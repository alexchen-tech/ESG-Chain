## 1. 權限目錄與資料庫

- [x] 1.1 建立 `PermissionCatalogSeeder`：依稽核整理出的「路由→現況允許角色」對照表，產生完整權限目錄（`模組.動作` 格式，約 80-150 筆）
- [x] 1.2 `role_has_permissions` seed：依現況（非 CLAUDE.md 簡化表）為 buyer/sustain/comply/analyst 指派對應權限；admin 角色權限固定不透過此機制管理（見 1.3）
- [x] 1.3 supplier/sup_esg 角色的權限範圍維持交由 `EnsureSupplierPortalScope` 管控，不納入這次的模組權限矩陣

## 2. 後端 — 權限檢查機制

- [x] 2.1 新增 `App\Http\Middleware\EnsurePermission`（`permission:module.action`），比照 `EnsureAdminRole`/`EnsureAnyRole` 寫法，未持有權限回 403
- [x] 2.2 `bootstrap/app.php` 註冊 `permission` middleware alias
- [x] 2.3 `AuthService::login()`／`me()` 回應加上 `permissions: string[]`（該使用者角色目前擁有的完整權限清單）

## 3. 後端 — 角色管理 API

- [x] 3.1 `App\Http\Controllers\Api\PermissionController`：`index`（列出全部權限目錄，依模組分組）、`rolePermissions`（查詢單一角色目前權限）、`updateRolePermissions`（更新角色權限，拒絕 admin 角色的調整請求）
- [x] 3.2 `routes/api.php` 新增 `settings/permissions`、`settings/roles/{role}/permissions` 路由，掛 `role.admin`（角色管理頁面本身用既有機制保護，不透過權限系統，避免自我鎖死）

## 4. 後端 — 路由遷移（依模組分批，每批改完立即用真實角色帳號 curl 回歸測試）

- [x] 4.1 第一批：使用者/角色管理相關路由（`users/*`、`suppliers/{supplier}/users`）改用 `permission:users.*`
- [x] 4.2 第二批：SAQ / 評核系列 / 問卷專案相關路由改用對應權限字串
- [x] 4.3 第三批：CAP 相關路由改用對應權限字串
- [x] 4.4 第四批：商品合規／市場規則／貿易商品相關路由改用對應權限字串
- [x] 4.5 第五批：系統設定（org-units、questionnaire-templates、question-bank、tag-library、scoring-models、carbon-price、country-risk）相關路由改用對應權限字串
- [x] 4.6 全專案 grep 確認 `role.admin`/`role.any:` 是否還有殘留未遷移的路由，逐一列出並決定保留原因或補遷移

## 5. 前端 — API 與型別

- [x] 5.1 `esgchain-web/src/api/modules/auth.ts`（或既有登入相關模組）補上 `permissions` 型別與讀取
- [x] 5.2 `esgchain-web/src/api/modules/permissions.ts`：權限目錄查詢、角色權限查詢/更新 API
- [x] 5.3 `esgchain-web/src/stores/auth.ts` 存放使用者權限清單

## 6. 前端 — 角色管理頁面

- [x] 6.1 `esgchain-web/src/views/settings/RolesView.vue`：矩陣式 UI，依模組分組摺疊，勾選框對應各角色×動作，admin 角色列唯讀
- [x] 6.2 `AppSidebar.vue`「系統設定」群組新增「角色管理」選單項目（與「使用者管理」並列，`roles:['admin']`）
- [x] 6.3 `router/index.ts` 新增 `/settings/roles` 路由

## 7. 前端 — 動態選單

- [x] 7.1 `AppSidebar.vue` 選單過濾邏輯改讀 `authStore` 的權限清單，路由設定新增對應 `meta.permission`（與既有 `meta.roles` 並存，見 design.md Decision 4）
- [x] 7.2 `router/index.ts` 的 `beforeEach` 守衛依「有 `meta.permission` 就只看它，沒有才看 `meta.roles`」邏輯調整

## 8. 部署與驗證

- [x] 8.1 執行 migration/seeder，部署後端，`route:clear`+`config:cache`+`route:cache`
- [x] 8.2 `vue-tsc --noEmit` 確認乾淨，部署前端
- [x] 8.3 針對第 4 節每一批遷移的路由，用對應角色的真實帳號逐一 curl 驗證行為與遷移前一致（比對稽核時整理的路由→角色對照表）
- [x] 8.4 驗證角色管理頁面：admin 調整某角色權限（如取消 buyer 的 `caps.create`）後，該角色帳號重新登入，實際呼叫該路由確認被擋
- [x] 8.5 驗證 admin 角色權限無法被調整（API 直接拒絕）
- [x] 8.6 驗證側邊欄選單依權限動態顯示：調整某角色權限後重新登入，確認選單項目增減正確
- [x] 8.7 確認既有測試帳號（admin/buyer/sustain/comply/analyst）登入後的可用功能範圍與遷移前完全一致（全面回歸，不只抽測）
