## ADDED Requirements

### Requirement: risk_assessments 記錄來源 SAQ

`risk_assessments` 表 SHALL 新增 `source_saq_id`（nullable UUID，FK → `saqs.id`）欄位。`RiskAssessmentObserver` 在自動建立 RA 時 SHALL 填入觸發來源的 SAQ ID；手動建立的 RA SHALL 保持 NULL。

#### Scenario: 自動建立 RA 時填入 source_saq_id

- **WHEN** SAQ 計分完成觸發 `RiskAssessmentObserver::created()`
- **THEN** 新建的 `RiskAssessment` 記錄 SHALL 有 `source_saq_id` 等於觸發 SAQ 的 ID

#### Scenario: 手動建立 RA 時 source_saq_id 為 NULL

- **WHEN** 使用者透過風險矩陣介面手動建立 RiskAssessment
- **THEN** `source_saq_id` SHALL 為 NULL

---

### Requirement: 供應商風險時間軸 API

系統 SHALL 提供 `GET /api/v1/suppliers/:id/risk-timeline` endpoint，回傳該供應商所有已計分 SAQ 與風險評估的時間排序統一事件流，並帶入關聯 CAP 資訊。

#### Scenario: 回傳統一事件陣列

- **WHEN** 呼叫 `GET /api/v1/suppliers/:id/risk-timeline`
- **THEN** 回應 SHALL 包含 `events` 陣列，每筆事件含 `type`（`risk_assessment` 或 `saq_scored`）、`date`、對應資料物件，依 `date` 降冪排列

#### Scenario: 自動建立的 RA 帶入 linked_saq

- **WHEN** 一筆 `risk_assessment` 事件的 `source_saq_id` 不為 NULL
- **THEN** 該事件 SHALL 包含 `linked_saq` 物件，含 `score`、`grade`、`score_e`、`score_s`、`score_g`、`submitted_at`

#### Scenario: RA 帶入關聯 CAP

- **WHEN** 某筆 RA 是 `caps.source_id` 的觸發來源
- **THEN** 該 `risk_assessment` 事件 SHALL 包含 `caps` 陣列，每筆含 `id`、`status`、`findings_count`

#### Scenario: 最新 SAQ 尚未計分時回傳 pending_saq

- **WHEN** 最新一筆 SAQ 的 `score IS NULL` 且 `status IN ('submitted', 'under_review')`
- **THEN** 回應 SHALL 包含頂層 `pending_saq` 物件，含 `id`、`status`、`submitted_at`

#### Scenario: 無資料時回傳空陣列

- **WHEN** 該供應商無任何已計分 SAQ 且無 RA 記錄
- **THEN** 回應 SHALL 為 `{ "events": [], "pending_saq": null }`

---

### Requirement: SupplierTimelineService 聚合邏輯

`SupplierTimelineService` SHALL 以單次查詢（UNION 或多表 eager-load）取得所有事件，不得對每筆事件發出 N+1 查詢。

#### Scenario: 無 N+1 查詢

- **WHEN** 供應商有 10 筆 RA 和 10 筆 SAQ
- **THEN** 總 SQL 查詢數 SHALL 不超過 5 次（RA、SAQ、CAP、linked SAQ、pending SAQ 各一次）
