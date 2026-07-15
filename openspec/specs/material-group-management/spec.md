### Requirement: MaterialGroup CRUD 介面
系統 SHALL 在設定頁提供「物料群組」Tab，讓管理員新增、編輯物料群組；`is_system=true` 的系統預載群組不可刪除。

#### Scenario: 新增自訂物料群組
- **WHEN** 管理員填寫名稱、類型、HS Code 前綴、所需文件類型並送出
- **THEN** 系統建立 MaterialGroup 記錄（`is_system=false`），清單立即更新

#### Scenario: 編輯系統預載物料群組
- **WHEN** 管理員編輯 is_system=true 的群組並送出
- **THEN** 系統允許更新 name、description、required_doc_types，但不允許刪除

#### Scenario: 刪除被使用中的物料群組
- **WHEN** 管理員嘗試刪除有 MaterialItem 或 ProductBomLine 參照的群組
- **THEN** 系統回傳 422，說明有幾個料號和 BomLine 正在使用，拒絕刪除

#### Scenario: 刪除未被使用的自訂物料群組
- **WHEN** 管理員刪除無任何參照且 is_system=false 的群組
- **THEN** 系統刪除該記錄，清單更新

### Requirement: HS Code 前綴自動推薦物料群組
系統 SHALL 在 BomLine 或 MaterialItem 輸入 HS Code 時，自動比對 MaterialGroup.hs_code_prefixes 並推薦對應群組。

#### Scenario: HS Code 符合某群組前綴
- **WHEN** 使用者輸入 HS Code（至少 4 碼），系統找到符合前綴的 MaterialGroup
- **THEN** 在物料群組欄位下方顯示推薦提示，使用者可一鍵套用

#### Scenario: HS Code 不符合任何群組前綴
- **WHEN** 使用者輸入 HS Code，系統找不到符合的 MaterialGroup
- **THEN** 不顯示推薦，使用者手動選取
