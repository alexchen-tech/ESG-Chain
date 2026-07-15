## ADDED Requirements

### Requirement: Scope 3 活動資料推送至外部計算服務

`ActivityDataReport` 核實後，系統 SHALL 透過 esgchain-ai Celery Task 將活動資料推送至外部 Scope 3 計算服務。推送結果記錄於 `ActivityDataReport.push_log`。

推送失敗 SHALL NOT 影響審核狀態（`status` 已為 `verified`）。系統 SHALL 支援手動重送。

#### Scenario: 核實後自動推送

- **WHEN** 買方核實 ActivityDataReport（`status → verified`）
- **THEN** Laravel 透過 Guzzle 呼叫 `POST /ai/v1/celery/scope3-push`（esgchain-ai 內部端點，不掛 JWT middleware），Celery Worker 非同步執行推送，推送結果更新 `push_log`

#### Scenario: 推送成功

- **WHEN** 外部 Scope 3 服務回傳 `200 OK` 含 `external_record_id`
- **THEN** `push_log = { status: 'success', external_record_id: '...', pushed_at: '...' }`

#### Scenario: 推送失敗（網路錯誤或外部系統異常）

- **WHEN** 外部服務回傳 5xx 或連線逾時
- **THEN** `push_log = { status: 'failed', error: '...', attempted_at: '...' }`，`ActivityDataReport.status` 保持 `verified` 不回退

#### Scenario: 手動重送

- **WHEN** 買方呼叫 `POST /api/v1/suppliers/{supplier}/activity-reports/{report}/push`
- **THEN** 系統再次觸發 Celery Task，新的推送結果覆寫 `push_log`
