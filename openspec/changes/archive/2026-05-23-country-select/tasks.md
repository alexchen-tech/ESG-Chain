## 1. 國家常數資料

- [x] 1.1 建立 `src/utils/countries.ts`：196 個 ISO 3166-1 alpha-2，每筆 `{ code, name, name_en }`，依常見地區排序（亞太優先）

## 2. CountrySelect.vue 元件

- [x] 2.1 建立 `src/components/common/CountrySelect.vue`（Options API）：props `modelValue`、`placeholder`；data `query`、`displayValue`、`open`；computed `filtered`（最多 10 筆，同時匹配 code/name/name_en）
- [x] 2.2 實作 `selectCountry(code)`：設 displayValue、emit update:modelValue、關閉下拉
- [x] 2.3 實作 `onInput()`：開啟下拉、query 同步 displayValue
- [x] 2.4 實作 `onBlur()`：100ms delay 後關閉下拉；若輸入為空則 emit null 並清空 displayValue
- [x] 2.5 實作 `onEsc()`：關閉下拉，恢復 displayValue 為已選定值
- [x] 2.6 watch `modelValue`：外部值變更時同步更新 displayValue
- [x] 2.7 mounted：根據初始 modelValue 設定 displayValue

## 3. 套用至各頁面

- [x] 3.1 `SupplierDetailView.vue`：編輯模式國家碼欄位換成 `<CountrySelect v-model="editForm.country_code" />`
- [x] 3.2 `SuppliersView.vue`：修正 `form.country` → `form.country_code`（data 初始化 + v-model + create API 送出），國家欄換成 `<CountrySelect v-model="form.country_code" />`
- [x] 3.3 `SettingsView.vue`：組織架構新增 Modal + 編輯 Modal 的國家碼欄位換成 `<CountrySelect v-model="ouForm.country_code" />` 與 `<CountrySelect v-model="editingOu.country_code" />`

## 4. 樣式

- [x] 4.1 CountrySelect.vue scoped CSS：input 全寬繼承 `form-input` 樣式、dropdown absolute 定位、option hover 樣式、empty/hint 文字樣式、`z-index: 50` 確保覆蓋其他元素

## 5. TypeScript 驗證

- [x] 5.1 執行 `npx vue-tsc --noEmit` 確認無型別錯誤
