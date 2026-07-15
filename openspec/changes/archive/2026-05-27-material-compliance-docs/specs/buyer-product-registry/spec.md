## ADDED Requirements

### Requirement: 採購商產品清單 CRUD
系統 SHALL 允許採購商（admin/buyer/sustain/comply）管理自身的產品清單，每筆產品記錄包含名稱、產品編號（選填）、說明，以及關聯的供應商與物料群組。

#### Scenario: 建立產品
- **WHEN** 採購商送出包含 name 的建立請求（product_code、description 為選填）
- **THEN** 系統 SHALL 建立產品記錄並回傳 201，applicable_regulations 初始為空陣列

#### Scenario: 供應商角色無法存取產品清單
- **WHEN** supplier/sup_esg 角色呼叫產品清單 API
- **THEN** 系統 SHALL 回傳 403

#### Scenario: 刪除產品
- **WHEN** admin 刪除一筆產品
- **THEN** 系統 SHALL 軟刪除該產品及其所有 buyer_product_suppliers 關聯記錄

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
- **THEN** 系統 SHALL 刪除該關聯，不影響供應商主檔與合規文件

### Requirement: applicable_regulations 自動推導
系統 SHALL 根據產品關聯的所有物料群組，自動彙總並更新 `applicable_regulations` 欄位。

#### Scenario: 關聯棉紡物料群組後自動標記 UFLPA
- **WHEN** 採購商將產品關聯至物料群組「棉紡原料」（required_doc_types 含 UFLPA_DECLARATION）
- **THEN** 系統 SHALL 自動將 UFLPA 加入該產品的 applicable_regulations

#### Scenario: 移除最後一個需要特定法規的物料群組後移除標記
- **WHEN** 採購商移除產品上唯一的棉紡物料群組關聯
- **THEN** 系統 SHALL 自動從 applicable_regulations 移除 UFLPA

### Requirement: CSV 批量匯入產品清單
系統 SHALL 支援採購商上傳 CSV 檔案批量建立產品與關聯。

#### Scenario: CSV 匯入成功
- **WHEN** 採購商上傳格式正確的 CSV（欄位：name, product_code, description, supplier_tax_id_or_name, material_group_name）
- **THEN** 系統 SHALL 建立產品記錄及對應關聯，回傳匯入結果摘要（created_count, skipped_count, warnings[]）

#### Scenario: CSV 中供應商名稱無法比對
- **WHEN** CSV 某行的 supplier_tax_id_or_name 在系統中找不到對應供應商
- **THEN** 系統 SHALL 跳過該行的供應商關聯，繼續處理其他行，並在 warnings[] 列出無法比對的行號與原因

#### Scenario: CSV 格式錯誤
- **WHEN** 上傳的 CSV 缺少必要欄位 name
- **THEN** 系統 SHALL 回傳 422，不執行任何匯入
