### Requirement: 題目 disclosure_field_slug 初始映射

系統 SHALL 在題目庫中為以下題目設定 `disclosure_field_slug`，使 SAQ 評分後自動觸發 disclosure 同步：

| question_type | 預期 disclosure_field_slug |
|--------------|---------------------------|
| boolean（持有 ISO 14001 認證）| cert.iso14001 |
| boolean（持有 ISO 45001 認證）| cert.iso45001 |
| boolean（持有 ISO 9001 認證）| cert.iso9001 |
| numeric（Scope 1 溫室氣體排放量，mt CO2e）| ghg.scope1_mt_co2e |
| numeric（Scope 2 溫室氣體排放量，mt CO2e）| ghg.scope2_mt_co2e |
| numeric（總用電量，kWh）| energy.total_kwh |
| numeric（再生能源使用比例，%）| energy.renewable_pct |
| boolean（具備反腐敗政策）| governance.has_anti_corruption_policy |
| boolean（出具 ESG 報告）| governance.has_esg_report |
| boolean（明令禁止童工與強迫勞動）| labor.child_labor_banned |
| numeric（女性員工比例，%）| diversity.female_pct |
| numeric（LTIFR）| safety.ltifr |
| numeric（總用水量，m³）| water.total_m3 |
| numeric（廢棄物回收率，%）| waste.recycling_pct |
| boolean（執行供應商盡職調查）| supply_chain.supplier_audit_conducted |

映射規則：`question_type` 與 `data_type`（來自 `supplier_disclosure_fields`）必須相容（boolean↔boolean、numeric↔numeric），否則不設映射。

#### Scenario: SAQ 評分後自動同步

WHEN SAQ 評分完成（`SAQService::updateScore()` 執行）
AND 問卷含有已設 `disclosure_field_slug` 的題目
THEN `DisclosureSyncService::syncFromSaq()` 應對每一有映射的回答執行 upsert 至 `supplier_disclosures`
AND `source` = `saq_sync`，`source_saq_id` = 該 SAQ 的 id

#### Scenario: 無映射題目不影響同步

WHEN 問卷含有 `disclosure_field_slug = null` 的題目
THEN 該題目的回答不寫入 `supplier_disclosures`，不報錯

### Requirement: 題目庫編輯介面支援 disclosure_field_slug 設定

題目庫（`/settings/question-bank`）的題目編輯 Modal SHALL 包含「Disclosure 欄位對應」下拉選單：
- 選項來自 `GET /api/v1/settings/disclosure-fields`，回傳所有 `supplier_disclosure_fields` 的 `slug` 與 `label`
- 選項按 prefix 分組顯示（cert / ghg / energy / labor…）
- 允許選擇「不對應」（null）
- 儲存時透過現有 `PUT /api/v1/settings/question-bank/{id}` 更新 `disclosure_field_slug`

#### Scenario: 管理員設定映射

WHEN 管理員在題目庫編輯 Modal 選擇「ghg.scope1_mt_co2e」並儲存
THEN `saq_questions.disclosure_field_slug` 更新為 `ghg.scope1_mt_co2e`
AND 後續該題目評分後的 SAQ 同步即可寫入 disclosure

### Requirement: Disclosure Fields API

`GET /api/v1/settings/disclosure-fields`

- 僅限 `admin` 角色
- 回傳所有 `supplier_disclosure_fields` 的 `slug`、`label`、`data_type`、`unit`
- 按 slug prefix 排序

### Requirement: 歷史 SAQ 補跑同步

系統提供 artisan command `disclosure:backfill`（已存在），可對所有已評分 SAQ 補跑 `DisclosureSyncService::syncFromSaq()`。

WHEN 管理員執行 `php artisan disclosure:backfill`
THEN 所有狀態為 `approved` 或含 score 的 SAQ 依序觸發 syncFromSaq
AND 執行結果（成功/失敗/跳過）log 至 Laravel log
