## Context

現況盤點（見 proposal.md Why）：`SettingsView.vue` 內建一個 `TABS` 陣列驅動的 tab 外殼模式，其中「國家風險評等」項目帶 `link` 屬性、點擊時 `router.push` 到獨立路由而非切換 `activeTab`——這是既有程式碼裡唯一一個「偽 tab」的先例。`ClassificationScoringHubView.vue` 是另一個同構的 tab 外殼，動態 `v-show` 切換四個子元件。這次新增的「存取控制」「市場與合規規則」兩個 hub 頁，以及「分類與計分管理」的擴充，都要沿用同一種外殼模式，不新發明其他 tab 實作方式。

被搬動的元件本身（`UsersView.vue`、`RolesView.vue`、`MarketComplianceRulesView.vue`、`CountryRiskView.vue`、`CarbonPriceSettingsView.vue`）目前都是「整頁元件」（`<div class="page-container">` 起手，帶自己的 `page-header`），而非設計給嵌入 tab 內的「面板元件」（`ClassificationScoringHubView.vue` 底下四個子元件是面板風格，無自己的 page-header）。這是實作時最主要的技術落差。

## Goals / Non-Goals

**Goals:**
- side bar「系統設定」5 個子項目，對應到 5 個獨立路由（一般設定、存取控制、分類與計分管理、市場與合規規則、客戶主檔）
- 每個 hub 頁的 tab 切換沿用 `SettingsView.vue`/`ClassificationScoringHubView.vue` 既有的 `activeTab` + `v-show` 模式與 `components.css` 共用樣式
- 舊路由（`/settings/users`、`/settings/roles`、`/settings/country-risk`、`/settings/market-rules`）保留、自動導向新 hub 頁並帶正確的 tab 參數
- 被搬動元件原本綁在路由上的 `meta.permission`/`meta.roles` 守衛邏輯，在新外殼下改綁在 hub 路由本身，但個別 tab 若有比 hub 頁更嚴格的權限（如問卷範本相關 tab 需要 `settings.questionnaire-templates.create`），仍需在該 tab 顯示邏輯內保留判斷，不因為進了 hub 頁就對所有子頁一視同仁放行

**Non-Goals:**
- 不改動 `UsersView.vue`/`RolesView.vue`/`MarketComplianceRulesView.vue`/`CountryRiskView.vue`/`CarbonPriceSettingsView.vue` 元件內部的業務邏輯與 API 呼叫，只處理外殼與路由/選單
- 不做「記住使用者上次停留的 tab」這類個人化狀態
- 不改動後端任何路由、權限、Controller
- 不處理其他側邊欄群組（供應商管理、物料管理等）的分組，範圍僅限「系統設定」群組

## Decisions

### Decision 1：被搬動的整頁元件改用「行內嵌入」而非 iframe 或動態 component 特殊處理，並剝除各自的 page-header
`UsersView.vue`/`RolesView.vue`/`MarketComplianceRulesView.vue`/`CountryRiskView.vue`/`CarbonPriceSettingsView.vue` 各自的最外層 `<div class="page-container">` 與 `<div class="page-header"><h1 class="page-title">...` 移除（改由 hub 頁統一提供一個 `page-header`），元件其餘內容原封不動地作為 hub 頁裡的一個 tab-panel 直接引用（`<UsersView v-show="activeTab==='users'" />` 這種寫法）。
- 替代方案（用 `<router-view>` 巢狀路由 + 各自保留 page-header）：否決，因為那樣每個 tab 切換還是會各自渲染一個 page-header，畫面上會有「hub 標題 + tab 標題」兩層標題重複，且巢狀路由在瀏覽器紀錄/keep-alive 行為上比 v-show 平坦切換複雜，這次沒有非用不可的理由。

### Decision 2：hub 頁路由 path 用 kebab-case 新路徑，舊路徑保留為 `redirect` 並帶 query 參數指定 tab
新路由：
- `/settings/access-control`（存取控制，預設 tab=`users`）
- `/settings/classification-scoring`（沿用既有路徑，不變，只是內容變多）
- `/settings/market-rules-hub`（市場與合規規則——沿用「市場合規規則」原本語意最接近的路徑當作 hub 路徑，避免跟舊的 `/settings/market-rules` 純規則頁混淆）

舊路徑導向規則（`router/index.ts` 用 `redirect` function）：
- `/settings/users` → `/settings/access-control?tab=users`
- `/settings/roles` → `/settings/access-control?tab=roles`
- `/settings/country-risk` → `/settings/market-rules-hub?tab=country-risk`
- `/settings/market-rules` → `/settings/market-rules-hub?tab=rules`

