## ADDED Requirements

### Requirement: Bank Question Disclosure Field Mapping

`saq_questions` 表新增 `disclosure_field_slug VARCHAR(80) NULL` 欄位，記錄此題對應的 disclosure field slug。

- 只有 `question_type IN ('number', 'boolean', 'single_choice')` 且有明確量化語意的 bank questions 才設此欄位
- 長文字題（`long_text`、`multi_select`）不映射
- mapping 在 seed / migration 中集中維護，不對外暴露 CRUD API

初始映射（bank question → disclosure_field_slug）：

| question_text（節錄） | disclosure_field_slug |
|----------------------|----------------------|
| Scope 1 直接溫室氣體排放量 | ghg.scope1_mt_co2e |
| 總用電量 | energy.total_kwh |
| 總用水量 | water.total_m3 |
| 職業災害失能傷害頻率（LTIFR） | safety.ltifr |
| 女性員工佔全體員工的比例 | diversity.female_pct |
| 反腐敗與商業道德政策 | governance.has_anti_corruption_policy |
| 國際永續相關認證（ISO 14001） | cert.iso14001 |
| 國際永續相關認證（ISO 45001） | cert.iso45001 |

#### Scenario: 有映射的題可被 DisclosureSyncService 識別

WHEN `DisclosureSyncService::syncFromSaq()` 執行
THEN 只有 `disclosure_field_slug IS NOT NULL` 的題的答案被寫入 supplier_disclosures

#### Scenario: 無映射的題不影響同步

WHEN saq_response 對應的 question 無 disclosure_field_slug
THEN 該筆 response 被跳過，不寫入 supplier_disclosures，也不拋出錯誤
