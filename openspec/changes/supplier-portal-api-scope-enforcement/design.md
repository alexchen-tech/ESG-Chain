## Context

探討「供應商 Portal 跟中心廠介面要不要分埠」時，先釐清目的是「安全隔離：供應商是外部不信任網路來源，想降低攻擊面」。研究既有架構發現：目前是單一 Vue SPA、單一 port，僅靠前端路由守衛（`router.beforeEach` 檢查 `meta.roles`）做邏輯隔離；進一步查後端才發現真正的洞在這裡——`routes/api.php` 所有業務路由共用同一組 `auth:api`，沒有任何角色層級存取控制，供應商 JWT 可以繞過前端直接打中心廠 API。這比「要不要分埠」急迫得多：分埠只是換個門牌，真正的門完全沒鎖。

## Goals / Non-Goals

**Goals：**
- 供應商角色的 JWT 只能存取明確允許的 Portal 相關路由，其餘中心廠路由一律拒絕
- 修復已確認的 IDOR：供應商不能操作/讀取其他供應商的資料
- 不破壞供應商 Portal 既有功能（白名單必須完整覆蓋前端實際會呼叫的每一條 API）

**Non-Goals：**
- 不做內部角色（buyer/sustain/comply/analyst）之間的模組級權限矩陣——CLAUDE.md 雖然定義了各角色可存取模組，但這些是內部員工，屬於較低優先級的權限精細化，這次只處理「外部供應商 vs 內部所有角色」這條最急迫的邊界
- 不做網路層/部署層隔離（分埠、子網域、WAF）——那是探討的原始問題，但判斷後端授權才是真正的信任邊界，先修這個

## Decisions

**1. Middleware 用「路由白名單」而非「路徑前綴/萬用字元」比對**

供應商可存取與不可存取的端點常常共用同一個路徑前綴（例如 `questionnaires/{id}` 供應商可以 `PUT`，但 `questionnaires/{id}/start-review` 是中心廠專用），用前綴比對會誤放行中心廠專用動作。改用「HTTP method + Laravel 路由定義的 URI pattern」精確比對（`$request->route()->uri()`，例如 `portal/caps/{cap}`），不用正則猜測路徑，直接比對路由定義本身的字串，最不容易出錯。

**2. Middleware 掛在 `auth:api` group 層級，不逐條路由加**

如果要求每個新路由自己記得加角色限制，長期一定會有人忘記加（就像這次發現的漏洞——沒有人蓄意不加，只是從來沒有這層防護，新路由自然而然全部裸奔）。改成「預設拒絕供應商角色，白名單內才放行」的機制掛在 group 層級，之後新增任何中心廠路由，預設就是供應商存取不到，不需要每次都記得加防護；反而是要讓供應商能用某個新路由時，才需要主動把它加進白名單——這個方向的預設值比較安全。

**3. IDOR 修復比照專案既有寫法，不引入新模式**

`SupplierComplianceDocController::store()` 已經有正確的 `abort_if($user->supplier_id !== $supplier->id, 403)` 寫法，`index()` 只是忘記加——直接複製既有寫法，不引入新的授權抽象（如 Policy class），維持修復範圍最小、風險最低。

**4. `QuestionnaireService::list()` 用「強制覆寫」而非「拒絕未帶參數」**

供應商角色呼叫 `list()` 時，不管請求有沒有帶 `supplier_id` 查詢參數，一律強制覆寫成自己的 `supplier_id`——而不是「沒帶參數就 400 拒絕」。理由：這樣即使前端或供應商自己竄改請求試圖夾帶別人的 `supplier_id` 想繞過，後端也會直接忽略請求值、永遠只用自己 token 裡的 `supplier_id`，比「檢查參數是否存在」更難被繞過。

## Risks / Trade-offs

- [風險] 白名單如果漏列，會讓供應商 Portal 某個功能直接壞掉——這種迴歸容易被立刻發現（使用者會回報功能壞了），風險可控；已逐一比對前端 `views/portal/*.vue` 實際呼叫過的 API 並用真實供應商帳號跑過回歸測試
- [取捨] 內部角色間的權限矩陣沒有一併做，代表 buyer 角色理論上還是能打到 sustain 專用的某些 API（如果存在的話）——這次判斷內部信任員工的風險遠低於外部供應商，優先級放在下一輪處理，不在這次範圍內
- [風險] Middleware 比對用 `$request->route()->uri()` 字串完全比對，未來如果有人改動路由定義字串（例如把 `{cap}` 改成 `{capId}`）但忘記同步更新白名單常數，會讓該路由對供應商變成不可存取——這是「安全優先於便利」的刻意設計，寧可誤擋（功能壞掉容易發現、可回滾）也不要誤放（資料外洩不容易被發現）

## Migration Plan

1. 新增 `EnsureSupplierPortalScope` middleware + 白名單常數
2. `bootstrap/app.php` 註冊 middleware alias，`routes/api.php` 掛載到 `auth:api` group
3. 修復三處 IDOR（`SupplierProfileController`、`SupplierComplianceDocController`、`QuestionnaireController`/`QuestionnaireService`）
4. 部署（`esgchain-api` + `esgchain-queue-worker`，`config:clear`/`config:cache`、`route:cache`）
5. 已完成驗證：供應商角色存取中心廠路由 403、存取 Portal 白名單路由 200、跨供應商 ownership 403、管理員角色不受影響（詳見 tasks.md）

## Open Questions

- 內部角色（buyer/sustain/comply/analyst）之間的模組級權限矩陣是否要做、何時做——留待未來評估
- 網路層隔離（分埠/子網域）是否還要做——這次判斷後端授權漏洞優先級更高已先修，網路層隔離屬於「探討的原始問題」，是否要接著做、要選哪種架構（子網域 vs 分埠），留給使用者後續決定
