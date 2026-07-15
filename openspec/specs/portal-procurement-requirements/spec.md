## ADDED Requirements

### Requirement: 供應商入口採購需求 API 從 BomLineSupplier 查詢

`GET /api/v1/portal/procurement-requirements` SHALL 改從 `bom_line_suppliers` 查詢，回傳該供應商在所有產品 BomLine 中的採購義務（含角色、物料群組、所需合規文件類型）。

#### Scenario: 登入供應商取得採購需求

- **WHEN** 供應商使用 JWT 呼叫 `GET /api/v1/portal/procurement-requirements`
- **THEN** 回傳該供應商參與的所有 BomLine 採購需求，每條包含：`product_name`、`material_name`、`bom_line_type`、`required_doc_types`、`role`（primary/alternate）、現有文件狀態

#### Scenario: 供應商不在任何 BomLine

- **WHEN** 供應商呼叫採購需求 API 但未在任何 BomLine 中
- **THEN** 回傳 `{ success: true, data: [] }`

### Requirement: 供應商 Portal「採購需求」頁面
供應商 Portal SHALL 提供 `/supplier/portal/procurement` 路由，讓已登入的供應商使用者查看自己被哪些客戶產品 BomLines 指定，以及各物料的合規文件需求與缺口。

**隱私規則**：客戶產品名稱與編號 SHALL NOT 揭露給供應商。產品以「客戶產品 #N」（N 為頁面內流水號）顯示。

#### Scenario: 顯示採購需求列表
- **WHEN** 供應商使用者進入「採購需求」頁
- **THEN** 系統 SHALL 呼叫後端 API（需 supplier JWT）取得：此供應商被指定的 BomLines，依匿名化客戶產品分組，每組顯示物料名稱、物料群組、required_doc_types

#### Scenario: 合規缺口顯示
- **WHEN** 某 required_doc_type 尚未提交或已過期
- **THEN** 該 doc_type 以醒目紅色標籤顯示「待補件」或「已過期」，並在頁面頂部顯示缺口摘要（共 N 個文件需補充）

#### Scenario: 無採購需求
- **WHEN** 此供應商未被任何 BomLine 指定
- **THEN** 顯示空狀態：「目前尚無客戶採購需求指定您為供應商」

#### Scenario: 已提交文件對應顯示
- **WHEN** 供應商已提交某 doc_type 且狀態 valid
- **THEN** 該 doc_type 以綠色「已提交」標籤顯示，並顯示到期日

### Requirement: Portal 導覽列新增「採購需求」入口
Portal 頂部導覽 SHALL 在現有功能項目後新增「採購需求」連結，路由至 `/supplier/portal/procurement`。

#### Scenario: 導覽切換
- **WHEN** 供應商點擊「採購需求」導覽項
- **THEN** 頁面切換至採購需求頁，該導覽項為 active 狀態
