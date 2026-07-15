## MODIFIED Requirements

### Requirement: 六維分數寫入 risk_assessments
`RiskAutoDerivationService`（或其繼任服務）在接收到 esgchain-ai 的計分結果後，SHALL 將 dim_e1–dim_e6 寫入對應的 `risk_assessments` 記錄，並依 D6 投影規則更新四軸欄位。assessment_version 標記為 'v2'。

#### Scenario: 新版計分結果觸發六維寫入
- **WHEN** esgchain-ai 呼叫 `POST /api/v1/saq/{id}/score-callback` 並在 payload 帶有 `dim_e1–dim_e6`
- **THEN** RiskAutoDerivationService 以 `source_saq_id` 查找或建立 risk_assessment，寫入六維分數，計算四軸，標記 assessment_version='v2'

#### Scenario: 舊版 payload 不含六維分數的相容處理
- **WHEN** score-callback payload 不含 dim_e1–dim_e6 欄位（legacy AI 版本）
- **THEN** 系統以舊版邏輯推導三軸，dim_e1–dim_e6 為 null，assessment_version='legacy'

### Requirement: 唯一性保證（source_saq_id）
每個 SAQ 問卷（saq_id）在 `risk_assessments` 中 SHALL 最多有一筆對應記錄（`UNIQUE(source_saq_id)`）。若重算觸發（如重新計分），系統以 upsert 取代舊值，不新增記錄。

#### Scenario: 重算不建立新記錄
- **WHEN** 採購方觸發 SAQ 重新計分（如 E4 外部資料更新後）
- **THEN** 系統 upsert `source_saq_id` 對應的既有記錄，updated_at 更新，不新增第二筆記錄
