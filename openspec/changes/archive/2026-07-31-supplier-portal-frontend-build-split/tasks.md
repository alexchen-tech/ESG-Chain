## 1. Vite Multi-page 設定

- [x] 1.1 `vite.config.ts` 新增 `build.rollupOptions.input`，含 `main: 'index.html'` 與 `portal: 'portal.html'`
- [x] 1.2 新增 `portal.html`（比照既有 `index.html` 結構，`<script>` 指向 `src/main-portal.ts`）

## 2. 供應商 Build 專屬檔案

- [x] 2.1 新增 `src/main-portal.ts`（比照 `src/main.ts`，掛載 `AppPortal.vue` + 供應商專用 router）
- [x] 2.2 新增 `src/AppPortal.vue`（不 import `AppSidebar`，無側邊欄的精簡版根元件）
- [x] 2.3 新增 `src/router/portal.ts`（只含 `/login`、`/supplier/*` 路由，根路徑導向 `/supplier/portal`）

## 3. 中心廠 Build 清理

- [x] 3.1 `src/router/index.ts` 移除 `/supplier/*` 路由
- [x] 3.2 `src/App.vue` 移除 `auth.isSupplier` 相關判斷邏輯（不再需要，供應商已是獨立 build）

## 4. 登入頁角色前置提示

- [x] 4.1 確認實際登入頁檔案路徑，供應商 build 與中心廠 build 各自的登入頁登入成功後檢查角色，不符合時顯示提示、不寫入 auth store、不導頁

## 5. Nginx 分流

- [x] 5.1 `nginx/default.conf` 新增第二個 `server` block，依 `server_name`／`Host` 分流至 `portal.html` 或 `index.html`

## 6. 驗證

- [x] 6.1 `vue-tsc --noEmit` 全專案型別檢查通過
- [x] 6.2 `vite build` 成功產出兩個 bundle（`dist` 底下 `main`/`portal` 兩組產出）
- [x] 6.3 檢查供應商 bundle 產出檔案不包含中心廠專屬 view 程式碼片段（grep 產出的 JS 檔案確認）
- [x] 6.4 檢查中心廠 bundle 產出檔案不包含 `views/portal/*` 程式碼片段
- [x] 6.5 `curl -H "Host: ..."` 驗證 nginx 依網域正確分流（供應商網域回應 portal 入口、中心廠網域回應中心廠入口）
- [x] 6.6 兩個 build 的登入流程分別用對應角色帳號驗證正常運作；用錯誤角色帳號登入該 build 驗證出現提示且不進入系統
