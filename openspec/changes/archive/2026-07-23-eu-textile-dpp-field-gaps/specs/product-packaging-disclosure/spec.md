## ADDED Requirements

### Requirement: 產品包材資訊資料模型
系統 SHALL 提供一個與 SalesProduct 一對一關聯的包材資訊資料模型（`product_packagings`），包含再生料比例（`recycled_content_ratio`）、是否可回收（`recyclable`）、是否可重複使用（`reusable`）、包材說明（`material_description`），皆為選填。

#### Scenario: 產品尚未填寫包材資訊
- **WHEN** 查詢某 SalesProduct 的包材資訊，且該產品從未建立過 `product_packagings` 紀錄
- **THEN** 系統回傳該產品包材資訊為 null，不視為錯誤

#### Scenario: 建立產品包材資訊
- **WHEN** 使用者為某 SalesProduct 首次填寫包材資訊（再生料比例、可回收性、可重複使用性）
- **THEN** 系統建立一筆 `product_packagings` 紀錄並與該產品關聯

#### Scenario: 更新既有包材資訊
- **WHEN** 使用者修改某 SalesProduct 已存在的包材資訊
- **THEN** 系統更新該筆既有紀錄，不建立重複紀錄（一對一關聯，非 append-only）
