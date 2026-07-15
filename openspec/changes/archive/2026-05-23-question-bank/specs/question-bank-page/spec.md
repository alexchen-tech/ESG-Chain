## ADDED Requirements

### Requirement: 題庫管理頁路由與入口
系統 SHALL 提供 `/settings/question-bank` 路由（QuestionBankView.vue），SettingsView 的「問卷範本」Tab 旁新增「題目庫」Tab（key='bank'），點擊後跳轉至 `/settings/question-bank`。

#### Scenario: 進入題目庫頁面
- **WHEN** admin 點擊「題目庫」Tab
- **THEN** 導覽至 `/settings/question-bank`，顯示所有 template_id IS NULL 的題目

### Requirement: 題目庫列表與過濾
題庫頁 SHALL 顯示題目列表，每題顯示：分類 badge（E/S/G）、題文（截斷 60 字）、題型 badge、SASB Topic（若有）、Tags chip、usage_count badge、操作（編輯/刪除）。提供搜尋欄位（題文關鍵字）、E/S/G 下拉過濾、Tag 多選過濾。

#### Scenario: Tag 過濾
- **WHEN** 使用者勾選「ISO-環境」tag
- **THEN** 僅顯示 tags 含「ISO-環境」的題目

#### Scenario: 空題庫時顯示引導
- **WHEN** 題庫無任何題目
- **THEN** 顯示「尚無題庫題目，點擊新增第一道題」及新增按鈕

### Requirement: 新增/編輯題庫題目 Modal
題庫頁 SHALL 提供「+ 新增題目」按鈕開啟 Modal，欄位與 TemplateDetailView 的題目 Modal 相同，額外增加「標籤」多選區域（顯示 10 個預設 tag checkboxes）。

#### Scenario: 新增含多個 tag 的題庫題目
- **WHEN** 管理員選擇「E」和「ISO-環境」tag 並儲存
- **THEN** 題目建立，tags = ["E", "ISO-環境"]，usage_count = 0

### Requirement: 刪除題目確認
刪除有 usage_count > 0 的題庫題目 SHALL 顯示警告：「此題目已被 N 個範本引用（均為副本，刪除不影響範本），確認刪除？」

#### Scenario: 刪除被引用的題目
- **WHEN** usage_count = 3，admin 確認刪除
- **THEN** 題庫題目刪除，3 個範本中的副本不受影響（因為是快照，無 FK 依賴）
