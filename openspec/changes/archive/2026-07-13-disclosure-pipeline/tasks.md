## 1. 路線 A — 題目映射資料

- [x] 1.1 新增 `GET /api/v1/settings/disclosure-fields` 端點，回傳所有 `supplier_disclosure_fields`（slug、label、data_type、unit）
- [x] 1.2 對現有 20 道題目逐一確認 question_type 與 disclosure field data_type 相容性
- [x] 1.3 執行 seeder / raw update，為相容題目批次寫入 `disclosure_field_slug`（15 個映射，不相容者跳過）
- [x] 1.4 驗證：呼叫 `/api/v1/settings/question-bank`，確認回傳的題目中有 `disclosure_field_slug` 非 null

## 2. 路線 A — 補跑歷史同步

- [x] 2.1 確認 `DisclosureBackfill` artisan command 運作正常（`php artisan disclosure:backfill`）
- [x] 2.2 執行 backfill，對所有已評分 SAQ 補跑 syncFromSaq
- [x] 2.3 驗證：查詢 `supplier_disclosures` 確認有 `source: saq_sync` 記錄，`source_saq_id` 有值

## 3. 路線 A — 題目庫編輯 UI

- [x] 3.1 題目庫編輯 Modal 新增「Disclosure 欄位對應」下拉選單，選項從 `/api/v1/settings/disclosure-fields` 載入
- [x] 3.2 下拉選項按 prefix 分組顯示（`<optgroup label="ghg">…</optgroup>`），支援「不對應」選項（value = null）
- [x] 3.3 `QuestionBankController::update()` 開放 `disclosure_field_slug` 欄位更新（加入 fillable / validation）
- [x] 3.4 驗證：管理員在 UI 修改一道題目的映射，儲存後重新整理確認持久化

## 4. 路線 B — Portal 後端 API

- [x] 4.1 新增 `PortalDisclosureController`（`app/Http/Controllers/Api/Portal/`）
- [x] 4.2 實作 `GET /api/v1/portal/disclosures`：回傳當前供應商所有 disclosures，join disclosure_fields 附加 label / data_type / unit
- [x] 4.3 實作 `POST /api/v1/portal/disclosures`：upsert，支援 numeric / boolean value，檢查 field_slug 有效性
- [x] 4.4 POST 回應附加 `overwritten_saq_sync: true/false` 旗標（檢查覆蓋前的 source 值）
- [x] 4.5 在 `routes/api.php` Portal 區段新增兩條路由，限定 `supplier / sup_esg` 角色
- [x] 4.6 驗證：用 supplier1@tpsteel.com.tw 登入，POST 一筆 manual disclosure，GET 確認回傳

## 5. 路線 B — Portal 前端頁面

- [x] 5.1 新增 `PortalDisclosureView.vue`（`views/portal/`），Options API，路由 `/supplier/portal/disclosures`
- [x] 5.2 實作頁面骨架：依 field prefix 分組為 section，展示所有 KPI 欄位
- [x] 5.3 每個欄位列：label + unit + 年度下拉（2020–當年）+ 值輸入（boolean 用 checkbox，numeric 用 number input）
- [x] 5.4 頁面載入時呼叫 `GET /portal/disclosures`，將已有數值預填至對應欄位
- [x] 5.5 已有數值的欄位顯示來源 badge（「來自問卷」/ 「手動填報」），saq_sync 來源顯示覆蓋警告
- [x] 5.6 每欄位獨立儲存按鈕，呼叫 POST，成功後 inline 顯示「已儲存 ✓」，loading 期間 disabled
- [x] 5.7 若 POST 回傳 `overwritten_saq_sync: true`，顯示橘色「已覆蓋問卷數值」提示
- [x] 5.8 在 `api/modules/` 新增 portal disclosure API helper（`portalDisclosureApi`）
- [x] 5.9 Portal 側邊欄新增「永續 KPI 填報」選單項目，導向 `/supplier/portal/disclosures`

## 6. 整合驗證

- [x] 6.1 端對端測試路線 A：提交一份包含已映射題目的 SAQ → 評分 → 確認 supplier_disclosures 自動更新
- [x] 6.2 端對端測試路線 B：供應商 Portal 手動填報 → 確認採購商側供應商詳情頁「揭露資料時間序列」顯示新數值
- [x] 6.3 驗證 saq_sync 覆蓋情境：先有 saq_sync 記錄 → Portal 手動覆蓋 → 確認 source 變更為 manual，UI 顯示警告
