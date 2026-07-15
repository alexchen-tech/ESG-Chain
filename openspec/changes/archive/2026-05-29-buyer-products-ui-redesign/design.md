## Context

BuyerProductsView 目前有兩個並列的展開入口：

1. 點產品主列 → 展開 AVL 供應商 panel（`expandedId`）
2. 點「BOM 明細（N）」按鈕 → 展開 BOM panel（`bomPanelOpen`）

架構重構後（bom-line-driven-compliance），合規計算路徑已完全從 BomLine → BomLineSupplier，AVL 已降格為純認可清單（不驅動合規）。UI 的展開結構尚未反映此語意，且 BomLineSupplier 目前沒有管理介面（只能透過 ERP 匯入時帶入，或改 seeder）。

## Goals / Non-Goals

**Goals:**
- 統一展開入口：點主列 → 直接開 BOM Panel
- BOM 表格每列支援行內展開，顯示並管理 BomLineSupplier
- AVL 管理移至 BOM Panel 底部，角色明確降格為候選池
- 新增後端 BomLineSupplierController + 路由
- 前端 API 模組新增 BomLineSupplier CRUD 函數

**Non-Goals:**
- 不改動合規計算邏輯（已完成）
- 不改動 MaterialComplianceView 或 SupplierComplianceDetailView
- 不引入 Composition API（保持 Options API）

## Decisions

### D1：展開狀態合併
**決策**：移除 `expandedId`，以 `bomPanelOpen` 為唯一展開狀態。點主列 toggle BOM panel。

**理由**：消除雙狀態競爭（使用者困惑），且 BOM 是主要操作對象。

### D2：BomLineSupplier 行內展開
**決策**：BOM 表格每列右側加「供應商 N」badge，點擊展開 sub-row（同列下方），顯示供應商清單 + 新增 / 移除按鈕。

**理由**：避免彈窗疊加，行內操作符合現有 BOM 表格的編輯風格（已有 inline edit）。Sub-row 技術上用 `expandedBomLineId` 狀態控制，搭配 `<tr>` collapse。

**替代方案考慮**：彈窗 modal → 但現有頁面已有兩個 modal（新增產品、新增供應商），避免 modal 地獄。

### D3：AVL 區塊位置
**決策**：AVL 移至 BOM Panel 底部，用分隔線隔開，標題「已認可供應商（AVL）」，並加說明文字「AVL 廠商需在 BOM 明細中指定為供應商，才會納入合規計算」。

**理由**：保留 AVL 管理功能（採購商仍需維護）但降低視覺權重，並用說明文字引導使用者理解語意。

### D4：新後端 BomLineSupplierController
**決策**：新增 `store` + `destroy` 兩個 action，掛在 `/api/v1/products/{buyerProduct}/bom-lines/{bomLine}/suppliers` 路由下。

**理由**：符合現有 nested resource 路由慣例（BuyerProduct → ProductBomLine → BomLineSupplier）。不需要 `index`（隨 BOM 明細一起 load）也不需要 `update`（改供應商等於刪舊建新）。

## Risks / Trade-offs

- **[展開狀態改變]** 移除 `expandedId` 後，原本點主列展開 AVL 的行為消失。使用者需重新學習。→ 緩解：點主列就開 BOM，比原本更直覺，AVL 仍在同一展開內可見。
- **[Sub-row 渲染複雜度]** BOM 表格 `<tr>` 插入 sub-row 需要 `<template>` 配合 `v-if`，colspan 計算需對應列數。→ 緩解：現有表格已有 inline edit row，模式一致。
- **[API 呼叫增加]** BomLineSupplier CRUD 新增 API 往返。→ 接受：操作頻率低（設定型操作）。

## Migration Plan

1. 後端：新增 `BomLineSupplierController` + 路由，無資料遷移
2. 前端：修改 `BuyerProductsView.vue`（單一檔案），docker cp 到容器
3. 無需 DB migration、無 breaking API 變更
