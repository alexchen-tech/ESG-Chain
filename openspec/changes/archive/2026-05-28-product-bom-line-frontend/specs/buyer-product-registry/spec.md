## MODIFIED Requirements

### Requirement: 採購商產品清單 CRUD
系統 SHALL 允許採購商（admin/buyer/sustain/comply）管理自身的產品清單，每筆產品記錄包含名稱、產品編號（選填）、說明，以及關聯的供應商與物料群組。產品卡片 SHALL 額外提供可展開的 BOM 明細管理 panel，供採購商管理 ProductBomLines 並支援 ERP CSV 匯入。

#### Scenario: 建立產品
- **WHEN** 採購商送出包含 name 的建立請求（product_code、description 為選填）
- **THEN** 系統 SHALL 建立產品記錄並回傳 201，applicable_regulations 初始為空陣列

#### Scenario: 供應商角色無法存取產品清單
- **WHEN** supplier/sup_esg 角色呼叫產品清單 API
- **THEN** 系統 SHALL 回傳 403

#### Scenario: 刪除產品
- **WHEN** admin 刪除一筆產品
- **THEN** 系統 SHALL 軟刪除該產品及其所有 buyer_product_suppliers 關聯記錄

#### Scenario: 展開 BOM 明細 Panel
- **WHEN** 採購商點擊產品卡片上的「BOM 明細」標籤
- **THEN** 系統 SHALL 展開 BOM panel 並載入該產品的 ProductBomLines，Panel header 顯示筆數

### Requirement: 產品關聯供應商與物料群組
系統 SHALL 允許採購商將產品與一個或多個「供應商 + 物料群組」組合關聯，代表該產品使用該供應商提供的該類物料。

#### Scenario: 新增產品供應商關聯
- **WHEN** 採購商送出 { supplier_id, material_group_id (nullable) } 至產品關聯 API
- **THEN** 系統 SHALL 建立 buyer_product_suppliers 記錄，回傳 201

#### Scenario: 同一供應商可關聯不同物料群組
- **WHEN** 採購商對同一產品新增同一 supplier_id 但不同 material_group_id 的關聯
- **THEN** 系統 SHALL 允許建立（不視為重複），因同一供應商可提供多種物料給同一產品

#### Scenario: 移除產品供應商關聯
- **WHEN** 採購商刪除特定 buyer_product_suppliers 記錄
- **THEN** 系統 SHALL 刪除該筆關聯，不影響同產品其他關聯及 ProductBomLines
