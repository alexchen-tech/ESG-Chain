## ADDED Requirements

### Requirement: 題目計分方向性（scoring_direction）

`template_questions` 與 `project_questions` SHALL 包含 `scoring_direction` 欄位（enum: `positive` | `negative`，預設 `positive`）。

- `positive`：「是」/ 第一選項 = 好的答案（得高分）
- `negative`：「否」/ 第一選項 = 好的答案（得高分）；「是」/ 最後選項 = 差的答案（得低分）

#### Scenario: boolean 正向題
- **WHEN** `question_type = boolean`，`scoring_direction = positive`，供應商答「是」
- **THEN** `answer_score = 1.0`

#### Scenario: boolean 反向題
- **WHEN** `question_type = boolean`，`scoring_direction = negative`，供應商答「否」
- **THEN** `answer_score = 1.0`（否定反向題 = 好的答案）

#### Scenario: boolean 反向題答「是」
- **WHEN** `question_type = boolean`，`scoring_direction = negative`，供應商答「是」
- **THEN** `answer_score = 0.0`

---

### Requirement: 題目計分類型（scoring_type）

`template_questions` 與 `project_questions` SHALL 包含 `scoring_type` 欄位，enum 值如下：

| 值 | 說明 |
|---|---|
| `ordered_asc` | 有序單選，第一個選項最差，自動線性化 |
| `ordered_desc` | 有序單選，第一個選項最好，自動線性化 |
| `custom` | 自訂分值，搭配 `option_scores` JSON |
| `evidence_only` | 佐證說明題，不計入評分 |
| `llm` | 開放式文字題，由 LLM 評分 |
| `null` | 未設定，使用既有 fallback 邏輯（向後相容） |

#### Scenario: ordered_asc 自動線性化
- **WHEN** `scoring_type = ordered_asc`，選項共 5 個，供應商選第 3 個（index 2）
- **THEN** `answer_score = round(2 / (5-1) * 100) / 100 = 0.50`

#### Scenario: ordered_desc 自動線性化
- **WHEN** `scoring_type = ordered_desc`，選項共 4 個，供應商選第 1 個（index 0）
- **THEN** `answer_score = 1.0`（第一個是最好的）

#### Scenario: custom 分值查找
- **WHEN** `scoring_type = custom`，`option_scores = {"每季": 1.0, "每年": 0.6, "從未": 0.0}`，供應商選「每年」
- **THEN** `answer_score = 0.6`

#### Scenario: evidence_only 不計分
- **WHEN** `scoring_type = evidence_only`
- **THEN** `raw_score = null`，此題不納入 E/S/G 分母，UI 顯示「📎 佐證」標籤

#### Scenario: null fallback
- **WHEN** `scoring_type = null`
- **THEN** 使用既有 `_score_single_response()` fallback 邏輯，行為與升級前相同

---

### Requirement: 選項分值字典（option_scores）

當 `scoring_type = custom` 時，`option_scores` SHALL 為 JSON 物件，key 為選項文字，value 為 0.0–1.0 的浮點數。

#### Scenario: 完整設定
- **WHEN** `option_scores` 涵蓋題目所有選項
- **THEN** scoring service 直接 lookup，不使用 fallback

#### Scenario: 部分缺失 fallback
- **WHEN** 供應商的答案不在 `option_scores` key 中
- **THEN** `answer_score = 0.5`（中立分），`score_confidence = low`

---

### Requirement: 模板編輯器計分設定面板

模板編輯器在每題設定區 SHALL 提供計分設定面板，讓設計者選擇 `scoring_direction` 與 `scoring_type`。

#### Scenario: 新建題目預設值
- **WHEN** 設計者新增一題，未手動設定
- **THEN** `scoring_direction = positive`，`scoring_type = null`

#### Scenario: 選擇 evidence_only
- **WHEN** 設計者將題目標記為 `evidence_only`
- **THEN** 分數設定欄位隱藏，題目卡片顯示「📎 佐證說明」badge

#### Scenario: 選擇 custom 顯示分值輸入
- **WHEN** 設計者選擇 `scoring_type = custom`
- **THEN** 每個選項旁出現 0–100 分值輸入欄
