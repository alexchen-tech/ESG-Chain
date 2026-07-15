## Context

現有 `saq_questions` 表的 `template_id` 為 NOT NULL，`foreign key cascadeOnDelete`。5 道現有題目都綁定同一個範本。`saq_responses.question_id` FK 至 `saq_questions.id`，需確保遷移後 FK 不斷鏈。計分引擎（FastAPI）接收 `[{question_id, category, weight, answer, sasb_topic}]`，不依賴 template_id，因此計分不受影響。

## Goals / Non-Goals

**Goals:**
- `saq_questions.template_id` nullable（題庫題目 = NULL，範本專屬題 = 有值）
- `saq_questions.tags` JSON 欄位（預設 10 個 tag 的 array）
- `saq_template_questions` pivot：template_id / question_id / order / weight_override
- 現有 5 道題遷移：template_id → NULL + pivot 補建
- QuestionBankController CRUD + usage_count
- 「從題庫選題」快照：複製 bank question 欄位建立新 saq_question（有 template_id）

**Non-Goals:**
- 題庫版本控制
- 跨題庫 merge / diff
- 批次 CSV 匯入題庫

## Decisions

**D1：題庫題目 vs 範本專屬題目的區分**
`template_id IS NULL` = 題庫題目；`template_id IS NOT NULL` = 範本專屬（保持現有行為）。
不引入新表，利用現有 `saq_questions` 表，簡化資料模型。

**D2：快照引用（Snapshot）**
「從題庫選題」時，將 bank question 所有欄位（question_text/category/question_type/options/weight/is_required/sasb_topic_id/sasb_metric_code/tags）**複製**一份，建立新 `saq_questions` 記錄，template_id 設為目標範本，order 設為範本現有最大 order+1。
- 好處：範本題目獨立存在，不受題庫後續修改影響（問卷進行中安全）
- pivot `saq_template_questions` 不需要，因為已複製，每個範本有獨立副本
- 因此 **D2 使得 saq_template_questions 不需要建立**

**D3：Tag 預設清單（10 個）**
```
'E', 'S', 'G',
'地域風險',
'ISO-組織治理', 'ISO-人權', 'ISO-勞工',
'ISO-環境', 'ISO-公平營運', 'ISO-消費者', 'ISO-社區'
```
存為 JSON array，允許多選。前端以 checkbox 選取。

**D4：usage_count**
即時計算：`saq_questions WHERE template_id IS NULL` 查各 bank question id 在 `saq_questions` 中被複製幾次（透過 `source_bank_question_id` 欄位追蹤）。
為追蹤快照來源，`saq_questions` 補 `source_bank_question_id nullable`（從題庫複製時記錄原始 bank question id）。
usage_count = `saq_questions.where(source_bank_question_id = bank_question.id).count()`

**D5：現有 5 道題遷移**
Migration script：
1. 這 5 道題的 `template_id` → NULL（成為題庫題目）
2. `source_bank_question_id` = NULL（它們本來就是原始題）
3. 在 `saq_questions` 建 5 筆新記錄（template_id = 原範本 id，order 不變，source_bank_question_id = 原 5 道題的 id）
4. 這樣範本仍有 5 道題（快照副本），題庫也有 5 道原始題

## Risks / Trade-offs

- **saq_responses FK**：response.question_id 指向的是範本副本（有 template_id 的題目），不指向題庫原題，因此遷移後不斷鏈
- **cascadeOnDelete**：template_id nullable 後，刪除範本不會級聯刪除 template_id IS NULL 的題庫題目（只刪有 template_id 的副本），符合預期
- **tags 欄位**：nullable JSON，不設 enum 約束，允許未來擴充 tag 清單

## Migration Plan

1. `alter_saq_questions_for_question_bank` migration：
   - `template_id` 改 nullable
   - 新增 `tags` JSON nullable
   - 新增 `source_bank_question_id` nullable UUID（self-reference，不設 FK 避免循環）
2. Migration script（在同一 migration 的 `up()` 中執行 DB 操作）：
   - 將現有 5 道題 template_id 設 NULL
   - 為原範本建 5 道快照副本（template_id = 原範本 id，source_bank_question_id = bank 題 id）
