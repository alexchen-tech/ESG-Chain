## 1. 路由設定

- [x] 1.1 `router/index.ts`：新增 `/materials`（redirect 至 `/materials/items`）、`/materials/items`（MaterialItemsView，roles: admin/buyer/sustain/comply/analyst）、`/materials/groups`（MaterialGroupsView，roles: admin/buyer/sustain/comply）
- [x] 1.2 `router/index.ts`：將 `/settings/material-settings` 改為 redirect 至 `/materials/items`（保留路由名稱 `material-settings`，component 改為 redirect）

## 2. 側邊欄

- [x] 2.1 `AppSidebar.vue`：在 `ALL_MENU` 中新增 `material-group` 分組，位置在 `suppliers` 項目之後，icon 用 `◧`，label `物料管理`，子項目：`物料主檔`（`/materials/items`，roles: admin/buyer/sustain/comply/analyst）、`物料群組`（`/materials/groups`，roles: admin/buyer/sustain/comply）
- [x] 2.2 `AppSidebar.vue`：`settings-group` 子項目中移除 `material-settings` 這個項目

## 3. 麵包屑更新

- [x] 3.1 `MaterialItemsView.vue`：麵包屑父層文字「系統設定」改為「物料管理」，router.push 目標改為 `/materials/items`
- [x] 3.2 `MaterialGroupsView.vue`：麵包屑父層文字「系統設定」改為「物料管理」

## 4. 驗證

- [x] 4.1 以 `admin` 登入：確認側邊欄出現「物料管理」分組，可進入物料主檔與物料群組頁面，麵包屑正確，系統設定子項目無「採購物料設定」
- [x] 4.2 以 `buyer` 登入：確認可進入 `/materials/items`，麵包屑正確；舊路由 `/settings/material-settings` redirect 至 `/materials/items`
