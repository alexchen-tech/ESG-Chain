## 1. 資料層：Disclosure 欄位主檔與填報表

- [x] 1.1 Migration：建立 `supplier_disclosure_fields` 表（slug PK、label、data_type、unit、period_type、description）
- [x] 1.2 Migration：建立 `supplier_disclosures` 表（unique key: supplier_id + field_slug + period_year；numeric/boolean/text_value；source enum；source_saq_id；verified_at）
- [x] 1.3 Migration：`saq_questions` 新增 `disclosure_field_slug VARCHAR(80) NULL` 欄位
- [x] 1.4 Seed：寫入 15 筆核心 `supplier_disclosure_fields`（SupplierDisclosureFieldSeeder）
- [x] 1.5 Seed/Migration：對現有 bank questions 填入 `disclosure_field_slug` mapping（8 筆，migration 2026_06_22_000006）

## 2. esgchain-api：DisclosureSyncService

- [x] 2.1 建立 `SupplierDisclosureField` Model（fillable、casts）
- [x] 2.2 建立 `SupplierDisclosure` Model（fillable、casts、belongsTo suppliers/saqs）
- [x] 2.3 建立 `DisclosureSyncService::syncFromSaq(SAQ $saq): void`：查詢 responses + disclosure_field_slug，upsert supplier_disclosures，例外 catch + log
- [x] 2.4 `SAQService::updateScore()` 在寫入分數後呼叫 `DisclosureSyncService::syncFromSaq()`
- [x] 2.5 `SAQ` Model 新增 `disclosures()` hasMany 關聯（透過 source_saq_id）

## 3. esgchain-api：Disclosure Profile API

- [x] 3.1 新增路由 `GET /api/v1/suppliers/{supplier}/disclosure-profile`
- [x] 3.2 建立 `SupplierDisclosureController::profile()`：回傳依 field_slug 分組的歷年填報值，含 period_year、source、source_saq_id
- [x] 3.3 權限：`supplier` / `sup_esg` 角色只能存取自己的 disclosure profile；其他角色無限制

## 4. esgchain-api：Backfill 既有資料

- [x] 4.1 建立 `DisclosureBackfill` Artisan command + `/tmp/backfill_disclosures.py`；執行後 27/27 ok，36 筆 disclosure 記錄已建立

## 5. esgchain-api：SAQ 發送時預填資料 API

- [x] 5.1 `GET /api/v1/saqs/{saq}/prefill-hints`：回傳該 SAQ 各題的 disclosure 歷史值
- [x] 5.2 只回傳 `disclosure_field_slug IS NOT NULL` 且供應商有歷史記錄的題

## 6. esgchain-web：Disclosure Profile Tab

- [x] 6.1 在供應商詳情頁新增「Disclosure Profile」tab
- [x] 6.2 `disclosureRaw` data + `disclosureEntries` computed + `loadDisclosureProfile()` method
- [x] 6.3 Numeric 顯示數值 + 年度變化 %；boolean 顯示 ✓ / ✗；single_choice 顯示文字
- [x] 6.4 `source_saq_id` 顯示為 router-link 跳轉至問卷詳情頁
- [x] 6.5 無 disclosure 資料時顯示「尚無填報資料」空白狀態

## 7. esgchain-web：問卷填報預填提示

- [x] 7.1 `SupplierSurveyView` mounted 後呼叫 `loadPrefillHints()`
- [x] 7.2 Boolean / single_choice 題：自動帶入 + 顯示「已從歷史記錄帶入，請確認（{year}）」提示
- [x] 7.3 Numeric 題：input 保持空白 + 顯示「上次填報：{value} {unit}（{year}）」參考文字

## 8. 驗收

- [x] 8.1 backfill 27/27 ok，36 筆 disclosure 記錄
- [x] 8.2 `GET /api/v1/suppliers/{id}/disclosure-profile` 台灣紡紗回傳 2 個 field × 2 年度共 4 筆
- [x] 8.3 prefill-hints API 驗證通過（2 hints），backfill 以修正後 query 重跑 27/27 ok
- [x] 8.4 SupplierSurveyView 載入 prefill-hints，boolean 自動帶入 + 提示，numeric 顯示參考值
