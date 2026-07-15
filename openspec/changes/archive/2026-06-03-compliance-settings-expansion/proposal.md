## Why

物料合規模組的核心設定（物料群組、料號主檔、目標市場、供應商分組文件要求）目前缺乏前端管理介面，管理員只能透過 seeder 建立資料，且 SupplierGroup 未綁定合規文件要求，MaterialItem（料號）概念尚未進入系統。Phase 1 以「純設定層」為邊界，為後續合規缺口計算（Phase 2）和批號追蹤（Phase 3）建立資料地基。

## What Changes

- **供應商分組** 新增 `required_doc_types` JSON 欄位，定義廠商資格層合規文件要求（SMETA、ISO 9001 等），並在設定頁提供多選 UI
- **物料群組** 新增設定頁 Tab，提供 MaterialGroup CRUD（現有只有 seeder，無前端管理）
- **料號主檔** 新增獨立子頁 `/settings/material-items`，提供 MaterialItem CRUD + CSV 批次匯入；BomLine 新增 nullable `material_item_id` FK，有料號時以料號資料為準
- **目標市場** 新增設定頁 Tab，提供 MarketDefinition CRUD，讓管理員自訂市場代碼與名稱（與 ERP 解耦）；後續 Phase 2 再接入 ComplianceFramework 激活邏輯

## Capabilities

### New Capabilities

- `supplier-group-compliance-docs`: SupplierGroup 綁定 required_doc_types，定義廠商資格文件要求
- `material-group-management`: MaterialGroup 設定頁 Tab，支援 CRUD
- `material-item-master`: 料號主檔獨立子頁，MaterialItem CRUD + CSV 匯入，BomLine 加 material_item_id nullable FK
- `market-definition-management`: 目標市場 MarketDefinition 設定頁 Tab，與 ERP 解耦的自訂市場代碼

### Modified Capabilities

（無，Phase 1 不修改現有業務邏輯）

## Impact

**後端**
- `supplier_groups` 表：新增 `required_doc_types` JSON 欄位（migration）
- `material_items` 表：新增（migration + Model + Controller + Route）
- `market_definitions` 表：新增（migration + Model + Controller + Route）
- `product_bom_lines` 表：新增 nullable `material_item_id` FK（migration）
- BomLine 的 API response 新增 `material_item` eager load
- MaterialGroup Controller 現有 API 已存在（`/api/v1/material-groups`），補齊 destroy 端點

**前端**
- `SettingsView.vue`：新增「物料群組」Tab、「目標市場」Tab，擴充「供應商分組」Tab
- 新增 `/settings/material-items` 頁面（MaterialItemsView.vue），link tab 模式
- `compliance.ts`：新增 MaterialItem 相關 API 型別與呼叫
- `settings.ts`：新增 MarketDefinition API

**不影響**
- 現有 BomLine 資料（material_item_id 為 nullable，不需遷移）
- 合規計算邏輯（Phase 2 才接入）
- Portal 供應商入口
