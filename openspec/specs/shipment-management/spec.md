## MODIFIED Requirements

### Requirement: Shipment 客戶綁定
出貨單可綁定客戶，並記錄客戶訂單號，target_market 自動從客戶國家帶入。

#### Scenario: 建立出貨單並選擇客戶
- **WHEN** 建立 Shipment 時提供有效的 customer_id
- **THEN** 自動從 Customer.country_code 推導並寫入 target_market（EU 成員國 → "EU"，其餘依規則）

#### Scenario: 建立出貨單不選客戶
- **WHEN** 建立 Shipment 時 customer_id 為空
- **THEN** target_market 由使用者手動填寫，允許不填

#### Scenario: 記錄客戶訂單號
- **WHEN** 建立或更新 Shipment 時提供 customer_po_no
- **THEN** 儲存 customer_po_no，顯示於出貨單列表與 CBAM 申報摘要

#### Scenario: agent 客戶警告
- **WHEN** 建立 Shipment 時選擇 customer_type='agent' 的客戶
- **THEN** 回傳 HTTP 201（允許建立），同時 response body 加 warnings 陣列提示 CBAM 責任確認
