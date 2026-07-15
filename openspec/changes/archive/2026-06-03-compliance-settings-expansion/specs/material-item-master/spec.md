## ADDED Requirements

### Requirement: 料號主檔 CRUD
系統 SHALL 提供獨立子頁 `/settings/material-items`，讓管理員建立、編輯、停用料號（MaterialItem）。料號代碼（item_code）在系統內唯一。

#### Scenario: 建立新料號
- **WHEN** 管理員填寫料號代碼、品名、物料群組並送出
- **THEN** 系統建立 MaterialItem，料號清單即時更新

#### Scenario: 料號代碼重複
- **WHEN** 管理員輸入已存在的 item_code 並送出
- **THEN** 系統回傳 422，說明代碼已存在，拒絕建立

#### Scenario: 刪除被 BomLine 使用中的料號
- **WHEN** 管理員嘗試刪除有 ProductBomLine.material_item_id 參照的料號
- **THEN** 系統回傳 422，說明使用中的產品數量，拒絕刪除；管理員可選擇「停用」替代刪除

#### Scenario: 停用料號
- **WHEN** 管理員將料號設為停用（is_active=false）
- **THEN** 系統保留記錄，但 BomLine 建立介面的料號下拉不再顯示此料號；已連結的 BomLine 不受影響

### Requirement: CSV 批次匯入料號
系統 SHALL 支援 CSV 匯入 MaterialItem，格式欄位為 `item_code, name, hs_code, material_group_name, unit`，以 material_group_name 文字比對 MaterialGroup。

#### Scenario: 成功匯入
- **WHEN** 管理員上傳格式正確的 CSV，所有 material_group_name 均能比對到現有群組
- **THEN** 系統批次建立或更新 MaterialItem，回傳 created/updated 計數

#### Scenario: 部分料號 material_group_name 找不到對應群組
- **WHEN** 部分列的 material_group_name 在系統中不存在
- **THEN** 系統匯入可對應的列，對找不到群組的列回傳 warnings 清單（含行號），不阻擋整批匯入

#### Scenario: 料號代碼已存在
- **WHEN** CSV 中的 item_code 已存在於系統
- **THEN** 系統更新該料號的其他欄位（upsert），不重複建立

### Requirement: BomLine 連結料號（nullable）
ProductBomLine SHALL 支援 nullable 的 `material_item_id` FK。當 material_item_id 存在時，以 MaterialItem 的資料為 effective 值（name、hs_code、material_group）覆蓋 BomLine 本地欄位。

#### Scenario: BomLine 有連結料號時的 API 回傳
- **WHEN** 取得有 material_item_id 的 BomLine
- **THEN** API 回傳包含 `material_item` 物件，前端使用 `effective_material_name`、`effective_hs_code`、`effective_material_group` 計算欄位

#### Scenario: BomLine 無連結料號時的回傳
- **WHEN** 取得 material_item_id 為 null 的 BomLine
- **THEN** API 回傳 `material_item: null`，前端使用本地 material_name、hs_code、material_group_id 欄位，行為與現行相同

#### Scenario: BomLine 清單顯示連結狀態
- **WHEN** 管理員在產品 BOM 頁面查看 BomLine 清單
- **THEN** 已連結料號的行顯示料號代碼標籤；未連結的行顯示「自由文字」狀態標示
