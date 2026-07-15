## ADDED Requirements

### Requirement: 物料化學組成管理

`MaterialItemChemical` 模型（`material_item_chemicals` 表）記錄 MaterialItem 的化學物質組成。一個物料可含多種化學物質。

欄位：`id (UUID)`、`material_item_id (FK→material_items)`、`cas_no VARCHAR(15)`（格式驗證 `^\d{1,7}-\d{2}-\d$`）、`substance_name VARCHAR(255)`、`weight_percentage DECIMAL(5,2)`（0.01–100.00）、`reporting_threshold DECIMAL(5,4) default 0.1`（%w/w，低於此值不申報）、`source ENUM('buyer-input','supplier-declared','sds-extracted') default 'buyer-input'`、`notes TEXT nullable`、`timestamps`。

系統 SHALL 驗證同一 `material_item_id` 下所有 `weight_percentage` 總和不超過 100%（警告，不阻擋儲存）。

#### Scenario: 買方輸入化學組成

- **WHEN** 買方透過 `POST /api/v1/material-items/{id}/chemicals` 提交 CAS No. + 百分比
- **THEN** 系統驗證 CAS No. 格式，建立記錄，並觸發合規掃描 Job

#### Scenario: CAS No. 格式錯誤

- **WHEN** 提交的 CAS No. 不符合 `^\d{1,7}-\d{2}-\d$` 格式
- **THEN** API 回傳 `422 Unprocessable Entity`，`errors.cas_no: ['CAS No. 格式不正確']`

#### Scenario: 刪除化學組成記錄

- **WHEN** 買方呼叫 `DELETE /api/v1/material-item-chemicals/{id}`
- **THEN** 軟刪除（`deleted_at`），歷史掃描警示保留，但下次掃描不再包含此項目
