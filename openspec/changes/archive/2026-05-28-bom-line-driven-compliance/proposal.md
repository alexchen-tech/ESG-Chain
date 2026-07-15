## Why

現行合規計算採用雙路徑（BomLine 路徑 + ProductSupplier 路徑），造成靜默合規缺口：BomLine 指定的供應商若未列在 ProductSupplier 清單中就永遠不被評估，且當同一供應商在兩路徑中的物料群組不一致時，合規需求會被錯誤覆蓋。PLM 匯入流程確立了 BomLine 才是物料-供應商綁定關係的唯一事實來源，需要以此重構合規計算架構。

## What Changes

- **BREAKING** 移除雙路徑合規計算，改為以 BomLine 為唯一驅動來源
- **BREAKING** 新增 `bom_line_suppliers` 表，支援每條 BomLine 指定主要供應商 + 替代供應商（AVL 概念），取代 BomLine 中的單一 `designated_supplier_id`
- **BREAKING** 移除 `buyer_product_suppliers.material_group_id` — ProductSupplier 退化為純 AVL（產品層級已認可供應商清單），不再攜帶物料群組語義
- 新增 `product_bom_lines.bom_line_type` 欄位（`material` | `service`），區分原物料採購行與加工服務行
- 擴充 `material_groups` 支援服務類型（染整加工服務 → SDS、成衣縫製服務 → UFLPA 勞工追溯、木製包材 → EUDR）
- 重寫 `SupplierComplianceStatusService`：改為迭代 BomLine → 取得每條 BomLine 所有供應商（主要 + 替代）→ 依 `materialGroup.applicable_regulations` 檢查文件
- 重寫 `syncApplicableRegulations`：從 BomLine.materialGroup 驅動，而非 ProductSupplier.materialGroup
- 所有 BomLine 供應商必須是 Supplier MDM 中已存在的供應商（ERP 驗證）

## Capabilities

### New Capabilities

- `bom-line-supplier-avl`：每條 BomLine 的多供應商管理（主要 + 替代），含 MDM 驗證、來源標記（erp_designated / manual）
- `bom-line-type`：BomLine 類型區分（material vs service），驅動不同合規需求模板
- `bom-line-compliance-engine`：以 BomLine 為單一驅動的合規計算引擎，取代現行雙路徑

### Modified Capabilities

- `product-bom-line`：新增 `bom_line_type` 欄位，移除 `designated_supplier_id`（改由 `bom_line_suppliers` 表管理）
- `buyer-product-registry`：`buyer_product_suppliers.material_group_id` 移除，ProductSupplier 退化為純 AVL
- `supplier-compliance-status`：合規計算邏輯從雙路徑改為 BomLine 單一驅動
- `material-group-registry`：新增服務類型物料群組（染整加工、成衣縫製、木製包材等）
- `supplier-bom-requirement-view`：供應商看到的物料需求來自 BomLineSupplier 而非 ProductSupplier
- `portal-procurement-requirements`：採購需求 API 改從 BomLineSupplier 查詢

## Impact

**資料庫**
- 新增 migration：`create_bom_line_suppliers_table`
- 修改 migration：`alter_product_bom_lines_add_bom_line_type_drop_designated_supplier_id`
- 修改 migration：`alter_buyer_product_suppliers_drop_material_group_id`
- 修改 migration：`alter_material_groups_add_type_column`

**後端（esgchain-api）**
- `ProductBomLine` model：移除 `designated_supplier_id`，新增 `bom_line_type`，新增 `bomLineSuppliers()` relation
- 新增 `BomLineSupplier` model（`bom_line_id`, `supplier_id`, `role: primary|alternate`, `source`）
- `BuyerProductSupplier` model：移除 `material_group_id`
- `SupplierComplianceStatusService`：完整重寫計算邏輯
- `SupplierService::syncApplicableRegulations()`：從 BomLine 驅動

**前端（esgchain-web）**
- `BuyerProductsView.vue`：BOM 表格改為多供應商顯示，移除 ProductSupplier 物料群組欄位
- `MaterialComplianceView.vue`：合規狀態列表改從 BomLine 維度展示
- `SupplierComplianceDetailView.vue`：細節頁顯示各 BomLine 對應的文件需求

**資料遷移**
- 將現有 `product_bom_lines.designated_supplier_id` 遷移至 `bom_line_suppliers`（role: primary）
- 將 `buyer_product_suppliers.material_group_id` 資料棄置（合規計算不再依賴）
