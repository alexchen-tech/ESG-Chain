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

### Requirement: 料號詳情頁（MaterialItemDetailView）
系統 SHALL 提供獨立詳情頁 `/materials/items/:id`，讓使用者在單一頁面存取料號的所有維度資訊。詳情頁採用 Tab 導覽，內容包含：基本資料、碳排資料庫、來源供應商、化學組成四個 Tab，各 Tab 採 lazy loading（切換時才呼叫 API）。

#### Scenario: 進入詳情頁
- **GIVEN** 使用者在物料主檔列表點擊「詳情」按鈕
- **WHEN** 進入 `/materials/items/{id}`
- **THEN** 頁面顯示料號代碼（accent 色、monospace）、品名、HS Code、物料群組作為 subtitle，右上角顯示啟用狀態 badge 與「編輯」按鈕；預設顯示「基本資料」Tab

#### Scenario: 料號不存在時
- **WHEN** `GET /api/v1/material-items/{id}` 回傳 404
- **THEN** 頁面顯示「料號不存在」空狀態

#### Scenario: 基本資料 Tab
- **WHEN** 使用者停留在「基本資料」Tab
- **THEN** 顯示 3 欄 grid：料號代碼、品名、HS Code、物料群組、計量單位、淨重、說明（全欄）；以及「可回收成分」子區塊顯示 PCR、PIR、Bio-based 百分比與可回收性評級

#### Scenario: 從詳情頁編輯料號
- **WHEN** 使用者點擊「編輯」按鈕並修改欄位後儲存
- **THEN** 呼叫 `PUT /api/v1/material-items/{id}`，成功後頁面即時更新，不需重新整理；item_code 不可修改（disabled）

### Requirement: 料號可回收成分細項（三欄位擴充）
MaterialItem SHALL 支援三個可回收相關欄位：`pir_percentage`（製程廢料回收比例）、`bio_based_percentage`（生物基材料比例）、`recyclability_rating`（可回收性評級，枚舉 high/medium/low/not_rated）。現有 `pcr_percentage` 欄位保留不重命名。

#### Scenario: 列表頁顯示回收成分
- **WHEN** 使用者瀏覽物料主檔列表
- **THEN** 每列顯示「回收成分」欄：pcr_percentage > 0 顯示綠底 PCR badge；pir_percentage > 0 顯示藍底 PIR badge；兩者皆為 null/0 則顯示「—」

#### Scenario: 更新可回收欄位
- **WHEN** 呼叫 `PUT /api/v1/material-items/{id}` 帶有 pir_percentage / bio_based_percentage / recyclability_rating
- **THEN** 欄位驗證：百分比欄位須為 numeric 且介於 0~100；recyclability_rating 須為 high/medium/low/not_rated 其一；更新成功後回傳完整的 MaterialItem 物件

#### Scenario: recyclability_rating 不合法值
- **WHEN** 傳入 recyclability_rating 不在枚舉值內
- **THEN** 系統回傳 422 驗證錯誤，說明允許的值

### Requirement: 物料主檔列表 UX 精簡
物料主檔列表頁操作欄 SHALL 只保留「詳情」、「編輯」、「✕」三個按鈕；碳排、供應商、化學、回收四個功能按鈕移除，統一由詳情頁提供。列表頁新增行號欄（`#`）與「回收成分」欄。

#### Scenario: 列表分頁
- **WHEN** 列表超過 20 筆
- **THEN** 使用全域 `.pagination` + `.pg-btn` class，顯示「第 N / M 頁」，分頁位置與供應商列表一致

#### Scenario: 列表篩選清除
- **WHEN** 使用者設定了任一篩選條件（搜尋字串、群組、僅啟用）
- **THEN** 出現「✕ 清除」按鈕，點擊後重設所有篩選條件並重新載入第一頁
