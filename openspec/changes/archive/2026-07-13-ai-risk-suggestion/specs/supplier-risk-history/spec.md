## MODIFIED Requirements

### Requirement: 三軸風險評估區塊佈局

三軸風險評估區塊 SHALL 改為左右分欄佈局（`flex-direction: row`）：
- **左欄**：三條 axis bar（軸1/軸2/軸3）+ 軸3 手動輸入表單，最小寬度 300px
- **右欄**：AI 風險建議面板（`AiRiskSuggestionPanel` 元件），flex: 1，最小寬度 260px
- 螢幕寬度 < 900px 時自動 wrap 為單欄

右欄面板 SHALL 顯示：
- 標題「🤖 風險評估建議」
- 整體摘要（summary）
- 各軸分項建議（axis1/axis2/axis3，無資料的軸略過）
- 「重新產生」按鈕（點擊後 force=true 重新呼叫 API，按鈕顯示 loading 並 disabled）
- 建議產生時間（`ai_generated_at`，格式 YYYY/M/D HH:mm）
- 「AI 產生，僅供參考」免責說明（小字）

#### Scenario: 有快取建議時直接顯示

- **WHEN** 使用者開啟風險歷史 tab，最新 RA 有 ai_suggestion 快取
- **THEN** 面板 SHALL 立即顯示建議內容，不顯示 loading

#### Scenario: 無快取時自動觸發

- **WHEN** 使用者開啟風險歷史 tab，最新 RA 無 ai_suggestion
- **THEN** 面板 SHALL 自動呼叫 API（force=false），顯示 loading 動畫，載入完成後顯示建議

#### Scenario: 重新產生

- **WHEN** 使用者點擊「重新產生」按鈕
- **THEN** 面板 SHALL 呼叫 API（force=true），按鈕 disabled + loading，完成後更新顯示

#### Scenario: 無 RA 資料時

- **WHEN** 供應商尚無 RA，API 回傳 422
- **THEN** 右欄 SHALL 顯示「尚無風險評估資料」提示，不顯示建議面板
