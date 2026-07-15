## ADDED Requirements

### Requirement: L1 Email 格式防呆
清洗服務 SHALL 對每筆 staged 記錄檢查 `primary_email`：空白或不符合 RFC 5322 基本格式（無 @、無域名）者設 cleanse_status=rejected，failure_codes 加入 "email_invalid"。

#### Scenario: Email 空白
- **WHEN** primary_email 為空字串或 NULL
- **THEN** cleanse_status=rejected，failure_codes=["email_invalid"]

#### Scenario: 明顯無效 Email（如 NA、123@abc）
- **WHEN** primary_email = "NA" 或無 TLD 的格式
- **THEN** cleanse_status=rejected，failure_codes=["email_invalid"]

#### Scenario: 有效 Email
- **WHEN** primary_email = "esg@supplier.com"
- **THEN** 通過 L1，進入 L2 檢查

### Requirement: L2 VAT Number 去重
清洗服務 SHALL 對同一 batch 內重複 vat_number 進行合併：保留第一筆（依 vendor_code 字母序），合併所有 erp_vendor_codes，其餘設為 rejected（failure_codes=["duplicate_vat_merged"]）。若 vat_number 已存在 suppliers 主表，設 failure_codes=["vat_exists_in_master"]（仍可人工豁免）。

#### Scenario: 同批次 3 筆相同 VAT
- **WHEN** batch 內有 TPS-001 / TPS-002 / TPS-999 同 vat_number
- **THEN** TPS-001 cleansed，erp_vendor_codes=["TPS-001","TPS-002","TPS-999"]；其餘兩筆 rejected

#### Scenario: VAT 已在主表
- **WHEN** vat_number 與 suppliers.vat_number 重複
- **THEN** cleanse_status=rejected，failure_codes=["vat_exists_in_master"]，採購員可豁免

### Requirement: 清洗結果 API
`GET /api/v1/suppliers/import/{batchId}/status` SHALL 回傳批次清洗統計：total / cleansed / rejected / staged / approved，以及 rejected 原因分類計數。

#### Scenario: 查詢清洗完成的批次
- **WHEN** 清洗已完成
- **THEN** 回傳各狀態計數，無 staged 剩餘
