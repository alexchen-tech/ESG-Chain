## 1. 資料庫

- [x] 1.1 Migration：`users` 表新增 `is_active`（boolean，預設 true）
- [x] 1.2 Migration：新增 `user_status_histories` 表（user_id, from_status, to_status, changed_by, reason, created_at）
- [x] 1.3 Migration：新增 `user_role_histories` 表（user_id, from_roles json, to_roles json, changed_by, created_at）

## 2. 後端 — Service

- [x] 2.1 `App\Services\User\UserService::list(array $filters): LengthAwarePaginator`
- [x] 2.2 `UserService::create(array $data, string $createdBy): User`（含角色指派，角色白名單 admin/buyer/sustain/comply/analyst）
- [x] 2.3 `UserService::updateRoles(User $user, array $roles, string $changedBy, ?string $reason): User`（寫入 user_role_histories）
- [x] 2.4 `UserService::setActive(User $user, bool $active, string $changedBy, ?string $reason): array`（寫入 user_status_histories，停用時查詢進行中 CAP/SAQ 數量回傳 warnings）
- [x] 2.5 `UserService::resetPassword(User $user, string $changedBy): string`（產生隨機密碼、雜湊存入、回傳明碼）
- [x] 2.6 `App\Services\User\SupplierUserService::listBySupplier(Supplier $supplier): Collection`
- [x] 2.7 `SupplierUserService::invite(Supplier $supplier, array $data, string $createdBy): User`（角色白名單僅 supplier/sup_esg）

## 3. 後端 — Controller / Routes

- [x] 3.1 `App\Http\Controllers\Api\UserController`：index/store/updateRoles/toggleActive/resetPassword
- [x] 3.2 `App\Http\Controllers\Api\Suppliers\SupplierUserController`：index/store
- [x] 3.3 `CreateUserRequest`/`UpdateUserRolesRequest`/`InviteSupplierContactRequest` FormRequest（email 唯一性、角色白名單驗證）
- [x] 3.4 `routes/api.php` 新增路由：`users`/`users/{user}/roles`/`users/{user}/toggle-active`/`users/{user}/reset-password` 掛 `role.admin`；`suppliers/{supplier}/users` index 允許 admin 或該供應商自己（ownership 檢查），store 僅 admin
- [x] 3.5 `AuthService::login()` 加入 `is_active` 檢查，帳號被停用時回傳與密碼錯誤一致的訊息

## 4. 前端 — API 模組

- [x] 4.1 `esgchain-web/src/api/modules/users.ts`：list/create/updateRoles/toggleActive/resetPassword + 型別定義
- [x] 4.2 `esgchain-web/src/api/modules/suppliers.ts` 補上 `supplierUsersApi`（list/invite）

## 5. 前端 — 頁面

- [x] 5.1 `esgchain-web/src/views/settings/UsersView.vue`：清單（server-side 分頁 20 筆，依角色/狀態篩選）
- [x] 5.2 建立使用者 modal、角色編輯 modal
- [x] 5.3 停用/啟用確認 modal（顯示進行中工作警告訊息）
- [x] 5.4 重設密碼按鈕與一次性密碼顯示 UI
- [x] 5.5 `SupplierDetailView.vue` 新增登入帳號區塊（清單＋邀請新聯絡人表單）
- [x] 5.6 `AppSidebar.vue`「系統設定」群組新增「使用者管理」選單項目（`roles:['admin']`）
- [x] 5.7 `router/index.ts` 新增 `/settings/users` 路由（`meta:{requiresAuth:true, roles:['admin']}`）

## 6. 部署與驗證

- [x] 6.1 執行 migration，部署後端（`esgchain-api` + `esgchain-queue-worker`）
- [x] 6.2 `vue-tsc --noEmit` 確認乾淨，部署前端
- [x] 6.3 curl 端到端驗證：建立使用者 → 登入 → 指派角色 → 停用 → 停用帳號登入應被拒絕 → 重新啟用 → 查歷程表確認稽核紀錄正確
- [x] 6.4 curl 驗證停用有進行中 CAP/SAQ 的使用者時，回應含正確的警告訊息且仍完成停用
- [x] 6.5 供應商多聯絡人情境驗證：對一家既有供應商邀請第二個帳號，兩組帳號分別登入確認資料隔離（比照既有 Portal IDOR 防護模式驗證方式）
- [x] 6.6 驗證非 admin 角色無法存取使用者管理 API 與頁面（403 / 選單不顯示）
