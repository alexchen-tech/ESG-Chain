## Why

後端授權漏洞已修復（`supplier-portal-api-scope-enforcement`），供應商角色的 JWT 現在無法呼叫中心廠專用 API。這次接續處理原本探討的「網路層隔離」：目前供應商 Portal 跟中心廠介面是同一份 Vue SPA、同一個 build、同一個 port，只靠前端路由守衛區分。使用者選擇更徹底的隔離方式：把兩者拆成兩個獨立的 Vue build，讓供應商瀏覽器完全不會下載到中心廠的路由/元件/API client 程式碼——即使供應商帳號被盜用，攻擊者也看不到中心廠系統的功能結構，降低偵察（reconnaissance）價值。

## What Changes

- Vite 改為 multi-page build：新增 `portal.html` 入口（對應新的 `src/main-portal.ts`），既有 `index.html`／`src/main.ts` 保留給中心廠介面
- 新增 `src/router/portal.ts`（只含 `/login`、`/supplier/*` 路由）與 `src/AppPortal.vue`（無側邊欄的精簡版根元件）；既有 `src/router/index.ts`／`src/App.vue` 移除供應商相關路由，只保留中心廠路由
- 共用程式碼（`api/`、`stores/`、`components/`（非 sidebar 類）、`assets/`、`constants/`）維持共用，不重複維護兩份
- Nginx 新增第二個 `server` block，依 `server_name` 分流：中心廠網域導向 `index.html`、供應商網域導向 `portal.html`（兩者仍指向同一個 Vite dev server / 同一份 dist，只是入口不同）
- 登入頁依所在 build 做角色前置判斷（UX 層級，非安全邊界——真正的存取控制已在後端 middleware）：供應商帳號在中心廠 build 登入被拒、反之亦然，並提示對方應使用的網址

## Capabilities

### New Capabilities
- `supplier-portal-frontend-build-split`：供應商 Portal 與中心廠介面的前端建置層級隔離

## Impact

- 前端建置：`vite.config.ts`（multi-page `build.rollupOptions.input`）、新增 `portal.html`、`src/main-portal.ts`、`src/router/portal.ts`、`src/AppPortal.vue`
- 前端路由：`src/router/index.ts` 移除供應商路由；供應商相關頁面元件（`views/portal/*.vue`）不變、只改由新 router 掛載
- 部署：`nginx/default.conf` 新增 server block；`docker-compose.yml`／本機測試視情況新增 hostname 對應（本次為 dev 環境，無真實網域，正式上線時對應真實子網域）
- 不影響：後端 API（授權邊界已在前一次修復完成，這次純前端/部署層變更）、既有頁面功能與樣式
- 明確排除範圍：不做正式網域/DNS/TLS 憑證申請（那是實際上線時的維運工作，這次只做架構與本機可驗證的路由設定）
