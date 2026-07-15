## Purpose

定義供應商明細頁「概況」Tab 的內容結構與預設行為，包含風險評估 scorecard 首位顯示、識別資訊 + 產業分類 + 管理歸屬三個稀疏資訊集中合一展示，以及風險歷史事件流（timeline）。
## Requirements
### Requirement: 概況 Tab 為供應商明細預設 Tab

供應商明細頁 SHALL 以「概況」作為預設 Tab（`activeTab` 初始值為 `'overview'`）。概況 Tab 包含：（1）風險評估 scorecard（E/S/G/GP 四維度）、（2）識別資訊 + 管理歸屬 + 產業分類合一的 detail-grid、（3）**風險歷史事件流**（取代原靜態 table）。

#### Scenario: 首次開啟明細頁

- **WHEN** 使用者從供應商列表點擊進入明細頁
- **THEN** 頁面 SHALL 預設顯示「概況」Tab，風險評估 scorecard 顯示於 Tab 內容區最上方，風險歷史事件流顯示於 scorecard 下方

#### Scenario: 風險評估缺失時顯示空狀態

- **WHEN** 該供應商尚無風險評估資料且無已計分 SAQ
- **THEN** 概況 Tab SHALL 在 scorecard 區顯示「尚無風險評估資料」提示，歷史區塊顯示「尚無風險評估紀錄」空狀態與「前往風險矩陣填報」連結，其餘識別資訊正常顯示

### Requirement: 概況 Tab 合併識別資訊、產業分類、管理歸屬

概況 Tab 的 detail-grid SHALL 包含原本分散在三個 Tab 的資訊：供應商代碼、名稱、國家、幣別（識別資訊）、SASB 產業、次產業（產業分類）、負責採購員、所屬採購群組（管理歸屬）。

#### Scenario: 概況 Tab 顯示完整識別資訊

- **WHEN** 使用者切換至概況 Tab
- **THEN** 頁面 SHALL 在單一 Tab 內顯示供應商代碼、名稱、國家/地區、幣別、SASB 產業分類、負責採購員、所屬採購群組

#### Scenario: 稀疏欄位不佔用獨立 Tab

- **WHEN** 供應商頁面載入完成
- **THEN** 「產業分類」與「管理歸屬」SHALL 不作為獨立 Tab 存在，其資訊整合於概況 Tab 的 detail-grid 中

### Requirement: 風險歷史以事件流呈現

概況 Tab 的風險評估歷史區塊 SHALL 改為事件流（timeline）呈現，取代原靜態 table。事件流 SHALL 呼叫 `GET /api/v1/suppliers/:id/risk-timeline` 並依 `date` 降冪排列顯示三種事件卡片。

#### Scenario: 顯示 risk_assessment 事件卡片

- **WHEN** 事件流含 `type: "risk_assessment"` 的事件
- **THEN** SHALL 顯示橙色（自動）或灰色（手動）左邊框卡片，包含 E/S/G/GP 四維度 progress bar（分數/25）、各維度 level badge；若 `linked_saq` 不為 null，SHALL 顯示來源 SAQ 分數摘要

#### Scenario: 顯示 saq_scored 事件卡片

- **WHEN** 事件流含 `type: "saq_scored"` 的事件
- **THEN** SHALL 顯示藍色左邊框卡片，包含整體分數、grade chip、E/S/G 子分數（三欄並排），並顯示「提交於 YYYY-MM-DD」副文字

#### Scenario: 顯示 CAP 關聯徽章

- **WHEN** `risk_assessment` 事件的 `caps` 陣列非空
- **THEN** 該事件卡片 SHALL 顯示「⚠ N 項 CAP」徽章，open CAP 以紅色、closed 以灰色顯示

#### Scenario: 頂端顯示 pending_saq 卡

- **WHEN** API 回傳 `pending_saq` 不為 null
- **THEN** 事件流最頂端 SHALL 顯示黃色虛線框的 pending 卡，含「問卷已提交，等待計分」文字與提交日期，以 spinning 指示器表示進行中狀態

#### Scenario: 自動 RA 可連結至來源 SAQ

- **WHEN** `risk_assessment` 事件的 `linked_saq` 不為 null 且 `source_saq_id` 有值
- **THEN** 事件卡片 SHALL 顯示「來源：SAQ #id」連結，點擊導航至對應問卷詳情頁

