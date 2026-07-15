## Context

三處國家輸入欄位目前均為 `<input maxlength="2">`，無搜尋、無驗證、無中文引導。`SuppliersView` 的 `form.country` 欄位名稱錯誤，導致新增供應商時 country_code 不被寫入後端。

## Goals / Non-Goals

**Goals:**
- `countries.ts`：196 國 ISO 3166-1 alpha-2 靜態清單，含 `code / name(中) / name_en(英)`
- `CountrySelect.vue`：可複用 Combobox，Options API，零外部依賴
- 統一套用三處，修正 SuppliersView bug

**Non-Goals:**
- 後端 countries 資料表
- 電話區碼（+886）
- 國旗 emoji（可以之後加）

## Decisions

**D1：資料放前端 constants，不走 API**
196 筆靜態資料，ISO 3166 極少變動。bundle size 約 6KB，無需 API round-trip。

**D2：Combobox 用 custom dropdown，不用 `<datalist>`**
`<datalist>` 跨瀏覽器樣式不可控，且無法顯示中文名+代碼的組合格式。
用 `v-if` 控制 dropdown div，配合 `@blur` + 100ms delay 關閉（防止點選前觸發）。

**D3：搜尋同時匹配中文名 + 代碼（OR 邏輯）**
```
輸入 "tw"  → 匹配 code="TW"（台灣）
輸入 "台"  → 匹配 name="台灣"、name="台..."
輸入 "jap" → 匹配 name_en="Japan"
```
最多顯示 10 筆，引導使用者繼續輸入縮小範圍。

**D4：modelValue 存 code（2字元大寫），顯示「名稱 (XX)」**
- input placeholder 顯示「搜尋國家...」
- 有值時 input 顯示「台灣 (TW)」
- 使用者開始輸入後清空顯示值，重新搜尋
- 選定後 emit `update:modelValue` 傳 code
- 清空 input → emit null

**D5：SuppliersView 修 bug：`form.country` → `form.country_code`**
同時把新增供應商 Modal 的 `<input>` 換成 `<CountrySelect>`。

## Risks / Trade-offs

- **blur 關閉 timing**：點選 option 時會先觸發 input blur，需 100ms setTimeout 讓 click 先執行
- **v-model 初始顯示**：父層傳入 `"TW"` 時，input 應顯示「台灣 (TW)」而非空白——需在 mounted/watch 設定 displayValue
