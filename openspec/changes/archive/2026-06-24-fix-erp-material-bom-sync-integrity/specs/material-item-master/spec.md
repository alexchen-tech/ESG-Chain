## MODIFIED Requirements

### Requirement: 料號主檔 CRUD
系統 SHALL 提供獨立子頁 `/settings/material-items`，讓管理員編輯、停用料號（MaterialItem）。料號代碼（item_code）在系統內唯一，且僅可透過 ERP 同步或 CSV 批次匯入建立，一般管理員 CRUD 表單不可自由輸入 item_code 建立新料號。

#### Scenario: 透過一般建立 API 嘗試帶入 item_code
- **WHEN** 呼叫 `POST /api/v1/material-items` 並帶有 `item_code` 欄位
- **THEN** 系統回傳 422，說明料號代碼僅可透過 ERP 同步或 CSV 匯入建立，拒絕本次請求

#### Scenario: 透過一般更新 API 嘗試修改 item_code
- **WHEN** 呼叫 `PUT /api/v1/material-items/{id}` 並帶有 `item_code` 欄位
- **THEN** 系統回傳 422，拒絕修改 item_code；其餘欄位（name、hs_code、unit、material_group_id、description、is_active、net_weight、pcr_percentage）仍可正常更新

#### Scenario: 刪除被 BomLine 使用中的料號
- **WHEN** 管理員嘗試刪除有 ProductBomLine.material_item_id 參照的料號
- **THEN** 系統回傳 422，說明使用中的產品數量，拒絕刪除；管理員可選擇「停用」替代刪除

#### Scenario: 停用料號
- **WHEN** 管理員將料號設為停用（is_active=false）
- **THEN** 系統保留記錄，但 BomLine 建立介面的料號下拉不再顯示此料號；已連結的 BomLine 不受影響
