## ADDED Requirements

### Requirement: 供應商合規詳情頁「關聯採購產品」Section
`SupplierComplianceDetailView` SHALL 在現有「供應材料清單」section 下方新增「關聯採購產品」section，列出哪些 BuyerProduct 的 BomLines 指定了此供應商，以及各 BomLine 對應的合規文件需求與目前狀態。

#### Scenario: 顯示關聯產品列表
- **WHEN** 頁面載入供應商合規詳情
- **THEN** 「關聯採購產品」section SHALL 呼叫 API 取得此供應商被指定的所有 BomLines，依 BuyerProduct 分組顯示

#### Scenario: 每個產品群組的顯示內容
- **WHEN** 展示某採購產品群組
- **THEN** SHALL 顯示：產品名稱、法規標籤、以及該產品下所有指向此供應商的 BomLines（物料名稱、物料群組、required_doc_types 清單、各 doc_type 的現有提交狀態）

#### Scenario: 無關聯產品
- **WHEN** 此供應商未被任何 BomLine 指定
- **THEN** 顯示空狀態：「此供應商尚未被任何產品 BOM 明細指定」

#### Scenario: 合規缺口高亮
- **WHEN** 某 BomLine 的 required_doc_types 中有尚未提交的文件類型
- **THEN** 該 doc_type 以紅色缺漏標籤顯示，提醒採購商須催請供應商補件
