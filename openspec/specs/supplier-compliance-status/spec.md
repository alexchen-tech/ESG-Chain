## MODIFIED Requirements

### Requirement: 合規計算從 BomLine 而非 ProductSupplier 出發

`SupplierComplianceStatusService` SHALL 重寫為：以 BomLine 為迭代單位，對每條 BomLine 的所有 BomLineSupplier 評估文件狀態。系統 SHALL 不再使用 `getProductCompliance()` 中的 ProductSupplier 路徑。

#### Scenario: 供應商只在 BomLine 而不在 ProductSupplier

- **WHEN** 供應商 X 出現在 `bom_line_suppliers` 但不在 `buyer_product_suppliers`
- **THEN** 合規引擎 SHALL 正確評估供應商 X 的文件狀態（不再因 ProductSupplier 缺席而被忽略）

#### Scenario: ProductSupplier 路徑不再執行

- **WHEN** 合規狀態計算被觸發
- **THEN** `buyer_product_suppliers` 表不被查詢於合規計算流程（僅用於 AVL 管理功能）

### Requirement: 合規結果按 BomLine 維度回傳

API response SHALL 提供按 BomLine 分組的合規結果，每個 BomLine 包含：`bom_line_id`、`material_name`、`bom_line_type`、`material_group`、`required_doc_types`、`suppliers`（陣列，每個含 `supplier_id`、`role`、`doc_status`、`docs`）。

#### Scenario: 回傳結構包含 BomLine 維度

- **WHEN** 請求產品合規詳情
- **THEN** response 包含 `bom_lines` 陣列，每個 BomLine 項目包含其所有供應商的文件狀態

#### Scenario: 合規狀態向上聚合

- **WHEN** 請求產品整體合規狀態
- **THEN** response 包含聚合後的 `overall_status`，為所有 BomLine 所有 primary 供應商的最差狀態

### Requirement: 供應商合規健康度彙總

系統 SHALL 為每位供應商計算合規健康度摘要，彙總其所有合規文件的狀態分佈，以及依物料群組要求判斷是否有缺漏文件。`expiring_soon_count` 與 `expired_count` > 0 的供應商，系統 SHALL 存在對應的 open 或 in_progress CAP（由排程自動建立）。

缺漏文件計算 SHALL 整合兩條來源：

1. 供應商自身 TradeGoods 綁定的 MaterialGroup.required_doc_types（現有路徑）
2. 採購商 ProductBomLines 中 `bom_line_suppliers` 指向此供應商的物料群組 required_doc_types（BomLine 路徑）

兩者取聯集後與已提交 doc_type 比對。

#### Scenario: 健康度彙總計算

- **WHEN** 採購商查詢某供應商的合規健康度
- **THEN** 系統 SHALL 回傳：`{ total_docs, valid_count, expiring_soon_count, expired_count, pending_count, missing_required_types[] }`

#### Scenario: 偵測缺漏必要文件（TradeGoods 路徑）

- **WHEN** 供應商的 trade_goods 綁定了物料群組，但該群組 required_doc_types 中有尚未提交的文件類型
- **THEN** missing_required_types SHALL 列出缺漏的 doc_type 清單

#### Scenario: 偵測缺漏必要文件（BomLine 路徑）

- **WHEN** 採購商的 ProductBomLine 透過 `bom_line_suppliers` 指定此供應商，且綁定的物料群組有 required_doc_types
- **THEN** missing_required_types SHALL 亦包含這些 BomLine 帶來的合規需求（若尚未提交）

#### Scenario: 無貿易商品或無物料群組綁定

- **WHEN** 供應商無任何 trade_goods 或所有 trade_goods 均未綁定物料群組，且無任何 ProductBomLine 透過 `bom_line_suppliers` 指向此供應商
- **THEN** missing_required_types SHALL 為空陣列，健康度以已有文件狀態計算

#### Scenario: expiring_soon 供應商有對應 CAP

- **WHEN** 供應商有 `expiring_soon_count > 0`
- **THEN** 排程執行後 SHALL 存在至少一個 `source_type = 'compliance_doc'` 且 `status IN ('open', 'in_progress')` 的 CAP

#### Scenario: 關聯採購產品 Section 顯示

- **WHEN** 採購商查看供應商合規詳情頁
- **THEN** 「關聯採購產品」section SHALL 列出透過 `bom_line_suppliers` 指定此供應商的 BomLines，依產品分組，並顯示各物料的 required_doc_types 與提交狀態

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
