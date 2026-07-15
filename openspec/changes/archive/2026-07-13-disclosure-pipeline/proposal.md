## Why

目前 `supplier_disclosures` 的時間序列資料全靠 seed 寫入（`source: 'manual'`），而不是來自真實的 SAQ 回答或供應商主動填報。後端的 `DisclosureSyncService` 已就位，但題目庫中沒有任何 `disclosure_field_slug` 映射，導致 SAQ 提交後同步管道空轉；Portal 側則完全沒有供應商手動填報 KPI 的入口。兩條路線都需要補齊，才能讓供應商詳情頁的「揭露資料時間序列」顯示真實資料。

## What Changes

- **路線 A：SAQ → Disclosure 自動同步**
  - 在題目庫的 20 道題目中，為符合 `SupplierDisclosureField` 定義的題目設定 `disclosure_field_slug`（涵蓋 cert / ghg / energy / labor / water / waste / governance / diversity 等欄位）
  - 在系統設定介面（題目庫編輯）開放 `disclosure_field_slug` 的設定欄位，讓管理員日後可維護映射
  - `DisclosureSyncService::syncFromSaq()` 已實作，觸發點（`SAQService::updateScore()`）已接好，本路線僅需補齊映射資料與管理介面

- **路線 B：Portal 手動填報**
  - 新增 `GET/POST /api/v1/portal/disclosures` 端點，讓供應商登入 Portal 後可查看與填報自身的 KPI 年度數值
  - Portal 前端新增「永續資料填報」頁面，顯示依類別分組的 KPI 表單（cert / ghg / energy…），支援逐年填報
  - `source` 記為 `manual`，不覆蓋同年已存在的 `saq_sync` 記錄（saq_sync 優先）

## Capabilities

### New Capabilities
- `saq-question-disclosure-mapping`：題目庫中 `disclosure_field_slug` 的設定介面與初始映射資料
- `portal-disclosure-manual-input`：供應商 Portal 的 KPI 手動填報頁面與 API

### Modified Capabilities
- `saq-disclosure-prefill`：現有預填邏輯不需變更，但映射補齊後才會真正有資料可預填（spec 行為不變，僅資料層補齊）

## Impact

- **esgchain-api**：
  - `saq_questions` 表需更新 `disclosure_field_slug`（seed / migration）
  - 新增 `PortalDisclosureController`（`portal/disclosures` CRUD）
  - `QuestionBankController` 需開放 `disclosure_field_slug` 欄位讀寫
- **esgchain-web**：
  - 題目庫編輯 Modal 新增 `disclosure_field_slug` 下拉選單（選項來自 `SupplierDisclosureField`）
  - Portal 新增「永續資料填報」頁面（Vue 3 Options API）
- **現有規格**：`supplier-disclosure-profile`、`saq-disclosure-prefill` spec 行為不需修改
