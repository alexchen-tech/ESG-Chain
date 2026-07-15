## ADDED Requirements

### Requirement: LLM 文字題非同步評分

當 SAQ 計分完成後，系統 SHALL 對所有 `scoring_type = llm` 的題目額外派發 `llm_text_scoring_task` Celery 任務進行品質評分。

LLM 評分 SHALL 獨立於主計分任務，不阻塞主分數的回傳與審核員進入審核。

#### Scenario: LLM 任務派發
- **WHEN** 主計分 callback 寫入 `saqs.score` 後
- **THEN** 系統對該 SAQ 中所有 `scoring_type = llm` 的 responses 批次派發 `llm_text_scoring_task`

#### Scenario: LLM 評分完成
- **WHEN** LLM 回傳評分結果
- **THEN** 系統寫入 `saq_responses.llm_score`（0.0–1.0）與 `llm_score_reason`（文字說明），並更新 `score_confidence = medium`，觸發 SAQ 重算（更新 `raw_score`）

#### Scenario: LLM 任務失敗
- **WHEN** LLM 任務重試 3 次後仍失敗
- **THEN** `score_confidence = low`，`llm_score = null`，`raw_score` 維持現有 fallback 值（50 分），不阻塞審核流程

---

### Requirement: LLM 評分 Prompt Rubric

LLM 評分 SHALL 使用固定 system prompt，包含：
- 角色設定：「永續供應鏈稽核專家」
- 評分標準（rubric）：

| 分數範圍 | 說明 |
|---|---|
| 0–20 | 完全未回答、答非所問、或明確否認 |
| 21–40 | 僅提及意識到議題，無具體措施 |
| 41–60 | 有初步措施但缺乏量化或時間表 |
| 61–80 | 有具體措施與部分量化目標 |
| 81–100 | 具體量化目標、時間表、且有佐證文件提及 |

- 回傳格式：`{"score": 72, "reason": "提及具體年份與範疇，但缺乏量化減排目標"}` (JSON only)

#### Scenario: Rubric 一致性
- **WHEN** 同一份文字回答由 LLM 評分兩次
- **THEN** 分數差距 SHALL 在 ±10 分以內（rubric 版本相同）

#### Scenario: Prompt 版本化
- **WHEN** rubric 內容被修改
- **THEN** 版本號更新，舊版本評分結果不自動重算

---

### Requirement: LLM 評分結果欄位

`saq_responses` SHALL 新增以下欄位：

| 欄位 | 類型 | 說明 |
|---|---|---|
| `llm_score` | decimal(5,2) \| null | LLM 給分（0–100 scale，非 0–1） |
| `llm_score_reason` | text \| null | LLM 評分理由（繁體中文） |
| `score_confidence` | enum \| null | `high` / `medium` / `low` |

#### Scenario: llm_score 轉換為 answer_score
- **WHEN** `llm_score` 不為 null
- **THEN** `answer_score = llm_score / 100`，`raw_score = answer_score × weight`

#### Scenario: 信心度計算規則

| 條件 | score_confidence |
|---|---|
| scoring_type 有明確 metadata（boolean/ordered/custom），且有回答 | `high` |
| LLM 已完成評分 | `medium` |
| LLM 尚未評分、或任務失敗、或無 metadata（fallback） | `low` |
| evidence_only | null（不顯示） |

- **WHEN** `score_confidence` 計算完成
- **THEN** 值寫入 `saq_responses.score_confidence`
