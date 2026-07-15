## Context

目前 12 個評核系列全部使用同一份範本（S³P 永續採購評核 ISO 20400，30 題），但系列的 `domain` 欄位各異（ESG / ISO20400 / Product-Compliance）。esgchain-ai 的計分邏輯以 `project_domain` 為前綴過濾 TAG slug，造成：

- ESG domain：30/30 題有 ESG TAG → 計分覆蓋完整 → avg 57–62
- ISO20400 domain：12/30 題有 ISO20400 TAG → 18 題被排除 → avg 14.6
- 同一供應商跨框架分數差距最高達 70 點，完全不可比

根本問題：**計分框架屬於範本的屬性，不應由系列 domain 決定**。範本宣告採用 ISO20400 框架，就應保證該框架 TAG 在每道題上的覆蓋率 100%。`domain` 應回歸純 UI 分類用途。

## Goals / Non-Goals

**Goals:**
- 計分框架由 `saq_templates.scoring_framework` 宣告，系列 domain 不再影響計分邏輯
- 同一範本下的所有系列，分數基礎完全一致，可直接跨專案比較
- 計分維度從固定 E/S/G 改為框架原生 L2 pillar（ISO20400 有 3 個 pillar，ESG 有 6 個，Geo-Risk 有 2 個）
- 補齊 ISO20400 範本 18 道缺口題的框架 TAG（立即修復現有分數失真問題）

**Non-Goals:**
- 不支援單一問卷同時套用多個框架計分
- 不修改 `saq_projects.domain` 的資料庫欄位名稱（避免大範圍 API 破壞性更新）
- 不刪除 `score_e / score_s / score_g` 欄位（向後相容，ESG 框架繼續填入）
- 不處理 ISO26000 / Geo-Risk / Product-Compliance 範本的 TAG 補齊（當前無系列使用，待日後建立範本時同步處理）

## Decisions

### D1：scoring_framework 設在 template 層，而非 project 層

**決策**：`saq_templates` 新增 `scoring_framework VARCHAR(50)`，枚舉值：`ESG` / `ISO20400` / `ISO26000` / `Geo-Risk` / `Product-Compliance` / NULL（通用）。

**理由**：範本的設計意圖本就決定了題目的框架屬性，同一份範本在不同系列下應呈現可比較的分數。讓 project domain 驅動計分，等於允許同一份問卷「因觀測角度不同得出不同的分數」，違反測量一致性原則。

**捨棄的替代方案**：在 project 層新增 `scoring_framework` 欄位——此方案保留了「同範本多框架計分」的彈性，但會讓管理員每次建立系列都要設定，且容易造成計分不一致。

---

### D2：計分輸出新增 category_scores JSON，保留舊欄位

**決策**：`saqs` 新增 `category_scores JSON`，結構為 `{"採購政策": 72.3, "績效評估": 85.1, "風險管理": 68.0}`。舊的 `score_e / score_s / score_g` 在 ESG 框架繼續填入（對映關係：score_e=環境管理+供應鏈環境加權、score_s=勞工人權+職場安全+社區消費者加權、score_g=公司治理），非 ESG 框架填 null。

**理由**：JSON 欄位支援任意 pillar 數量（ISO26000 有 7 個），且不需要 schema migration 就能應對未來新框架。保留舊欄位確保現有儀表板、報告模組不需立即改動。

---

### D3：計分 payload 改帶 scoring_framework，不再帶 project_domain

**決策**：Laravel 組裝計分 payload 時，改讀 `project.template.scoring_framework`，payload 欄位改名為 `scoring_framework`（舊的 `project_domain` 欄位廢棄，AI 端不再使用）。

**理由**：計分語意應由範本框架決定，傳遞 project_domain 是歷史設計錯誤的延伸。

---

### D4：ISO20400 範本 18 道缺口題的 TAG 對映策略

依語意分類補充 ISO20400 L2 pillar TAG：

| 題目類型 | 補充 TAG (L2 pillar) |
|---|---|
| Scope 1/能源/用水/廢棄物/碳減量/再生能源 | `iso20400.performance.env_performance` |
| 女性比例/職災 LTIFR | `iso20400.performance.social_performance` |
| 反腐/公司治理政策 | `iso20400.policy.governance` |
| 供應商大會/認證 | `iso20400.policy.sustainability_criteria` |
| CAP 矯正行動/LCC | `iso20400.risk.corrective_action` |
| EUDR/化學/溯源（Product-Compliance 雙標） | `iso20400.risk.supply_chain_risk` |

這些題目同時保留現有 ESG TAG，雙重標籤設計不變。

---

### D5：pillar 等權計算，assessment_series_weights 暫不啟用

**決策**：各框架的 L2 pillar 預設等權平均（例如 ISO20400 三個 pillar 各佔 1/3）。`assessment_series_weights` 表繼續保留但維持空置，作為未來客製化 pillar 權重的擴充點。

**理由**：目前無客戶要求自訂 pillar 權重，優先保持計算透明度。

## Risks / Trade-offs

**[風險] 現有 ISO20400 系列分數會大幅提升**（從 avg 14.6 → 預計 40–60）
→ 補 TAG 後重新計分是預期行為，應視為「修正」而非「異常」；建議在變更說明中向客戶說明分數調整原因

**[風險] `category_scores` 欄位在 NULL scoring_framework 範本下為空 JSON `{}`**
→ 前端需處理空 category_scores 的 fallback，顯示舊的 E/S/G 欄位或「無維度細項」

**[風險] AI scoring API 的 backward compatibility**
→ 舊版 payload（帶 `project_domain`）在過渡期仍需能運作；用 `scoring_framework ?? project_domain` 的 fallback 兼容

## Migration Plan

1. **資料層先行**：執行 `question_tag_assignments` INSERT（18 筆 ISO20400 TAG），同時 `saq_templates` 加 `scoring_framework` 欄位並設值
2. **AI 層更新**：`scoring_service.py` 支援新 payload 欄位 `scoring_framework`，輸出 `category_scores`
3. **API 層更新**：`saqs` migration 加 `category_scores JSON`，計分 payload 組裝改讀 template.scoring_framework
4. **全量重算**：執行 rescore_all.py 重算所有有回覆的 SAQ（預計影響 41 份）
5. **前端更新**：ReviewDetailView、分數顯示元件改讀 `category_scores`（ESG 框架 fallback 到 score_e/s/g）
6. **驗收**：確認 ISO20400 系列分數合理（預計 40–60 範圍），跨系列比較圖表正常顯示
