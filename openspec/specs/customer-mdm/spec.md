## ADDED Requirements

### Requirement: Customer CRUD
系統提供 Customer 主檔的建立、查詢、修改、軟刪除功能。

#### Scenario: 建立客戶
- **WHEN** 使用者提交有效的客戶資料（name, code, country_code, customer_type 必填）
- **THEN** 建立 Customer 記錄，code 全域唯一，返回完整客戶資料

#### Scenario: 重複代碼
- **WHEN** 使用者提交的 code 已存在
- **THEN** 返回 422，錯誤訊息指出 code 重複

#### Scenario: 查詢列表
- **WHEN** GET /api/v1/customers，可帶 search（name/code 模糊）、status、customer_type 篩選
- **THEN** 返回分頁結果，含 contact_count、trade_good_count

---

### Requirement: EORI 條件驗證
EU 成員國客戶的 EORI Number 須通過格式與一致性驗證。

#### Scenario: EU 客戶缺少 EORI
- **WHEN** country_code 屬於 EU 成員國且 eori_number 為空
- **THEN** 建立仍允許（nullable），但 API response 加 warning 欄位提示補填

#### Scenario: EORI 格式錯誤
- **WHEN** eori_number 提供但不符合 /^[A-Z]{2}[A-Z0-9]{1,15}$/ 或前兩碼不等於 country_code
- **THEN** 返回 422 驗證錯誤

---

### Requirement: Customer Contacts 管理
每位客戶可維護多筆聯絡人，其中一筆為主要聯絡人。

#### Scenario: 新增聯絡人
- **WHEN** POST /api/v1/customers/{id}/contacts，email 唯一
- **THEN** 建立聯絡人，若 is_primary=true 則自動取消其他聯絡人的 is_primary

#### Scenario: 刪除主要聯絡人
- **WHEN** 刪除 is_primary=true 的聯絡人
- **THEN** 允許刪除，不自動指派新主要聯絡人

> **已移除**：原「agent 類型警告」需求（Shipment 綁定 agent 客戶時提示 CBAM 責任確認）隨出口申報（Shipment）模組一併移除。系統邊界止於出口前合規檢查，出口交易執行（含客戶綁定）屬 ERP 範疇。
