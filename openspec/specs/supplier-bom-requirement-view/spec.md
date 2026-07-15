## MODIFIED Requirements

### Requirement: 供應商 BOM 需求視圖從 BomLineSupplier 查詢

`getSupplierBomRequirements()` SHALL 改從 `bom_line_suppliers` JOIN `product_bom_lines` 查詢，取得該供應商參與的所有 BomLine 及其物料群組合規需求。回傳結構新增 `pcf_status` 欄位（`none` / `pending` / `submitted` / `verified`），反映最新一筆 PCF 請求的申報狀態。

#### Scenario: 供應商看到自己在各 BomLine 的角色

- **WHEN** 供應商查詢自己的採購需求
- **THEN** response 包含每條 BomLine 的：產品名稱、物料名稱、`bom_line_type`、物料群組、所需文件類型、自己的角色（primary/alternate）、`pcf_status`

#### Scenario: 供應商不在任何 BomLine 時回傳空列表

- **WHEN** 供應商未出現在任何 `bom_line_suppliers` 記錄
- **THEN** 回傳空陣列，不報錯

#### Scenario: PCF 申報狀態顯示

- **WHEN** 某 BomLine 存在對應的 `pcf_request_line` 記錄
- **THEN** `pcf_status` SHALL 回傳最新請求的狀態（`pending` / `submitted` / `verified`）；若無任何請求則回傳 `none`

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
