## Context

SAQ 計分引擎（`esgchain-ai/scoring_service.py`）目前透過 `_score_single_response()` 對所有題型使用同一套靜態規則。問題集中在三點：反向題語意錯誤、有序單選喪失鑑別力、文字題只看有無填寫。

涉及三個服務：esgchain-api（資料模型、任務派發）、esgchain-ai（計分邏輯、LLM 任務）、esgchain-web（模板編輯器、審核檢視頁）。

審核員目標：20 分鐘內審核完一份問卷（約 12 題）。LLM 評分必須在供應商提交後非同步完成，不可阻塞審核流程。

## Goals / Non-Goals

**Goals**
- 修正反向題評分錯誤
- 有序單選題自動線性化，無需人工逐項設定分值
- 文字題明確分類（佐證 vs. 開放式評估），佐證題不計分
- 開放式文字題由 LLM 評分，附評分理由
- 每題計算信心度（high/medium/low），低信心題在 UI 標示 ⚠

**Non-Goals**
- 不修改 E/S/G 維度權重邏輯
- 不修改分數快照、申訴、Shipment 鎖定機制
- 不引入即時（同步）LLM 評分，維持非同步 Celery 架構
- 不對現有已完成 SAQ 自動重算（只影響新提交）

## Decisions

### 決策 1：scoring_type 用 enum 而非純 JSON 分值字典

**選擇**：在 `template_questions` / `project_questions` 加入 `scoring_type` enum（`ordered_asc` / `ordered_desc` / `custom` / `evidence_only` / `llm`）和 `option_scores` JSON（custom 才需填）。

**理由**：純 JSON 字典需要模板設計者對每個選項手動填分，負擔高且容易出錯。`ordered_asc`/`ordered_desc` 的自動線性化可覆蓋 80% 的有序單選題，`custom` 保留給分值非線性的邊緣情況。

**放棄的方案**：純靠 `option_scores` JSON，設定成本過高且容易遺漏。

---

### 決策 2：自動線性化算法

```
n = 選項數量
score[i] = round(i / (n - 1) * 100)   ← i 從 0 開始

5 個選項：0 / 25 / 50 / 75 / 100
4 個選項：0 / 33 / 67 / 100
3 個選項：0 / 50 / 100
```

`ordered_desc` 則反轉 index（第一個選項得 100）。

**理由**：等差分佈是最直覺、最容易向審核員解釋的方式。如需非線性，改用 `custom`。

---

### 決策 3：LLM 評分獨立 Celery task，不與主計分合併

**選擇**：主計分 task（`scoring_tasks.py`）完成後，對 `scoring_type = llm` 的題目另外派發 `llm_text_scoring_task`，完成後 patch `saq_responses.llm_score` 與 `llm_score_reason`，並觸發 SAQ 重算。

**理由**：LLM 回應時間不穩定（1–10 秒），若與主計分合併，會延遲整個 callback，阻礙審核員進入審核狀態。獨立 task 讓主分數先到，LLM 分數後補。

**放棄的方案**：同步呼叫 LLM 於 `_score_single_response()` 內，會使整個計分任務逾時風險大增。

---

### 決策 4：`evidence_only` 題不計分，但仍顯示於 UI

**選擇**：`scoring_type = evidence_only` 的題目 `raw_score = null`，不納入分母（不懲罰），UI 顯示「📎 佐證」標籤，分數欄顯示「—」。

**理由**：若計入分母但給 0 分，會懲罰提供完整佐證的供應商（因為佐證題「答」了仍得 0）。若給固定分（如 50），則失去佐證的意義。最合理是完全排除在計分之外。

---

### 決策 5：信心度（confidence）計算規則

| scoring_type | 信心度 |
|---|---|
| `boolean`（有 direction） | HIGH |
| `ordered_asc` / `ordered_desc` | HIGH |
| `custom`（有 option_scores） | HIGH |
| `llm`（LLM 已回傳） | MEDIUM |
| `llm`（LLM 尚未回傳） | LOW |
| fallback（無 metadata） | LOW |
| `evidence_only` | —（不顯示） |

信心度存於 `saq_responses.score_confidence`，不存於 snapshot。

## Risks / Trade-offs

**[風險] LLM 評分一致性**  
→ 緩解：使用固定 system prompt rubric，包含題目類型、ESG 脈絡、評分標準（0=完全未回答, 50=模糊提及, 100=具體量化有佐證）。rubric 版本化管理於 `esgchain-ai/app/prompts/`。

**[風險] 舊問卷模板無 metadata 欄位**  
→ 緩解：`scoring_type` 預設為 `null`，scoring service 遇到 `null` 使用現有 fallback 邏輯（維持向後相容）。舊模板不受影響，新建模板才需設定。

**[風險] LLM task 失敗時分數卡在 LOW 信心**  
→ 緩解：task 最多重試 3 次，失敗後 `score_confidence = 'low'`，UI ⚠ 提示審核員手動覆核。不阻塞審核流程。

**[Trade-off] 自動線性化不適用於非線性分值題**  
→ 設計者可改用 `custom`，但設定成本增加。接受此 trade-off，因為非線性題在現有問卷中屬少數。

## Migration Plan

1. 執行 migration：`template_questions` / `project_questions` 加入 `scoring_direction`、`scoring_type`、`option_scores`；`saq_responses` 加入 `llm_score`、`llm_score_reason`、`score_confidence`
2. 所有現有欄位預設 `null`，scoring service fallback 邏輯不變
3. 模板編輯器上線後，設計者可逐步為現有模板的題目補充 metadata
4. 不需要重算歷史 SAQ（避免分數歷史不一致）

Rollback：移除新欄位 migration（nullable，drop 安全），還原 scoring_service.py。

## Open Questions

- LLM rubric 由誰維護？是否需要版本審查流程（避免 rubric 改動影響分數一致性）？
- `custom` option_scores 是否需要驗證「所有選項都要有對應分值」，還是允許部分缺失時 fallback？
- 模板複製（clone）時，`scoring_type` / `option_scores` 是否隨著複製？（建議是）
