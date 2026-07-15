## Why

現有儀表板對所有角色顯示相同內容（供應商數量、待審問卷、逾期 CAP、極高風險），無法反映不同部門的每日工作重點。永續部門、採購部門、出口合規部門每天開啟儀表板，需要立即知道「今天要處理什麼」，但目前的設計讓使用者必須自行到各功能頁查找緊急事項。

## What Changes

- **角色感知 Widget 配置**：依登入角色（sustain / buyer / comply / admin）動態組裝儀表板，呈現不同的 Widget 組合
- **新增「最近供應商動態」Widget**：取代靜態的「最近供應商」列表，改為顯示過去 7 天有狀態變更、SAQ 提交、CAP 更新或文件到期的供應商
- **新增「合規文件 7 天到期預警」Widget**：時間軸呈現 7 天內到期的供應商文件，依緊急度排序
- **新增「商品合規完整風險」Widget**：整合 CBAM、EUDR、UFLPA 等多維法規風險，以申報風險金額（碳成本內部定價）呈現 CBAM 曝險
- **新增「系統碳價假設」設定頁**：供 admin 設定內部碳成本定價，作為 CBAM 風險金額計算基準
- **新增 Dashboard API endpoints**：`/api/v1/dashboard/summary`、`/api/v1/dashboard/recent-activity`、`/api/v1/dashboard/expiring-docs`、`/api/v1/dashboard/compliance-risk`
- **新增「ESG 分數分布」Widget**：顯示當季 SAQ 專案的 E/S/G 三維度平均分（sustain 角色）

## Capabilities

### New Capabilities

- `dashboard-role-widgets`: 角色感知的儀表板 Widget 系統，包含今日行動區、最近動態、到期預警、合規風險彙總
- `carbon-price-settings`: 系統碳價假設管理頁面，支援碳成本內部定價設定，供 CBAM 風險金額計算使用

### Modified Capabilities

（無現有 spec 需修改）

## Impact

**前端**
- `esgchain-web/src/views/dashboard/DashboardView.vue`：重構為角色感知的 Widget 組裝邏輯
- 新增 `esgchain-web/src/components/dashboard/` 目錄，包含各 Widget 元件
- 新增 `esgchain-web/src/api/modules/dashboard.ts`：Dashboard 專用 API 模組
- 新增 `esgchain-web/src/views/settings/CarbonPriceSettingsView.vue`
- `AppSidebar.vue`、`router/index.ts`：新增碳價設定路由

**後端（esgchain-api）**
- 新增 `DashboardController.php`，包含 4 個 endpoint
- 新增 `DashboardService.php`，處理角色感知的數據聚合
- 新增 `SystemSettingController.php` 或擴充現有 Settings，管理碳價假設
- `routes/api.php`：新增 dashboard 路由群組

**資料庫**
- 需確認 `system_settings`（或等效）表已存在，可儲存 `carbon_price_eur` 等系統設定值
