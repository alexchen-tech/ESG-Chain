## ADDED Requirements

### Requirement: 供應商設施主檔管理

`SupplierFacility` 模型（`supplier_facilities` 表）代表供應商的生產設施（廠區），作為活動資料申報的主體。一個供應商可有多個設施。

系統 SHALL 支援以下欄位：`id (UUID)`、`supplier_id (FK)`、`name`（設施名稱）、`country`、`address`、`facility_type enum('manufacturing','warehouse','office','other')`、`energy_types JSON`（能源類型清單，如 `['electricity','natural_gas']`）、`main_products TEXT nullable`、`is_active boolean default true`、`timestamps`。

#### Scenario: 買方為供應商新增設施

- **WHEN** 永續團隊在買方端透過 `POST /api/v1/suppliers/{supplier}/facilities` 新增設施
- **THEN** 系統建立 `SupplierFacility` 記錄，`supplier_id` 正確關聯，`is_active = true`

#### Scenario: 供應商在 Portal 查看自己的設施

- **WHEN** 已登入供應商呼叫 `GET /api/v1/portal/facilities`
- **THEN** 系統回傳該供應商旗下所有 `is_active = true` 的設施清單，含最新一筆 `ActivityDataReport` 的申報狀態

#### Scenario: 停用設施

- **WHEN** 買方呼叫 `PATCH /api/v1/suppliers/{supplier}/facilities/{facility}` 設定 `is_active = false`
- **THEN** 設施不再出現在 Portal 任務區，但歷史申報資料保留
