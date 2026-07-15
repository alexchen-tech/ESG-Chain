## ADDED Requirements

### Requirement: 從題庫選題 API（快照複製）
`POST /api/v1/settings/questionnaire-templates/:id/import-from-bank` SHALL 接受 `question_ids: string[]`，將每個題庫題目**複製**一份建立新 saq_questions 記錄（template_id = 目標範本 id、source_bank_question_id = 來源題庫題目 id、order = 現有最大 order + 序號），回傳新建題目列表。

#### Scenario: 從題庫複製 3 道題到範本
- **WHEN** POST 帶 question_ids 含 3 個有效題庫題目 id
- **THEN** 建立 3 筆新 saq_questions（template_id = 目標範本，source_bank_question_id 各自對應），回傳 201 含 3 筆新題目資料

#### Scenario: 題目不是題庫題目（template_id IS NOT NULL）
- **WHEN** question_ids 中某個 id 的 template_id 不為 NULL
- **THEN** 回傳 422「ID xxx 不是題庫題目」

#### Scenario: 同一題庫題目可重複複製到同一範本
- **WHEN** 題庫題目已被複製到某範本，再次複製
- **THEN** 允許（建立第二個副本），不報錯

### Requirement: TemplateDetailView 加「從題庫選題」按鈕
`TemplateDetailView.vue` 的題目列表標題旁 SHALL 新增「從題庫選題」按鈕，點擊開啟選題 Modal。

#### Scenario: 開啟選題 Modal
- **WHEN** admin 點擊「從題庫選題」
- **THEN** Modal 顯示題庫所有題目（含過濾），每題可勾選，底部顯示「已選 N 道」及「加入範本」按鈕

### Requirement: 選題 Modal 功能
選題 Modal SHALL 支援：搜尋欄位（題文關鍵字）、E/S/G 過濾、Tag 過濾、每題顯示 usage_count，以 checkbox 多選，「加入範本」按鈕（disabled when 0 selected）呼叫 import-from-bank API。

#### Scenario: 選題後加入範本
- **WHEN** 使用者勾選 2 道題後點擊「加入範本」
- **THEN** 呼叫 import-from-bank API，成功後重新整理題目列表，顯示新加入的 2 道題

#### Scenario: 題庫為空時選題 Modal
- **WHEN** 題庫無任何題目
- **THEN** Modal 顯示「題庫目前沒有題目，請先到題目庫新增」及「前往題目庫」連結
