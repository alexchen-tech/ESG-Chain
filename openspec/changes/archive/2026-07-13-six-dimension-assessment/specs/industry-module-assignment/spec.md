## ADDED Requirements

### Requirement: 產業分類欄位
`suppliers` 表 SHALL 新增 `industry_group` 欄位（enum），可選值為：製造業、勞動密集製造、農林漁業、科技電子、物流倉儲、原物料化工、服務業。此欄位在 supplier onboarding 時由管理員設定，並提供 UI 覆蓋機制。

#### Scenario: 設定產業分類
- **WHEN** 管理員於供應商編輯頁選擇 industry_group
- **THEN** 系統儲存該值，並在下次問卷發送時依此決定加掛模組

#### Scenario: 未設定 industry_group 的預設行為
- **WHEN** 供應商 industry_group 為 null
- **THEN** 問卷只包含 E1/E4 核心題目（最小集合），不加掛任何選配模組

### Requirement: 自動模組加掛
問卷發送時，系統 SHALL 依 `supplier.industry_group` 查詢 mapping 配置，自動決定除 E1/E4 核心模組外需加掛的維度模組。

#### Scenario: 製造業自動加掛 E2/E5
- **WHEN** 發送問卷給 industry_group 為「製造業」的供應商
- **THEN** 問卷題集包含 E1 核心題 + E4 地緣題 + E2（ISO 20400）題 + E5（ISO 28000）題

#### Scenario: 農林漁業自動加掛 E3/E6
- **WHEN** 發送問卷給 industry_group 為「農林漁業」的供應商
- **THEN** 問卷題集包含 E1/E4 核心題 + E3（ISO 26000）題 + E6（產品合規）適用題（經動態篩選後）

### Requirement: 手動模組覆蓋
管理員 SHALL 能針對特定問卷發送，手動調整加掛模組清單（新增或移除），覆蓋自動映射結果。

#### Scenario: 手動新增模組
- **WHEN** 管理員在發送問卷前手動勾選額外的 E3 模組（雖供應商分類為「製造業」）
- **THEN** 該次問卷包含 E1/E4/E2/E5/E3，計分時 E3 維度有值
