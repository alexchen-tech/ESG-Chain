## ADDED Requirements

### Requirement: Series 計分設定欄位
`assessment_series` 表新增兩個可為 null 的 JSON 欄位：
- `pillar_weights`：以 slug prefix（不含結尾點）為 key 的浮點加權，各值合計須為 1.0（容差 ±0.01）
- `grade_thresholds`：等級閾值 `{A, B, C, D}`，須滿足 A > B > C > D > 0

#### Scenario: 儲存 pillar_weights 加總驗證
- **WHEN** PUT /api/v1/assessment-series/{id}/scoring-config 傳入 pillar_weights，各值合計不在 0.99–1.01 之間
- **THEN** 回傳 422，錯誤訊息說明「pillar_weights 合計須等於 1.0」

#### Scenario: 儲存 grade_thresholds 遞減驗證
- **WHEN** PUT /api/v1/assessment-series/{id}/scoring-config 傳入 grade_thresholds，不滿足 A > B > C > D > 0
- **THEN** 回傳 422，錯誤訊息說明「閾值須遞減且 D > 0」

#### Scenario: 取得計分設定
- **WHEN** GET /api/v1/assessment-series/{id}/scoring-config
- **THEN** 回傳 `{ pillar_weights, grade_thresholds, available_pillars }`
- **AND** `available_pillars` 依 series 綁定的 template.scoring_framework 列出可設定的 slug prefix 清單

#### Scenario: 未設定時回傳 null
- **WHEN** series 從未設定計分設定
- **THEN** GET 回傳 `{ pillar_weights: null, grade_thresholds: null, available_pillars: [...] }`

### Requirement: 計分設定 Tab UI
Series 詳情頁新增第四個 Tab「計分設定」。

#### Scenario: 顯示框架對應的 pillar 清單
- **WHEN** 使用者切換到「計分設定」Tab
- **THEN** 依 series 的 scoring_framework 顯示對應 pillar 的中文名稱與輸入欄位（%）
- **AND** 若 pillar_weights 已設定，顯示現有值；否則顯示等權平均作為 placeholder

#### Scenario: 合計驗證
- **WHEN** 使用者輸入 pillar 加權
- **THEN** 即時顯示合計百分比，不等於 100% 時顯示紅色警示，儲存按鈕禁用

#### Scenario: 重設為等權
- **WHEN** 使用者點擊「重設為等權」
- **THEN** 各 pillar 自動填入 100/n（n = pillar 數量），合計顯示 100%

#### Scenario: 修改後提示歷史分數不重算
- **WHEN** 使用者儲存計分設定成功
- **THEN** 顯示提示：「設定已儲存。已完成的問卷分數不會自動重算。」

### Requirement: DispatchSaqScoringJob 傳遞 series 計分設定
計分 Job 觸發時，將 series 的 pillar_weights 與 grade_thresholds 一併傳給 AI service。

#### Scenario: 傳遞 series 設定
- **WHEN** DispatchSaqScoringJob 執行
- **AND** SAQ 所屬 project 有 series_id
- **THEN** payload 包含 `series_pillar_weights` 與 `series_grade_thresholds`（可為 null）

#### Scenario: 無 series 時不傳
- **WHEN** project 無 series_id
- **THEN** payload 中 `series_pillar_weights` 與 `series_grade_thresholds` 均為 null
