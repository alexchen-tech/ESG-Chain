## MODIFIED Requirements

### Requirement: store()/update() 自動同步 material_group_id

當 API 呼叫端傳入 `material_item_id` 時，系統 SHALL 自動從 MaterialItem 帶入 `material_group_id` 與 `material_group_source='erp_imported'`，**除非**呼叫端同時明確傳入 `material_group_id`（此時尊重呼叫端的值，`material_group_source='manual'`）。

#### Scenario: 傳入 material_item_id 且未指定 material_group_id

- **WHEN** POST 或 PATCH BomLine 帶入有效 `material_item_id`，且未傳入 `material_group_id`
- **THEN** 系統 SHALL 自動將 `material_group_id` 設為 `materialItem.material_group_id`，`material_group_source` 設為 `'erp_imported'`

#### Scenario: 傳入 material_item_id 且同時指定 material_group_id

- **WHEN** POST 或 PATCH BomLine 同時帶入 `material_item_id` 與 `material_group_id`
- **THEN** 系統 SHALL 使用呼叫端傳入的 `material_group_id`，`material_group_source` 設為 `'manual'`
