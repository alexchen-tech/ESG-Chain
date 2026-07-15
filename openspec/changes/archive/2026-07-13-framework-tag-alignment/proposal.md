## Why

評核框架的命名在三個資料層之間不一致：加權表（`framework_default_weights`）、標籤庫（`question_tags`）、題庫標記（`saq_questions.tags`）各自使用不同的 key（E-code、ISO 標準名稱、非結構化字串），導致計分引擎無法直接 JOIN 三層資料，六維評分鏈無法連通。

## What Changes

- **framework_default_weights** 移除 E1–E6 六列（E-code 命名），改以 `ISO28000` 和 `Product-Compliance` 補齊缺失框架，讓加權表的 `scoring_framework` 與標籤庫 `l1_domain` 完全一致（6 個值）
- **saq_questions.tags** 從兩種舊格式（`{framework:"E1", pillar:"G"}` 字母格式 / 自由字串陣列）統一轉換為新格式 `{framework: l1_domain, pillar: L2_pillar_slug, weight}`，180 題全部遷移
- **`SAQQuestion::VALID_FRAMEWORKS`** 從 E-code 改為 L1 domain 名稱；新增 `VALID_PILLARS` 常數對每個 framework 列出合法的 L2 pillar_slug
- **`QuestionBankController`** store/update 的 tags 驗證規則同步更新
- **`FrameworkDefaultWeightPanel.vue`** 移除 E1–E6 重複 tab，在現有四個 tab 加上 E-code 標示，新增 ISO28000（E5）與 Product-Compliance（E6）tab

## Capabilities

### New Capabilities

- `framework-tag-alignment`: 建立三層框架資料一致性規範——加權表 `scoring_framework` = 標籤庫 `l1_domain` = 題庫 `tags[].framework`；加權表 `pillar_slug` = 標籤庫 `l2_pillar` 前綴 = 題庫 `tags[].pillar`

### Modified Capabilities

（無新增或修改的現有 spec）

## Impact

- **esgchain-api**
  - `app/Models/SAQQuestion.php`：`VALID_FRAMEWORKS`、`VALID_PILLARS` 常數
  - `app/Http/Controllers/Api/Settings/QuestionBankController.php`：tags 驗證規則
  - `database/migrations/2026_07_11_*_retag_saq_questions.php`：資料遷移
- **esgchain-web**
  - `src/views/settings/FrameworkDefaultWeightPanel.vue`：FRAMEWORKS 陣列
- **esgchain-ai**（後續）
  - 計分任務中 framework key 的比對邏輯須對應新命名
- **DB**
  - `framework_default_weights`：刪除 E1–E6 行，新增 ISO28000（4 pillars）與 Product-Compliance（4 pillars）
  - `saq_questions.tags`：180 題全數更新
  - `saq_questions.compliance_domains`：同步自 tags.framework
