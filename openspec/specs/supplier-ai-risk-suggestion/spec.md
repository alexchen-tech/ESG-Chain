## ADDED Requirements

### Requirement: AI 風險建議產生

系統 SHALL 提供 `POST /api/v1/suppliers/{id}/risk-suggestion` endpoint，根據該供應商最新 RiskAssessment 的 E/S/G/GP 分數與三軸分數，呼叫 esgchain-ai 產生繁體中文建議，並將結果快取至 `risk_assessments.ai_suggestion`。

Request body（可選）：
- `force: boolean`（預設 false）— true 時忽略快取強制重新產生

Response：
```json
{
  "cached": true,
  "generated_at": "2026-07-09T10:36:07Z",
  "suggestion": {
    "summary": "...",
    "recommendations": [
      { "axis": "axis1", "label": "ESG 暴露", "action": "..." },
      { "axis": "axis2", "label": "治理成熟度", "action": "..." },
      { "axis": "axis3", "label": "地緣產業", "action": "..." }
    ]
  }
}
```

#### Scenario: 首次產生建議

- **WHEN** 最新 RA 的 `ai_suggestion` 為 null，呼叫 POST /api/v1/suppliers/{id}/risk-suggestion
- **THEN** 系統 SHALL 呼叫 esgchain-ai，存回結果，回傳 `cached: false` 與建議內容

#### Scenario: 回傳快取建議

- **WHEN** 最新 RA 的 `ai_suggestion` 非 null 且 `force=false`
- **THEN** 系統 SHALL 直接回傳快取結果，不呼叫 AI，回傳 `cached: true`

#### Scenario: 強制重新產生

- **WHEN** request body 帶 `force: true`
- **THEN** 系統 SHALL 忽略快取，重新呼叫 AI，更新 `ai_suggestion` 與 `ai_generated_at`

#### Scenario: 供應商無 RA 資料

- **WHEN** 供應商尚無任何 RiskAssessment
- **THEN** 系統 SHALL 回傳 422 錯誤，訊息「尚無風險評估資料，無法產生建議」

### Requirement: esgchain-ai risk-suggestion endpoint

esgchain-ai SHALL 提供 `POST /ai/v1/risk-suggestion` endpoint，接收供應商與 RA 資料 payload，呼叫 Claude API，回傳結構化建議 JSON。

輸出語言固定為繁體中文。建議內容針對 axis1、axis2、axis3 各給一條具體行動建議，加上整體摘要（不超過 60 字）。

#### Scenario: 成功產生建議

- **WHEN** 收到有效 payload（含 latest_ra 欄位）
- **THEN** 系統 SHALL 回傳包含 summary 與三條 recommendations 的 JSON

#### Scenario: axis3 為 null 時略過

- **WHEN** payload 中 `latest_ra.axis3_score` 為 null
- **THEN** 系統 SHALL 在 recommendations 中省略 axis3 項目，只回傳 axis1、axis2 建議

### Requirement: DB 欄位擴充

`risk_assessments` 表 SHALL 新增：
- `ai_suggestion` TEXT NULL — 儲存 AI 建議 JSON 字串
- `ai_generated_at` TIMESTAMP NULL — 最後一次 AI 產生時間

#### Scenario: migration 執行

- **WHEN** 執行 migration
- **THEN** 既有 risk_assessments 記錄的兩欄預設為 NULL，不影響現有功能
