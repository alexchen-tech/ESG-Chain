## ADDED Requirements

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

## MODIFIED Requirements

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
