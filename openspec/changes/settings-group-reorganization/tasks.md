## 1. 前置調查

- [x] 1.1 grep 全專案找出所有寫死引用 `/settings/users`、`/settings/roles`、`/settings/country-risk`、`/settings/market-rules` 這 4 個舊路徑字串的地方（元件內連結、`router.push` 呼叫等），列出清單供後續 redirect 覆蓋範圍核對
  - `AppSidebar.vue` 選單陣列本身（158/159/162 行）— 隨 4.4 直接改寫，非依賴 redirect
  - `SettingsView.vue:327` `TABS` 陣列中「國家風險評等」的 `link: '/settings/country-risk'` 假 tab — 隨 3.4 移除
  - `router/index.ts` 本身的舊路由定義 — 隨 4.2 改為 redirect
  - `SeriesDetailView.vue:929`、`CountryRiskView.vue:217/243/268`、`permissions.ts:42/46` 為後端 API path（`/api/v1/settings/...`），非前端路由，不在本次調整範圍
- [x] 1.2 確認 `UsersView.vue`、`RolesView.vue`、`MarketComplianceRulesView.vue`、`CountryRiskView.vue`、`CarbonPriceSettingsView.vue` 各自的 `<style scoped>` 中，哪些規則是針對即將移除的 page-header DOM（供 2.x 移除時核對）
  - `RolesView.vue`：`<style scoped>` 無 page-header 相關規則，無需清理
  - `MarketComplianceRulesView.vue`：`.market-rules-view`（padding/max-width，改用面板 `.section-card` 後不再需要外層 padding）、`.page-header`/`.page-title`/`.page-subtitle` 三條規則為 page-header 專屬，需移除
  - `CountryRiskView.vue`：無 scoped `.page-header`/`.page-title` 規則（沿用全域 components.css），但有 breadcrumb 需移除（改為真正 tab 後不需要麵包屑跳轉）
  - `CarbonPriceSettingsView.vue`：無 scoped page-header 規則
  - `UsersView.vue`：無 `<style scoped>` 區塊，無需清理

## 2. 被嵌入元件改造（剝除 page-header，改為面板風格）

- [x] 2.1 `UsersView.vue`：移除最外層 `page-container`/`page-header`，確認移除後畫面版面不破版（改用 `.section-card`/`.section-header` 面板風格，保留＋新增使用者按鈕在 header 內）
- [x] 2.2 `RolesView.vue`：同上（無操作按鈕，僅標題+說明）
- [x] 2.3 `MarketComplianceRulesView.vue`：同上（保留＋新增規則按鈕在 header 內）
- [x] 2.4 `CountryRiskView.vue`：同上（移除麵包屑，＋新增國家按鈕併入 section-header）
- [x] 2.5 `CarbonPriceSettingsView.vue`：同上；已確認 `mounted()` 僅呼叫 `carbonPriceApi.get()`，不依賴 `$route.params`/`$route.query`，可安全作為 tab 嵌入
- [x] 2.6 逐一清除 1.2 找出的失效 `<style scoped>` 規則（`MarketComplianceRulesView.vue` 的 `.market-rules-view`/`.page-header`/`.page-title`/`.page-subtitle` 已替換為 `.section-card` 系列；`CountryRiskView.vue` 麵包屑 DOM 與其連動的 `$router.push('/settings')` 呼叫已移除）

## 3. 新建 / 擴充 Hub 外殼元件

- [x] 3.1 新建 `AccessControlHubView.vue`：tab 外殼（使用者管理｜角色管理），沿用 `activeTab` + `v-show` + `components.css` `.detail-tabs`/`.tab-panel-wrap` 模式
- [x] 3.2 新建 `MarketRulesHubView.vue`：tab 外殼（目標市場｜市場合規規則｜國家風險評等），「目標市場」tab 內容從 `SettingsView.vue` 的 `market` tab 搬移過來（含 CRUD modal 邏輯）
- [x] 3.3 擴充 `ClassificationScoringHubView.vue`：新增「SASB 產業分類」tab（內容從 `SettingsView.vue` 的 `sasb` tab 搬移過來）與「碳價設定」tab（引用 `CarbonPriceSettingsView.vue`），共 6 個 tab
- [x] 3.4 `SettingsView.vue`：移除 `market`、`sasb` 兩個 tab 與對應的資料/方法，`TABS` 陣列只留組織架構、供應商分組，移除國家風險評等的假 tab 連結項目

