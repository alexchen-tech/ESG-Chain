## ADDED Requirements

### Requirement: 國家常數資料
系統 SHALL 提供 `src/utils/countries.ts`，包含 196 個 ISO 3166-1 alpha-2 國家，每筆含 `code`（2字母大寫）、`name`（繁體中文）、`name_en`（英文）。

#### Scenario: 常用亞太國家存在
- **WHEN** 引用 COUNTRIES 常數
- **THEN** 包含 TW/CN/JP/KR/VN/TH/MY/SG/IN/ID 等亞太國家，且 code 全為大寫

#### Scenario: 總筆數正確
- **WHEN** 取得 COUNTRIES.length
- **THEN** 回傳 196

### Requirement: CountrySelect Combobox 搜尋
CountrySelect.vue SHALL 提供文字輸入框，輸入時即時篩選國家清單，同時匹配中文名稱、英文名稱、國家代碼（大小寫不敏感），最多顯示 10 筆。

#### Scenario: 輸入代碼搜尋
- **WHEN** 使用者輸入 "tw"（不分大小寫）
- **THEN** 下拉顯示包含「台灣 (TW)」的選項

#### Scenario: 輸入中文名稱搜尋
- **WHEN** 使用者輸入「台」
- **THEN** 下拉顯示包含「台灣 (TW)」的選項

#### Scenario: 無匹配時顯示提示
- **WHEN** 輸入無任何國家匹配的字串
- **THEN** 下拉顯示「無符合國家」提示，不顯示選項

#### Scenario: 超過 10 筆時截斷
- **WHEN** 搜尋結果超過 10 筆
- **THEN** 只顯示前 10 筆，並顯示「繼續輸入以縮小範圍」提示

### Requirement: CountrySelect 選定與清空
使用者點選選項後 SHALL emit `update:modelValue` 傳出 2 字元大寫 code；清空輸入後 SHALL emit `update:modelValue` 傳出 null。

#### Scenario: 選定國家
- **WHEN** 使用者點選下拉中的「台灣 (TW)」
- **THEN** input 顯示「台灣 (TW)」，emit update:modelValue 傳出 "TW"，下拉關閉

#### Scenario: 清空輸入
- **WHEN** 使用者清空 input 內容後失焦
- **THEN** emit update:modelValue 傳出 null

### Requirement: CountrySelect 初始值顯示
當父層傳入非空 modelValue 時，input SHALL 顯示對應的「中文名稱 (XX)」格式。

#### Scenario: 初始值 "TW"
- **WHEN** `modelValue="TW"` 傳入元件
- **THEN** input 顯示「台灣 (TW)」

#### Scenario: 初始值 null 或空字串
- **WHEN** modelValue 為 null 或空字串
- **THEN** input 顯示 placeholder「搜尋國家...」

### Requirement: Esc 關閉下拉
按下 Esc 鍵 SHALL 關閉下拉選單並保留現有選定值不變。

#### Scenario: 下拉開啟時按 Esc
- **WHEN** 下拉選單開啟中，使用者按 Esc
- **THEN** 下拉關閉，input 恢復顯示已選定的值（或空白）