Hub 頁 `mounted()` 讀 `this.$route.query.tab` 決定初始 `activeTab`（沒有帶 query 時預設第一個 tab），比照 `TradeGoodsView.vue`/`SuppliersView.vue` 既有「讀 query 決定初始篩選」的寫法慣例，不新發明模式。
- 替代方案（用 path param 如 `/settings/access-control/:tab`）：否決，query 參數寫法已經是專案既有慣例（dashboard 深連結、供應商 tier 篩選等都用 query），維持一致風格比路徑參數更省事。

### Decision 3：路由層級權限守衛採「聯集」，tab 內部再做各自的顯示判斷
Hub 路由本身的 `meta.permission`/`meta.roles` 設為「該 hub 底下所有 tab 所需權限的聯集」（例如「分類與計分管理」hub 因為新增了需要 `settings.carbon-price.update`/`settings.country-risk`... 等等的 tab，hub 路由本身維持 `roles: ['admin']` 不變，因為這些 tab 現況全部都是 admin-only）。個別 tab 若原本在獨立路由時有更細的權限判斷（例如問卷範本相關功能需要 `settings.questionnaire-templates.create`），這類判斷目前是在元件內部（v-if 之類）處理、不是路由層級判斷，本次維持不動，因為這次盤點的 5 個 hub 頁全部路由層級都只有 `roles: ['admin']`，沒有 hub 內 tab 需要比 admin 更嚴格的路由層級权限守衛。
- 替代方案（每個 tab 各自的路由 meta 精細控制）：否決，這次盤點的 5 個 hub 頁底下的 tab 全部要求 admin，沒有實際差異化需求，過度設計。

### Decision 4：AppSidebar.vue 選單項目直接替換，不做「舊選單淡出新選單淡入」之類的過渡設計
選單項目從 6 個變 5 個，直接改陣列內容（`使用者管理`、`角色管理`、`市場合規規則` 三個項目移除，新增「存取控制」「市場與合規規則」兩個項目），沒有版本切換開關或 feature flag。
- 替代方案（feature flag 讓使用者選擇新舊選單）：否決，CLAUDE.md 明確要求「不使用 feature flag 或向後相容 shim，直接改」，且這是內部管理後台、只有 admin 使用，不需要漸進遷移。

## Risks / Trade-offs

- [風險] 被搬動元件的 `<style scoped>` 若有針對自己 page-header 的樣式（現在被移除），可能有殘留失效的 CSS 規則 → 緩解：實作時逐一檢查每個被搬動元件的 `<style scoped>`，移除 page-header 相關但不再有對應 DOM 的樣式
- [風險] 舊路由的 redirect 若忘記處理某個既有內部連結（例如其他頁面內寫死 `router.push('/settings/roles')` 的地方），會依賴 redirect 生效，若 redirect 規則寫錯會導致連結失效 → 緩解：實作前先 grep 全專案找出所有寫死引用這 4 個舊路徑字串的地方，確認 redirect 涵蓋所有情境
- [風險] Hub 頁一次要嵌入到 2-6 個原本獨立的整頁元件，若元件間有同名的 CSS class 或同名的 data/method（尤其 `isSubmitting`/`loading` 這類常見命名），v-show 切換雖然不會有雙向資料污染（各元件各自 scope），但若 hub 外殼本身也定義了同名 top-level data，可能誤蓋 → 緩解：hub 外殼元件保持極簡，只管 `activeTab` 與 tab 清單本身，不定義任何業務相關 data/method，業務邏輯完全留在各自被嵌入的子元件內

## Migration Plan

1. 逐一調整 5 個被嵌入元件：移除各自 page-header，確認 `<style scoped>` 無殘留
2. 新建 3 個 hub 外殼元件（AccessControlHubView.vue 存取控制、擴充既有 ClassificationScoringHubView.vue、新建 MarketRulesHubView.vue）
3. `router/index.ts`：新增 3 個 hub 路由，4 個舊路由改 redirect
4. `AppSidebar.vue`：選單項目從 6 個改為 5 個
5. 前端部署後，逐一用瀏覽器/curl 確認舊書籤網址正確導向且 tab 正確選中，5 個 hub 頁全部 tab 可正常操作（沿用個別元件既有的 CRUD 功能不變）
6. `vue-tsc --noEmit` 確認零錯誤
