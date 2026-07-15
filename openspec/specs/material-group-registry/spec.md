## ADDED Requirements

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

### Requirement: 物料群組主檔管理
系統 SHALL 提供物料群組（Material Group）主檔，每個群組定義名稱、說明、適用的 HS Code 前綴規則，以及該群組所需的合規文件類型清單。

#### Scenario: 系統預載標準物料群組
- **WHEN** 系統初始化（seeder 執行）
- **THEN** 系統 SHALL 預載以下 5 個標準群組：棉紡原料（UFLPA_DECLARATION）、木質農產品（EUDR_DDS、ORIGIN_CERT）、電子五金（CMRT）、化工塑料（SDS）、機電終端（CE_DOC）

#### Scenario: Admin 新增自訂物料群組
- **WHEN** admin 送出包含 name、required_doc_types[] 的建立請求
- **THEN** 系統 SHALL 建立新物料群組並回傳 201

#### Scenario: 非 admin 嘗試修改物料群組
- **WHEN** buyer/sustain/comply/analyst 角色呼叫物料群組寫入 API
- **THEN** 系統 SHALL 回傳 403

### Requirement: HS Code 自動推導物料群組
系統 SHALL 根據 `trade_goods.hs_code` 前綴自動比對對應物料群組，並在貿易商品建立或更新時自動填入 `material_group_id`（若有符合規則）。

#### Scenario: HS Code 符合推導規則
- **WHEN** 建立 trade_good 且 hs_code 前綴符合某物料群組規則（如 "52" = 棉紡）
- **THEN** 系統 SHALL 自動設定 material_group_id 為對應群組

#### Scenario: HS Code 無對應規則
- **WHEN** 建立 trade_good 且 hs_code 無符合規則
- **THEN** material_group_id SHALL 保持 null，不強制綁定

#### Scenario: 手動覆蓋自動推導
- **WHEN** 採購商明確指定 trade_good 的 material_group_id
- **THEN** 系統 SHALL 使用指定值，不再以 HS Code 覆蓋
