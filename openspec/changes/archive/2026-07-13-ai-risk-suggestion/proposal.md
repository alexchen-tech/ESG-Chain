## Why

供應商風險歷史頁面已能呈現 E/S/G/GP 矩陣與三軸評分，但採購商與永續團隊需要在數字之外得到可行動的建議——「這個分數代表什麼？接下來應該做什麼？」。引入 AI 建議面板，讓 esgchain-ai 根據最新 RA 資料自動生成針對該供應商的風險改善建議，降低分析師解讀成本。

## What Changes

- 在供應商風險歷史頁面的三軸風險評估區塊右側，新增 AI 建議面板
- 建議文字由 esgchain-ai 呼叫 LLM 產生，結果快取至 DB
- 首次開啟若已有快取直接顯示；使用者可按「重新產生」手動觸發更新
- 建議內容針對軸1（ESG暴露）、軸2（治理成熟度）、軸3（地緣產業）分項說明，並附整體摘要

## Capabilities

### New Capabilities
- `supplier-ai-risk-suggestion`: 針對供應商最新風險評估產生 AI 建議，包含分項行動建議與整體摘要，快取至 DB 並可手動重新產生

### Modified Capabilities
- `supplier-risk-history`: 三軸風險評估區塊改為左右分欄，右欄嵌入 AI 建議面板

## Impact

- **esgchain-ai**：新增 `POST /ai/v1/risk-suggestion` endpoint，接收供應商與最新 RA 資料，回傳建議 JSON
- **esgchain-api**：新增 `POST /api/v1/suppliers/{id}/risk-suggestion` controller + service，轉發至 AI 並將結果存入 `risk_assessments.ai_suggestion`（JSON）與 `ai_generated_at`（timestamp）
- **DB migration**：`risk_assessments` 表新增 `ai_suggestion` TEXT 與 `ai_generated_at` TIMESTAMP NULL
- **esgchain-web**：`SupplierDetailView.vue` 三軸區塊改左右分欄，右欄為 `AiRiskSuggestionPanel` 元件
