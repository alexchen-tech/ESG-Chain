## Context

`role-permission-management`（進行中，未 commit）已將 66 條路由的角色檢查收斂成 18 個 `module.manage` 統包權限，資料表結構是 spatie/laravel-permission 標準的 `roles` / `permissions` / `model_has_roles` / `model_has_permissions` / `role_has_permissions`（`model_has_permissions` 目前完全未使用，因為目前只有角色層級的授權）。`EnsurePermission` middleware 目前直接呼叫 `$user->hasPermissionTo()`（spatie 內建，會查 `model_has_permissions` ∪ 角色的 `role_has_permissions`——spatie 其實已經原生支援個人權限，只是這次之前完全沒用到這個能力）。

這次要做兩件事：把 `module.manage` 拆成 CRUD 四動作；並啟用 spatie 原生就有的「個人直接授權」路徑，而不是自己重新發明一套。

## Goals / Non-Goals

**Goals:**
- 權限目錄粒度提升到 `module.view` / `module.create` / `module.update` / `module.delete`
- 使用者可以在角色權限之上/之下有個人例外（多授予或少授予），且行為與 spatie 標準語意一致
- 拆分後 66 條路由的實際存取行為（誰能打哪支 API）與 `role-permission-management` 完成時的現況 100% 一致，不因粒度變細而改變任何人的實際權限
- admin 角色與 admin 使用者維持全權限鎖定、不可覆寫

**Non-Goals:**
- 不做權限到期時間、暫時授權、審核簽核流程
- 不做「個人負向覆寫」的專屬新表（見 Decision 2，直接沿用 spatie 語意，不支援明確 revoke，只支援明確 grant）
- 不重新設計既有 66 條路由各自「該歸類哪個角色」的現況行為本身，只重新標記它們屬於 CRUD 哪個動作

## Decisions

### Decision 1：CRUD 分類依「HTTP 語意」而非猜測業務含義
路由拆分規則：`GET`（單筆/列表查詢）→ `.view`；`POST`（新建）→ `.create`；`PUT`/`PATCH`（更新/狀態轉換，含如 CAP 的 `approve`/`reject` 這類動作型端點）→ `.update`；`DELETE`→ `.delete`。同一模組同一動作若橫跨多條路由但角色白名單完全相同，合併為一個權限字串（沿用 `role-permission-management` 既有的合併邏輯，只是合併的維度從「模組」變成「模組+動作」）。若同模組同動作但角色白名單不同（例如查看列表 admin/sustain/comply/analyst 都能看，但查看單一敏感明細只有 admin/sustain 能看），拆成更細的權限字串（如 `saqs.view` vs `saqs.view-detail`），保留粒度差異，不強行合併掩蓋現況差異。
- 替代方案：依「業務語意」分類（例如把 CAP 的「標記完成」算作 update 還是一個獨立的 `caps.complete`）——否決，因為業務語意判斷主觀、之後容易跟人工認知產生分歧；HTTP method 是路由既有、客觀、可重現的依據。

### Decision 2：個人覆寫直接用 spatie 原生 `model_has_permissions`，不新建 `user_has_permissions` 表
提案（proposal.md）原先設想新建 `user_has_permissions` 含 `granted` 布林欄位以支援「個人負向覆寫（明確從角色權限中收回）」。設計階段重新評估後改用 spatie 已經內建、`EnsurePermission` 目前就已經在呼叫的 `hasPermissionTo()`／`model_has_permissions` pivot 表：直接對使用者 `givePermissionTo()` 即為個人「多授予」；spatie 的 `hasPermissionTo()` 語意本身就是「角色權限 ∪ 個人直接權限」的聯集，沒有「負向覆寫」的概念。
若要支援「該使用者其角色本來有某權限，但要單獨拔掉」，spatie 沒有原生支援（要嘛拔角色本身的權限、要嘛換角色），因此本次個人覆寫**只做「多授予」，不做「負向收回」**——與 proposal.md 原先設想不同，proposal.md 的 `granted` 布林欄位設計於此正式改為：只使用 spatie 既有的 `model_has_permissions`（多對多 pivot，無 `granted` 欄位，存在即代表 grant），`granted_by`/時間戳等稽核資訊改記錄在既有的稽核 log 機制（比照 `UserRoleHistory` 的模式，新增 `UserPermissionOverrideHistory` 記錄「誰在何時給某使用者額外授予了哪個權限」，而非把稽核欄位塞進 pivot 表本身）。
- 替代方案（proposal.md 原案：新表 + `granted` 布林支援正負覆寫）：否決。理由：(a) 大幅增加 `EnsurePermission` 的查詢複雜度（要同時查角色權限、個人正覆寫、個人負覆寫，三者做集合運算）；(b) spatie 官方語意本來就不支援負覆寫，硬做等於繞過框架自建一套平行邏輯，未來 spatie 版本升級或既有其他呼叫 `hasPermissionTo()` 的地方會與這套平行邏輯不一致；(c) 使用者原始需求「某位 buyer 需要額外看到某個模組的刪除功能」本質上就是「多授予」，沒有明確提出「收回」的具體情境，YAGNI。

