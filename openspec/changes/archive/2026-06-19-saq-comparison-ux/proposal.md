## Why

供應商比較頁的 SVG 折線圖因 `points` 屬性不支援百分比座標，導致折線無法渲染、只顯示散點。同時，逐題趨勢矩陣欄位過寬、波次標籤被截斷、題目欄無固定，使資料難以閱讀。此次修正在功能驗收後補建 spec，使變更受版本控制追蹤。

## What Changes

- **修正折線圖座標系**：SVG 改用 `viewBox="0 0 1000 200"` 絕對座標，`buildPolylinePoints` 不再拼接 `%` 字串，折線可正確連接各波次分數點
- **折線圖強化**：加入 Y 軸刻度（0/25/50/75/100）、背景格線、圓點白色描邊；折線只連有分數的波次，null 點不斷線
- **趨勢矩陣題目欄 sticky**：`position: sticky; left: 0`，橫向捲動時固定
- **波次標籤智慧縮短**：`waveLabel()` 從波次名稱提取 `Q1/Q2/H1/YYYY` 短標，取代固定 `.slice(0,8)`
- **修正 question_trends 只顯示 1 題**：後端 `source_template_question_id` 欄位回傳 null 導致前端 Map key 全部衝突；改為回傳 fallback key（`order:N`），確保所有題目都能顯示
- **SAQ 提交必填驗證**：前端確認 Modal 列出未答必填題並停用提交按鈕；後端 `QuestionnaireService::submit()` 加入 `assertRequiredAnswered()` 返回 422

## Capabilities

### New Capabilities
- `saq-submission-required-validation`：SAQ 提交前強制必填題全數作答，前後端雙重驗證

### Modified Capabilities
- `cross-project-score-comparison`：`source_template_question_id` 回傳邏輯改用 fallback key，修正比較矩陣只顯示 1 題的問題
- `assessment-series-management`：供應商比較頁折線圖與趨勢矩陣 UI 強化

## Impact

- `esgchain-api/app/Services/SAQ/AssessmentSeriesService.php`（回傳欄位修正）
- `esgchain-api/app/Services/Questionnaire/QuestionnaireService.php`（必填驗證）
- `esgchain-web/src/views/questionnaires/SeriesDetailView.vue`（圖表、矩陣 UI）
- `esgchain-web/src/views/portal/SupplierSurveyView.vue`（提交驗證 Modal）
