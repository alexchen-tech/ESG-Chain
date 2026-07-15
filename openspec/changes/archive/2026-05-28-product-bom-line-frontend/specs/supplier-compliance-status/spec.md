## MODIFIED Requirements

### Requirement: 供應商合規健康度彙總
系統 SHALL 為每位供應商計算合規健康度摘要，彙總其所有合規文件的狀態分佈，以及依物料群組要求判斷是否有缺漏文件。`expiring_soon_count` 與 `expired_count` > 0 的供應商，系統 SHALL 存在對應的 open 或 in_progress CAP（由排程自動建立）。

缺漏文件計算 SHALL 整合 TradeGoods 路徑與 ProductBomLine 路徑（參見 product-bom-line-backend）。

`SupplierComplianceDetailView` SHALL 在頁面中新增「關聯採購產品」section，展示哪些採購商 BomLines 指定此供應商，及各物料的合規文件需求與提交狀態，讓採購商清楚看到來自 BOM 驅動的合規缺口。

#### Scenario: 健康度彙總計算
- **WHEN** 採購商查詢某供應商的合規健康度
- **THEN** 系統 SHALL 回傳：`{ total_docs, valid_count, expiring_soon_count, expired_count, pending_count, missing_required_types[] }`

#### Scenario: 偵測缺漏必要文件
- **WHEN** 供應商的 trade_goods 綁定了物料群組，但該群組 required_doc_types 中有尚未提交的文件類型
- **THEN** missing_required_types SHALL 列出缺漏的 doc_type 清單

#### Scenario: 無貿易商品或無物料群組綁定
- **WHEN** 供應商無任何 trade_goods 或所有 trade_goods 均未綁定物料群組
- **THEN** missing_required_types SHALL 為空陣列，健康度以已有文件狀態計算

#### Scenario: 關聯採購產品 Section 顯示
- **WHEN** 採購商查看供應商合規詳情頁
- **THEN** 「關聯採購產品」section SHALL 列出指定此供應商的 BomLines，依產品分組，並顯示各物料的 required_doc_types 與提交狀態

#### Scenario: expiring_soon 供應商有對應 CAP
- **WHEN** 供應商有 `expiring_soon_count > 0`
- **THEN** 排程執行後 SHALL 存在至少一個 `source_type = 'compliance_doc'` 且 `status IN ('open', 'in_progress')` 的 CAP
