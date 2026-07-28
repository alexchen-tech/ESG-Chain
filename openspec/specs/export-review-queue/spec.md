### Requirement: 跨批號出口審查清單查詢
系統 SHALL 提供一個跨生產批號的出口審查清單查詢端點，以生產批號為查詢主體，未曾建立任何出口審查記錄的批號 SHALL 一併出現在清單中並標示為「未審查」，不得因為 `batch_export_reviews` 沒有資料而被排除在清單之外。

#### Scenario: 批號完全沒有審查記錄
- **WHEN** 某生產批號尚未被建立過任何 `BatchExportReview` 記錄
- **THEN** 該批號 SHALL 出現在清單中，狀態欄位顯示為「未審查」

#### Scenario: 批號已有審查記錄
- **WHEN** 某生產批號在指定市場已有一筆或多筆 `BatchExportReview` 記錄
- **THEN** 清單 SHALL 顯示該批號在該市場最新一筆審查記錄的狀態（pending/pass/warning/fail）與審查時間

### Requirement: 清單篩選與分頁
清單查詢 SHALL 支援依市場（EU/US/UK/JP/GLOBAL）、狀態（pending/pass/warning/fail/未審查）、生產日期區間篩選，並 SHALL 採用 server-side pagination，每頁固定 20 筆。

#### Scenario: 依狀態篩選出「未審查」批號
- **WHEN** 使用者以 `status=unreviewed` 篩選
- **THEN** 系統 SHALL 只回傳在指定市場（若有指定）沒有任何審查記錄的批號

#### Scenario: 分頁請求
- **WHEN** 使用者請求清單第 2 頁
- **THEN** 系統 SHALL 回傳第 21-40 筆資料，並附帶 `total`/`per_page`/`current_page`/`last_page` 分頁資訊

### Requirement: 清單自帶審查操作面板
清單頁本身 SHALL 提供展開式操作面板，讓使用者可直接對清單中任一批號執行出口市場審查、刪除審查記錄、產出 DDS 草稿、查看批次護照 JSON，核心審查操作不需要導向生產批號詳情頁。生產批號詳情頁 SHALL NOT 提供執行審查/刪除審查記錄等審查操作本身，但清單頁面板 SHALL 提供連結導向生產批號詳情頁，作為使用者補齊溯源或合規資料時的逃生艙口（escape hatch）。

#### Scenario: 展開清單項目的審查面板
- **WHEN** 使用者點擊清單中某一筆批號
- **THEN** 系統 SHALL 在該列下方展開審查面板（不導向其他頁面），首次展開時載入該批號的審查記錄

#### Scenario: 在清單頁直接執行審查
- **WHEN** 使用者在展開的面板中選擇市場並點擊「執行審查」
- **THEN** 系統 SHALL 呼叫既有的批號審查 API，審查完成後面板內容即時更新，且清單列的狀態欄位同步反映最新結果

#### Scenario: 在清單頁查看批次護照
- **WHEN** 使用者在展開的面板中點擊「查看批次護照」
- **THEN** 系統 SHALL 顯示該批號的批次護照 JSON，不需要離開出口審查頁面

#### Scenario: 清單頁提供導向批號詳情頁的連結
- **WHEN** 使用者在清單中點擊批號本身，或在展開面板中點擊「前往補齊溯源資料」
- **THEN** 系統 SHALL 開啟生產批號詳情頁（新分頁），讓使用者可以補齊原料溯源或查看供應鏈合規資料，之後不強制導回出口審查頁

#### Scenario: 生產批號詳情頁不提供審查操作
- **WHEN** 使用者進入生產批號詳情頁
- **THEN** 頁面 SHALL 顯示「批號資訊」「碳足跡與循環經濟」「有害物質揭露」「供應鏈合規」四個分頁，供使用者查看與編輯批次相關資料
- **AND** 頁面 SHALL NOT 提供「執行出口市場審查」「刪除審查記錄」等審查操作本身，該操作仍只存在於出口審查清單頁的面板中
