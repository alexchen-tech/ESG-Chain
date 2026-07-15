## Why

供應商在多份問卷中重複填寫相同的量化 KPI（Scope 1、LTIFR、用電量等），導致填報疲勞與資料不一致；採購商也無法比較同一供應商跨年度的 KPI 趨勢。根本原因是系統缺乏「供應商 Profile」層——答案屬於問卷，而非屬於供應商。

## What Changes

- 新增 `supplier_disclosure_fields` 主檔：定義可量化的 disclosure 欄位（slug、資料型態、單位、期間類型）
- 新增 `supplier_disclosures` 填報表：以 `(supplier_id, field_slug, period_year)` 為 unique key，跨問卷保存供應商的歷史 KPI
- 建立 bank question → disclosure field 映射：在 `saq_questions` 或獨立 mapping 表中記錄哪些題對應到哪個 disclosure field
- 問卷送出後自動同步：SAQ scored 事件觸發 disclosure sync，從 saq_responses 提取 KPI 回寫至 `supplier_disclosures`
- 問卷發送時預填：對 boolean / single_choice 欄位自動帶入最近一期 disclosure 值；numeric 欄位顯示上次值作為參考，但需重填
- 新增 Supplier Disclosure Profile UI：在供應商詳情頁顯示各 KPI 的歷年趨勢

## Capabilities

### New Capabilities

- `supplier-disclosure-fields`: disclosure 欄位本體論（slug 定義、data_type、unit、period_type）的 CRUD 管理
- `supplier-disclosure-profile`: 供應商 KPI 填報值的儲存、歷年趨勢查詢、跨問卷同步機制
- `saq-disclosure-prefill`: 問卷發送時依 disclosure 歷史值自動預填回答

### Modified Capabilities

- `saq-scoring-engine`: scored 事件後觸發 disclosure sync job
- `question-tag-library`: bank question 增加 `disclosure_field_slug` 映射欄位

## Impact

- **esgchain-api（MySQL）**：新增 `supplier_disclosure_fields`、`supplier_disclosures` 兩張表；`saq_questions` 新增 `disclosure_field_slug` 欄位
- **esgchain-ai**：scored callback 後通知 API 執行 sync（或由 API 在 `updateScore()` 後直接觸發）
- **esgchain-web**：供應商詳情頁新增 Disclosure Profile tab；問卷填報頁新增預填提示
- **現有 SAQ 流程不破壞**：sync 是單向附加（SAQ → Disclosure），不影響現有計分與狀態機
