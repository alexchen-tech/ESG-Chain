## ADDED Requirements

### Requirement: 合規文件上傳（供應商）
供應商 SHALL 能透過 Portal 上傳合規文件，指定文件類型（doc_type）、有效起始日、到期日，並附上檔案。

#### Scenario: 供應商上傳文件成功
- **WHEN** 供應商透過 Portal 送出文件上傳請求（含 doc_type、file、expires_at）
- **THEN** 系統 SHALL 儲存文件記錄，status 計算為 valid（若 expires_at 未到期），回傳 201

#### Scenario: 供應商只能上傳自己的文件
- **WHEN** 供應商嘗試為其他 supplier_id 上傳文件
- **THEN** 系統 SHALL 回傳 403

#### Scenario: 文件大小超過限制
- **WHEN** 上傳檔案超過 10MB
- **THEN** 系統 SHALL 回傳 422，訊息說明檔案大小限制

### Requirement: 合規文件效期狀態計算
系統 SHALL 根據 `expires_at` 與當前 UTC 時間動態計算每份文件的狀態：
- `valid`：expires_at > 今日 + 30 天
- `expiring_soon`：今日 < expires_at ≤ 今日 + 30 天
- `expired`：expires_at ≤ 今日
- `pending`：expires_at 為 null（長期有效文件或未填）

#### Scenario: 計算 valid 狀態
- **WHEN** 查詢文件且 expires_at 距今超過 30 天
- **THEN** 回傳的 status 字段 SHALL 為 "valid"

#### Scenario: 計算 expiring_soon 狀態
- **WHEN** 查詢文件且 expires_at 距今 30 天內但尚未到期
- **THEN** 回傳的 status 字段 SHALL 為 "expiring_soon"

#### Scenario: 計算 expired 狀態
- **WHEN** 查詢文件且 expires_at 已過今日
- **THEN** 回傳的 status 字段 SHALL 為 "expired"

### Requirement: 合規文件審核（採購商）
採購商（admin/buyer/sustain/comply）SHALL 能審核供應商提交的合規文件，設定 verified_at 與 verified_by。

#### Scenario: 採購商審核文件
- **WHEN** 採購商呼叫審核 API（POST .../verify）
- **THEN** 系統 SHALL 設定 verified_at = now()、verified_by = 當前 user id，回傳 200

#### Scenario: 取消審核
- **WHEN** 採購商呼叫取消審核 API（DELETE .../verify）
- **THEN** 系統 SHALL 清空 verified_at 與 verified_by

### Requirement: 採購商查閱合規文件清單
採購商 SHALL 能依供應商、文件類型、狀態篩選查詢合規文件清單。

#### Scenario: 依供應商查詢文件清單
- **WHEN** 採購商呼叫 GET /suppliers/{id}/compliance-docs
- **THEN** 系統 SHALL 回傳該供應商所有合規文件，含動態計算的 status 欄位

#### Scenario: 依狀態篩選
- **WHEN** 請求帶有 ?status=expiring_soon 或 ?status=expired
- **THEN** 系統 SHALL 只回傳符合該狀態計算結果的文件
