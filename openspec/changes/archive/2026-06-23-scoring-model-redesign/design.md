## Context

ESG-Chain 目前有四個評核框架（ESG / ISO20400 / ISO26000 / Geo-Risk），各框架的 pillar 清單已定義在 `SLUG_PREFIX_TO_PILLAR`（AI service）與 `FRAMEWORK_PILLARS`（前端 SeriesDetailView）。供應商主檔有 `sasb_industry_code`，題庫的 ESG 題目有 `question_tags.slug`（30 個 L3 topic，格式 `esg.<pillar>.<topic>`）。

Series 計分設定（上一個 change）讓品牌客戶可在 Series 層自訂 pillar 加權，但初始值目前是等權平均，缺乏業務語意的起點。

## Goals / Non-Goals

**Goals:**
- 管理所有框架的 pillar 預設加權（admin 設定，品牌客戶繼承）
- 管理 SASB 產業 → ESG L3 topic 必調清單
- Series 計分設定的初始值從框架預設讀取
- 供應商填答時，SASB 必調題目顯示標記（UI）

**Non-Goals:**
- 不修改 AI service 的計分引擎核心邏輯
- 不實作 SASB 必調題目的提交強制驗證（本次只標記，驗證留後續）
- 不刪除 ScoringModel PostgreSQL 表（退場但不移除，避免資料遺失）
- 不處理非 ESG 框架的 SASB 必調（SASB 標準僅對應 ESG 框架）

## Decisions

**D1：`framework_default_weights` 存於 MySQL（esgchain-api）**
- 框架預設加權是業務設定（非計分引擎邏輯），屬於 API 層管理
- Schema：`id, scoring_framework VARCHAR(30), pillar_slug VARCHAR(60), weight DECIMAL(5,4), sort_order INT`
- Unique constraint：`(scoring_framework, pillar_slug)`
- Seed：四個框架各自的預設比例（由 admin 決定，非等權平均）

**D2：`sasb_required_topics` 存於 MySQL（esgchain-api）**
- Schema：`id, sasb_industry_code VARCHAR(20), tag_slug VARCHAR(80), rationale TEXT NULL`
- `tag_slug` FK 不強制（tag 可能更新），但有唯一索引 `(sasb_industry_code, tag_slug)`
- Seed：主要 SASB 代碼的必調清單（EM-IS, TC-ES, EM-MM, TR-MT 等）

**D3：`getScoringConfig()` 改從 `framework_default_weights` 讀取預設值**
- 當 `series.pillar_weights IS NULL` 時，從 `framework_default_weights` 按 `scoring_framework` 查詢
- 回傳格式不變：`{ pillar_weights: {slug: ratio}, grade_thresholds, available_pillars }`
- `pillar_weights` 由框架預設填入（取代等權平均），`grade_thresholds` 仍 null（由 series 設定）

**D4：SASB 必調標記在 `project_questions` 層注入**
- 當 SAQ 發送給供應商時（`SaqProjectSendService`），對每道 `project_question` 檢查其 `tag_slugs` 是否在 `sasb_required_topics[supplier.sasb_industry_code]`
- 若有交集，設 `project_questions.is_sasb_required = true`（需加欄位）
- Portal 填答頁讀取此欄位，顯示「SASB 必調」標籤

**D5：ScoringModelView.vue 完全重寫，分兩個區塊**
- 區塊 1：框架預設加權（Tab 或 Accordion，一個框架一組 pillar 加權，可編輯）
- 區塊 2：SASB 必調設定（Table，每列一個 SASB code，展開顯示 topic 清單，可新增/刪除）
- 副標題改為「管理各框架預設 Pillar 加權與 SASB 必調題目設定」

**D6：ScoringModel（PostgreSQL）退場策略**
- `_get_scoring_model_sync()` 保留但只用於等級閾值 fallback（當 series 無 grade_thresholds 時）
- pillar 加權不再從 ScoringModel 讀取（改為全等權 fallback，因框架預設加權已在 MySQL 管理）
- UI 頁面不再顯示舊 ScoringModel CRUD

## Risks / Trade-offs

- **SASB code 清單未標準化**：系統內 `sasbIndustry` 資料的代碼需與 `sasb_required_topics` 的 code 一致，seed 前需確認現有代碼格式（如 `EM-IS` vs `EM_IS`）。
- **必調標記時機**：D4 選擇在「發送」時注入，若題目後來被修改（如 tag 變更），已發送的 SAQ 不會自動更新。這是刻意設計（快照語意），與 `project_questions` 現有行為一致。
- **框架預設加權 seed 的業務判斷**：ESG 框架各 pillar 的預設比例需由業務決定（非技術問題），本次 seed 使用保守值（近似等權但給 E/S/G 合理比重），管理員可在 UI 調整。
