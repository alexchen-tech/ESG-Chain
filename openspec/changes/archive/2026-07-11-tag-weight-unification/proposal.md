## Why

`framework_default_weights` 與 `question_tags` 在 L1（框架）和 L2（軸度）層面存在結構性重疊：兩張表各自定義同一套框架與軸度，卻使用不同命名系統（slug vs 中文），造成計分引擎無法直接 JOIN、UI 需要雙重維護、新增框架必須同時修改兩個地方。廢棄現有專案問卷資料、以 L3 slug 作為題目標籤的唯一 key 是重構計分鏈的前提，此時是合併兩表的最低成本時間點。

## What Changes

- **`question_tags` 新增 `default_weight` 欄位**（DECIMAL NULL，僅 L2 節點有值）
- **L2 節點 slug 統一為 pillar 前綴**（如 `iso28k.cert`），L3 節點 slug 為 `iso28k.cert.standards`，形成前綴樹結構
- **修正 ISO20400 的錯誤 L2 節點**（現在掛的是 ISO26000 七主題，應改為 ISO20400 五流程軸）
- **補建缺失的 L2 節點**：ISO26000（7個）、Geo-Risk（4個）、ISO28000（4個）、Product-Compliance（4個）
- **ESG L2 節點 slug 對齊**：從 `esg.e.general` 格式改為 `esg.env`、`esg.soc`、`esg.gov`
- **`assessment_series.pillar_weights` JSON key 從 pillar_slug 改為 question_tags L2 節點 UUID**
- **`framework_default_weights` 廢棄並 DROP**
- **`saq_questions.tags` 格式改為純 L3 slug 陣列**，framework/pillar/weight 全部從標籤庫推導
- **`FrameworkDefaultWeightPanel.vue` 改為讀寫 `question_tags` L2 節點**
- **`question_tag_assignments` 中的 General 節點 assignments 全部清除，重新以 L3 slug 對應題目**

## Capabilities

### New Capabilities

- `tag-weight-unification`: 標籤庫（`question_tags`）成為計分框架的唯一真相來源——L1 框架定義、L2 軸度定義與權重、L3 題目標籤全部在同一張表，計分引擎透過 slug 前綴樹從 L3 → L2 → 權重一路推導，無需額外 JOIN 其他表

### Modified Capabilities

（無）

## Impact

- **esgchain-api**
  - `database/migrations/`：新增 `default_weight` 欄位、重建 L2 節點、遷移 pillar_weights JSON、DROP framework_default_weights
  - `app/Models/QuestionTag.php`：新增 `default_weight` cast
  - `app/Services/SAQ/SAQScoringService.php`（或計分相關 service）：計分路徑改為 L3→L2 slug prefix JOIN
  - `app/Http/Controllers/Api/Settings/`：`FrameworkDefaultWeightController` 改為操作 question_tags L2 節點
- **esgchain-web**
  - `src/views/settings/FrameworkDefaultWeightPanel.vue`：API endpoint 與資料格式更新
- **esgchain-ai**
  - `app/tasks/six_dim_scoring_tasks.py`：pillar weight 查詢路徑更新（Task 5.1–5.3 from framework-tag-alignment）
- **DB**
  - `question_tags`：新增欄位、重建 28 個 L2 節點
  - `assessment_series.pillar_weights`：JSON key 格式遷移（目前 0 筆，低風險）
  - `saq_questions.tags`：格式從 `[{framework,pillar,weight}]` 改為 `["l3.slug"]`
  - `question_tag_assignments`：清除 General 節點，重建為 L3 級別
  - DROP TABLE `framework_default_weights`
