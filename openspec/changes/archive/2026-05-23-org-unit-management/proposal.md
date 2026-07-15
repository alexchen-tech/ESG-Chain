## Why

ESG·Chain 目前是 flat 單一命名空間，所有使用者、問卷專案、供應商都混在一起。當採購商為集團企業（多子公司、多事業部）時，無法區分資料歸屬、無法按組織單位篩選報告，管理員也無法依 OU 指派使用者職責。

## What Changes

- 新增 `organization_units` 資料表，支援 4 層樹狀結構（公司 → 子公司/事業部 → 部門 → 分支）
- `users` 表新增 `organization_unit_id` 外鍵
- 系統設定頁新增「組織架構」Tab（置於第一個，原有三個 Tab 後移）
- 提供 CRUD API：建立、讀取（含樹狀）、更新、刪除組織單位
- 新增 Seeder 建立預設根節點（公司層）

## Capabilities

### New Capabilities
- `org-unit-management`: 組織單位 CRUD，樹狀結構管理（4 層，自我關聯），含類型標籤（headquarters / subsidiary / business_unit / department / branch）
- `org-unit-settings-tab`: 系統設定頁「組織架構」Tab，含樹狀展示、新增/編輯/刪除互動

### Modified Capabilities
- `user-management`: User model 新增 organization_unit_id 欄位，Settings 使用者清單顯示所屬 OU

## Impact

- **後端**：新 Model `OrganizationUnit`、Controller、Service、Migration、Seeder
- **前端**：`SettingsView.vue` 新增 Tab，新增 `api/modules/org-units.ts`
- **資料庫**：`organization_units` 新表 + `users.organization_unit_id` 欄位
- **現有資料**：users 的 `organization_unit_id` 預設 null，無破壞性變更
