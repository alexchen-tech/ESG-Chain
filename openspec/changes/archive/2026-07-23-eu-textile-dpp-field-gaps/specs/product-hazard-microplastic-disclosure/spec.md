## ADDED Requirements

### Requirement: 產品有害物質揭露即時判定
系統 SHALL 提供一個服務方法，針對指定 SalesProduct，即時彙總其所有 BOM 物料關聯的 ChemicalComplianceAlert（`status != 'resolved'`），回傳是否存在未解除的有害物質風險（布林）與明細清單，不落地為靜態欄位。

#### Scenario: 產品存在未解除的有害物質警示
- **WHEN** 產品的 BOM 物料中，有一筆 ChemicalComplianceAlert 的 `status` 為 `open` 或 `acknowledged`
- **THEN** 判定結果回傳 `has_hazardous_substance: true`，並列出該筆警示的 `chemical_id`、`alert_level`、`status`

#### Scenario: 產品所有警示皆已解除
- **WHEN** 產品的 BOM 物料關聯的 ChemicalComplianceAlert 全部 `status = 'resolved'`，或完全無警示紀錄
- **THEN** 判定結果回傳 `has_hazardous_substance: false`，明細清單為空

### Requirement: 物料塑膠微纖維釋放風險欄位
MaterialItem SHALL 提供 `microfiber_release_risk` 欄位，值域為 `low`/`medium`/`high`/`not_rated`，預設 `not_rated`，供人工填報使用。

#### Scenario: 新增物料未填寫微纖維風險
- **WHEN** 使用者建立新的 MaterialItem 且未指定 `microfiber_release_risk`
- **THEN** 系統儲存該欄位值為 `not_rated`

#### Scenario: 更新物料微纖維風險等級
- **WHEN** 使用者將某物料的 `microfiber_release_risk` 更新為 `high`
- **THEN** 系統儲存並可於物料詳情頁顯示該等級
