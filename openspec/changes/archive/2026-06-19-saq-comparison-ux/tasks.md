## 1. 後端：比較 API fallback key 修正

- [x] 1.1 `AssessmentSeriesService::getComparison()` — `question_trends[].source_template_question_id` 改回傳 `$key`（`UUID ?? 'order:N'`）

## 2. 後端：SAQ 提交必填驗證

- [x] 2.1 `QuestionnaireService::submit()` — 呼叫 `assertRequiredAnswered()` 於狀態轉換前
- [x] 2.2 新增 `assertRequiredAnswered()` private method — 查詢必填 project_questions，比對有效 responses，缺答返回 422 + `REQUIRED_UNANSWERED`

## 3. 前端：折線圖座標修正

- [x] 3.1 SVG 加 `viewBox="0 0 1000 200" preserveAspectRatio="none"`
- [x] 3.2 `chartX()` 返回值域改為 50–950（整數），`chartY()` 返回值域改為 10–190
- [x] 3.3 `buildPolylinePoints()` — 過濾 null 分數波次，只連有效點
- [x] 3.4 折線圖加 Y 軸刻度區塊、格線 `<line>`、圓點 `stroke="#fff"` 描邊

## 4. 前端：趨勢矩陣 UI 強化

- [x] 4.1 題目欄（`<th>`、`<td>`）加 `.trend-q-col`：`position: sticky; left: 0; z-index: 2`
- [x] 4.2 新增 `waveLabel(name)` method — 正規表示法提取 `Q[1-4]|H[12]|[0-9]{4}`
- [x] 4.3 矩陣欄標題波次子標籤改用 `waveLabel(p.name)`

## 5. 前端：SAQ 提交必填驗證 UI

- [x] 5.1 `SupplierSurveyView.vue` — 新增 `unansweredRequired` computed（檢查 answers / multiAnswers）
- [x] 5.2 確認 Modal — 未答必填題時顯示紅色清單，「確認提交」按鈕 disabled
- [x] 5.3 加入 `.required-warning` / `.required-warning-list` 樣式

## 6. 驗證

- [x] 6.1 折線圖在多波次系列中正確連線
- [x] 6.2 趨勢矩陣顯示完整題目數（非僅 1 題）
- [x] 6.3 空白問卷提交 → 前端 Modal 列出未答題、後端返回 422
- [x] 6.4 全數作答後提交 → 正常流程通過
