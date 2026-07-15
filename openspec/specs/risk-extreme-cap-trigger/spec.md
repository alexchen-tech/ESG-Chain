## MODIFIED Requirements

### Requirement: CAP 觸發條件改為 E1–E6 閾值
系統 SHALL 在 RiskAssessment 建立後，依據各維度分數與 `system_settings.cap_thresholds` 的閾值比對，取代舊的 `probability × impact ≥ 20` 觸發邏輯。

#### Scenario: 某維度低於閾值觸發 CAP
- **WHEN** 新 RiskAssessment（version='v3'）被建立
- **THEN** `RiskAssessmentObserver::checkAndCreateCap()` 讀取 `system_settings.cap_thresholds`
- **AND** 對 E1–E6 各維度比對：若 `dim_eN < threshold[N]`，產生一筆 CAPFinding（category=N，如 `E3`）
- **AND** 若至少一個 CAPFinding 產生，建立新 CAP（source_type='risk_assessment'，source_id=RA.id）
- **AND** 若所有維度皆達標，不建立 CAP

#### Scenario: dim_e6 為 null 時跳過 E6 判斷
- **WHEN** 新 RA 的 dim_e6 為 null（供應商無 E6 相關評核）
- **THEN** E6 閾值比對 SHALL 被跳過（不視為不達標）
- **AND** CAP 觸發僅基於 E1–E5 中有值的維度

#### Scenario: 地緣事件驅動 RA 同樣觸發 CAP
- **WHEN** Celery 回調建立新 RA（source_type='geo_event'）
- **THEN** Observer SHALL 對該 RA 執行相同的 E1–E6 閾值 CAP 觸發邏輯
- **AND** CAPFinding category 標記為 `E4`（地緣事件重算通常只更新 dim_e4）

#### Scenario: 閾值設定
- **GIVEN** `system_settings.cap_thresholds` 預設值為 `{"E1":40,"E2":40,"E3":35,"E4":35,"E5":40,"E6":40}`
- **WHEN** admin 修改 cap_thresholds 設定值
- **THEN** 後續新建 RA 的 CAP 觸發使用更新後的閾值
- **AND** 既有 RA 與 CAP 記錄 SHALL NOT 被回溯修改

#### Scenario: 舊 legacy RA 不觸發 CAP
- **WHEN** assessment_version 為 'legacy' 或 'v1' 的 RA 被建立（理論上不再發生，但防禦性處理）
- **THEN** Observer SHALL 跳過 CAP 閾值比對（因四軸欄位無法對應 E1–E6 閾值）
