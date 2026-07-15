## ADDED Requirements

### Requirement: SAQ 評分完成後自動建立 RiskAssessment

當 `scoreCallback()` 成功更新 SAQ 分數且 SAQ 包含維度分數（score_e / score_s / score_g 至少一項非 null）時，系統 SHALL 自動建立一筆 `risk_assessments` 記錄。

換算規則：
- `{dim}_probability = max(1, ceil((100 - score_{dim}) / 20))`
- `{dim}_impact = 3`（固定基準）
- `gp_probability` 與 `gp_impact`：不自動填入，設為 null 或保留上一次手動值
- `assessed_by = null`（系統自動）
- `notes = '自動從 SAQ {saq_id} 推導'`

若 score_e / score_s / score_g 全為 null，SHALL 跳過自動建立，不建立 RiskAssessment。

#### Scenario: SAQ 有維度分數時自動建立

- **WHEN** `scoreCallback()` 接收到含 `score_e`、`score_s`、`score_g` 的評分結果
- **THEN** 系統 SHALL 自動建立一筆 RiskAssessment，E/S/G 的 probability 依換算規則設定，impact 均為 3，assessed_at 為當下時間

#### Scenario: score_e = 20 時換算 probability

- **WHEN** score_e = 20
- **THEN** `e_probability = ceil((100 - 20) / 20) = 4`

#### Scenario: score_e = 100 時換算 probability

- **WHEN** score_e = 100（最高分）
- **THEN** `e_probability = max(1, ceil(0/20)) = 1`（最低風險）

#### Scenario: score_e = 0 時換算 probability

- **WHEN** score_e = 0（最低分）
- **THEN** `e_probability = max(1, ceil(100/20)) = 5`（最高風險）

#### Scenario: SAQ 無維度分數時跳過

- **WHEN** scoreCallback 的 score_e / score_s / score_g 均為 null
- **THEN** 系統 SHALL 不建立 RiskAssessment，正常回傳評分結果

#### Scenario: GP 維度不自動填入

- **WHEN** 自動建立 RiskAssessment
- **THEN** `gp_probability` 與 `gp_impact` SHALL 不被自動設定；使用者可事後在 RiskAssessment 編輯介面手動補填

### Requirement: 自動建立的 RiskAssessment 可由使用者編輯

自動建立的 RiskAssessment 與手動建立者在資料格式上完全相同，使用者 SHALL 可透過現有風險矩陣介面或供應商詳情頁對所有欄位（含 probability、impact、GP 維度）進行編輯。

#### Scenario: 使用者編輯自動建立的評估

- **WHEN** 使用者對自動建立的 RiskAssessment 進行 PATCH（修改 gp_probability/gp_impact 或調整 e_impact）
- **THEN** 系統 SHALL 接受更新並回傳 200，更新後的值即為該筆評估的最終值
