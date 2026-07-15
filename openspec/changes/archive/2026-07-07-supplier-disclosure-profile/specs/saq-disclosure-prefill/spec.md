### Requirement: 問卷預填邏輯

問卷發送（`saq.status = 'sent'`）後，供應商首次開啟填報頁時，系統 SHALL 依 `saq_questions.disclosure_field_slug` 查詢該供應商最近一期的 `supplier_disclosures` 記錄，並依以下規則預填：

| data_type | 預填行為 |
|-----------|---------|
| `boolean` | 自動填入上次的 boolean_value，欄位顯示「已從歷史記錄帶入，請確認」提示 |
| `single_choice` | 自動填入上次的 text_value（選項字串），同上提示 |
| `numeric` | 不自動填入；input 欄位保持空白，欄位下方顯示「上次填報：{value} {unit}（{period_year}）」參考資訊 |

若供應商無歷史 disclosure 記錄，不做預填，問卷正常顯示空白。

#### Scenario: Boolean 題自動預填

WHEN 供應商開啟問卷且 `governance.has_anti_corruption_policy` 有 2024 年歷史記錄（true）
THEN 該題答案預填為「是」，並顯示「已從歷史記錄帶入，請確認」

#### Scenario: Numeric 題顯示參考值

WHEN 供應商開啟問卷且 `ghg.scope1_mt_co2e` 有 2024 年歷史記錄（1,560 mt_co2e）
THEN 該題 input 欄位為空白，欄位下方顯示「上次填報：1,560 mt_co2e（2024）」

#### Scenario: 無歷史記錄時正常顯示

WHEN 供應商首次填報，`supplier_disclosures` 無任何記錄
THEN 問卷所有題目正常顯示空白，不出現預填提示
