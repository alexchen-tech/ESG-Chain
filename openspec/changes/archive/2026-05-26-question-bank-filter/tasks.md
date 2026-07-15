## 1. 常數與 Helper

- [x] 1.1 建立 `src/constants/questionBank.ts`：定義 `QUESTION_BANK_TAXONOMY`（三大類 × 細項，每節點含 `label` + `param: { category } | { tag }`）
- [x] 1.2 在同檔案匯出 `applyBankFilter(items, { keyword, category, tag })`：三條件 AND，keyword 不分大小寫

## 2. QuestionBankFilter 元件

- [x] 2.1 建立 `src/components/common/QuestionBankFilter.vue`（Options API）：
  - 關鍵字 input（300ms debounce）
  - 大類 select（從 TAXONOMY 產生選項）
  - 細項 select（依 selectedL1 動態產生，L1 為空時 disabled）
- [x] 2.2 L1 切換時自動 reset L2，立即 emit change
- [x] 2.3 emit `change(payload: { keyword: string; category?: string; tag?: string })`
- [x] 2.4 套用 Warm Paper Light 設計系統樣式，三個控制項同一列

## 3. QuestionBankView 整合

- [x] 3.1 移除舊 `<select optgroup>` 與 `filters.filterKey`，改用 `<QuestionBankFilter @change="onFilterChange">`
- [x] 3.2 `onFilterChange` 解構 payload，呼叫 `loadBank()`（帶 keyword/category/tag 至 API）
- [x] 3.3 麵包屑更新：「系統設定 › 題目庫」改為「ESG 問卷 › 問卷題庫」，點擊跳轉 `/questionnaires/templates`（與 TemplateDetailView 麵包屑一致）

## 4. BankImportModal 整合

- [x] 4.1 移除舊 `<select optgroup>`、`filterKey`、`onFilterChange` 舊邏輯
- [x] 4.2 改用 `<QuestionBankFilter @change="onFilterChange">`
- [x] 4.3 `onFilterChange` 呼叫 `applyBankFilter(allItems, payload)` 更新 `filtered`

## 5. TemplateDetailView 整合

- [x] 5.1 在題目 table 上方加入 `<QuestionBankFilter @change="onBankFilterChange">`
- [x] 5.2 新增 `bankFilter` data（`{ keyword:'', category:undefined, tag:undefined }`）與 `filteredQuestions` computed：對 `questions` 套用 `applyBankFilter`
- [x] 5.3 table `v-for` 改為遍歷 `filteredQuestions`，顯示「共 N 道（篩選後）」

## 6. 驗證

- [x] 6.1 `npx vue-tsc --noEmit` 無錯誤
- [ ] 6.2 QuestionBankView：大類選 ISO 20400 → 細項出現 7 項；選細項「人權」→ 只顯示含 ISO-人權 tag 的題目
- [ ] 6.3 BankImportModal：同上邏輯，client-side filter 正常
- [ ] 6.4 TemplateDetailView：題目 table 可依兩階分類篩選，keyword 搜尋有 debounce
- [ ] 6.5 切換 L1 時 L2 自動 reset，不殘留上一個大類的細項選擇
