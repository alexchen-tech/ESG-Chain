## ADDED Requirements

### Requirement: 識別資訊區塊
明細頁 SHALL 顯示「識別資訊」區塊，包含：名稱、代碼、國家碼、Tier、建立時間。

#### Scenario: 顯示識別資訊
- **WHEN** 使用者進入供應商明細頁
- **THEN** 顯示名稱、代碼（font-mono）、國家碼、Tier、建立時間

### Requirement: 產業分類區塊
明細頁 SHALL 顯示「產業分類」區塊，包含：產業文字、SASB code + industry name（from eager loaded sasbIndustry）。

#### Scenario: 有 SASB 分類時顯示
- **WHEN** supplier.sasb_industry 不為 null
- **THEN** 顯示 `{code} — {industry}` 格式

#### Scenario: 無 SASB 分類時顯示佔位
- **WHEN** supplier.sasb_industry 為 null
- **THEN** 顯示「—」

### Requirement: 管理歸屬區塊
明細頁 SHALL 顯示「管理歸屬」區塊，包含：供應商分組（group.name）、onboarding 階段。

#### Scenario: 有分組時顯示分組名稱
- **WHEN** supplier.group 不為 null
- **THEN** 顯示 group.name

#### Scenario: 無分組時顯示佔位
- **WHEN** supplier.group 為 null
- **THEN** 顯示「未分組」

### Requirement: 聯絡資訊區塊
明細頁 SHALL 顯示「聯絡資訊」區塊，包含：地址、網站、聯絡人列表（contacts[]，顯示姓名/職稱/Email，primary 聯絡人標記）。

#### Scenario: 顯示聯絡人列表
- **WHEN** supplier.contacts 有資料
- **THEN** 每筆顯示姓名、職稱、email；is_primary=true 者加「主要」badge

#### Scenario: 無聯絡人時顯示佔位
- **WHEN** supplier.contacts 為空陣列
- **THEN** 顯示「尚無聯絡人」

### Requirement: 狀態歷程 Timeline
明細頁 SHALL 在頁尾顯示狀態歷程 vertical timeline，資料來自 statusHistories[]，按 created_at 降序排列（最新在上）。

#### Scenario: 顯示歷程記錄
- **WHEN** supplier.statusHistories 有資料
- **THEN** 每筆顯示：from_status → to_status（以箭頭連接）、reason（若有）、日期時間

#### Scenario: 無歷程時顯示提示
- **WHEN** supplier.statusHistories 為空
- **THEN** 顯示「尚無狀態變更記錄」
