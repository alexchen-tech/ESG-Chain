## ADDED Requirements

### Requirement: 供應商 Portal 與中心廠介面各自獨立建置
系統 SHALL 將供應商 Portal 與中心廠介面拆分為兩個獨立的前端建置產出（Vite multi-page entries），供應商 build SHALL NOT 包含任何中心廠專屬路由、頁面元件或側邊欄選單程式碼；中心廠 build SHALL NOT 包含供應商 Portal 專屬頁面元件程式碼。共用程式碼（API client、狀態管理、通用 UI 元件、樣式）SHALL 由兩者共用，不得重複維護。

#### Scenario: 供應商 bundle 不含中心廠程式碼
- **WHEN** 建置供應商 Portal 的產出（`portal.html` 對應的 bundle）
- **THEN** 產出檔案 SHALL NOT 包含 `AppSidebar.vue` 或任何中心廠專屬 view（如供應商列表、銷售產品、出口審查等頁面）的程式碼

#### Scenario: 中心廠 bundle 不含供應商 Portal 程式碼
- **WHEN** 建置中心廠介面的產出（`index.html` 對應的 bundle）
- **THEN** 產出檔案 SHALL NOT 包含 `views/portal/*.vue` 相關頁面元件的程式碼

#### Scenario: 共用程式碼不重複維護
- **WHEN** 修改 `api/`/`stores/` 或通用 UI 元件
- **THEN** 變更 SHALL 同時反映在兩個 build 的產出中，不需要在兩處各自修改一次

### Requirement: Nginx 依網域分流至對應建置產出
系統 SHALL 在 nginx 依請求的 `server_name`（或本機測試時的 `Host` 標頭）將供應商網域的請求導向供應商 build 入口、中心廠網域的請求導向中心廠 build 入口，兩者共用同一個後端 API 服務，不需要拆分後端。

#### Scenario: 供應商網域請求導向供應商入口
- **WHEN** 請求帶有供應商網域對應的 `Host` 標頭
- **THEN** nginx SHALL 回應供應商 build 的入口內容（`portal.html` 對應產出）

#### Scenario: 中心廠網域請求導向中心廠入口
- **WHEN** 請求帶有中心廠網域對應的 `Host` 標頭
- **THEN** nginx SHALL 回應中心廠 build 的入口內容（`index.html` 對應產出）

### Requirement: 登入頁角色前置提示（UX 層級）
供應商帳號在中心廠 build 的登入頁登入成功時，前端 SHALL 顯示提示告知應改至供應商 Portal 網址登入，不 SHALL 將該登入狀態寫入應用程式狀態或導向任何中心廠頁面；反之非供應商帳號在供應商 build 登入亦同。此判斷 SHALL 僅為使用者體驗優化，系統的實際存取控制邊界 SHALL 仍由後端 API 角色範圍限制（見 `supplier-portal-api-scope-enforcement`）保證，不得因此判斷被繞過而產生安全風險。

#### Scenario: 供應商帳號誤用中心廠登入頁
- **WHEN** 供應商角色帳號在中心廠 build 的登入頁輸入正確帳密
- **THEN** 系統 SHALL 顯示「請至供應商 Portal 登入」提示，不 SHALL 導向中心廠任何頁面

#### Scenario: 中心廠帳號誤用供應商登入頁
- **WHEN** 非供應商角色帳號在供應商 build 的登入頁輸入正確帳密
- **THEN** 系統 SHALL 顯示對應提示，不 SHALL 導向供應商 Portal 任何頁面
