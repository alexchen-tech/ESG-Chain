## ADDED Requirements

### Requirement: 題目信心度視覺化

ReviewDetailView 的每題分數列 SHALL 依 `score_confidence` 顯示信心度徽章：

| confidence | 顯示 | 樣式 |
|---|---|---|
| `high` | ● 高信心 | 綠色小點 |
| `medium` | ◐ 中信心 | 黃色半圓 |
| `low` | ⚠ 建議覆核 | 橘色警示 |
| `null`（evidence_only） | 不顯示 | — |

#### Scenario: 高信心題快速確認
- **WHEN** 題目 `score_confidence = high`
- **THEN** UI 不強調覆核，審核員可快速掃視確認

#### Scenario: 低信心題標示警示
- **WHEN** 題目 `score_confidence = low`
- **THEN** 題目卡片右上角顯示 ⚠ 橘色 badge，分數列附「建議覆核」文字

#### Scenario: LLM 評分理由顯示
- **WHEN** `score_confidence = medium`（LLM 已評分）
- **THEN** 分數列下方折疊顯示 `llm_score_reason`，審核員可展開查看 AI 理由後決定是否覆核

---

### Requirement: 佐證題 UI 標示

`scoring_type = evidence_only` 的題目 SHALL 在題目卡片中明確標示為佐證說明，不顯示分數列。

#### Scenario: evidence_only 題目顯示
- **WHEN** 題目 `scoring_type = evidence_only`
- **THEN** 題目 header 顯示「📎 佐證」chip，分數列區域改為「此題為佐證說明，不計入評分」灰色提示文字

#### Scenario: evidence_only 無覆核入口
- **WHEN** SAQ 狀態為 `under_review` 或 `re_review`，且題目為 `evidence_only`
- **THEN** 覆核分輸入面板 SHALL NOT 顯示
