## ADDED Requirements

### Requirement: BomLine 類型區分（material vs service）
每條 `product_bom_lines` 記錄 SHALL 包含 `bom_line_type` 欄位，值為 `material`（原物料採購行）或 `service`（加工服務行）。預設值為 `material`。

#### Scenario: 預設為 material
- **WHEN** 建立新 BomLine 且未指定 `bom_line_type`
- **THEN** `bom_line_type` 自動設為 `material`

#### Scenario: 標記服務行
- **WHEN** 使用者將某 BomLine 的 `bom_line_type` 設為 `service`
- **THEN** 該 BomLine 的合規需求由其 `material_group`（服務類型物料群組）的 `applicable_regulations` 決定

### Requirement: bom_line_type 驅動合規模板語義
`bom_line_type=material` 的 BomLine 代表實體原料採購（如棉紗、染料），其合規文件需求以物料安全為主。`bom_line_type=service` 的 BomLine 代表加工服務（如染整、縫製），其合規文件需求以勞工和製程管理為主。

#### Scenario: 原物料行合規評估
- **WHEN** 合規引擎處理 `bom_line_type=material` 的 BomLine
- **THEN** 依其 `material_group.applicable_regulations` 評估所有 BomLineSupplier 的文件

#### Scenario: 服務行合規評估
- **WHEN** 合規引擎處理 `bom_line_type=service` 的 BomLine
- **THEN** 依其服務類型 `material_group.applicable_regulations` 評估所有 BomLineSupplier 的文件（如成衣縫製服務 → UFLPA_DECLARATION）
