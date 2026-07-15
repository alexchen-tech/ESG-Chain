## ADDED Requirements

### Requirement: sasb_disclosure_topics 資料表
系統 SHALL 建立 `sasb_disclosure_topics` 表，每筆記錄代表某 SASB Industry 下的一個 Disclosure Topic，欄位包含 `sasb_industry_id`（FK）、`topic_name`、`topic_code`（Metric prefix，如 "EM-IS-110a"）、`esg_category`（E/S/G）、`description`。

#### Scenario: 查詢特定 Industry 的 Topics
- **WHEN** `GET /api/v1/settings/sasb-industries/:id/topics`
- **THEN** 回傳該 Industry 所有 Topics，依 esg_category 排序

#### Scenario: 無 Topics 的 Industry
- **WHEN** 該 Industry 尚未建立 Topics
- **THEN** 回傳空陣列 `[]`

### Requirement: 初始 Seed 資料
系統 SHALL 在 `SasbDisclosureTopicSeeder` 中植入至少 20 個優先 SASB Industries 的 Disclosure Topics，涵蓋鋼鐵（EM-IS）、電子製造（TC-ES）、化工（RC-CH）、軟體（TC-SI）、食品飲料（FB-AG）等常見供應鏈產業。

#### Scenario: Seeder 執行後資料存在
- **WHEN** 執行 `php artisan db:seed --class=SasbDisclosureTopicSeeder`
- **THEN** `sasb_disclosure_topics` 至少有 60 筆，涵蓋 E/S/G 三類

### Requirement: Topic 列表 API
`GET /api/v1/settings/sasb-topics` SHALL 支援 `industry_id` 和 `esg_category` 過濾，回傳 topic_name、topic_code、esg_category。

#### Scenario: 依 Industry + 類別過濾
- **WHEN** 呼叫 `?industry_id=xxx&esg_category=E`
- **THEN** 只回傳該 Industry 的 E 類 Topics
