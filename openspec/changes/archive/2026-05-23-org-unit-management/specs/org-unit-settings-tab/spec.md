## ADDED Requirements

### Requirement: 組織架構 Tab 置於設定頁第一位
系統設定頁 SHALL 將「組織架構」Tab 置於最前，原有「問卷範本」、「供應商分組」、「SASB 產業分類」後移。

#### Scenario: 進入系統設定頁
- **WHEN** admin 導覽至 `/settings`
- **THEN** 預設選中「組織架構」Tab，顯示樹狀組織圖

### Requirement: 樹狀展示組織單位
組織架構 Tab SHALL 以縮排樹狀列表呈現所有 OU，每節點顯示名稱、代碼、類型 badge、國家碼，並提供展開/收合。

#### Scenario: 顯示多層樹狀
- **WHEN** 已有 3 層 OU 資料
- **THEN** 以縮排方式呈現父子關係，子節點預設展開

#### Scenario: 無資料時顯示引導
- **WHEN** 尚無任何 OU
- **THEN** 顯示「尚無組織單位，請建立根節點」及新增按鈕

### Requirement: 新增組織單位互動
頁面 SHALL 提供 Modal 表單，欄位包含：名稱（必填）、代碼（必填）、類型（下拉）、上層單位（建立非 L1 時必填）、國家碼（預設 TW）。

#### Scenario: 建立 L1 公司節點
- **WHEN** admin 點擊「+ 新增組織單位」，選擇類型「母公司」
- **THEN** 上層單位欄位隱藏，送出後樹狀更新

#### Scenario: 建立子節點
- **WHEN** admin 選擇類型非「母公司」
- **THEN** 上層單位下拉顯示，列出可選 parent（depth < 4 的節點）

### Requirement: 編輯與刪除操作
每個節點 SHALL 提供編輯（inline 或 Modal）與刪除按鈕；有子節點時刪除按鈕 disabled 並顯示 tooltip。

#### Scenario: 刪除有子節點的節點
- **WHEN** 節點有子節點
- **THEN** 刪除按鈕 disabled，hover 顯示「請先移除子單位」

#### Scenario: 刪除葉節點
- **WHEN** 節點無子節點，admin 點擊刪除
- **THEN** 顯示確認 Modal，確認後節點從樹狀移除
