## Context

`User` model 已用 `Spatie\Permission\Traits\HasRoles`（guard `api`），`roles`/`permissions`/`model_has_roles` 等表已存在但只用到角色層。`Supplier` 的狀態機（`onboarding_stage` + `SupplierStatusHistory`）是這次要沿用的稽核模式範本。目前 `User` 建立僅發生在 `SupplierSeeder.php`（單一 primary contact，`firstOrCreate` by email），沒有任何 API 可以再新增同供應商的第二個帳號。

## Goals / Non-Goals

**Goals：**
- Admin 可以在 UI 上完成使用者的建立、角色指派、停用/啟用、代重設密碼
- 供應商多聯絡人可以由中心廠端直接邀請新增，不必等 ERP 或工程師介入
- 帳號停用/角色變更都要有稽核歷程可查
- 停用帳號時要提醒操作者「這個人手上還有沒結掉的工作」，但不強制擋下停用

**Non-Goals：**
- 不做 permission 細粒度權限（角色層已足夠涵蓋目前六種角色的存取邊界）
- 不做使用者自助忘記密碼/寄信重設（demo 環境無可靠 SMTP，且是獨立功能）
- 不做停用後工作自動轉移給其他使用者（只做警告提示）
- 不修改既有 `role.admin`/`role.any` middleware 機制本身，新端點直接沿用

## Decisions

**1. 稽核歷程用兩張獨立表（`user_status_histories` / `user_role_histories`），不合併成一張泛用 audit log**

理由：比照專案既有的 `SupplierStatusHistory` 模式（單一用途表，欄位語意明確），且狀態變更（active/inactive）跟角色變更（roles 陣列）的資料形狀不同（一個是簡單列舉值，一個是陣列快照），分開存比塞進單一 JSON 欄位的泛用 log 更容易查詢與呈現。

**2. 停用帳號時的「進行中工作」檢查是唯讀查詢＋回傳警告，不是資料庫層的阻擋**

理由：使用者在 CLAUDE.md/proposal 已明確定調「先做警告，不做自動轉移」。若在 Service 層直接 `abort_if` 擋下停用動作，會讓 admin 在真的需要緊急停用一個離職員工帳號時被卡住；改成回傳 `{success:true, warnings:[...]}`，前端顯示警告內容，讓 admin 自行判斷是否繼續。

**3. 密碼重設採「admin 產生一組新密碼直接顯示」而非寄信**

理由：demo 環境沒有可靠 SMTP（CAP mail 通知已經因為這個問題被記錄過，見 `NotifyCapAssignedJob` 的 try/catch 靜默失敗設計）。改成 `resetPassword()` 產生一組隨機密碼、雜湊存入 DB、明碼在 API 回應裡回傳一次給前端顯示（比照許多內部後台系統「顯示一次性密碼，請自行轉告使用者」的做法），不依賴寄信管道。

**4. 供應商多聯絡人邀請走獨立端點（`SupplierUserController`），不是共用 `UserController::store` 加一個 supplier_id 參數**

理由：兩者的權限模型與呼叫情境不同——`UserController::store` 是 admin 建立任何角色的內部帳號；`SupplierUserController::store` 語意是「幫這家供應商加人」，角色白名單只能是 `supplier`/`sup_esg`，且未來若供應商角色本身要開放給 comply/buyer 邀請（現在還沒開放，先鎖 admin），兩者的角色檢查會分岔，分開端點比共用一個端點加條件判斷更清楚。

**5. `is_active` 而非重用 `onboarding_stage` 概念**

理由：`onboarding_stage` 是 Supplier 專屬的三態狀態機（active/suspended/terminated），語意是「供應商合作關係」；User 帳號停用是單純的二態開關（能不能登入），語意不同，用簡單 boolean 欄位即可，不需要狀態機。

## Risks / Trade-offs

- **[風險] 一次性密碼顯示在 API 回應／畫面上，若 admin 忘記轉告或畫面被截圖，密碼外洩風險** → 緩解：僅回應一次、不記錄在任何 log／通知內容裡；文件註明這是 demo 環境簡化方案，正式上線前應改為寄信＋強制首次登入改密碼
- **[風險] `user_role_histories` 存 roles 陣列快照，如果角色定義本身之後改名，歷史紀錄會跟現在的角色清單對不上** → 緩解：歷程表存的是「當下」的角色字串陣列，本來就是時間點快照，屬預期行為，不強求跟未來角色定義同步
- **[風險] 停用檢查的「進行中工作」查詢（CAP/SAQ）如果之後系統新增其他「使用者名下的工作」類型（例如未來的供應商稽核任務），這裡會遺漏** → 緩解：先涵蓋 CAP（`created_by`/`assigned` 相關欄位）與 SAQ（`reviewed_by_id`）這兩個現有主要工作類型，之後有新工作類型再擴充檢查清單，不做成外掛式通用機制（過度設計）

## Migration Plan

1. Migration：`users` 加 `is_active`（預設 `true`，避免既有帳號被鎖住）；新增 `user_status_histories`、`user_role_histories` 兩張表
2. 後端：`UserService`/`UserController`/`SupplierUserController` + 路由（全部掛 `role.admin`，`SupplierUserController::index` 額外允許 `supplier`/`sup_esg` 查自己名下帳號但需 ownership 檢查）
3. `AuthService::login()` 加入 `is_active` 檢查，訊息比照現有「帳號或密碼錯誤」風格，不區分「帳號不存在」與「帳號被停用」以避免帳號枚舉
4. 前端：API 模組 → `UsersView.vue` → 供應商詳情頁區塊 → 側邊欄入口，依序建置並各自可獨立部署測試
5. 部署後用既有 39 家供應商中的一家實際測試邀請第二聯絡人、雙帳號分別登入確認資料隔離（沿用稽核時已驗證過的 Portal IDOR 防護模式）

## Open Questions

（無，範圍已在 proposal 明確排除 permission 細粒度層、自助密碼重設、工作自動轉移三項，其餘技術決策已在上方 Decisions 定案）
