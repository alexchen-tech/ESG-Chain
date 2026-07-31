# export-review-queue Specification
## Purpose
跨生產批號的出口市場審查清單：查詢、篩選分頁與清單內建的審查操作面板規則。
## Requirements
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
清單頁本身 SHALL 提供展開式操作面板，讓使用者可直接對清單中任一批號執行出口市場審查、刪除審查記錄、產出 DDS 草稿、查看批次護照 JSON。批次護照與 DDS 草稿 SHALL 顯示該筆審查是「完整審查」還是特定法規範疇（`program`），以及該筆審查結果是否可能已過期（`possibly_stale`，供應商合規文件在審查後有更新）；這兩個欄位的判斷邏輯 SHALL 在出貨關卡查詢（`gate-check`）、DDS 草稿（`dds-draft`）、批次護照（`passport`）三個端點間保持一致。

#### Scenario: 展開清單項目的審查面板
- **WHEN** 使用者點擊清單中某一筆批號
- **THEN** 系統 SHALL 在該列下方展開審查面板（不導向其他頁面），首次展開時載入該批號的審查記錄

#### Scenario: 在清單頁直接執行審查
- **WHEN** 使用者在展開的面板中選擇市場並點擊「執行審查」
- **THEN** 系統 SHALL 呼叫既有的批號審查 API，審查完成後面板內容即時更新，且清單列的狀態欄位同步反映最新結果

#### Scenario: 在清單頁查看批次護照
- **WHEN** 使用者在展開的面板中點擊「查看批次護照」
- **THEN** 系統 SHALL 顯示該批號的批次護照 JSON，每筆 `export_market_reviews` 項目 SHALL 包含 `program` 與 `possibly_stale` 欄位，不需要離開出口審查頁面

#### Scenario: 三個彙總端點的過期判斷一致
- **WHEN** 某批號的供應商合規文件在審查完成後被更新（`updated_at` 晚於 `reviewed_at`）
- **THEN** `gate-check`、`dds-draft`、`passport` 三個端點對同一筆審查記錄的 `possibly_stale` SHALL 回傳相同結果，不得互相矛盾

### Requirement: 法規範疇篩選審查
執行審查時，使用者 SHALL 可選定一個法規範疇（見 `MarketComplianceRule::PROGRAMS`），選定後系統 SHALL 只執行該範疇對應的檢查項目；未選定範疇時系統 SHALL 執行完整審查（涵蓋全部範疇），行為與既有未提供範疇篩選前完全相同。

#### Scenario: 選定 DPP 範疇
- **WHEN** 使用者對某批號選定 EU 市場、法規範疇為 DPP，並執行審查
- **THEN** 審查結果 SHALL 只包含市場文件規則（篩選為 DPP 範疇的規則）、批次 PCF、DPP 六大類別、電池 DPP（如適用）的檢查項目，不 SHALL 包含 EUDR 或 UFLPA 溯源檢查

#### Scenario: 選定 CBAM 範疇
- **WHEN** 使用者對某批號選定 EU 市場、法規範疇為 CBAM，並執行審查
- **THEN** 審查結果 SHALL 只包含市場文件規則（篩選為 CBAM 範疇的規則）與批次 PCF，不 SHALL 包含 EUDR/UFLPA/DPP 相關檢查項目

#### Scenario: 未選定範疇時執行完整審查
- **WHEN** 使用者執行審查時未選定法規範疇
- **THEN** 系統 SHALL 執行該市場全部適用的檢查項目，與未提供範疇篩選前的既有行為一致

#### Scenario: 審查結果顯示範疇標籤
- **WHEN** 使用者查看已完成的審查結果或 DDS 草稿
- **THEN** 系統 SHALL 顯示該筆審查是「完整審查」還是特定法規範疇，不得讓使用者誤以為範疇審查結果等同完整審查結果

### Requirement: 出貨關卡查詢 API
系統 SHALL 提供 `GET production-batches/{batchId}/gate-check` 端點，讓外部模組（如出貨流程）查詢指定生產批號在指定市場的出口審查關卡狀態，市場為必填 query 參數且值需在 `BatchExportReview::MARKETS` 內。

#### Scenario: 查詢已審查市場的關卡狀態
- **WHEN** 呼叫 `GET production-batches/{batchId}/gate-check?market=EU`，該批號在 EU 市場已有審查記錄
- **THEN** 系統 SHALL 回傳 `market`/`program`/`status`/`blocked`/`reviewed_at`/`possibly_stale`/`findings`，`blocked` 於 `status` 為 `fail` 時 SHALL 為 `true`

#### Scenario: 查詢未審查市場的關卡狀態
- **WHEN** 呼叫 `gate-check` 端點時該批號在指定市場尚無任何審查記錄
- **THEN** 系統 SHALL 回傳 `status: "missing"`、`blocked: false`，不得回傳錯誤

#### Scenario: 未提供或提供不合法市場代碼
- **WHEN** 呼叫 `gate-check` 端點未帶 `market` 參數，或帶入不在 `BatchExportReview::MARKETS` 內的值
- **THEN** 系統 SHALL 回傳驗證錯誤，不得執行查詢

