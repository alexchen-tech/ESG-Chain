## Why

題庫（QuestionBankView）、從題庫選題（BankImportModal）、範本題目管理（TemplateDetailView）三個地方各自用一個 `<select optgroup>` 做分類過濾，邏輯重複且不一致。使用者需要一個統一的「大類 → 細項」兩階級聯過濾 UI，同時支援關鍵字搜尋，三個入口體驗一致。

## What Changes

- **新增** `src/constants/questionBank.ts`：定義 `QUESTION_BANK_TAXONOMY`（兩階分類樹）與 `applyBankFilter` client-side filter helper
- **新增** `src/components/common/QuestionBankFilter.vue`：關鍵字 input + 大類 select + 細項 select（級聯），emit `{ keyword, category?, tag? }`
- **修改** `QuestionBankView.vue`：換掉舊 `<select optgroup>`，改用 `<QuestionBankFilter>`，onChange 觸發 API re-fetch
- **修改** `BankImportModal.vue`：換掉舊過濾列，改用 `<QuestionBankFilter>`，onChange 做 client-side filter
- **修改** `TemplateDetailView.vue`：題目 table 上方新增 `<QuestionBankFilter>`，onChange 做 client-side filter

## Capabilities

### New Capabilities

- `question-bank-filter`: 可複用的兩階分類查詢元件，關鍵字 + 大類（ESG / ISO 20400 / 地緣政治）+ 細項級聯，emit 統一 filter params，父元件決定 API fetch 或 client filter

### Modified Capabilities

（無 spec-level 行為變更，只修改 UI 實作）

## Impact

- 前端：`QuestionBankView.vue`、`BankImportModal.vue`、`TemplateDetailView.vue`
- 新增：`src/constants/questionBank.ts`、`src/components/common/QuestionBankFilter.vue`
- 後端 API 無需變更（`?category=` 與 `?tag=` 參數維持不變）
