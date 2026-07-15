## Context

現有程式碼狀況：
- `MaterialItemsView.vue`、`MaterialGroupsView.vue` 已完整存在於 `esgchain-web/src/views/settings/`，但**未掛載任何路由**
- `MaterialSettingsView.vue` 是一個 Tab Hub，目前掛在 `/settings/material-settings`（admin only）
- `AppSidebar.vue` 的 `ALL_MENU` 中，物料設定在 `settings-group` 子項目 `{ path: '/settings/material-settings', label: '採購物料設定', roles: ['admin'] }`
- router/index.ts 中僅有 `/settings/material-settings` 一個物料相關路由

## Goals / Non-Goals

**Goals:**
- 新增「物料管理」頂層選單，子項目：物料主檔、物料群組
- 掛載現有 `MaterialItemsView` 至 `/materials/items`、`MaterialGroupsView` 至 `/materials/groups`
- 擴展路由 meta roles 讓 `buyer / sustain / comply` 可讀取（頁面本身已有唯讀展示，無需另外改 View 邏輯）
- 更新 `MaterialItemsView` 和 `MaterialGroupsView` 的麵包屑，從「系統設定 >」改為「物料管理 >」
- 保留 `/settings/material-settings` 路由，改為 redirect 至 `/materials/items`（避免既有書籤失效）
- 系統設定側邊欄移除「採購物料設定」子項目

**Non-Goals:**
- 不拆分 MaterialComplianceView 中的矩陣 Tab（合規看板保持不動，避免牽動較大）
- 不新增唯讀 guard（`buyer` 等角色進入後本來就無法觸發寫入 API，後端有 role 檢查）
- 不調整 MaterialSettingsView.vue 本身（改為純 redirect 即可廢棄）

## Decisions

1. **新路由路徑**：`/materials/items` 和 `/materials/groups`，頂層前綴 `/materials` 語意清晰，未來可擴展 `/materials/compliance`。

2. **不新增 `/materials` Hub 頁面**：直接讓 `/materials` redirect 至 `/materials/items`，減少不必要的中間頁面，保持流暢。

3. **角色**：
   - `/materials/items`：`['admin','buyer','sustain','comply','analyst']` — 物料主檔是各角色都需要查閱的基礎資料
   - `/materials/groups`：`['admin','buyer','sustain','comply']` — 群組設定，analyst 唯讀需求較低，與其他設定類頁面一致

4. **側邊欄新分組位置**：插在「供應商管理」之後、「永續倡議問卷」之前，因為物料管理在業務上緊接供應商（supplier → material → BOM → PCF 這條鏈）。

5. **麵包屑更新**：
   - `MaterialItemsView`：`系統設定 > 物料主檔` → `物料管理 > 物料主檔`（breadcrumb-link 改為導向 `/materials/items`）
   - `MaterialGroupsView`：`系統設定 > 物料群組` → `物料管理 > 物料群組`（純文字 breadcrumb 不需按鈕）

## Migration Plan

1. `router/index.ts`：
   - 新增 `/materials`（redirect `/materials/items`）
   - 新增 `/materials/items`（MaterialItemsView，roles 擴展）
   - 新增 `/materials/groups`（MaterialGroupsView，roles 擴展）
   - 修改 `/settings/material-settings`：`component` 改為 `redirect: '/materials/items'`

2. `AppSidebar.vue`：
   - 新增 `material-group` 分組，位於 suppliers 之後
   - 系統設定子項目移除 `material-settings`

3. `MaterialItemsView.vue`：更新麵包屑
4. `MaterialGroupsView.vue`：更新麵包屑
