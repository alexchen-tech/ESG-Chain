## Why

系統目前完全沒有使用者/角色管理功能：`User` 帳號只能透過 seeder 或 tinker 手動建立（密碼寫死 `demo1234`），角色指派、帳號停用、稽核歷程全部空白。已實際碰到的痛點是供應商多聯絡人情境——一家供應商如果有第二個人要登入（如稽核測試時遇到的 `esg@vietgarment.vn`），目前完全沒有 API/UI 可以新增，只能靠工程師手動介入資料庫。spatie/laravel-permission 的角色資料表架構已經就緒，但整條使用者生命週期管理（建立、指派角色、停用/啟用、稽核留痕）從未被建置。

## What Changes

- 新增中心廠端使用者管理：建立使用者、指派/變更角色、停用/啟用帳號、admin 代重設密碼
- 新增使用者狀態與角色變更的稽核歷程（比照既有 `supplier_status_histories` 模式）
- 新增供應商多聯絡人邀請：中心廠可為既有供應商新增第二、第三個 Portal 登入帳號
- 停用帳號時檢查該使用者名下是否有進行中的 CAP/SAQ，回傳警告訊息但不阻擋停用動作
- 登入時檢查帳號是否停用（`is_active`），停用帳號無法登入
- 新增前端使用者管理頁面與供應商詳情頁的登入帳號區塊，側邊欄新增「使用者管理」入口（僅 admin 可見）

明確排除範圍（這次不做）：
- Permission 細粒度權限層（`permissions`/`role_has_permissions` 表已存在但不啟用，角色層已足夠）
- 使用者自助忘記密碼寄信流程（demo 環境無可靠 SMTP，且屬於獨立功能）
- 帳號停用後進行中工作自動轉移給其他使用者（先做警告，轉移邏輯留待有真實需求再議）

## Capabilities

### New Capabilities
- `user-management`：中心廠端使用者建立、角色指派、停用/啟用、admin 代重設密碼、稽核歷程
- `supplier-contact-invitation`：中心廠為既有供應商邀請/新增額外的 Portal 登入帳號（多聯絡人）

### Modified Capabilities
（無現有 spec 涉及使用者帳號生命週期管理，此次為全新能力，無既有 spec 需要修改）

## Impact

- 資料庫：`users` 表新增 `is_active` 欄位；新增 `user_status_histories`、`user_role_histories` 兩張稽核表
- 後端：新增 `UserService`、`UserController`、`SupplierUserController`，`routes/api.php` 新增對應路由（皆掛 `role.admin`），`AuthService::login()` 增加 `is_active` 檢查
- 前端：新增 `UsersView.vue`、供應商詳情頁登入帳號區塊、側邊欄選單項目、`users.ts`/`suppliers.ts` API 模組擴充
- 不影響：既有角色檢查 middleware（`EnsureAdminRole`/`EnsureAnyRole`）、既有 SAQ/CAP 業務邏輯本身（僅新增停用時的唯讀查詢提示）
