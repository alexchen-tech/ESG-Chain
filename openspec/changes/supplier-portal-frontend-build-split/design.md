## Context

`supplier-portal-api-scope-enforcement` 已經把真正的信任邊界（後端授權）補齊。這次做的前端 build 拆分是**防禦縱深（defense in depth）**，不是取代後端授權——即使拆了兩個 build，安全性的根本保證仍然來自後端 middleware。拆 build 解決的是另一個問題：目前單一 SPA 讓供應商瀏覽器下載到完整的中心廠路由表、元件、API client 程式碼（只是被路由守衛擋住不能「進入」），這些程式碼本身就是攻擊者做偵察時的情報（知道系統有哪些功能、API 長什麼樣子），拆開後供應商端完全不會拿到這些程式碼。

## Goals / Non-Goals

**Goals：**
- 供應商 build 的最終產出（JS/CSS chunk）不包含任何中心廠專屬 view/元件的程式碼
- 中心廠 build 不包含供應商 Portal 專屬頁面的程式碼
- 兩個 build 共用同一套 `api/`/`stores/`/共用元件/樣式，不重複維護
- 本機 docker 開發環境可用 `curl -H "Host: ..."` 驗證 nginx 分流邏輯是否正確，不需要真實 DNS

**Non-Goals：**
- 不處理正式網域申請、TLS 憑證、DNS 設定——那是實際上線時的維運工作
- 不重新設計後端 API（授權邊界已在前一次修復完成）
- 不做「中心廠介面收進 VPN/IP 白名單」——這是拆 build 之後的下一步強化，這次先把 build 拆開、nginx 分流做出來，是否要進一步限制中心廠網域的公開存取留待之後決定

## Decisions

**1. Vite multi-page build，不是兩個獨立專案**

用 Vite 原生支援的 multi-page app 設定（`build.rollupOptions.input: { main: 'index.html', portal: 'portal.html' }`），而不是把 `esgchain-web` 拆成兩個獨立的 npm 專案。理由：兩者需要共用大量程式碼（`api/`、`stores/auth`、UI 元件、CSS 設計系統），拆成獨立專案要嘛複製一份（維護成本高、容易長歪）要嘛抽成 shared package（工程量更大，這次沒有必要）。Multi-page build 讓 Rollup 自動依「每個 entry 實際 import 到什麼」做 tree-shaking/code-splitting，兩個 bundle 天然分離、共用程式碼天然共用，不需要額外設定。

**2. Router 完全拆開（兩份 router 設定檔），不是同一個 router 用 meta 篩選**

現有 `router.beforeEach` 用 `meta.roles` 篩選是「執行期」判斷（程式碼都下載下來了，只是不給進入這個路由）。這次的目標是「建置期」就不下載——因此兩個 build 必須是完全獨立的 router 實例，各自的 `routes` 陣列只列出自己需要的路由。中心廠 router 移除所有 `/supplier/*` 路由；供應商 router 只有 `/login`、`/supplier/*`、以及根路徑導向 `/supplier/portal`。因為路由定義本身用的是 `component: () => import('...')` 動態匯入（既有寫法本來就是這樣），沒有被任何一個 router 引用到的 view 元件，Rollup 分析不到匯入路徑，自然不會被打包進該 bundle。

**3. `App.vue` 拆成 `App.vue`（中心廠，含 AppSidebar）與 `AppPortal.vue`（供應商，無側邊欄）**

現有 `App.vue` 已經有 `showSidebar = ... && !auth.isSupplier` 這種「執行期判斷要不要顯示側邊欄」的邏輯，這次順勢把它拆乾淨：`AppPortal.vue` 直接不 import `AppSidebar` 元件，供應商 bundle 連 `AppSidebar.vue`（以及它可能間接用到的中心廠選單資料）都不會被打包進去。

**4. 登入頁角色判斷是 UX 層級，不是安全邊界**

供應商帳號在中心廠 build 的登入頁登入成功後（後端仍會驗證帳密正確、回傳合法 JWT——這步驟後端不會因為「你用錯 build 登入」而拒絕，因為後端不知道、也不需要知道前端用哪個 build），前端檢查 `user.role`，若是 `supplier`/`sup_esg`，不寫入 auth store／不導向任何頁面，顯示「請至供應商 Portal 登入」提示（附正確網址文字，不做自動 cross-origin 導頁，避免使用者混淆）。這純粹是體驗優化，就算跳過這個檢查、供應商真的把 token 存進中心廠 build 的 store，後端 middleware 依然會擋掉所有非白名單 API 呼叫，安全性不會因此打折。

**5. Nginx 用 `server_name` 分流，兩個 server block 指向同一個 upstream**

`vue` upstream（`esgchain-web:5173`）不變，新增第二個 `server` block，`server_name` 比對到供應商網域時，`location /` 的 `proxy_pass` 目標路徑改成請求 `portal.html`（Vite dev server 對 multi-page 專案本來就會用路徑對應到各自的 html 檔案）；中心廠網域維持原本 `location /` 行為（回應 `index.html`）。正式環境（`vite build` 產出的 dist）由 nginx 直接用 `try_files` 依 server block 指向對應的靜態檔案，本次先讓 dev 環境（proxy 到 Vite dev server）跑通即可，正式環境的 dist 版設定一併寫好但不強制這次驗證（沒有正式部署環境）。

## Risks / Trade-offs

- [取捨] 兩份 router/App 設定檔意味著之後新增「供應商也要用、中心廠也要用」的路由時，要記得在兩邊都加（或者共用的路由抽成陣列常數、兩邊各自 concat）——這次先各自維護，如果之後發現重複路由頻繁出現，再抽共用常數
- [風險] 本機 docker-compose 開發環境沒有真實 DNS，這次只能用 `curl -H "Host: ..."` 驗證 nginx 分流邏輯正確，無法在瀏覽器實際用兩個網域測試登入/導頁體驗，正式上線前建議再用真實網域跑一次完整驗收
- [取捨] Vite dev server 的 multi-page HMR 行為（例如同時開著兩個 tab 分別連 `index.html`／`portal.html`）理論上互不干擾，但這次沒有做長時間穩定性測試，如果開發時遇到 HMR 跨 entry 誤觸發的情況，需要額外排查

## Migration Plan

1. `vite.config.ts` 改 multi-page 設定，新增 `portal.html`
2. 新增 `src/main-portal.ts`、`src/AppPortal.vue`、`src/router/portal.ts`
3. `src/router/index.ts` 移除供應商路由；`src/App.vue` 移除 `isSupplier` 判斷邏輯（不再需要，因為供應商已經是完全獨立的 build）
4. 登入頁（`src/views/auth/LoginView.vue`，先確認實際檔名）依 build 加角色前置檢查
5. `nginx/default.conf` 新增第二個 server block
6. 驗證：`vue-tsc --noEmit`、`vite build` 兩個 bundle 都能成功產出、檢查供應商 bundle 的 JS chunk 不包含中心廠 view 程式碼片段、`curl -H "Host: ..."` 驗證 nginx 分流正確、兩個 build 各自登入流程正常

## Open Questions

（無）
