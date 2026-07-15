## 1. 後端：系統設定與碳價 API

- [x] 1.1 確認 `system_settings` 表是否存在；若不存在，建立 migration（key VARCHAR(100) PK, value TEXT, updated_by UUID nullable, updated_at timestamp）
- [x] 1.2 建立或更新 `SystemSetting` Eloquent Model，提供 `get(key, default)` / `set(key, value, userId)` 靜態方法
- [x] 1.3 在 `Settings/` Controller 群組新增 `CarbonPriceController`，實作 `GET /api/v1/settings/carbon-price` 與 `PUT /api/v1/settings/carbon-price`
- [x] 1.4 `PUT` 驗證規則：`carbon_price_eur` 為 numeric、min:0.01，admin 角色限定
- [x] 1.5 在 `routes/api.php` 新增碳價路由，套用 `auth:api` + `admin` 角色中介層
- [x] 1.6 Seed 預設碳價值 `carbon_price_eur = 65.00`

## 2. 後端：Dashboard API

- [x] 2.1 建立 `app/Http/Controllers/Api/Dashboard/DashboardController.php`，包含 `summary()`、`recentActivity()`、`expiringDocs()`、`complianceRisk()` 四個 action
- [x] 2.2 建立 `app/Services/Dashboard/DashboardService.php`，實作 `getSummary(User $user)`：依角色回傳對應的行動卡片數字（SAQ 待審、CAP 7 天到期、文件 7 天到期、高風險供應商、待審核供應商、合規問題商品數）
- [x] 2.3 實作 `DashboardService::getRecentActivity()`：查詢過去 7 天的 `supplier_status_histories`、`saqs`（submitted_at）、`caps`（updated_at + status changed）、`supplier_compliance_docs`（expires_at 7 天內 / created_at），合併排序取前 20 筆，回傳統一格式 `{ supplier_id, supplier_name, event_type, event_label, occurred_at, severity }`
- [x] 2.4 實作 `DashboardService::getExpiringDocs()`：查詢 `supplier_compliance_docs` 中 `expires_at BETWEEN now() AND now()+7days`，JOIN suppliers，回傳 `{ supplier_id, supplier_name, doc_type, expires_at, days_remaining }`，依 `expires_at` 升序
- [x] 2.5 實作 `DashboardService::getComplianceRisk()`：查詢 `trade_goods` 中 `is_cbam_applicable=true`，計算 `embedded_emissions × carbon_price_eur`；統計 `upstream_compliance_status IN (expired, missing)` 的商品數；統計 EUDR pending 商品數；回傳彙總結構
- [x] 2.6 在 `routes/api.php` 新增 dashboard 路由群組（`GET /api/v1/dashboard/summary`、`recent-activity`、`expiring-docs`、`compliance-risk`），套用 `auth:api` 中介層

## 3. 前端：Dashboard API 模組

- [x] 3.1 建立 `esgchain-web/src/api/modules/dashboard.ts`，定義所有 interface 與 4 支 API 函數：`summaryApi.get()`、`recentActivityApi.list()`、`expiringDocsApi.list()`、`complianceRiskApi.get()`
- [x] 3.2 建立 `esgchain-web/src/api/modules/carbonPrice.ts`，定義 `carbonPriceApi.get()` 與 `carbonPriceApi.update(value: number)`

## 4. 前端：Dashboard Widget 元件

- [x] 4.1 建立 `src/components/dashboard/DashboardActionCards.vue`（Options API）：接收 `role` prop 與 `summary` data，渲染 3 張卡片；卡片數字 > 0 時顯示紅色；點擊導向對應路由
- [x] 4.2 建立 `src/components/dashboard/DashboardRecentActivity.vue`：接收 `activities` array，依 severity 顯示圖示（●/!/○），顯示事件類型標籤、供應商名稱、發生時間（相對時間，如「2 小時前」）；點擊導向 `/suppliers/{id}`
- [x] 4.3 建立 `src/components/dashboard/DashboardExpiringDocs.vue`：接收 `docs` array，以時間軸樣式顯示，最緊急在上；`days_remaining <= 3` 時顯示紅色警示
- [x] 4.4 建立 `src/components/dashboard/DashboardComplianceRisk.vue`（comply 角色）：顯示 CBAM 商品數、預估申報金額（€）、合規問題商品數、EUDR 未提交數；CBAM 金額以千分位格式顯示
- [x] 4.5 建立 `src/components/dashboard/DashboardEsgScores.vue`（sustain 角色）：顯示 E/S/G 三維度均值，各附進度條（0-100），無資料時顯示空狀態
- [x] 4.6 建立 `src/components/dashboard/DashboardSupplierTier.vue`（buyer 角色）：顯示 Tier 1/2/3 供應商數量分布

## 5. 前端：重構 DashboardView

- [x] 5.1 重構 `DashboardView.vue`：新增 `widgetConfig` computed，依 `authStore.user.role` 回傳 Widget 清單；新增對應 data properties（`summary`、`activities`、`expiringDocs`、`complianceRisk`、`esgScores`）
- [x] 5.2 在 `loadData()` 中依角色選擇性呼叫所需 API（sustain 呼叫 summary + recentActivity + expiringDocs；comply 額外呼叫 complianceRisk；buyer 額外呼叫 supplier tier 資料）
- [x] 5.3 在 template 中依 `widgetConfig` 動態渲染對應 Widget 元件，取代原本的硬編碼佈局
- [x] 5.4 保留原本的供應商狀態分布（admin 角色）；「最近供應商」替換為 `DashboardRecentActivity`

## 6. 前端：碳價設定頁

- [x] 6.1 建立 `src/views/settings/CarbonPriceSettingsView.vue`（Options API）：顯示目前碳價、最後更新時間、更新者；提供編輯表單（數字輸入，min:0.01）；儲存時 disabled 按鈕防重複送出
- [x] 6.2 在 `src/router/index.ts` 新增路由：`{ path: '/settings/carbon-price', name: 'carbon-price-settings', meta: { requiresAuth: true, roles: ['admin'] } }`
- [x] 6.3 在 `AppSidebar.vue` settings 群組新增「碳價假設設定」選項，`roles: ['admin']`

## 7. Docker 同步與驗證

- [x] 7.1 執行 Laravel migration（`system_settings` 表）並 seed 預設碳價
- [x] 7.2 docker cp 後端新增檔案至容器，docker restart esgchain-api，驗證 4 支 dashboard API 回傳正確資料
- [x] 7.3 docker cp 前端新增檔案，touch 觸發 Vite HMR，驗證三個角色（sustain / buyer / comply）登入後看到正確 Widget 組合
- [x] 7.4 驗證碳價設定頁：admin 可讀取與更新，非 admin 無法存取
