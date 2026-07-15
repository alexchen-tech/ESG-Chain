## ADDED Requirements

### Requirement: BOM 匯入時自動 upsert MaterialItem
系統 SHALL 在 BOM 行匯入時，依 `material_code` 欄位自動 upsert `MaterialItem`（`item_code = material_code`，`hs_code`、`name` 同步更新），並將 `ProductBomLine.material_item_id` 設為對應 MaterialItem 的 id。無需額外的物料映射步驟。

#### Scenario: 新物料代碼自動建立 MaterialItem
- **WHEN** BOM 匯入包含 `material_code = "CTN-32S"`，MaterialItem 中不存在此 item_code
- **THEN** 系統 SHALL 建立 `MaterialItem(item_code: "CTN-32S", hs_code: ..., name: ...)`，並設定 BomLine.material_item_id

#### Scenario: 已存在的物料代碼更新 MaterialItem
- **WHEN** BOM 匯入包含已存在的 `material_code`，但 hs_code 有變更
- **THEN** 系統 SHALL 更新 MaterialItem 的 hs_code，material_item_id 不變

#### Scenario: 無 material_code 時 material_item_id 保持 null
- **WHEN** BOM 行沒有提供 `material_code`
- **THEN** 系統 SHALL 建立 BomLine，material_item_id = null，不強制建立 MaterialItem

### Requirement: BOM 匯入後觸發碳排缺口掃描
系統 SHALL 在每次 BOM 匯入（JSON API 或 CSV）完成後，dispatch Celery job 執行碳排缺口掃描（參照 pcf-emission-gap-scan spec）。

#### Scenario: BOM 匯入後非同步觸發掃描
- **WHEN** BOM 匯入 API 成功回傳 `{ created: N, updated: M }`
- **THEN** 系統 SHALL 非同步 dispatch 缺口掃描 job，API 不等待 job 完成即回傳
