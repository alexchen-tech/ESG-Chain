## Why

系統目前三處需要輸入國家代碼（供應商明細編輯、新增供應商、組織架構 OU），均採用自由文字輸入（maxlength=2），無法防止非法代碼（如小寫、拼錯）。SuppliersView 新增供應商表單的欄位名稱仍為舊的 `form.country`（應為 `form.country_code`），導致建立時國家欄位不被寫入。統一改為 Combobox 選擇器後，資料品質有保障且操作更直覺。

## What Changes

- 新增 `src/utils/countries.ts`：196 個 ISO 3166-1 alpha-2 國家常數，含中文名稱與英文名稱
- 新增 `CountrySelect.vue`：Combobox 元件，支援中文/代碼搜尋，最多顯示 10 筆，Esc 關閉，選定後存 2 字元大寫代碼
- 套用至三處：`SupplierDetailView.vue` 編輯模式、`SuppliersView.vue` 新增供應商 Modal、`SettingsView.vue` 組織架構新增/編輯 Modal
- 修正 `SuppliersView.vue` `form.country` → `form.country_code` bug

## Capabilities

### New Capabilities
- `country-select-component`: CountrySelect.vue Combobox，props: modelValue(string)，emit: update:modelValue，顯示「中文名稱 (XX)」，搜尋同時匹配中文名 + 代碼

### Modified Capabilities
- `supplier-create`: SuppliersView.vue 新增供應商 form 欄位名稱修正，國家欄改用 CountrySelect
- `supplier-edit`: SupplierDetailView.vue 編輯模式國家碼欄位改用 CountrySelect
- `org-unit-management`: SettingsView.vue 組織架構新增/編輯 Modal 國家碼欄位改用 CountrySelect

## Impact

- **前端**：新增 2 個檔案（countries.ts、CountrySelect.vue），修改 3 個 Vue 頁面
- **後端**：無變更（後端驗證 `size:2` 已足夠）
- **無 DB migration**