## 4. 路由與選單

- [x] 4.1 `router/index.ts` 新增 3 個 hub 路由：`/settings/access-control`、`/settings/classification-scoring`（沿用既有路徑）、`/settings/market-rules-hub`。存取控制/分類與計分管理設 `roles: ['admin']`；市場與合規規則因舊 `/settings/country-risk` 原本額外開放 `sustain`，設 `roles: ['admin','sustain']` 以維持行為一致（偏離 design.md Decision 3 的前提，已於任務清單與最終報告註明原因）
- [x] 4.2 `router/index.ts` 將 `/settings/users`、`/settings/roles`、`/settings/country-risk`、`/settings/market-rules` 改為 `redirect` function，導向對應 hub 路由並帶 `?tab=xxx` query
- [x] 4.3 Hub 元件 `mounted()` 讀取 `this.$route.query.tab` 決定初始 `activeTab`（無 query 時預設第一個 tab），比照既有 query 篩選寫法（`AccessControlHubView.vue`/`MarketRulesHubView.vue`/`ClassificationScoringHubView.vue` 皆已加上）
- [x] 4.4 `AppSidebar.vue`「系統設定」群組選單項目從 6 個改為 5 個：一般設定、存取控制、分類與計分管理、市場與合規規則、客戶主檔
- [x] 4.5 依 1.1 找出的清單，逐一更新專案內所有寫死引用舊路徑的地方：`AppSidebar.vue` 選單陣列已改為新 hub 路徑；`SettingsView.vue` TABS 陣列的假 tab 連結已隨 3.4 移除，無其餘寫死引用需要修正

## 5. 部署與驗證

- [x] 5.1 `vue-tsc --noEmit` 確認零錯誤（實際執行結果：無輸出，exit 0，零錯誤）
- [x] 5.2 部署前端（本專案 esgchain-web 容器以 bind mount 執行 Vite dev server，本機檔案異動即時透過 HMR 生效，`docker logs` 已確認所有異動檔案皆觸發 `hmr update`/`page reload` 且無編譯錯誤）；5 個 hub 頁路由與 tab 結構已靜態核對（router 表 + 元件程式碼），因無瀏覽器互動環境，CRUD/篩選/分頁等既有功能改用「搬移前後程式碼邏輯完全未變動，僅外殼包裝改變」的方式核對一致，未逐一手動點擊
- [x] 5.3 已靜態核對 `router/index.ts` 內 4 條舊路徑皆為 `redirect` function，導向對應 hub 路徑並帶正確 `?tab=` query（見最終報告貼出的路由表）；未實際瀏覽器導航驗證（SPA client routing 無法用 curl 驗證，已於任務要求中說明改採靜態核對）
- [x] 5.4 `CarbonPriceSettingsView.vue` 業務邏輯（`carbonPriceApi.get()`/`update()`）搬移前後完全未變動，僅剝除 page-header 外層，已確認可作為 tab 面板正常掛載；未實際手動走一次 CRUD（無瀏覽器互動環境）
- [x] 5.5 5 個 hub 路由 `meta.roles` 已核對：`settings`（一般設定）/`settings-access-control`/`classification-scoring`/`customers` 為 `['admin']`；`market-rules-hub` 因舊 `country-risk` 路由原開放 `sustain`，設為 `['admin','sustain']` 以維持原行為，路由守衛邏輯（`router/index.ts` `beforeEach`）本身未變動，行為與整併前一致
