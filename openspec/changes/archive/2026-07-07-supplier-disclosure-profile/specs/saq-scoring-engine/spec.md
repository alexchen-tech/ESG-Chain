## ADDED Requirements

### Requirement: Scored 事件觸發 Disclosure Sync

`SAQService::updateScore()` 在更新 SAQ 分數後 SHALL 呼叫 `DisclosureSyncService::syncFromSaq($saq)`。

#### Scenario: Sync 在 updateScore 之後執行

WHEN `scoreCallback` 成功寫入 score / grade / category_scores
THEN `DisclosureSyncService::syncFromSaq()` 被呼叫一次，傳入該 SAQ 實例

#### Scenario: Sync 例外不中斷評分

WHEN `DisclosureSyncService::syncFromSaq()` 拋出任何例外
THEN SAQ 的 score、grade、status 已寫入成功，例外被 catch 並寫入 Laravel log，HTTP response 正常回傳
