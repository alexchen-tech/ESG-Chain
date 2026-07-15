## MODIFIED Requirements

### Requirement: 供應商合規健康度彙總
系統 SHALL 為每位供應商計算合規健康度摘要，彙總其所有合規文件的狀態分佈，以及依物料群組要求判斷是否有缺漏文件。`expiring_soon_count` 與 `expired_count` > 0 的供應商，系統 SHALL 存在對應的 open 或 in_progress CAP（由排程自動建立）。

缺漏文件計算 SHALL 整合兩條來源：
1. 供應商自身 TradeGoods 綁定的 MaterialGroup.required_doc_types（現有路徑）
2. 採購商 ProductBomLines 中 `designated_supplier_id` 指向此供應商的物料群組 required_doc_types（新增路徑）

兩者取聯集後與已提交 doc_type 比對。

#### Scenario: 健康度彙總計算
- **WHEN** 採購商查詢某供應商的合規健康度
- **THEN** 系統 SHALL 回傳：`{ total_docs, valid_count, expiring_soon_count, expired_count, pending_count, missing_required_types[] }`

#### Scenario: 偵測缺漏必要文件（TradeGoods 路徑）
- **WHEN** 供應商的 trade_goods 綁定了物料群組，但該群組 required_doc_types 中有尚未提交的文件類型
- **THEN** missing_required_types SHALL 列出缺漏的 doc_type 清單

#### Scenario: 偵測缺漏必要文件（BomLine 路徑）
- **WHEN** 採購商的 ProductBomLine 指定此供應商（designated_supplier_id），且綁定的物料群組有 required_doc_types
- **THEN** missing_required_types SHALL 亦包含這些 BomLine 帶來的合規需求（若尚未提交）

#### Scenario: 無貿易商品或無物料群組綁定
- **WHEN** 供應商無任何 trade_goods 或所有 trade_goods 均未綁定物料群組，且無任何 ProductBomLine 指向此供應商
- **THEN** missing_required_types SHALL 為空陣列，健康度以已有文件狀態計算

#### Scenario: expiring_soon 供應商有對應 CAP
- **WHEN** 供應商有 `expiring_soon_count > 0`
- **THEN** 排程執行後 SHALL 存在至少一個 `source_type = 'compliance_doc'` 且 `status IN ('open', 'in_progress')` 的 CAP
