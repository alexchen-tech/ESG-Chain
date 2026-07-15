## MODIFIED Requirements

### Requirement: 概況 Tab 為供應商明細預設 Tab

供應商明細頁 SHALL 以「概況」作為預設 Tab（`activeTab` 初始值為 `'overview'`）。概況 Tab 包含：（1）風險評估 scorecard（E/S/G/GP 四維度）、（2）識別資訊 + 管理歸屬 + 產業分類合一的 detail-grid、（3）**風險評估歷史區塊**（位於 Tab 最下方）。

#### Scenario: 首次開啟明細頁

- **WHEN** 使用者從供應商列表點擊進入明細頁
- **THEN** 頁面 SHALL 預設顯示「概況」Tab，風險評估 scorecard 顯示於 Tab 內容區最上方，風險評估歷史顯示於最下方

#### Scenario: 風險評估缺失時顯示空狀態

- **WHEN** 該供應商尚無風險評估資料
- **THEN** 概況 Tab SHALL 在 scorecard 區顯示「尚無風險評估資料」提示，歷史區塊顯示「尚無風險評估紀錄」空狀態與「前往風險矩陣填報」連結，其餘識別資訊正常顯示
