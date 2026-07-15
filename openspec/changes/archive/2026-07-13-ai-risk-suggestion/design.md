## Context

供應商風險歷史頁面已有三軸風險評估（axis1/axis2/axis3）的數值顯示，但使用者（採購商、永續長）需要「建議行動」而不只是數字。AI 建議由 esgchain-ai 呼叫 LLM 產生，結果快取至 DB 避免重複計算。

依 CLAUDE.md 原則：計算/AI 邏輯一律在 esgchain-ai，esgchain-api 僅轉發並持久化結果。

## Goals / Non-Goals

**Goals:**
- 在三軸風險評估右側新增 AI 建議面板（左右分欄佈局）
- esgchain-ai 新增 `/ai/v1/risk-suggestion` endpoint 呼叫 Claude API
- esgchain-api 新增 proxy endpoint，存快取到 `risk_assessments.ai_suggestion`
- 前端顯示建議內容（快取優先，可手動重新產生）

**Non-Goals:**
- 不自動在 RA 建立時觸發 AI 建議（避免未使用的 API 消耗）
- 不支援多語言（繁體中文固定輸出）
- 不提供 AI 建議的歷史版本記錄

## Decisions

### 1. 快取策略：存在 risk_assessments 本身

**決定**：在 `risk_assessments` 表新增 `ai_suggestion` JSON TEXT 欄位與 `ai_generated_at` TIMESTAMP。

**理由**：建議內容與特定 RA 綁定；若供應商有新 RA，前端自動取最新 RA 的建議，不需額外關聯表。

**替代考慮**：獨立 `ai_suggestions` 表 → 過度設計，目前不需版本歷史。

### 2. 觸發方式：手動觸發 + 快取顯示

**決定**：頁面載入時若最新 RA 有 `ai_suggestion` 則直接顯示；按「重新產生」才呼叫 AI。

**理由**：避免每次開頁面都消耗 LLM API；使用者明確意圖觸發才計費。

### 3. AI 輸入資料範圍

傳入 esgchain-ai 的 payload：
```json
{
  "supplier": { "name": "...", "country_code": "TW", "tier": 1, "industry_name": "..." },
  "latest_ra": {
    "assessed_at": "...",
    "e": { "score": 4, "level": "very_low" },
    "s": { "score": 6, "level": "low" },
    "g": { "score": 8, "level": "low" },
    "gp": { "score": 3, "level": "very_low" },
    "axis1_score": 78.7,
    "axis2_score": 72.0,
    "axis3_score": 35.0
  },
  "latest_saq": {
    "grade": "B", "score": 85.1,
    "score_e": 80, "score_s": 75, "score_g": 84,
    "submitted_at": "..."
  },
  "open_cap_count": 0
}
```

### 4. AI 輸出格式

```json
{
  "summary": "整體風險低至中，軸1（ESG暴露）成熟度持續提升，建議維持年度自評節奏。",
  "recommendations": [
    { "axis": "axis1", "label": "ESG 暴露", "level": "high_score", "action": "建議持續推動..." },
    { "axis": "axis2", "label": "治理成熟度", "level": "high_score", "action": "建議確認..." },
    { "axis": "axis3", "label": "地緣產業", "level": "low_score", "action": "目前風險低，..." }
  ],
  "generated_at": "2026-07-09T10:36:07Z"
}
```

### 5. esgchain-api endpoint

`POST /api/v1/suppliers/{id}/risk-suggestion`：
1. 取最新 RA（含 SAQ 資料）
2. 若 `ai_suggestion` 非 null 且 `force=false`（預設），直接回傳快取
3. 否則 POST 至 esgchain-ai，存回 `ai_suggestion` 與 `ai_generated_at`，回傳結果

## Risks / Trade-offs

- **LLM 延遲**：Claude API 回應約 2-5 秒 → 前端顯示 loading 狀態，按鈕 disabled 防重複送出
- **AI 幻覺**：建議內容僅供參考，不作為決策依據 → UI 加「AI 產生，僅供參考」免責說明
- **esgchain-ai Claude API Key**：需確認 `.env` 已設定 `ANTHROPIC_API_KEY`

## Migration Plan

1. 執行 DB migration（新增兩欄，nullable，無 breaking change）
2. 部署 esgchain-ai 新 endpoint
3. 部署 esgchain-api 新 route + service
4. 部署前端新元件
