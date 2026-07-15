## Context

題庫分類目前混用兩個欄位：ESG 大類用 `category`（E/S/G），ISO 20400 與地緣政治用 `tags` JSON array。三個使用者入口（QuestionBankView、BankImportModal、TemplateDetailView）各自用 `<select optgroup>` 搭配 `"cat:E"` / `"tag:ISO-人權"` 前綴 hack 處理這個差異，邏輯重複、難維護。

## Goals / Non-Goals

**Goals:**
- 單一 `<QuestionBankFilter>` 元件封裝兩階分類 + 關鍵字，emit 統一格式
- TAXONOMY 常數集中定義，新增分類只改一處
- 三個入口體驗一致，各自決定 API re-fetch 或 client-side filter

**Non-Goals:**
- 後端 API 不變（`?category=` 與 `?tag=` 參數維持）
- 不處理多選分類（單一細項選取即可）
- 不改變題目資料模型

## Decisions

### 1. TAXONOMY 常數放 `src/constants/questionBank.ts`

每個節點定義 `param`，型別為 `{ category: string } | { tag: string }`。父元件收到 emit 後直接解構傳給 API 或 filter helper，不需要 `"cat:"` / `"tag:"` 前綴 hack。

### 2. `applyBankFilter` helper 放同一個檔案

```ts
function applyBankFilter(items, { keyword, category, tag }):
  → keyword: question_text includes（不分大小寫）
  → category: item.category === category
  → tag: item.tags?.includes(tag)
  → 三個條件 AND 組合
```

TemplateDetailView 和 BankImportModal 都呼叫這個 helper，不各自寫 filter 邏輯。

### 3. `<QuestionBankFilter>` 用 Options API，emit `change`

```
props:  無（filter 狀態內部管理）
emits:  change(payload: FilterPayload)
```

L1 切換時自動 reset L2（`selectedL2 = ''`），同時 emit。keyword 用 300ms debounce 避免每keystroke 觸發 API。

### 4. TemplateDetailView 過濾為純 client-side

範本題目已全量載入，filter 直接操作 computed `filteredQuestions`，不重新呼叫 API。QuestionBankFilter 放在題目 table 上方，佔滿一行。

## Risks / Trade-offs

- [Debounce 造成 keyword 更新延遲] → 300ms 為可接受的 UX 延遲，使用者感知不明顯
- [TemplateDetailView 題目量大時 client filter 慢] → 範本題目通常 < 100 道，無效能疑慮
