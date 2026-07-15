## ADDED Requirements

### Requirement: TAXONOMY 常數

`src/constants/questionBank.ts` SHALL 匯出 `QUESTION_BANK_TAXONOMY`（三大類 × 細項陣列）與 `applyBankFilter(items, payload)` helper。

#### Scenario: 切換大類時細項自動 reset

- **WHEN** L1 選擇從 ESG 切換至 ISO 20400
- **THEN** L2 自動重設為「所有細項」，emit payload 不帶 category/tag

#### Scenario: client-side filter 三條件 AND

- **WHEN** keyword="人權", tag="ISO-人權"
- **THEN** 只回傳 question_text 含「人權」且 tags 含「ISO-人權」的題目

### Requirement: QuestionBankFilter 元件

`src/components/common/QuestionBankFilter.vue` SHALL 渲染：關鍵字 input（debounce 300ms）+ 大類 select + 細項 select（依大類動態產生），三個控制項同一列。

#### Scenario: 大類未選時細項 disabled

- **WHEN** L1 為「所有大類」
- **THEN** L2 select disabled，顯示「— 請先選大類 —」

#### Scenario: emit 格式正確

- **WHEN** L1=ISO 20400, L2=人權, keyword=""
- **THEN** emit change({ keyword: "", tag: "ISO-人權" })，無 category 欄位

### Requirement: QuestionBankView 整合

QuestionBankView SHALL 以 `<QuestionBankFilter>` 取代舊 `<select optgroup>`，onChange 觸發 API re-fetch（`?category=` 或 `?tag=` + `?keyword=`）。

### Requirement: BankImportModal 整合

BankImportModal SHALL 以 `<QuestionBankFilter>` 取代舊過濾列，onChange 呼叫 `applyBankFilter` 對 allItems 做 client-side filter。

### Requirement: TemplateDetailView 整合

TemplateDetailView 題目 table 上方 SHALL 加入 `<QuestionBankFilter>`，computed `filteredQuestions` 套用 `applyBankFilter`，預設顯示全部題目。
