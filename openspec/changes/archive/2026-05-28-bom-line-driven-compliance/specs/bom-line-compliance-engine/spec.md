## ADDED Requirements

### Requirement: 合規計算以 BomLine 為唯一驅動來源
系統 SHALL 廢棄 ProductSupplier 路徑的合規計算。所有合規評估 MUST 從 `product_bom_lines` 出發，對每條 BomLine 的所有 BomLineSupplier（primary + alternate）逐一評估文件狀態。

#### Scenario: BomLine 有效且供應商文件齊全
- **WHEN** 合規引擎處理某產品
- **THEN** 對該產品每條 BomLine，取得 `material_group.applicable_regulations`，對每個 BomLineSupplier 查詢對應文件，全部有效則該 (BomLine, Supplier) 組合狀態為 `compliant`

#### Scenario: BomLine 無 MaterialGroup 時跳過
- **WHEN** 某 BomLine 的 `material_group_id` 為 null
- **THEN** 該 BomLine 在合規評估中被跳過（不產生 compliant 也不產生 violation）

#### Scenario: BomLine 無供應商時標記為缺漏
- **WHEN** 某 BomLine 的 `bom_line_suppliers` 為空（無任何供應商）
- **THEN** 該 BomLine 在合規評估中產生 `no_supplier` 警告狀態

### Requirement: 合規狀態聚合至產品與供應商層級
系統 SHALL 從 (BomLine, Supplier) 的合規評估結果向上聚合：
- **供應商層級**：某供應商在一個產品中所有 BomLine 的最差狀態
- **產品層級**：產品所有 BomLine 所有 BomLineSupplier 的最差狀態

狀態優先級（由高至低）：`expired` > `expiring_soon` > `pending` > `no_supplier` > `compliant`

#### Scenario: 部分 BomLine 文件過期
- **WHEN** 產品 A 有 3 條 BomLine，其中 1 條有供應商文件已過期
- **THEN** 產品 A 的整體合規狀態為 `expired`

#### Scenario: 替代供應商文件缺失不影響產品合規
- **WHEN** primary 供應商文件有效，alternate 供應商文件缺失
- **THEN** 替代供應商標記為 `pending`，但不拉低整體產品狀態（替代供應商僅作參考，不強制要求）

### Requirement: `syncApplicableRegulations` 從 BomLine 驅動
系統 SHALL 重寫供應商的 `applicable_regulations` 同步邏輯：從該供應商參與的所有 `bom_line_suppliers` JOIN 對應 `BomLine.materialGroup.applicable_regulations`，取 UNION 後更新 `suppliers.applicable_regulations`。

#### Scenario: 供應商參與多條不同物料群組的 BomLine
- **WHEN** 供應商 A 同時是 棉紡原料（UFLPA） 和 染料化學品（SDS） 兩條 BomLine 的供應商
- **THEN** `syncApplicableRegulations` 執行後，供應商 A 的 `applicable_regulations` = `['UFLPA_DECLARATION', 'SDS']`

#### Scenario: 供應商不再參與任何 BomLine
- **WHEN** 供應商 B 的所有 BomLineSupplier 記錄被刪除
- **THEN** `syncApplicableRegulations` 執行後，供應商 B 的 `applicable_regulations` = `[]`
