## Context

`User` model 已用 `Spatie\Permission\Traits\HasRoles`（guard `api`），`permissions`/`role_has_permissions` 表已存在但從未使用。目前角色檢查分散在三個地方，彼此獨立維護：`routes/api.php` 的 `role.admin`/`role.any:...` middleware（66+ 處）、少數 Controller 內硬寫的 `hasRole()`/`hasAnyRole()`（6 處）、`AppSidebar.vue` 的選單角色陣列。前端角色判斷目前是單一字串（`authStore.user?.role`），不是陣列，隱含「一個使用者只有一個角色」的假設，跟後端 `hasRole`/`syncRoles` 可支援多角色的模型不完全一致——這次沿用「單一角色」的現況，不在這個 change 裡擴充多角色支援（那是更大的範圍，若未來需要另開 change）。

## Goals / Non-Goals

**Goals：**
- Admin 能在 UI 上直接看到並調整每個角色對每個模組的權限，不用改程式碼
- 新機制上線當下，所有角色的實際可用功能與現況完全一致（先精確複製現況，再談之後要不要調整）
- 前端選單顯示邏輯跟後端權限檢查用同一份資料源（使用者的權限清單），不再各自維護一份角色陣列

**Non-Goals：**
- 不擴充角色種類、不做多角色、不做個人化例外權限（維持角色→權限、使用者→角色兩層，不做使用者→權限直接指派）
- 不做細到欄位層級的權限（例如「只能改 CAP 的某幾個欄位」），停在「模組.動作」這個粒度
- 不強制既有 session 的權限即時生效（下次登入才反映最新設定）

## Decisions

**1. Permission 命名格式：`模組.動作`（如 `suppliers.view`、`caps.create`），全小寫、模組用複數名詞**

理由：對應前端 `AppSidebar.vue` 現有的選單 key 分組方式（供應商管理／CAP 矯正／…），比 spatie 官方預設的 `"edit articles"` 這種英文語句式命名更貼合本專案既有的路由分組習慣，之後在 UI 上呈現矩陣（列＝模組，欄＝動作）時也直接對應資料結構，不需要額外解析字串。

**2. 用一支新的泛用 `permission:xxx.xxx` middleware 取代 `role.admin`/`role.any:...`，不是在既有 middleware 旁邊多加一層**

理由：如果兩套機制並存（角色字串陣列 + 權限字串），未來只要有人改了角色管理 UI 卻忘記同步改路由上的角色陣列，馬上又回到現在「兩份清單各自維護、容易漂移」的老問題——這正是這次要解決的問題本身。改用單一權限字串當作路由的唯一真相來源，`role.admin`/`role.any:...` 逐步淘汰。

**3. Seed 資料嚴格依照稽核時整理出的「路由 → 現況允許角色」對照表產生 `role_has_permissions`，不依 CLAUDE.md 簡化版模組表**

理由：稽核已確認 CLAUDE.md 的角色×模組表是簡化版設計文件，跟程式碼實際檢查的粒度對不上（例如 CAP 寫入實際限定 admin/buyer/sustain/comply，SAQ 系列寫入限定 admin/sustain/comply/analyst 排除 buyer，這些細節 CLAUDE.md 表格沒有反映）。若照 CLAUDE.md 表格重新生成權限，會在切換當下就改變既有行為，不符合「先精確複製現況」的目標。CLAUDE.md 的表格之後可以更新成連結到角色管理頁面的說明，不再是唯一事實來源。

**4. 前端選單權限判斷改讀 `/auth/me` 回傳的 `permissions: string[]`，路由 `meta.roles` 保留但新增 `meta.permission` 並行**

理由：一次把全部 60+ 個路由的 `meta.roles` 改成 `meta.permission` 風險較高（漏改一個就是權限漏洞或誤擋），先讓兩者並存一段時間：路由守衛先檢查 `meta.permission`（若有設定），沒有設定的路由 fallback 回舊的 `meta.roles` 檢查，逐步遷移，不強求一次到位、也不因為漏改而讓某條路由完全沒有保護。

**5. `EnsureSupplierPortalScope` 不動**

理由：那是供應商租戶隔離的白名單機制，管的是「這個角色能不能碰中心廠 API」，跟這次「角色能做哪些 CRUD 動作」是不同維度的問題（供應商角色本來就不該出現在這次的權限矩陣管理範圍內，Portal 端的權限模型維持獨立）。

## Risks / Trade-offs

- **[風險] 66+ 處路由要逐一從角色陣列改成權限字串，改動面大，任何一處改錯字串就會變成該路由完全無保護或誤擋合法角色** → 緩解：seed 資料與路由改動要對照第一次稽核整理出的「路由→現況允許角色」對照表逐條核對，且每一條路由改完後都要用該角色的真實帳號 curl 驗證行為跟改動前一致（不能只信任 seed 資料寫對，要實際驗證 HTTP 行為）
- **[風險] 前端 `meta.roles`/`meta.permission` 並存期間，兩者判斷邏輯如果寫錯（例如 AND 而非 OR/fallback），可能讓某些路由變成兩邊都要過才能進，實質上比現況更嚴格導致誤擋** → 緩解：路由守衛邏輯明確定義為「有 `meta.permission` 就只看它，沒有才看 `meta.roles`」，不是兩者都檢查
- **[風險] Permission 數量上看 80-150 筆，矩陣 UI 如果做成單一巨大表格，可用性會很差** → 緩解：UI 依模組分組摺疊顯示，不做成一個攤平的超大表格
- **[風險] admin 角色本身如果被誤設成缺少某個關鍵權限，可能把自己鎖在角色管理頁面外面** → 緩解：admin 角色的權限管理入口本身（`settings.roles` 相關權限）在 seed 資料裡固定歸給 admin，且 `EnsureAdminRole`（`role.admin`）作為 admin 專屬頁面的最後一道防線可以保留不轉換（管理員身分本身不該透過可設定的權限系統來管理，避免自我鎖死的迴圈）

## Migration Plan

1. 建立 `permissions` migration/seeder：依稽核路由表產生完整權限目錄，`role_has_permissions` 依現況（非 CLAUDE.md 簡化表）seed
2. 新增 `EnsurePermission` middleware（`permission:xxx.xxx`），新增 `PermissionController`（角色管理頁面用的查詢/更新 API，限定 `role.admin` 存取，不透過權限系統管理，見 Decision 5 的 admin 鎖死風險緩解）
3. 逐一（依模組分批，不求一次全改）把 `routes/api.php` 的 `role.admin`/`role.any:...` 改為 `permission:xxx.xxx`，每改一批用該角色真實帳號 curl 回歸測試
4. 前端：`AuthService::me()` 回應加上 `permissions` 陣列；`AppSidebar.vue` 選單過濾邏輯改讀權限；`router/index.ts` beforeEach 守衛依 Decision 4 的並存邏輯調整
5. 新增 `RolesView.vue` 角色管理頁面（矩陣式、依模組分組摺疊）
6. 全部路由遷移完成後，確認沒有路由還在用舊的 `role.admin`/`role.any:...`（用 grep 全專案掃過一次），此時可以考慮移除 `EnsureAdminRole`/`EnsureAnyRole` 或保留當作 fallback（視實際遷移完成度決定，不在這次 change 強制刪除舊 middleware）

## Open Questions

（無，範圍已在 proposal 明確排除多角色、個人化例外權限、欄位層級權限、即時生效四項）
