## MODIFIED Requirements

### Requirement: 合規文件效期狀態計算
系統 SHALL 根據 `expires_at` 與當前 UTC 時間動態計算每份文件的狀態：
- `valid`：expires_at > 今日 + 30 天
- `expiring_soon`：今日 < expires_at ≤ 今日 + 30 天
- `expired`：expires_at ≤ 今日
- `pending`：expires_at 為 null（長期有效文件或未填）

當文件狀態為 `expiring_soon` 或 `expired` 時，系統每日排程 SHALL 嘗試為該文件建立 CAP（依重複保護規則）。

#### Scenario: 計算 valid 狀態
- **WHEN** 查詢文件且 expires_at 距今超過 30 天
- **THEN** 回傳的 status 字段 SHALL 為 "valid"

#### Scenario: 計算 expiring_soon 狀態
- **WHEN** 查詢文件且 expires_at 距今 30 天內但尚未到期
- **THEN** 回傳的 status 字段 SHALL 為 "expiring_soon"

#### Scenario: 計算 expired 狀態
- **WHEN** 查詢文件且 expires_at 已過今日
- **THEN** 回傳的 status 字段 SHALL 為 "expired"

#### Scenario: expiring_soon 文件觸發 CAP 排程
- **WHEN** 文件狀態為 expiring_soon 且無有效 CAP 存在
- **THEN** 排程 SHALL 於下次執行時建立對應 CAP（詳見 compliance-cap-trigger spec）
