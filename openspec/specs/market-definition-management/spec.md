### Requirement: 目標市場 CRUD
系統 SHALL 在設定頁提供「目標市場」Tab，讓管理員建立、編輯自訂市場代碼（MarketDefinition）。市場代碼（code）在系統內唯一，格式為大寫底線（如 `US_MARKET`、`BRAND_PATAGONIA`）。系統預載常用市場（US_MARKET、EU_MARKET、JP_MARKET、UK_MARKET、GLOBAL）不可刪除。

#### Scenario: 建立自訂市場
- **WHEN** 管理員填寫市場代碼、標籤名稱、說明並送出
- **THEN** 系統建立 MarketDefinition（is_system=false），清單即時更新

#### Scenario: 市場代碼格式驗證
- **WHEN** 管理員輸入不符合格式（含小寫或特殊字元）的 code 並送出
- **THEN** 系統回傳 422，說明 code 須為大寫字母與底線組成

#### Scenario: 刪除系統預載市場
- **WHEN** 管理員嘗試刪除 is_system=true 的市場定義
- **THEN** 系統回傳 422，說明系統預載市場不可刪除

#### Scenario: 市場清單顯示系統與自訂分類
- **WHEN** 管理員開啟目標市場 Tab
- **THEN** 系統預載市場列顯示「系統」標籤且操作欄只有「編輯」（無刪除）；自訂市場有「編輯」和「刪除」兩個操作

### Requirement: 目標市場代碼與 ERP 解耦
MarketDefinition.code SHALL 為系統內部自訂值，不依賴 ERP 系統的市場代碼格式或 ID。

#### Scenario: 建立對應 ERP 市場的自訂代碼
- **WHEN** 管理員建立代碼 `US_MARKET`，說明「對應 ERP US01、US02 市場」
- **THEN** 系統儲存此定義，未來 BuyerProduct.target_markets 參照此 code，不直接使用 ERP 代碼
