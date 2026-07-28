## ADDED Requirements

### Requirement: BOM 異動即時觸發法規重算
系統 SHALL 在 BomLine 新增、更新、刪除，以及 BOM 匯入完成時，即時重新執行「BOM 驅動法規自動推算」並更新該產品的 `inferred_regulations` 欄位，不得只依賴合規儀表板頁面開啟或每日排程指令才觸發。

#### Scenario: 新增 BomLine 後即時重算
- **WHEN** 使用者對某 SalesProduct 新增一條關聯 EUDR 相關物料群組的 BomLine
- **THEN** 系統 SHALL 立即重算該產品的 `inferred_regulations`，不需等待排程或開啟儀表板頁面
- **AND** 隨後查詢該產品資料時 `inferred_regulations` SHALL 已包含新增的法規 key

#### Scenario: 刪除 BomLine 後即時重算
- **WHEN** 使用者刪除某 BomLine，且該 BomLine 是產品內唯一觸發某法規 key 的來源
- **THEN** 系統 SHALL 立即重算，該法規 key SHALL 從 `inferred_regulations` 中移除

#### Scenario: BOM 匯入完成後即時重算
- **WHEN** 使用者透過 BOM 匯入功能一次匯入多筆 BomLine
- **THEN** 系統 SHALL 在匯入處理完成後，對受影響的產品即時重算 `inferred_regulations`，不需等待排程
