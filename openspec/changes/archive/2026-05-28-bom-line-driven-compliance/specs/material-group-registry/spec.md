## MODIFIED Requirements

### Requirement: MaterialGroup 支援服務類型
`material_groups` 表 SHALL 新增 `group_type` 欄位（ENUM: `material` | `service`，預設 `material`）。系統 SHALL 預建以下服務類型物料群組：
- 成衣縫製服務（service）→ `applicable_regulations: ['UFLPA_DECLARATION']`
- 染整加工服務（service）→ `applicable_regulations: ['SDS']`
- 木製包材服務（service）→ `applicable_regulations: ['EUDR_DDS']`

#### Scenario: 現有物料群組維持 material 類型
- **WHEN** migration 執行後
- **THEN** 現有 `material_groups` 記錄的 `group_type` 為 `material`

#### Scenario: 服務類型群組可被 BomLine 使用
- **WHEN** 一條 `bom_line_type=service` 的 BomLine 關聯至「成衣縫製服務」物料群組
- **THEN** 合規引擎依 `applicable_regulations: ['UFLPA_DECLARATION']` 評估其所有 BomLineSupplier
