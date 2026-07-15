# SAQ LLM 文字題評分規格

**capability**: `saq-llm-text-scoring`  
**版本**: v1  
**最後更新**: 2026-06-23

---

## 1. 概述

針對 `scoring_type = llm` 的開放式文字題，主計分完成後由獨立 Celery 任務呼叫 LLM 進行品質評分，結果非同步寫回並觸發 SAQ 重算。LLM 評分不阻塞主計分流程與審核員進入審核。

---

## 2. 資料欄位

### 2.1 `saq_responses` 新增欄位

| 欄位 | 類型 | 說明 |
| --- | --- | --- |
| `llm_score` | decimal(5,2) \| null | LLM 給分（0–100 scale） |
| `llm_score_reason` | text \| null | LLM 評分理由（繁體中文） |
| `score_confidence` | enum \| null | `high` / `medium` / `low` / null |

### 2.2 score_confidence 計算規則

| 條件 | score_confidence |
| --- | --- |
| scoring_type 有明確 metadata（boolean/ordered/custom），且有回答 | `high` |
| LLM 已完成評分 | `medium` |
| LLM 尚未評分、或任務失敗、或無 metadata（fallback） | `low` |
| `evidence_only` | `null`（不顯示） |

---

## 3. Celery 任務流程

### 3.1 任務派發

```text
主計分 callback 寫入 saqs.score 後
    ↓
對該 SAQ 中所有 scoring_type = llm 的 responses
    ↓
批次派發 llm_text_scoring_task（每題一個任務）
```

### 3.2 LLM 評分完成

```text
LLM 回傳 JSON 結果
    ↓
寫入 saq_responses.llm_score（0–100 scale）
寫入 saq_responses.llm_score_reason（繁體中文說明）
更新 saq_responses.score_confidence = 'medium'
    ↓
觸發 SAQ 重算，更新 raw_score（answer_score = llm_score / 100）
```

### 3.3 任務失敗處理

```text
重試 3 次後仍失敗
    ↓
score_confidence = 'low'
llm_score = null
raw_score 維持 fallback 值（answer_score = 0.5 → raw_score = 0.5 × weight）
不阻塞審核流程
```

---

## 4. LLM 評分 Prompt Rubric

### 4.1 System Prompt

角色設定：「永續供應鏈稽核專家」

評分標準（rubric）：

| 分數範圍 | 說明 |
| --- | --- |
| 0–20 | 完全未回答、答非所問、或明確否認 |
| 21–40 | 僅提及意識到議題，無具體措施 |
| 41–60 | 有初步措施但缺乏量化或時間表 |
| 61–80 | 有具體措施與部分量化目標 |
| 81–100 | 具體量化目標、時間表、且有佐證文件提及 |

### 4.2 回傳格式

LLM 必須回傳純 JSON，不含其他文字：

```json
{"score": 72, "reason": "提及具體年份與範疇，但缺乏量化減排目標"}
```

### 4.3 Prompt 版本化

- rubric 內容修改時，版本號必須更新
- 舊版本評分結果不自動重算
- `llm_text_scoring_task` payload 須帶 `rubric_version` 欄位

---

## 5. llm_score 換算邏輯

```text
若 llm_score 不為 null：
    answer_score = llm_score / 100
    raw_score = answer_score × weight

若 llm_score 為 null（LLM 尚未評分或失敗）：
    answer_score = 0.5（暫定 fallback）
    raw_score = 0.5 × weight
    score_confidence = 'low'
```

---

## 6. Scenarios

### Scenario: LLM 任務派發
- **WHEN** 主計分 callback 寫入 `saqs.score` 後
- **THEN** 系統對該 SAQ 中所有 `scoring_type = llm` 的 responses 批次派發 `llm_text_scoring_task`

### Scenario: LLM 評分完成
- **WHEN** LLM 回傳評分結果
- **THEN** 系統寫入 `saq_responses.llm_score`（0–100 scale）與 `llm_score_reason`（繁體中文），並更新 `score_confidence = 'medium'`，觸發 SAQ 重算

### Scenario: LLM 任務失敗
- **WHEN** LLM 任務重試 3 次後仍失敗
- **THEN** `score_confidence = 'low'`，`llm_score = null`，`raw_score` 維持現有 fallback 值，不阻塞審核流程

### Scenario: Rubric 一致性
- **WHEN** 同一份文字回答由 LLM 評分兩次（rubric 版本相同）
- **THEN** 分數差距 SHALL 在 ±10 分以內

### Scenario: Prompt 版本化
- **WHEN** rubric 內容被修改
- **THEN** 版本號更新，舊版本評分結果不自動重算

---

## 7. 限制

- LLM 評分獨立於主計分任務，不阻塞主分數回傳
- `llm_text_scoring_task` 屬於非同步 Celery 任務，計算邏輯在 esgchain-ai
- esgchain-api 透過 callback endpoint 接收 LLM 評分結果
