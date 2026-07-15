## Context

目前 `assessment_series` 以自由文字 `domain`（ESG / ISO20400 / ISO26000 / Geo-Risk）標記系列類型，但 Project 層可自由選擇任何範本，導致：
1. **跨期可比性破壞**：同一系列的 2024 Project 用 ISO20400 範本，2025 Project 改用 ESG 範本，結果無法並排比較。
2. **框架 TAG 不一致**：ESG 範本目前可加入 `iso20400.*` TAG 的題目，評分引擎的 pillar 歸屬混亂。
3. **TAG 覆蓋缺口**：ISO26000 範本的 bank questions 只有 18/30 有 `iso26k.*` TAG，Geo-Risk 範本更低（5–10/30）。

## Goals / Non-Goals

**Goals:**
- 系列層級（series）綁定範本，Project 繼承而非自選
- DB TRIGGER 強制範本題必須有與 `scoring_framework` 一致的 TAG
- `framework_pillar` 欄位快照，讓 pillar 計算不依賴動態 TAG 查詢
- `is_comparable` 旗標追蹤升版後的可比性
- 補齊 ISO26000 / Geo-Risk bank questions 的 TAG

**Non-Goals:**
- 支援單一 Project 跨多個框架（不在此次範圍）
- 範本題目的 TAG 多選（仍允許，只要其中一個符合）
- 修改評分引擎計算邏輯（只影響資料取得路徑）

## Decisions

### 1. 系列綁定 template_id，Project 僅快照版本

`assessment_series.template_id` → `saq_templates.id`（NOT NULL）。Project 建立時記錄 `template_version`（如 `v1.2`）。升版策略：舊 Project 保留舊版本號 + `is_comparable = false`，新 Project 用新版本；跨版本比較時前端顯示警告。

替代方案考量：在 Project 層仍允許選範本 → 否決，核心問題就是這個自由度造成的混亂。

### 2. DB TRIGGER 取代應用層驗證

MySQL 8.4 不支援跨表 CHECK CONSTRAINT，改用 BEFORE INSERT TRIGGER on `saq_questions`：
```sql
-- 偽代碼
IF NEW.template_id IS NOT NULL AND NEW.is_bank_question = 0 THEN
    -- 查 template.scoring_framework
    -- 查 question_tag_assignments 是否有 l1_domain 匹配
    -- 若無 → SIGNAL SQLSTATE '45000'
END IF;
```
應用層（`SaqQuestionService`）額外做相同檢查並回傳友善錯誤訊息，TRIGGER 作為最後防線。

### 3. framework_pillar 建立時快照

`saq_questions.framework_pillar VARCHAR(100) NULL`，新增範本題時從 TAG l2_pillar 取第一個符合 l1_domain 的值寫入，之後不再更新。評分引擎讀 `framework_pillar`，不需即時 JOIN `question_tags`。

### 4. 資料完整清除後 Seed 重建

Dev 環境無業務資料，直接清除比逐筆修正更安全且可預期。Seed 重建順序：Templates → Series（帶 template_id）→ Projects（帶 template_version）→ SAQs → Responses（選擇性，保留乾淨狀態即可）。

## Risks / Trade-offs

- **TRIGGER 維護成本高**：TRIGGER 不在版本控制的 Migration 之外可見 → 必須寫成 Migration（`DB::unprepared()`），並在 README 標註。
- **TAG 補齊工程量大**：ISO26000 缺 12 個 tag assignments，Geo-Risk ISO28000 缺 25 個，Geo-Risk GPR 缺 20 個 → 逐一對照 bank questions 的文字手動配對，需設計 review。
- **is_comparable 旗標只影響前端**：後端不阻擋不可比 SAQ 計算 → 貿然對比的風險由 UI 警告承擔。
- **升版時舊系列 Project 的 template_version 需回填**：清除資料後重建不受影響，但未來 Prod 升版需要 Migration 回填。

## Migration Plan

1. Migration A：`assessment_series` 加 `template_id NOT NULL`（先加 NULL 再清資料再加 NOT NULL constraint）；廢棄 `domain` 欄位（保留但標記 deprecated）。
2. Migration B：`saq_projects` 加 `is_comparable`、`template_version`。
3. Migration C：`saq_questions` 加 `framework_pillar`。
4. Migration D：建立 TRIGGER `trg_saq_questions_framework_check`。
5. Migration E：補齊 TAG assignments（ISO26000 + Geo-Risk）。
6. Artisan Command `dev:reset-series-data`：清除 dev 環境資料並重建 Seed。
7. 驗收：透過 Tinker 測試 TRIGGER（插入不符合框架的題目應拋例外）。

## Open Questions

- ISO26000 的 l2_pillar 對應值：`iso26k.core_subject.*` 還是用 ISO26000 的七大 Core Subjects（org_governance / human_rights / labour / environment / fair_operating / consumers / community_involvement）？→ 建議用英文縮寫小寫，如 `human_rights`。
- Geo-Risk 是否拆成 `geo_risk.iso28000` 和 `geo_risk.gpr` 兩個獨立 l1_domain？→ 建議維持 `geo_risk` 單一 l1_domain，以 l2_pillar 區分（`iso28000.*` / `gpr.*`）。
