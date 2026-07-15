## Why

供應商明細頁目前只顯示 4 個欄位（產業、onboarding 階段、網站、地址），後端已 eager load 的 group、contacts、statusHistories 資料完全未呈現；SASB 分類、供應商分組等重要管理維度缺席。採購商無法在明細頁直接編輯基本資料，且後端 `update()` 有 `country` 欄位名稱 bug。

## What Changes

- 供應商明細頁改為方案 B 分區塊佈局（7 個區塊）
- 新增整頁 Edit 模式：點「編輯」→ 所有可編輯欄位變 input → 儲存/取消
- 所有 eager load 資料完整呈現：group、contacts、statusHistories
- 新增狀態歷程 timeline（from statusHistories）
- 後端 Supplier `show()` 補 eager load `sasbIndustry`
- 修後端 `update()` 驗證 bug：`country` → `country_code`，補 `sasb_industry_id`

## Capabilities

### New Capabilities
- `supplier-detail-view`: 方案 B 分區塊明細頁（識別資訊 / 產業分類 / 管理歸屬 / 聯絡資訊 / 風險評估 / 問卷記錄 / 狀態歷程）
- `supplier-edit-mode`: 整頁 Edit 模式，可編輯名稱/代碼/國家碼/Tier/產業/SASB/分組/地址/網站

### Modified Capabilities
- `supplier-api`: 後端 `show()` 補 sasbIndustry eager load；`update()` 修正欄位名稱 bug 並補 sasb_industry_id 驗證

## Impact

- **前端**：`SupplierDetailView.vue` 全面重寫；`api/modules/suppliers.ts` 補 Supplier 型別欄位
- **後端**：`SupplierController::show()` + `update()` 修改
- **無 DB migration**：所有欄位已存在
