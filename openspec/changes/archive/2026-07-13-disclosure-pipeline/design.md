## Context

`supplier_disclosures` 設計為三來源匯聚點（`saq_sync` / `manual` / `erp_sync`），unique key 為 `(supplier_id, field_slug, period_year)`。後端同步服務 `DisclosureSyncService` 已實作，觸發點也已接入 `SAQService::updateScore()`，但因題目庫中 `disclosure_field_slug` 全為 null，管道空轉。Portal 目前有碳排填報（`portal/material-emissions`）及 PCF 填報路由，但無 KPI 揭露填報。

**現有題目庫（20 題）與 disclosure fields（15 個 slug）的重疊**：
- cert.iso14001 / cert.iso45001 / cert.iso9001 ← SAQ 認證類題目有直接對應
- ghg.scope1_mt_co2e / ghg.scope2_mt_co2e ← SAQ 溫室氣體排放題目有對應
- energy.total_kwh / energy.renewable_pct ← SAQ 能源題目有對應
- governance.has_anti_corruption_policy / governance.has_esg_report ← 有對應
- labor.child_labor_banned、diversity.female_pct、safety.ltifr、water.total_m3、waste.recycling_pct、supply_chain.supplier_audit_conducted ← 需確認題目是否存在

## Goals / Non-Goals

**Goals:**
- 路線 A：題目庫補設 `disclosure_field_slug` 映射，讓 SAQ 評分後自動填寫 `supplier_disclosures`
- 路線 A：題目庫編輯 UI 支援 `disclosure_field_slug` 選取
- 路線 B：Portal 新增 KPI 年度手動填報頁面與 API
- 兩路線資料在 `supplier_disclosures` 共存，依 `source` 欄位區分來源

**Non-Goals:**
- 不實作 `erp_sync` 路線（ERP 同步 KPI 屬獨立功能）
- 不修改 disclosure-profile API 結構（已符合需求）
- 不實作 `verified_at` 驗證流程（驗證屬採購商側工作，已有欄位留白備用）
- Portal 填報不開放新增 disclosure field slug（slug 由管理員在後台定義）

## Decisions

### 決策 1：路線 A 映射方式 — 直接更新 seed/migration vs. UI 維護

選擇：**兩者並行**。先用 migration 或 seeder 一次性寫入已知的映射（cert / ghg / energy / governance 等），同時在題目庫編輯 UI 加入 `disclosure_field_slug` 下拉選單，讓管理員日後可維護新題目的映射。

理由：seed 映射讓現有資料立即生效；UI 維護讓未來題目擴充不需 migration。

替代方案：只做 seed，不加 UI → 日後每次新增映射都需要工程師介入，維護成本高。

### 決策 2：路線 B API 設計 — CRUD vs. upsert-only

選擇：**upsert-only**（`POST /api/v1/portal/disclosures`，body 帶 `field_slug + period_year + value`）。

理由：`supplier_disclosures` unique key 為 `(supplier_id, field_slug, period_year)`，重複寫入同年同欄位應覆蓋（與 saq_sync 行為一致）。不需要 PUT/DELETE，簡化 API 設計。

替代方案：完整 CRUD → 過度設計，供應商端不需要刪除 KPI 記錄。

### 決策 3：saq_sync 與 manual 衝突處理

選擇：**saq_sync 優先**。若同年同欄位已有 `source: saq_sync` 的記錄，Portal 手動填報（`source: manual`）**仍允許覆蓋**，但 UI 需顯示警告「此欄位已有來自問卷的數值，確認覆蓋？」

理由：供應商可能在問卷之外有更新的數據（如年報發布後的最終值）。完全封鎖 manual 覆蓋會讓填報流程卡住。

替代方案：saq_sync 後鎖定，禁止 manual 覆蓋 → 彈性太低，實務上供應商會有修正需求。

### 決策 4：Portal 填報 UI 分組方式

選擇：依 `field_slug` 的 prefix 分組顯示（cert / ghg / energy / labor / water / waste / governance / diversity / supply_chain），每組一個 section，每行一個 KPI + 年度輸入。

理由：與供應商詳情頁的揭露時間序列分組邏輯一致，減少認知切換。

## Risks / Trade-offs

- **映射不完整**：若現有 20 道題目中，部分題目問法與 disclosure field 定義不完全吻合（例如題目問「Scope 1 排放量（噸）」但單位不同），直接映射會寫入錯誤值。
  → 緩解：在 seeder 中逐題確認 question_type 與 data_type 匹配，不匹配的跳過，寧缺勿濫。

- **Portal 填報覆蓋 saq_sync 值**：供應商可能誤填，覆蓋正確的問卷同步值。
  → 緩解：UI 警告提示；`updated_at` 有時間戳可稽查；不刪除歷史（append-only by period_year）。

- **題目庫 disclosure_field_slug 下拉選項過多**：15 個 slug 目前數量少，但未來可能增長。
  → 緩解：下拉加搜尋過濾；slug 按 prefix 分組顯示。

## Migration Plan

1. 新增 migration 對 `saq_questions` 表無影響（`disclosure_field_slug` 欄位已存在）
2. 執行 seeder `DisclosureFieldSlugSeeder`：對現有題目批次更新 `disclosure_field_slug`
3. 執行 `DisclosureBackfill` artisan command，對所有已評分的 SAQ 補跑同步（命令已存在：`/app/app/Console/Commands/DisclosureBackfill.php`）
4. 部署 Portal 新頁面與 API（獨立路由，不影響現有路由）
5. Rollback：清空 `disclosure_field_slug`（設回 null）即可關閉路線 A；Portal 路由刪除即可關閉路線 B

## Open Questions

- 現有 20 道題目中，是否有題目問法與 disclosure field 定義不符，需要新增題目或修改欄位定義？（需逐題對照確認）
- Portal 填報的 `period_year` 由供應商自選（下拉年份）還是系統預設當年度？建議允許選擇，以支援補填歷史年度。
