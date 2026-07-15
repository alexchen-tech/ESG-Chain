> ⚠ 已被 `tag-library-and-project-domain` 取代，請勿繼續實作本變更。

## Why

題庫題目目前使用單一 `tags` JSON array 混用三種語意：ESG 分類（E/S/G）、ISO 26000 七大主題（ISO-勞工等）、未來保留標籤。這造成資料模型不清晰、篩選邏輯需要前綴 hack、無法支援未來 L3 細項擴充。Phase 1 目標是將 ISO 26000 七大主題從 `tags` 獨立出來成為正式欄位 `iso_subject`，建立清楚的兩個分類維度：`category`（L2 ESG Pillar）× `iso_subject`（L2 ISO 26000 主題）。

## What Changes

- **新增** DB migration：`saq_questions` 加 `iso_subject` nullable enum 欄位（七大 ISO 26000 主題）
- **資料遷移**：現有 `tags` 中的 `ISO-xxx` 值轉移至 `iso_subject`，`tags` 保留但移除 E/S/G 與 ISO-xxx 項目
- **後端**：`QuestionBankController` 支援 `?iso_subject=` 查詢參數；store/update 驗證 `iso_subject` enum；`QUESTION_BANK_TAGS` 常數清理
- **前端 Modal**：標籤選擇改為 `category` radio（必填）+ `iso_subject` radio（選填），移除舊 tag checkboxes
- **前端列表**：標籤欄改顯示 `iso_subject` 值（取代 tag chips）
- **QuestionBankFilter**：`QUESTION_BANK_TAXONOMY` 中 ISO 20400 群組改為查詢 `?iso_subject=`，移除 ESG 群組（category 欄位已獨立存在）

## Capabilities

### New Capabilities

- `question-bank-taxonomy`: 題庫題目的 L2 雙維度分類（ESG Pillar + ISO 26000 主題），定義兩個獨立的分類維度語意與 UI 互動規範

### Modified Capabilities

（無既有 spec-level 規格，為全新能力）

## Impact

- **DB**：`saq_questions` 新增 `iso_subject` 欄位，現有 `tags` 資料遷移
- **後端**：`QuestionBankController`、`SAQQuestion` model、`DEFAULT_TAGS` 常數
- **前端**：`QuestionBankView.vue`（Modal + 列表）、`QuestionBankFilter.vue`、`src/constants/questionBank.ts`
- **不影響**：範本題目（template questions）的資料結構不變，`source_bank_question_id` snapshot 機制不變
