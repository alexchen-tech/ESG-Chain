### Requirement: Disclosure Field Master

`supplier_disclosure_fields` 表定義系統支援的 KPI 欄位本體。每筆記錄包含：
- `slug` (PK, varchar 80)：語意識別碼，格式為 `<domain>.<metric>`，例如 `ghg.scope1_mt_co2e`
- `label`：繁體中文顯示名稱
- `data_type`：`numeric` / `boolean` / `single_choice`
- `unit`：單位字串（nullable，boolean 題為 null）
- `period_type`：`annual`（每年度一筆）/ `point_in_time`（時間點快照）
- `description`：欄位說明（nullable）

初始 seed 應包含以下核心欄位（不限於此）：

| slug | label | data_type | unit |
|------|-------|-----------|------|
| ghg.scope1_mt_co2e | Scope 1 溫室氣體排放量 | numeric | mt_co2e |
| ghg.scope2_mt_co2e | Scope 2 溫室氣體排放量 | numeric | mt_co2e |
| energy.total_kwh | 總用電量 | numeric | kWh |
| water.total_m3 | 總用水量 | numeric | m³ |
| safety.ltifr | 職業災害失能傷害頻率（LTIFR） | numeric | per_million_hrs |
| diversity.female_pct | 女性員工比例 | numeric | % |
| governance.has_anti_corruption_policy | 具備反腐敗政策 | boolean | null |
| governance.has_esg_report | 出具 ESG 報告 | boolean | null |
| cert.iso14001 | 持有 ISO 14001 認證 | boolean | null |
| cert.iso45001 | 持有 ISO 45001 認證 | boolean | null |

#### Scenario: Seed 資料完整載入

WHEN 執行 `php artisan db:seed`
THEN `supplier_disclosure_fields` 應至少包含上表 10 筆記錄，slug 唯一無重複

#### Scenario: Slug 格式驗證

WHEN 嘗試 insert 一筆 slug 不符合 `<domain>.<metric>` 格式（例如缺少 `.`）的記錄
THEN 應拋出 DB constraint 錯誤或 validation error，不允許寫入
