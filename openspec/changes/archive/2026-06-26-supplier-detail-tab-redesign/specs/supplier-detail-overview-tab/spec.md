## ADDED Requirements

### Requirement: 概況 Tab 為供應商明細預設 Tab

供應商明細頁 SHALL 以「概況」作為預設 Tab（`activeTab` 初始值為 `'overview'`）。概況 Tab 包含：（1）風險評估 scorecard（E/S/G/GP 四維度）、（2）識別資訊 + 管理歸屬 + 產業分類合一的 detail-grid。

#### Scenario: 首次開啟明細頁

- **WHEN** 使用者從供應商列表點擊進入明細頁
- **THEN** 頁面 SHALL 預設顯示「概況」Tab，風險評估 scorecard 顯示於 Tab 內容區最上方

#### Scenario: 風險評估缺失時顯示空狀態

- **WHEN** 該供應商尚無風險評估資料
- **THEN** 概況 Tab SHALL 顯示「尚無風險評估資料」提示，其餘識別資訊正常顯示

### Requirement: 概況 Tab 合併識別資訊、產業分類、管理歸屬

概況 Tab 的 detail-grid SHALL 包含原本分散在三個 Tab 的資訊：供應商代碼、名稱、國家、幣別（識別資訊）、SASB 產業、次產業（產業分類）、負責採購員、所屬採購群組（管理歸屬）。

#### Scenario: 概況 Tab 顯示完整識別資訊

- **WHEN** 使用者切換至概況 Tab
- **THEN** 頁面 SHALL 在單一 Tab 內顯示供應商代碼、名稱、國家/地區、幣別、SASB 產業分類、負責採購員、所屬採購群組

#### Scenario: 稀疏欄位不佔用獨立 Tab

- **WHEN** 供應商頁面載入完成
- **THEN** 「產業分類」與「管理歸屬」SHALL 不作為獨立 Tab 存在，其資訊整合於概況 Tab 的 detail-grid 中
