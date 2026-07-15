# SAQ 題目計分 Metadata 規格

**capability**: `saq-question-scoring-metadata`  
**版本**: v1  
**最後更新**: 2026-06-23

---

## 1. 概述

為 `template_questions` 與 `project_questions` 新增計分方向性（`scoring_direction`）、計分類型（`scoring_type`）與選項分值字典（`option_scores`）欄位，讓問卷設計者可精確控制每題的計分邏輯，取代既有的 fallback 機制。

---

## 2. 資料欄位

### 2.1 `template_questions` 與 `project_questions` 新增欄位

| 欄位 | 類型 | 預設值 | 說明 |
| --- | --- | --- | --- |
| `scoring_direction` | enum | `positive` | `positive` / `negative` |
| `scoring_type` | enum \| null | `null` | `ordered_asc` / `ordered_desc` / `custom` / `evidence_only` / `llm` / `null` |
| `option_scores` | JSON \| null | `null` | 選項文字→分值對應（僅 `scoring_type = custom` 時使用） |

---

## 3. scoring_direction（計分方向性）

| 值 | 說明 |
| --- | --- |
| `positive` | 「是」/ 第一選項 = 好的答案（得高分） |
| `negative` | 「否」/ 第一選項 = 好的答案（得高分）；「是」/ 最後選項 = 差的答案（得低分） |

### Scenario: boolean 正向題
- **WHEN** `question_type = boolean`，`scoring_direction = positive`，供應商答「是」
- **THEN** `answer_score = 1.0`

### Scenario: boolean 反向題答「否」
- **WHEN** `question_type = boolean`，`scoring_direction = negative`，供應商答「否」
- **THEN** `answer_score = 1.0`（否定反向題 = 好的答案）

### Scenario: boolean 反向題答「是」
- **WHEN** `question_type = boolean`，`scoring_direction = negative`，供應商答「是」
- **THEN** `answer_score = 0.0`

---

## 4. scoring_type（計分類型）

| 值 | 說明 |
| --- | --- |
| `ordered_asc` | 有序單選，第一個選項最差，自動線性化 |
| `ordered_desc` | 有序單選，第一個選項最好，自動線性化 |
| `custom` | 自訂分值，搭配 `option_scores` JSON |
| `evidence_only` | 佐證說明題，不計入評分 |
| `llm` | 開放式文字題，由 LLM 評分 |
| `null` | 未設定，使用既有 fallback 邏輯（向後相容） |

### Scenario: ordered_asc 自動線性化
- **WHEN** `scoring_type = ordered_asc`，選項共 5 個，供應商選第 3 個（index 2）
- **THEN** `answer_score = round(2 / (5-1), 4) = 0.5`

### Scenario: ordered_desc 自動線性化
- **WHEN** `scoring_type = ordered_desc`，選項共 4 個，供應商選第 1 個（index 0）
- **THEN** `answer_score = 1.0`（第一個是最好的）

### Scenario: custom 分值查找
- **WHEN** `scoring_type = custom`，`option_scores = {"每季": 1.0, "每年": 0.6, "從未": 0.0}`，供應商選「每年」
- **THEN** `answer_score = 0.6`

### Scenario: evidence_only 不計分
- **WHEN** `scoring_type = evidence_only`
- **THEN** `raw_score = null`，此題不納入 E/S/G 分母，UI 顯示「📎 佐證」標籤

### Scenario: null fallback
- **WHEN** `scoring_type = null`
- **THEN** 使用既有 `_score_single_response()` fallback 邏輯，行為與升級前相同

---

## 5. option_scores（選項分值字典）

當 `scoring_type = custom` 時使用，格式為 JSON 物件：

```json
{"每季": 1.0, "每年": 0.6, "不定期": 0.3, "從未": 0.0}
```

- key：選項文字（與問卷選項文字完全匹配）
- value：0.0–1.0 之間的浮點數

### Scenario: 完整設定
- **WHEN** `option_scores` 涵蓋題目所有選項
- **THEN** scoring service 直接 lookup，不使用 fallback

### Scenario: 部分缺失 fallback
- **WHEN** 供應商的答案不在 `option_scores` key 中
- **THEN** `answer_score = 0.5`（中立分），`score_confidence = 'low'`

---

## 6. 模板編輯器計分設定面板（UI）

模板編輯器在每題設定區提供計分設定面板，讓設計者選擇 `scoring_direction` 與 `scoring_type`。

### 6.1 預設值

- `scoring_direction = positive`
- `scoring_type = null`

### Scenario: 新建題目預設值
- **WHEN** 設計者新增一題，未手動設定
- **THEN** `scoring_direction = positive`，`scoring_type = null`

### Scenario: 選擇 evidence_only
- **WHEN** 設計者將題目標記為 `evidence_only`
- **THEN** 分數設定欄位隱藏，題目卡片顯示「📎 佐證說明」badge

### Scenario: 選擇 custom 顯示分值輸入
- **WHEN** 設計者選擇 `scoring_type = custom`
- **THEN** 每個選項旁出現 0–100 分值輸入欄

### Scenario: 選擇 llm
- **WHEN** 設計者選擇 `scoring_type = llm`
- **THEN** 選項分值設定隱藏，顯示「由 AI 評分」提示

---

## 7. 限制

- `option_scores` 僅在 `scoring_type = custom` 時有意義，其他類型忽略
- `scoring_type` 變更後，既有 SAQ 回答需重新計分（觸發 `weight_updated` 類型 snapshot）
- `project_questions` 的 `scoring_direction`、`scoring_type`、`option_scores` 在 SAQ 專案建立時從 `template_questions` 快照複製，之後獨立管理
