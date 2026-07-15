# Spec: template-framework-constraint

## 定義

範本框架約束確保：加入特定框架（`scoring_framework`）範本的題目，至少需有一個 TAG 的 `l1_domain` 與該範本的 `scoring_framework` 相符。

## 約束執行層次

1. **應用層**（`SaqQuestionService`）：新增/移動範本題目時，先驗證 TAG 相符性，不符者回傳 422 友善訊息。
2. **資料層**（MySQL TRIGGER）：`trg_saq_questions_framework_check` BEFORE INSERT 確保無法繞過應用層直接寫入。

`scoring_framework = NULL` 的範本視為通用型，不執行任何 TAG 約束。

## Requirements

### Requirement: 應用層框架 TAG 驗證

系統 SHALL 在新增或從題庫匯入題目至範本時，驗證題目的 TAG l1_domain 是否包含範本的 `scoring_framework`。

#### Scenario: 符合框架 TAG 的題目可加入
- **WHEN** Admin 將具有 `l1_domain = 'ESG'` TAG 的題目加入 `scoring_framework = 'ESG'` 的範本
- **THEN** 系統 SHALL 允許操作，返回 201

#### Scenario: 不符框架 TAG 的題目被拒絕
- **WHEN** Admin 嘗試將只有 `l1_domain = 'ISO20400'` TAG 的題目加入 `scoring_framework = 'ESG'` 的範本
- **THEN** 系統 SHALL 返回 422，message: '題目的 TAG 領域（ISO20400）與範本框架（ESG）不相符'

#### Scenario: 通用型範本無約束
- **WHEN** Admin 將任意題目加入 `scoring_framework = NULL` 的範本
- **THEN** 系統 SHALL 允許操作，不進行 TAG 驗證

#### Scenario: 題目有多個 TAG 時只需其中一個符合
- **WHEN** 題目同時具有 `l1_domain = 'ESG'` 和 `l1_domain = 'ISO26000'` 的 TAG，加入 `scoring_framework = 'ESG'` 的範本
- **THEN** 系統 SHALL 允許操作（至少一個匹配即可）

### Requirement: DB TRIGGER 最後防線

系統 SHALL 部署 MySQL BEFORE INSERT TRIGGER `trg_saq_questions_framework_check`，在資料層拒絕不符合框架約束的範本題目插入。

#### Scenario: TRIGGER 攔截不合規插入
- **WHEN** 直接對 `saq_questions` 執行 INSERT，其 `template_id` 關聯的範本 `scoring_framework = 'ESG'`，但題目無任何 `l1_domain = 'ESG'` 的 TAG 指派（在 INSERT 前未完成 TAG 指派）
- **THEN** TRIGGER SHALL SIGNAL SQLSTATE '45000'，訊息: 'Template question must have at least one TAG matching template scoring_framework'

**注意**：TAG 指派（`question_tag_assignments`）在題目 INSERT 後才能插入（需要 question_id FK）。因此 TRIGGER 的實際邏輯為：若範本有 `scoring_framework`，則在 AFTER INSERT 時（或透過應用層 transaction 後驗證）確認 TAG 已指派；BEFORE INSERT 檢查僅用於非首次建立（UPDATE）場景。具體觸發策略由 design.md 的 Migration D 定義。

### Requirement: framework_pillar 欄位快照

系統 SHALL 在新增範本題目（非 bank question）時，自動從符合框架 l1_domain 的 TAG 中取第一個 l2_pillar 值填入 `saq_questions.framework_pillar`。

#### Scenario: 自動填入 framework_pillar
- **WHEN** Admin 將具有 TAG `l1_domain='ESG', l2_pillar='environment'` 的題目加入 ESG 範本
- **THEN** `saq_questions.framework_pillar` SHALL 自動設為 `'environment'`

#### Scenario: 無匹配 TAG 時 framework_pillar 為 NULL
- **WHEN** 題目無任何 TAG（通用型範本允許此情況）
- **THEN** `saq_questions.framework_pillar` SHALL 為 NULL
