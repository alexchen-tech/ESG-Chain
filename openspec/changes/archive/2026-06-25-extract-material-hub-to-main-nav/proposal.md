## Why

物料主檔是 BOM 填寫、PCF 計算、供應商合規的基礎資料層，但目前埋在「系統設定 > 採購物料設定」（admin-only），導致以下問題：

1. **角色存取錯位**：`buyer`、`sustain`、`comply` 角色需要查閱物料資料（例如確認 BOM 中物料群組是否正確、查看碳排情況），卻完全無法進入這個頁面。
2. **資訊架構語意錯誤**：「設定」應放靜態配置（框架、規則、帳號），物料主檔是日常營運資料，與設定類頁面混在一起定位模糊。
3. **物料群組的合規文件需求（EUDR / UFLPA 等）** 設定介面藏在設定區，`comply` 角色無法自行維護。
4. **合規看板中的「合規矩陣」Tab**（物料群組 × 文件類型）與物料主檔在語意上是同一個情境，卻分散在不同路徑。

## What Changes

新增頂層導覽項目「**物料管理**」，提取並整合現有分散功能：

- **新路由結構**
  - `/materials` → 重定向至 `/materials/items`（或顯示 Hub 導覽頁）
  - `/materials/items` → 物料主檔（現：`/settings/material-settings` Tab1 MaterialItemsView）
  - `/materials/groups` → 物料群組（現：`/settings/material-settings` Tab2 MaterialGroupsView）
  - `/materials/compliance` → 物料合規矩陣（現：`/compliance` 中的矩陣 Tab 拆出）

- **角色調整**
  - 物料主檔、物料群組：`admin` 可讀寫；`buyer / sustain / comply` 唯讀
  - 物料合規矩陣：`admin / buyer / sustain / comply` 均可讀

- **側邊欄調整**
  - 新增「物料管理」頂層分組，位置在「供應商管理」之後
  - 「系統設定」中的「採購物料設定」項目移除，統一由新路由入口管理

- **MaterialSettingsView.vue 重構**
  - 現有的 Tab 制 Hub（`/settings/material-settings`）拆成兩個獨立頁面，分別由 MaterialItemsView 和 MaterialGroupsView 承接（這兩個 View 已存在於 codebase，目前未掛載路由）
  - 原 `/settings/material-settings` 路由加 redirect 到 `/materials/items`

- **MaterialComplianceView.vue 調整**
  - 「矩陣視角」Tab 保留在 `/compliance`（合規看板）供合規角色快速存取
  - `/materials/compliance` 作為獨立頁面，提供更完整的物料合規矩陣視圖（可同時顯示物料群組設定入口）

## Capabilities

### New Capabilities

- `material-hub`（新）：物料管理頂層入口，整合物料主檔、群組、合規矩陣於同一功能區

### Modified Capabilities

- `material-item-master`：路由遷移至 `/materials/items`，角色擴展至 buyer / sustain / comply（唯讀）
- `erp-sync-gateway`：ERP 同步物料主檔的路徑語意不變，僅 UI 入口改變
- navigation（AppSidebar）：新增物料管理分組

## Impact

- **前端**：`AppSidebar.vue`、`router/index.ts`、`MaterialItemsView.vue`、`MaterialGroupsView.vue`（掛載路由）、`MaterialSettingsView.vue`（廢棄或 redirect）
- **後端**：無需異動，API 路徑不變
- **角色權限**：`buyer / sustain / comply` 首次可讀取物料主檔資料，需確認後端對應 API 是否有角色檢查（目前 `/api/v1/materials` 應已允許這些角色）
