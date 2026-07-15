## MODIFIED Requirements

### Requirement: PCF 請求資料模型
系統 SHALL 維護 `pcf_requests` 與 `pcf_request_lines` 兩張表。

`pcf_requests` 欄位：`id`（UUID）、`supplier_id`、`period_start`（date, nullable）、`period_end`（date, nullable）、`due_date`（date, nullable）、`status`（`pending` / `partial` / `submitted` / `verified` / `overdue`）、`trigger_source`（`system_bom_import` / `system_supplier_change` / `buyer_manual`）、`notes`（nullable）、`created_by`、`created_at`、`updated_at`

`pcf_request_lines` 欄位：`id`（UUID）、`pcf_request_id`、`material_item_id`（FK → material_items, nullable for legacy）、`bom_line_id`（nullable）、`material_name`（快照，從 MaterialItem.name 取得）、`hs_code`（快照，從 MaterialItem.hs_code 取得）、`submitted_at`（nullable）、`fulfilled_emission_id`（FK → material_item_emissions, nullable）、`status`（`pending` / `submitted` / `verified`）

#### Scenario: 資料完整性
- **WHEN** 建立 pcf_request_line
- **THEN** 系統 SHALL 從 MaterialItem 快照 `material_name` 與 `hs_code`，確保歷史記錄不受 MaterialItem 後續修改影響

#### Scenario: PcfRequest status 從 pending 升為 partial
- **WHEN** PcfRequest 下第一條 PcfRequestLine 狀態變為 submitted
- **THEN** PcfRequest.status SHALL 更新為 `partial`

#### Scenario: PcfRequest status 從 partial 升為 submitted
- **WHEN** PcfRequest 下所有 PcfRequestLine 均為 submitted
- **THEN** PcfRequest.status SHALL 更新為 `submitted`

## REMOVED Requirements

### Requirement: PCF 請求關聯 SAQ Round
**Reason**: PCF 請求屬於產品合規主線，SAQ 屬於永續韌性主線，兩條主線獨立運作，不應在資料層耦合。
**Migration**: `pcf_requests.saq_round_id` 欄位廢棄（保留欄位但不再使用），新建立的 PcfRequest 使用 `trigger_source` 記錄觸發來源。
