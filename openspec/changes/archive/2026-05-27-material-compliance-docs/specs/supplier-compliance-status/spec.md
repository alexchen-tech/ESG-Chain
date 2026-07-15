## ADDED Requirements

### Requirement: 供應商合規健康度彙總
系統 SHALL 為每位供應商計算合規健康度摘要，彙總其所有合規文件的狀態分佈，以及依物料群組要求判斷是否有缺漏文件。

#### Scenario: 健康度彙總計算
- **WHEN** 採購商查詢某供應商的合規健康度
- **THEN** 系統 SHALL 回傳：{ total_docs, valid_count, expiring_soon_count, expired_count, pending_count, missing_required_types[] }

#### Scenario: 偵測缺漏必要文件
- **WHEN** 供應商的 trade_goods 綁定了物料群組，但該群組 required_doc_types 中有尚未提交的文件類型
- **THEN** missing_required_types SHALL 列出缺漏的 doc_type 清單

#### Scenario: 無貿易商品或無物料群組綁定
- **WHEN** 供應商無任何 trade_goods 或所有 trade_goods 均未綁定物料群組
- **THEN** missing_required_types SHALL 為空陣列，健康度以已有文件狀態計算

### Requirement: 合規看板（採購商）—— 供應商視角
系統 SHALL 提供採購商一個合規看板頁面，以清單形式呈現所有供應商的合規健康度，支援依狀態篩選。

#### Scenario: 看板顯示高風險供應商
- **WHEN** 採購商進入合規看板（供應商視角）
- **THEN** 系統 SHALL 顯示所有供應商，並標示有 expired 或 missing_required_types 的供應商

#### Scenario: 篩選待處理供應商
- **WHEN** 採購商選擇「有問題」篩選條件
- **THEN** 系統 SHALL 只顯示 expired_count > 0 或 missing_required_types 不為空的供應商

### Requirement: 產品層級合規健康度
系統 SHALL 為每個 buyer_product 計算合規健康度，基於其所有關聯的「供應商 + 物料群組」組合所對應的合規文件狀態。

#### Scenario: 產品合規狀態計算
- **WHEN** 採購商查詢某產品的合規健康度
- **THEN** 系統 SHALL 回傳：{ product_id, product_name, applicable_regulations[], overall_status, supplier_results[{ supplier_id, material_group_id, required_doc_types[], compliance_summary }] }
- **AND** overall_status SHALL 為所有 supplier_results 中最嚴重狀態（expired > expiring_soon > pending > valid）

#### Scenario: 產品無任何供應商關聯
- **WHEN** 採購商查詢尚未關聯任何供應商的產品合規狀態
- **THEN** overall_status SHALL 為 "unconfigured"，supplier_results SHALL 為空陣列

### Requirement: 合規看板產品視角
系統 SHALL 在合規看板提供產品視角，以產品清單呈現每個產品的整體合規狀態與所適用的法規標記。

#### Scenario: 產品看板顯示法規風險
- **WHEN** 採購商切換至看板的「產品視角」
- **THEN** 系統 SHALL 顯示所有 buyer_products，含 applicable_regulations[] badge 與 overall_status 色彩標示

#### Scenario: 篩選特定法規的高風險產品
- **WHEN** 採購商選擇篩選條件「EUDR 有問題」
- **THEN** 系統 SHALL 只顯示 applicable_regulations 含 EUDR 且 overall_status 非 valid 的產品
