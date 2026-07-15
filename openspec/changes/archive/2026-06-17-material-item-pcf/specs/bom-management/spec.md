## MODIFIED Requirements

### Requirement: BomLine 主供應商 UI 優化

BomLine 供應商 sub-row SHALL 明確顯示主供應商（role=primary），並提供切換主供應商的操作，切換後立即觸發 PCF 重算。

#### Scenario: 主供應商視覺標記

- **WHEN** BomLine 有 role=primary 的 BomLineSupplier
- **THEN** 供應商 sub-row 中該供應商 SHALL 以「主要」角色 badge 顯示，並顯示對應的碳排值（若有）

#### Scenario: 切換主供應商

- **WHEN** 採購商將某 BomLineSupplier 的 role 從 alternate 改為 primary
- **THEN** 系統 SHALL 自動將原 primary 供應商改為 alternate，並觸發 PCF 重算（非同步）

#### Scenario: BomLine 碳排值即時顯示

- **WHEN** BomLine 有 primary 供應商且該供應商有 material_item_emissions 記錄
- **THEN** BomLine 列 SHALL 顯示 emission_per_unit 值（來源標記：🧑 自填 / 🤖 AI估算）

#### Scenario: 多個主供應商防呆

- **WHEN** 使用者嘗試為已有 primary 的 BomLine 再設定另一個 primary
- **THEN** 系統 SHALL 自動降級原 primary 為 alternate，確保每條 BomLine 僅有一個 primary
