## Context

`SupplierDetailView.vue` 現為單頁 Options API 元件，後端 `show()` 已 eager load `group / contacts / statusHistories`，但前端完全未消費這些資料。`sasbIndustry` 尚未 eager load。後端 `update()` 驗證有 `country` 欄位名稱 bug（應為 `country_code`），且缺少 `sasb_industry_id` 驗證。

## Goals / Non-Goals

**Goals:**
- 7 區塊分區顯示全部 eager load 資料
- 整頁 Edit 模式（isEditing flag 切換 view/edit template）
- 狀態歷程 vertical timeline
- 後端 show() + update() 修正

**Non-Goals:**
- 聯絡人 CRUD（本次只顯示，不新增/刪除）
- 風險評估編輯（獨立模組）
- 問卷直接開啟（保留「查看」跳列表頁行為）

## Decisions

**D1：整頁 Edit 模式用 `isEditing` boolean + `editForm` object**
- view 模式顯示靜態值；edit 模式同一 template 用 `v-if="isEditing"` 切換 input/span
- `editForm` 在進入 edit 時從 `supplier` 深拷貝，取消時直接丟棄，不污染顯示資料
- 儲存成功後 re-fetch supplier（保持單一資料來源）

**D2：SASB 顯示策略 — 後端 eager load（不用前端 lookup）**
- `show()` 補 `sasbIndustry` relation（Supplier `belongsTo SasbIndustry`）
- 前端直接用 `supplier.sasb_industry?.code + industry`
- 比前端 lookup 更乾淨，減少前端狀態

**D3：狀態歷程用 vertical CSS timeline（不用第三方 lib）**
- `statusHistories` 陣列按 `created_at` 降序
- 純 CSS `::before` 左側豎線 + 圓點
- 顯示：from_status → to_status、reason、時間

**D4：Edit 表單中 SASB / 分組用 select**
- SASB 下拉：呼叫已存在的 `settingsApi.sasb.list()` 取得所有選項
- 分組下拉：呼叫已存在的 `settingsApi.groups.list()` 取得所有選項
- 進入 edit 模式時一次載入，避免重複請求

## Risks / Trade-offs

- **SASB 下拉資料量大**（77 筆）→ 使用 `<datalist>` 或帶搜尋的 select，本次用原生 select + optgroup by sector
- **edit 模式中離頁**（route 跳走）→ 本次不做離頁確認，保持簡單

## Migration Plan

無 DB migration。後端修改完成後需 `docker cp` + `php artisan route:cache`。
