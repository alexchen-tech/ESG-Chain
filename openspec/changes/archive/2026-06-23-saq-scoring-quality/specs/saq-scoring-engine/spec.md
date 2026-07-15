## MODIFIED Requirements

### Requirement: 單題計分邏輯（_score_single_response）

`_score_single_response()` SHALL 依以下優先順序決定 `answer_score`（0–1 scale）：

1. **evidence_only**：`scoring_type = evidence_only` → 回傳 `None`（不計分）
2. **llm**：`scoring_type = llm` → 主計分時回傳 `0.5`（暫定），等待 LLM 非同步補分
3. **custom**：`scoring_type = custom` → lookup `option_scores`，未命中回傳 `0.5`
4. **ordered_asc**：選項 index `i`，共 `n` 個 → `round(i / (n-1), 4)`
5. **ordered_desc**：選項 index `i`，共 `n` 個 → `round((n-1-i) / (n-1), 4)`
6. **boolean + direction**：依 `scoring_direction` 決定 0 / 1
7. **fallback（null metadata）**：維持現有 `OPTION_SCORE_MAP` + 文字 50 + 多選數量邏輯

#### Scenario: evidence_only 排除分母
- **WHEN** `scoring_type = evidence_only`
- **THEN** 此題不加入 `Σ(raw_score)` 也不加入 `Σ(weight)`，完全排除於 E/S/G 聚合之外

#### Scenario: ordered_asc 5 選項第 3 個
- **WHEN** `scoring_type = ordered_asc`，5 個選項，供應商選 index 2（第 3 個）
- **THEN** `answer_score = round(2/4, 4) = 0.5`，`raw_score = 0.5 × weight`

#### Scenario: 未回答題懲罰不變
- **WHEN** 題目無回答（`answer = null`，`answer_options = []`），且非 `evidence_only`
- **THEN** `answer_score = 0.0`，計入分母（懲罰性）

#### Scenario: null metadata 向後相容
- **WHEN** `scoring_type = null`（舊模板）
- **THEN** 行為與 saq-scoring-v2 實作前相同，不因升級而改變分數
