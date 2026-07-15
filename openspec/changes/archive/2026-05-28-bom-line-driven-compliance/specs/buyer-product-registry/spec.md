## MODIFIED Requirements

### Requirement: ProductSupplier 為純 AVL，不承擔合規語義
`buyer_product_suppliers` 表 SHALL 移除 `material_group_id` 和 `material_group_source` 欄位。ProductSupplier 的職責僅為記錄「此產品的採購商認可哪些供應商」，不再參與合規計算。

#### Scenario: ProductSupplier 清單不影響合規評估
- **WHEN** 合規引擎計算產品合規狀態
- **THEN** 計算過程不查詢 `buyer_product_suppliers` 表；所有合規評估從 `product_bom_lines` + `bom_line_suppliers` 出發

#### Scenario: 供應商在 ProductSupplier 中但不在任何 BomLine
- **WHEN** 供應商 A 列在某產品的 ProductSupplier 清單，但未出現在該產品任何 BomLine 的 `bom_line_suppliers` 中
- **THEN** 供應商 A 不產生任何合規評估結果（僅作為認可供應商存在，不觸發合規義務）

## REMOVED Requirements

### Requirement: ProductSupplier.material_group_id 驅動合規
**Reason**: 物料群組語義應由 BomLine 承載，ProductSupplier 對同一供應商的物料群組標記在跨產品使用時語義模糊（同一染整廠為不同產品標記不同物料群組）。
**Migration**: DROP COLUMN `material_group_id` 和 `material_group_source` from `buyer_product_suppliers`。現有資料棄置（不遷移，因合規計算邏輯已切換至 BomLine 路徑）。
