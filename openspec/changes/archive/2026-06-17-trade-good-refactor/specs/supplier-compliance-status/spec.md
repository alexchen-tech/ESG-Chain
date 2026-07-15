## MODIFIED Requirements

### Requirement: TradeGood 上游合規狀態計算
系統 SHALL 透過 trade_good_suppliers 多對多關聯計算每個 TradeGood 的上游合規狀態，取代原本依賴單一 supplier_id 的查詢路徑。合規狀態計算邏輯：遍歷所有 trade_good_suppliers → 取得各 material_group.required_doc_types → 查詢對應 supplier.complianceDocs → 取最差狀態（expired > expiring_soon > pending > missing > valid）。

#### Scenario: 所有上游供應商文件有效
- **WHEN** TradeGood 的所有 trade_good_suppliers 關聯的供應商，其 required doc_type 均有 valid 文件
- **THEN** upstream_compliance_status = 'valid'

#### Scenario: 任一上游供應商有文件即將到期
- **WHEN** 任一 trade_good_suppliers 的供應商有 expiring_soon 文件，且無 expired 文件
- **THEN** upstream_compliance_status = 'expiring_soon'

#### Scenario: 缺少必要文件
- **WHEN** 任一 trade_good_suppliers 的供應商缺少 material_group.required_doc_types 中的文件
- **THEN** upstream_compliance_status = 'missing'

#### Scenario: 無上游供應商設定
- **WHEN** TradeGood 的 trade_good_suppliers 為空
- **THEN** upstream_compliance_status = 'unconfigured'
