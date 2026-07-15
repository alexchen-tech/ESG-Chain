## ADDED Requirements

### Requirement: 活動資料申報（按季度）

`ActivityDataReport` 模型（`activity_data_reports` 表）記錄設施層級的原始能源消費資料。採 append-only 策略，每季新增一筆，不覆寫。

欄位：`id (UUID)`、`supplier_facility_id (FK)`、`report_period VARCHAR(10)`（格式：`2024-Q1`）、`electricity_kwh DECIMAL(14,2) nullable`、`natural_gas_gj DECIMAL(14,2) nullable`、`fuel_oil_l DECIMAL(14,2) nullable`、`heat_gj DECIMAL(14,2) nullable`、`water_m3 DECIMAL(14,2) nullable`、`notes TEXT nullable`、`status ENUM('draft','submitted','verified') default 'draft'`、`push_log JSON nullable`（推送結果記錄）、`submitted_at TIMESTAMP nullable`、`verified_at TIMESTAMP nullable`、`timestamps`。

#### Scenario: 供應商在 Portal 填報活動資料

- **WHEN** 供應商透過 Portal 呼叫 `POST /api/v1/portal/facilities/{facility}/activity-reports`，傳入 `report_period`、能源數值
- **THEN** 系統建立 `ActivityDataReport`（status=draft），並更新 Portal 設施任務區的申報狀態為「申報中」

#### Scenario: 供應商提交申報

- **WHEN** 供應商呼叫 `POST /api/v1/portal/facilities/{facility}/activity-reports/{report}/submit`
- **THEN** `status → submitted`、`submitted_at = now()`，買方端可見待審核通知

#### Scenario: 買方核實申報

- **WHEN** 永續團隊呼叫 `POST /api/v1/suppliers/{supplier}/activity-reports/{report}/verify`
- **THEN** `status → verified`、`verified_at = now()`，觸發 Scope 3 推送 Celery Task

#### Scenario: 重複申報同一季度

- **WHEN** 同一設施同一 `report_period` 已有記錄
- **THEN** 系統允許新增（append-only），API 回傳 `201 Created`，並在列表中標示最新一筆
