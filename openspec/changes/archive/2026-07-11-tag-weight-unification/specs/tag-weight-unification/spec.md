## ADDED Requirements

### Requirement: question_tags 為計分唯一真相來源

`question_tags` 表包含三層節點，透過 `slug` 欄位的前綴樹結構識別層級：
- L1 節點：`l2_pillar = 'General' AND l3_topic = 'General'`，`slug` 格式為 `{l1_key}`
- L2 節點：`l2_pillar ≠ 'General' AND l3_topic = 'General'`，`slug` 格式為 `{l1_key}.{pillar}`，`default_weight` 有值
- L3 節點：`l3_topic ≠ 'General'`，`slug` 格式為 `{l1_key}.{pillar}.{topic}`，`default_weight` 為 NULL

#### Scenario: L2 節點覆蓋六大框架
- **WHEN** 查詢 `SELECT DISTINCT l1_domain FROM question_tags WHERE l3_topic='General' AND l2_pillar!='General'`
- **THEN** 結果集合為 `{ESG, ISO20400, ISO26000, Geo-Risk, ISO28000, Product-Compliance}`

#### Scenario: 每個框架的 L2 節點 default_weight 總和為 1.0
- **WHEN** 查詢某 l1_domain 下所有 L2 節點的 default_weight
- **THEN** SUM(default_weight) = 1.0（允許 ±0.01 浮點誤差）

#### Scenario: L3 slug 為 L2 slug 的前綴延伸
- **WHEN** 存在 L3 節點 slug `iso28k.cert.standards`
- **THEN** 必定存在對應的 L2 節點 slug `iso28k.cert`

---

### Requirement: default_weight 欄位

`question_tags` 新增 `default_weight DECIMAL(5,4) NULL`：
- L2 節點：必填，範圍 0.0001–1.0
- L1、L3 節點：必須為 NULL

#### Scenario: 儲存 L2 節點加權
- **WHEN** PUT `/api/v1/settings/tag-library/l2-nodes/{id}/weight` 帶 `{"default_weight": 0.30}`
- **THEN** 對應 question_tags L2 節點的 default_weight 更新為 0.30

#### Scenario: 拒絕在 L3 節點設定 default_weight
- **WHEN** 嘗試更新 l3_topic ≠ 'General' 的節點
- **THEN** 回傳 422

---

### Requirement: 計分路徑（L3 → L2 前綴 JOIN）

計分引擎透過 slug 前兩段推導 L2 slug，再取 default_weight：

```
slug_l3 = "iso28k.cert.standards"
slug_l2 = SUBSTRING_INDEX(slug_l3, '.', 2) = "iso28k.cert"
weight   = SELECT default_weight FROM question_tags WHERE slug = slug_l2
```

#### Scenario: L3 tag 可推導到 L2 weight
- **WHEN** 題目有 L3 tag `["iso28k.cert.standards", "iso28k.cargo.seal"]`
- **THEN** 計分引擎可對應到 iso28k.cert（0.25）和 iso28k.cargo（0.25）的權重

#### Scenario: 無 L3 tag 的題目不計入任何軸度
- **WHEN** 題目的 tags 為空陣列
- **THEN** 該題目不貢獻任何軸度分數

---

### Requirement: saq_questions.tags 為 L3 slug 陣列

`saq_questions.tags` 欄位格式為 JSON 字串陣列，每個元素為 `question_tags.slug`（L3 節點）。

#### Scenario: tags 只含 L3 slug
- **WHEN** 讀取 `saq_questions.tags`
- **THEN** 每個元素可在 `question_tags` 中找到對應的 L3 節點（`l3_topic ≠ 'General'`）

#### Scenario: 透過 question_tag_assignments 同步 tags
- **WHEN** UI 在題目上新增 L3 tag assignment
- **THEN** `saq_questions.tags` 自動更新（或在計分時從 assignments 即時讀取）

---

### Requirement: assessment_series.pillar_weights 使用 L2 節點 UUID

`assessment_series.pillar_weights` JSON 的 key 為 `question_tags` L2 節點的 UUID。

#### Scenario: 新建 Series 初始化 pillar_weights
- **WHEN** 建立新的 assessment_series
- **THEN** `pillar_weights` 以 `{l2_node_id: default_weight}` 格式初始化，讀自 question_tags L2 節點

#### Scenario: Series 可覆蓋個別 pillar 權重
- **WHEN** 修改 series 的 pillar_weights
- **THEN** 計分時使用 series 的覆蓋值而非 question_tags 的 default_weight

---

### Requirement: framework_default_weights 廢棄

`framework_default_weights` 表不再存在，所有原有功能由 `question_tags` L2 節點承接。

#### Scenario: 不存在 framework_default_weights 表
- **WHEN** 執行 migration 後
- **THEN** `SHOW TABLES LIKE 'framework_default_weights'` 回傳 0 筆

---

### Requirement: 框架加權設定 UI

「設定框架加權」頁面改為操作 `question_tags` L2 節點的 `default_weight`，六個框架 Tab 各顯示對應 L2 節點清單。

#### Scenario: 顯示六框架 Tab 與 L2 軸度
- **WHEN** 開啟「設定框架加權」頁
- **THEN** 顯示六個 Tab，每個 Tab 列出該 l1_domain 的所有 L2 節點（label_zh + default_weight）

#### Scenario: 儲存加權後即時生效
- **WHEN** 管理員修改 ISO28000 的 iso28k.cert default_weight 為 0.30
- **THEN** 後續新建的 assessment_series 的 pillar_weights 讀到 0.30
