## ADDED Requirements

### Requirement: 整頁 Edit 模式切換
明細頁 SHALL 提供「編輯」按鈕，點擊後切換為 edit 模式；edit 模式下提供「儲存」與「取消」按鈕。

#### Scenario: 進入 edit 模式
- **WHEN** 使用者點擊頁頭「編輯」按鈕
- **THEN** 所有可編輯欄位切換為 input/select，按鈕區顯示「儲存」「取消」，「編輯」按鈕隱藏

#### Scenario: 取消 edit 模式
- **WHEN** 使用者點擊「取消」
- **THEN** 所有欄位恢復為顯示模式，editForm 資料丟棄，supplier 原始資料不變

### Requirement: 可編輯欄位範圍
Edit 模式 SHALL 允許編輯：名稱、代碼、國家碼、Tier（1/2/3 select）、產業（text）、SASB 分類（select by sector optgroup）、供應商分組（select）、地址、網站。

#### Scenario: 儲存成功
- **WHEN** 使用者填寫表單後點擊「儲存」，API 回傳 200
- **THEN** 退出 edit 模式，重新 fetch 供應商資料，顯示更新後內容

#### Scenario: 儲存失敗
- **WHEN** API 回傳 4xx 錯誤
- **THEN** 保持 edit 模式，顯示錯誤訊息 alert，不清除 editForm

### Requirement: Edit 模式載入輔助資料
進入 edit 模式時 SHALL 非同步載入 SASB 產業列表（grouped by sector）與供應商分組列表，供下拉選單使用。

#### Scenario: 首次進入 edit 模式
- **WHEN** 使用者首次點擊「編輯」
- **THEN** 系統載入 SASB 列表與分組列表後，select 選項填入，當前值預選

#### Scenario: 再次進入 edit 模式
- **WHEN** 使用者已載入過輔助資料再次進入 edit
- **THEN** 不重新 fetch，直接使用已快取資料