### Decision 3：`EnsurePermission` middleware 不需要改動邏輯本身
因為 Decision 2 改用 spatie 原生 `hasPermissionTo()`，而 `EnsurePermission` 目前已經呼叫這個方法（見 `role-permission-management` design.md），middleware 本身**不需要修改查詢邏輯**——只要 `model_has_permissions` 有資料，`hasPermissionTo()` 自動會把個人直接權限納入判斷。這次唯一需要動的是「admin 使用者不可被個人覆寫」的短路判斷（如果目前是只判斷角色 `admin` 就短路，需確認個人覆寫也不會意外讓 admin 之外的使用者被誤判——admin 短路邏輯本身不受影響，因為短路判斷發生在查詢 `model_has_permissions` 之前）。
- 替代方案：自行在 middleware 內合併角色+個人查詢結果——否決，spatie 已經做了，重寫是多餘工作。

### Decision 4：RolesView.vue 矩陣改「模組×動作」為主鍵，角色仍是可勾選的欄
現有矩陣是「權限列 × 角色欄」，一列就是一個 `module.manage`。拆分後一個模組會展開成最多 4 列（view/create/update/delete），角色欄不變（buyer/sustain/comply/analyst，admin 唯讀）。UI 結構不需要重新設計，只是 `catalog[mod]` 陣列元素從 1 個變成最多 4 個，模板邏輯不變（沿用既有 `v-for="perm in catalog[mod]"`），純粹是資料量變化，不是架構變化。
- 替代方案：改成「動作」為外層分組、「模組」為內層——否決，模組分組摺疊的既有互動模式（`role-permission-management` 6.1 已實作）使用者已經熟悉，不必為了粒度變細而改變資訊架構。

### Decision 5：個人權限覆寫 UI 放在 UsersView.vue 的使用者編輯 modal，不做獨立頁面
比照 proposal.md 設想，於既有「編輯角色」modal 旁加一個子頁籤「個人權限覆寫」，列出該使用者角色本身沒有、但可額外勾選授予的權限（依模組分組，比照 RolesView.vue 的摺疊 UI，只是這裡只勾自己的、不勾角色的）。已經透過角色繼承擁有的權限，於此頁籤顯示為「已透過角色『xxx』取得」的唯讀列（不可重複勾選/取消，取消請去角色管理頁面調整角色本身），避免「個人覆寫」跟「角色權限」在 UI 上產生混淆的雙重來源錯覺。

## Risks / Trade-offs

- [風險] 66 條路由的 CRUD 重新分類是人工判斷，即使依 HTTP method 這種客觀規則，仍可能把個別路由分類分歧（例如某些查詢類路由實際上會寫入 side-effect 資料，如 view 動作附帶更新「已讀」狀態）→ 緩解：任務執行時每一批分類完成後，立即用真實角色帳號 curl 回歸測試，比對分類前後的存取結果是否一致（沿用 `role-permission-management` 已驗證有效的分批驗證流程）
- [風險] Decision 2 放棄「負向覆寫」是需求範圍縮減，與使用者原始措辭「勾選額外授予或收回特定權限」字面上的「收回」有落差 → 緩解：於實作完成後的回報中明確告知使用者這個範圍調整與理由，若使用者仍堅持要「個人負向收回角色權限」，那是下一個獨立變更（需要重新設計，不在本次範圍）
- [風險] 拆分權限字串是 **BREAKING**，任何地方若有寫死引用舊 `module.manage` 字串（例如前端寫死判斷、或忘記遷移的路由）會在 seed 新權限目錄後失效 → 緩解：任務章節安排「全專案 grep 確認舊 `.manage` 字串殘留」步驟，仿照 `role-permission-management` tasks 4.6 的做法

## Migration Plan

1. 新 migration：`PermissionCatalogSeeder` 內容改版（不新建資料表，因 Decision 2 改用既有 spatie 表結構），需要先 `php artisan permission:cache-reset` 清快取
2. 依模組分批遷移（比照 `role-permission-management` 既有分批策略），每批改完立即用真實角色帳號回歸測試
3. 前端 `RolesView.vue`/`UsersView.vue` 隨後端每批同步部署，避免前端顯示的權限目錄與後端資料庫不一致
4. 部署後 `route:clear` + `config:cache` + `route:cache`（依專案既有 Docker 同步規則）

## Open Questions

- 個人權限覆寫的稽核歷程（`UserPermissionOverrideHistory`）是否需要在 UI 上有獨立的查詢頁面（比照使用者狀態/角色歷程），或先只落地在資料庫、之後有需要再補 UI？本次先只落地資料庫記錄，不做獨立查詢頁面（YAGNI，待實際需求出現）
