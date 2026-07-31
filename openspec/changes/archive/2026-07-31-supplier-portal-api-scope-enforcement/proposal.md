## Why

探討「供應商 Portal 與中心廠介面是否要分埠」時發現：後端 API 目前完全沒有角色層級的存取控制，所有業務路由共用同一組 `auth:api` middleware——供應商角色的合法 JWT 可以直接呼叫任何中心廠專用 API（供應商列表、銷售產品、出口審查等），不受前端路由守衛限制。同時發現 3 處具體 IDOR（橫向越權）漏洞：供應商可竄改其他供應商主檔、列出其他供應商合規文件、讀寫其他供應商問卷。這是實際存在的安全漏洞，優先於任何網路層/部署層隔離（分埠、子網域）修復——沒有後端授權邊界，前端或網路層的隔離都只是 false sense of security。

## What Changes

- 新增 `EnsureSupplierPortalScope` middleware，掛在 `auth:api` route group 上：非供應商角色直接放行；供應商角色（`supplier`/`sup_esg`）只允許存取明確列出的 Portal 白名單路由（依 `METHOD + route URI pattern` 比對），其餘一律 403
- 修復 3 處 IDOR：
  - `SupplierProfileController::update()` 補上 `supplier_id` ownership 檢查
  - `SupplierComplianceDocController::index()` 補上 ownership 檢查（比照該檔案 `store()` 既有寫法）
  - `QuestionnaireController::show/update/submit/dispute` 補上 ownership 檢查；`QuestionnaireService::list()` 供應商角色時強制以自己 `supplier_id` 覆寫查詢條件，不受請求參數影響

## Capabilities

### New Capabilities
- `supplier-portal-api-scope-enforcement`：後端 API 層的供應商角色存取範圍控制

## Impact

- 後端：新增 `app/Http/Middleware/EnsureSupplierPortalScope.php`；修改 `bootstrap/app.php`（middleware alias）、`routes/api.php`（掛載 middleware）、`SupplierProfileController`、`SupplierComplianceDocController`、`QuestionnaireController`、`QuestionnaireService`
- 不影響：供應商 Portal 既有前端功能（已逐一比對 `esgchain-web/src/views/portal/*.vue` 實際呼叫的 API，白名單完整覆蓋，並用真實供應商帳號回歸測試過）；中心廠角色（admin/buyer/sustain/comply/analyst）存取權限不受影響（此次白名單機制只處理「供應商 vs 非供應商」邊界，中心廠角色一律放行，尚未實作 CLAUDE.md RBAC 表裡 buyer/sustain/comply/analyst 彼此之間的細緻模組權限區分）
- 明確排除範圍：
  - 未實作內部角色（buyer/sustain/comply/analyst）之間的模組級權限區分——這些是內部信任員工，非外部不信任來源，優先級較低，留待未來需要時再做
  - 未處理「供應商 Portal 與中心廠介面分埠/子網域」的網路層隔離——這是本次探討的原始問題，判斷後端授權漏洞優先級更高、更急迫，先修這個；網路層隔離是否要做、要用子網域還是分埠，留待後端授權補齊後再議
