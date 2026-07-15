## Context

目前 ESG-Chain 的 SAQ 答案以問卷為單位存放（`saq_responses`），無法跨問卷串聯同一供應商的相同 KPI。75 道 bank questions 已掛有跨框架 TAG，表示這些題的答案語意上是供應商的「能力事實」，而非某份問卷的一次性答覆。建立 Supplier Disclosure Profile 就是把這個隱性知識顯性化。

現有 SAQ 狀態機與計分流程不動，disclosure 是附加層：SAQ scored → disclosure sync → 下次問卷預填。

## Goals / Non-Goals

**Goals:**
- 以 `(supplier_id, field_slug, period_year)` 唯一鍵保存供應商 KPI 歷年值
- 問卷送出後自動同步 numeric / boolean / single_choice 回答到 disclosure
- 問卷發送時依 disclosure 歷史值預填（boolean/選擇自動帶入；numeric 顯示參考值需重填）
- 供應商詳情頁顯示 KPI 歷年趨勢（至少 3 年）

**Non-Goals:**
- 不取代現有問卷流程（SAQ 仍然存在，disclosure 是衍生層）
- 不支援開放式文字題（`question_type = long_text`）的 disclosure 化
- 不實作跨供應商 KPI 比較排名（此為後續功能）
- 不對接 ERP（disclosure 值來源為 SAQ，不直接從 ERP 同步）

## Decisions

**D1：disclosure_field 以 slug 為主鍵而非 bank_question_id**

理由：一個 KPI 可能對應多道措辭不同的問題（Scope 1 在不同範本的問法稍異），用語意 slug（`ghg.scope1_mt_co2e`）比問題 ID 更穩定，也與現有 question_tag slug 命名系統一致。

bank question → disclosure field 映射存在 `saq_questions.disclosure_field_slug` 欄位（nullable）。

**D2：(supplier_id, field_slug, period_year) 為 unique key，append 模式**

`period_year` 允許同一欄位保留多年歷史值。同年若多次填報則 upsert（最新值覆蓋前次）。不使用 append-only + superseded_by，避免查詢複雜度過高。

`source_saq_id` 記錄最後一次寫入來自哪份問卷，可回溯。

**D3：sync 在 API 層執行，不走 esgchain-ai**

SAQ scored callback 打到 `esgchain-api` 的 `SAQService::updateScore()` 後，由 API 直接呼叫 `DisclosureSyncService::syncFromSaq()`。不引入新的 Celery task，保持同步（若 sync 失敗不影響主流程，只 log）。

**D4：numeric 題預填為「參考值顯示」，不自動填入欄位**

每年的 Scope 1 數字必然不同，自動填入舊值可能讓供應商誤送舊數據。UI 顯示「上次填報：1,234 tCO₂e（2024）」但 input 欄位保持空白，強制重填。

boolean / single_choice 自動帶入，因為這類答案短期內變化機率低（政策、認證、系統是否建立等）。

**D5：Phase 1 不建 disclosure_fields 管理後台**

disclosure_fields 初始以 seed 資料建立（約 15～20 個核心 KPI），由開發者維護，不對外開放 CRUD。Phase 2 再視需要開放 admin 管理介面。

## Risks / Trade-offs

**[Risk] 供應商填舊值確認錯誤導致資料品質下降**
→ Mitigation：numeric 題強制重填（D4）；boolean 預填後在問卷 UI 顯示「已從歷史記錄帶入，請確認」標示

**[Risk] bank question 與 disclosure_field 映射維護成本**
→ Mitigation：只映射有明確量化語意的題（約 15 道），開放式文字題不映射；映射在 seed 中集中管理

**[Risk] 同一供應商同一年填了兩份問卷，Scope 1 答案不同**
→ Mitigation：upsert 取最後一次（以問卷送出時間排序）；稽核員在 disclosure UI 可看到 source_saq_id 追溯

## Migration Plan

1. 執行 migration：建立 `supplier_disclosure_fields`、`supplier_disclosures` 表，`saq_questions` 加 `disclosure_field_slug` 欄位
2. Seed disclosure_fields（約 15 筆核心 KPI）
3. 為現有 bank questions 填入 `disclosure_field_slug` mapping（seed/migration 一併執行）
4. 部署 `DisclosureSyncService`；`SAQService::updateScore()` 呼叫 sync
5. 對現有已 scored 的 27 份 SAQ 執行一次性補跑 sync（backfill script）
6. 前端新增 Disclosure Profile tab
