## 1. 後端修正

- [x] 1.1 `Supplier` Model 補 `sasbIndustry()` belongsTo SasbIndustry 關聯
- [x] 1.2 `SupplierController::show()` 補 eager load `sasbIndustry`（在現有的 group/contacts/statusHistories 之後）
- [x] 1.3 `SupplierController::update()` 修正驗證：`country` → `country_code`，補 `sasb_industry_id` nullable uuid exists:sasb_industries,id
- [x] 1.4 docker cp 後端修改檔案進容器，執行 `php artisan route:cache`

## 2. 前端型別與 API

- [x] 2.1 `suppliers.ts` Supplier interface 補齊：`group`, `contacts`, `statusHistories`, `sasb_industry` 關聯型別
- [x] 2.2 `suppliers.ts` 補 `update()` method（已有但確認包含所有可編輯欄位）

## 3. 供應商明細頁重寫

- [x] 01 頁面 template：7 區塊結構（識別資訊/產業分類/管理歸屬/聯絡資訊/風險評估/問卷記錄/狀態歷程）靜態顯示模式
- [x] 02 識別資訊區塊：名稱、代碼（font-mono）、國家碼、Tier、建立時間
- [x] 03 產業分類區塊：產業文字 + SASB code—industry（`supplier.sasb_industry?.code`）
- [x] 04 管理歸屬區塊：group.name（或「未分組」）、onboarding 階段 badge
- [x] 05 聯絡資訊區塊：地址/網站 + contacts 列表（姓名/職稱/email，primary badge）
- [x] 06 狀態歷程 timeline：statusHistories 降序，每筆顯示 from→to、reason、日期；無資料時顯示提示

## 4. 整頁 Edit 模式

- [x] 01 data 補：`isEditing`、`editForm`（深拷貝 supplier 可編輯欄位）、`sasbOptions`、`groupOptions`、`auxLoaded`
- [x] 02 `enterEditMode()`：設 isEditing=true，若 !auxLoaded 則載入 SASB + 分組 options
- [x] 03 `cancelEdit()`：isEditing=false，丟棄 editForm
- [x] 04 `saveEdit()`：呼叫 `suppliersApi.update()`，成功後 isEditing=false + reload，失敗顯示 alert
- [x] 05 Edit 模式 template：名稱 input、代碼 input、國家碼 input(2)、Tier select(1/2/3)、產業 input、SASB select（optgroup by sector）、分組 select、地址 textarea、網站 input
- [x] 06 頁頭按鈕區：view 模式顯示「編輯」，edit 模式顯示「儲存（disabled 時 loading）」+「取消」

## 5. 樣式

- [x] 01 區塊 card 樣式：`detail-section` + `section-title`
- [x] 02 detail-grid 2 欄（桌面）/ 1 欄（手機 < 640px）
- [x] 03 Timeline CSS：左側豎線 + 圓點（::before）、狀態 badge、時間文字
- [x] 04 聯絡人列表樣式：每筆 row flex，primary badge 綠色
