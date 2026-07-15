## ADDED Requirements

### Requirement: Canonical Framework Key

六維評核框架在所有資料層使用統一的字串 key，與 `question_tags.l1_domain` 完全對應。

| E-code（UI 標籤） | Canonical Key | 中文 |
|---|---|---|
| E1 | `ESG` | ESG 整體 |
| E2 | `ISO20400` | 採購永續 |
| E3 | `ISO26000` | 社會責任 |
| E4 | `Geo-Risk` | 地緣風險 |
| E5 | `ISO28000` | 供應鏈安全 |
| E6 | `Product-Compliance` | 產品合規 |

#### Scenario: 加權表 scoring_framework 對應標籤庫 l1_domain
- **WHEN** 查詢 `framework_default_weights` 的 `scoring_framework` 欄位
- **THEN** 所有值必須存在於 `question_tags.l1_domain` 的枚舉集合中

#### Scenario: 題庫 tags framework 使用 canonical key
- **WHEN** 新增或更新 `saq_questions.tags`
- **THEN** 每個 tag 的 `framework` 欄位必須是 `SAQQuestion::VALID_FRAMEWORKS` 之一（L1 domain 名稱，非 E-code）

---

### Requirement: L2 Pillar Slug 對應

`saq_questions.tags[].pillar` 使用 L2 層級的 pillar_slug，與 `framework_default_weights.pillar_slug` 的值完全相同，使計分引擎可直接 JOIN。

每個 framework 的合法 pillar_slug：

| Framework | 合法 pillar_slug |
|---|---|
| ESG | `esg.env`, `esg.soc`, `esg.gov` |
| ISO20400 | `iso20400.policy`, `iso20400.due_diligence`, `iso20400.action`, `iso20400.reporting`, `iso20400.capacity`, `iso20400.stakeholder` |
| ISO26000 | `iso26000.governance`, `iso26000.hr`, `iso26000.labor`, `iso26000.environment`, `iso26000.fairop`, `iso26000.consumer`, `iso26000.community` |
| Geo-Risk | `georisk.political`, `georisk.environmental`, `georisk.social`, `georisk.regulatory` |
| ISO28000 | `iso28k.physical`, `iso28k.cert`, `iso28k.cargo`, `iso28k.infosec` |
| Product-Compliance | `prod_comp.cbam`, `prod_comp.eudr`, `prod_comp.chem`, `prod_comp.trace` |

#### Scenario: pillar_slug 驗證
- **WHEN** 新增 `saq_questions.tags` 且 `framework` 為 `ISO28000`
- **THEN** `pillar` 必須是 `iso28k.physical / iso28k.cert / iso28k.cargo / iso28k.infosec` 之一，否則回傳 422

#### Scenario: 計分引擎 JOIN
- **WHEN** 計分引擎讀取題目的 `tags[].pillar`
- **THEN** 可直接以 `pillar = pillar_slug` 查詢 `framework_default_weights` 取得加權值

---

### Requirement: 加權表覆蓋六維框架

`framework_default_weights` 的 `scoring_framework` 集合恰好等於六個 canonical key，不多不少。

#### Scenario: 六個框架行存在
- **WHEN** 查詢 `SELECT DISTINCT scoring_framework FROM framework_default_weights`
- **THEN** 結果集合為 `{ESG, ISO20400, ISO26000, Geo-Risk, ISO28000, Product-Compliance}`

#### Scenario: 不存在 E-code 行
- **WHEN** 查詢 `SELECT * FROM framework_default_weights WHERE scoring_framework IN ('E1','E2','E3','E4','E5','E6')`
- **THEN** 回傳 0 筆

---

### Requirement: 題庫標記全覆蓋

題庫（`template_id IS NULL`）的所有題目必須有至少一個合法的 object-format tag。

#### Scenario: 無舊字串格式殘留
- **WHEN** 查詢 `saq_questions.tags` 中所有 bank 題目
- **THEN** 每筆 `tags` 欄位皆為 `[{framework, pillar, weight}]` 結構，不得為字串陣列

#### Scenario: compliance_domains 自動同步
- **WHEN** 儲存或更新 `saq_questions.tags`
- **THEN** `compliance_domains` 欄位自動更新為 tags 中所有 framework 的去重集合

---

### Requirement: UI 框架 Tab 顯示

框架預設加權設定頁顯示六個 tab，順序與六維一致，每個 tab 以 `E-code · 標準名稱` 格式顯示。

#### Scenario: 六個 Tab 存在
- **WHEN** 開啟設定頁「框架預設 Pillar 加權」
- **THEN** 顯示 E1 · ESG、E2 · ISO 20400、E3 · ISO 26000、E4 · Geo-Risk、E5 · ISO 28000、E6 · 產品合規 六個 tab

#### Scenario: 每個 Tab 的 Pillar 與加權表一致
- **WHEN** 切換至 E5 · ISO 28000 tab
- **THEN** 顯示四個 pillar：實體與人員安全、認證與管理體系、貨物與物流安全、資訊安全與韌性
