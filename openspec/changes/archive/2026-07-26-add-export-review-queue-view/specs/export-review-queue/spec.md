## ADDED Requirements

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

### Requirement: 清單導向既有審查功能
清單頁本身 SHALL NOT 提供新增、編輯或刪除出口審查記錄的操作；使用者從清單點擊某一列後，SHALL 被導向對應生產批號詳情頁既有的出口審查功能區塊進行處理。

#### Scenario: 點擊清單項目
- **WHEN** 使用者點擊清單中某一筆批號
- **THEN** 系統 SHALL 導向該生產批號詳情頁，並定位到既有的出口審查分頁區塊
