## Why

「系統設定」側邊欄群組目前把 6 個性質不同的頁面拉平陳列，但實際盤點各頁內部 tab 內容後發現分組跟內容本身脫節：`CarbonPriceSettingsView.vue` 完全沒有路由/選單入口（孤兒元件）；SASB 相關設定分散在兩個不同 hub 頁（一般設定的「SASB 產業分類」與分類與計分管理的「SASB 必調題目」）；「目標市場」（市場代碼定義）跟依賴它的「市場合規規則」隔了一整層選單；「國家風險評等」在一般設定的 TABS 陣列裡假裝是 tab，實際卻是連結跳轉到獨立路由。這次要依內部 tab 內容重新設計分組，讓相關概念收斂在同一個 hub 頁，同時讓孤兒元件重新可被存取。

## What Changes

- 「一般設定」瘦身為只保留組織架構、供應商分組兩個 tab
- 新增「存取控制」hub 頁，把「使用者管理」（`UsersView.vue`）與「角色管理」（`RolesView.vue`）包成同一頁的兩個 tab，取代原本兩個獨立側邊欄項目
- 「分類與計分管理」hub 頁新增兩個 tab：「SASB 產業分類」（從一般設定搬入）與「碳價設定」（`CarbonPriceSettingsView.vue` 首次掛上路由/選單，原本完全無法存取），連同既有 4 個 tab 共 6 個
- 新增「市場與合規規則」hub 頁，把「目標市場」（從一般設定搬入）、「市場合規規則」（`MarketComplianceRulesView.vue`，原獨立側邊欄項目）、「國家風險評等」（`CountryRiskView.vue`，從假 tab 改為真正的頁內 tab）整併成三個 tab
- 「客戶主檔」維持獨立不變
- 側邊欄選單（`AppSidebar.vue`）「系統設定」群組項目數從 6 個減為 5 個
- 舊網址（`/settings/users`、`/settings/roles`、`/settings/country-risk`、`/settings/market-rules`）保留為導向新 hub 頁對應 tab 的相容路由，避免既有收藏連結或書籤失效
- **BREAKING（UI 層）**：原本直接連到「使用者管理」「角色管理」「市場合規規則」「國家風險評等」的側邊欄項目消失，改為透過 hub 頁的 tab 切換到達；使用者既有操作習慣（點選單直達）改為「點選單到 hub 頁 → 點 tab」兩步

## Capabilities

### New Capabilities
- `settings-hub-navigation`：系統設定內 hub 頁的 tab 分組規則、URL 相容導向、與各 tab 內元件既有權限檢查在新外殼下仍正確套用的保證

### Modified Capabilities
（無既有 spec capability 的需求內容變更，純屬前端路由/選單重組，不涉及後端行為或既有已封存 capability 的需求異動）

## Impact

- 前端：`AppSidebar.vue`（選單項目重組）、`router/index.ts`（新增 hub 路由、舊路由改為相容導向）
- 新增 3 個 hub 外殼元件（存取控制、分類與計分管理擴充、市場與合規規則），沿用 `components.css` 既有 `.detail-tabs`/`.tab-panel-wrap` 樣式
- 既有元件 `UsersView.vue`、`RolesView.vue`、`MarketComplianceRulesView.vue`、`CountryRiskView.vue`、`CarbonPriceSettingsView.vue`、`ClassificationScoringHubView.vue`、`SettingsView.vue` 皆需調整（部分是搬移 tab 內容、部分是被包進新外殼）
- 不涉及後端變更；需逐一確認搬動後的 tab 元件原本依賴的 `meta.permission`/`meta.roles` 路由守衛與元件內權限檢查仍正確生效
