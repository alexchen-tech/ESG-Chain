## 1. DB Migration

- [x] 1.1 建立 migration：`risk_assessments` 新增 `ai_suggestion` TEXT NULL 與 `ai_generated_at` TIMESTAMP NULL
- [x] 1.2 在 `RiskAssessment` Model 的 `$fillable` 加入兩個新欄位

## 2. esgchain-ai：risk-suggestion endpoint

- [x] 2.1 建立 `apps/ai/routers/risk_suggestion.py`，定義 `RiskSuggestionRequest` / `RiskSuggestionResponse` Pydantic schema
- [x] 2.2 實作 `POST /ai/v1/risk-suggestion`：組 Claude prompt，呼叫 Anthropic SDK，解析回傳 JSON
- [x] 2.3 在 `apps/ai/main.py` 註冊新 router
- [x] 2.4 確認 `ANTHROPIC_API_KEY` 在 esgchain-ai `.env` 中已設定（或補充 `.env.example`）

## 3. esgchain-api：proxy endpoint

- [x] 3.1 建立 `AiRiskSuggestionService`：取最新 RA、讀快取或轉發 esgchain-ai、存回 DB
- [x] 3.2 建立 `AiRiskSuggestionController`：`POST /api/v1/suppliers/{id}/risk-suggestion`
- [x] 3.3 在 `routes/api.php` 註冊新路由（需 JWT auth）
- [x] 3.4 加入 seed/測試資料：為宏遠興業最新 RA 預存一筆 `ai_suggestion` 快取（避免 demo 時等待 AI）

## 4. esgchain-web：AiRiskSuggestionPanel 元件與佈局

- [x] 4.1 建立 `src/components/suppliers/AiRiskSuggestionPanel.vue`（Options API），props: `supplierId`、`hasRa`
- [x] 4.2 元件邏輯：mounted 時若 hasRa 自動呼叫 API（force=false）；「重新產生」呼叫 force=true
- [x] 4.3 元件 UI：summary 摘要、分項建議列表（axis label + action）、產生時間、免責說明、重新產生按鈕
- [x] 4.4 修改 `SupplierDetailView.vue` 三軸風險評估區塊：改為 `.axis-suggestion-layout`（左右分欄），右欄嵌入 `AiRiskSuggestionPanel`
- [x] 4.5 新增 CSS：`.axis-suggestion-layout`（flex row, wrap < 900px）及 AI 面板樣式
