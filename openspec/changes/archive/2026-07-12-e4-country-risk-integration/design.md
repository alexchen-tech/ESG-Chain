## Context

目前 E4（地緣風險）維度完全依賴供應商 SAQ 自填的 georisk.* 系列題目，缺乏客觀資料佐證。`country_risk_ratings` 表已存在（labor_risk / env_risk / geo_risk 三欄），但尚未與六維計分引擎整合。`assessment_series` 表已有框架加權設定能力（series-scoring-config 變更），本次在其上疊加 E4 的客觀比例 α。

計分流程：esgchain-api 派送 Celery 任務 → esgchain-ai `six_dim_scoring_tasks.py` 執行六維計分 → 寫回 `saq_score_snapshots`。

## Goals / Non-Goals

**Goals:**

- 為 E4 維度引入客觀國家風險資料，降低自填偏差
- 在供應商尚未完成 SAQ 時，仍能透過純客觀路徑提供 E4 初始評分
- admin 可在計分設定頁調整 α 值，控制客觀佔比
- `country_risk_ratings` 補充 `sub_scores` JSON，對應地緣風險四個 L2 支柱（political, environmental, social, regulatory）

**Non-Goals:**

- E6 客觀計分（PCF/合規資料驅動）留待後續變更
- 修改 E1–E3、E5、S1–S3、G1–G3 維度計分方式
- 重新設計 `country_risk_ratings` 現有 labor_risk / env_risk / geo_risk 欄位（維持相容）

## Decisions

### D1：`country_defense_score` 由 API 側計算後傳入 AI

**選項 A**：API 傳入原始 `risk_rating`，AI 側自行做 `100 - risk_rating`。  
**選項 B**：API 計算 `country_defense_score = 100 - country_risk_rating`，直接傳給 AI。

**決定**：選 B。AI 任務保持無狀態、不依賴 MySQL，所有資料由派送端準備好傳入。一致於現有 `ScoringJobDispatchService` 的組裝模式。

### D2：`country_risk_rating` 以 `sub_scores` 平均值作為主分數

`country_risk_ratings` 新增 `sub_scores: { political, environmental, social, regulatory }`（各 1–5）。計算 `country_risk_rating = mean(sub_scores)`，`country_defense_score = 100 - (rating - 1) / 4 × 100`（線性對映至 0–100）。

API 派送時僅傳 `country_defense_score`（scalar）與 `sub_scores`（dict），AI 可選擇性拆用四個支柱對應 E4 的 L2 pillars。

### D3：`e4_objective_ratio`（α）放在 `assessment_series` 而非 `scoring_model`

α 是作業設定（per-series 業務決策），不是模型超參數，應歸屬 series 層而非 AI 側模型。與 series-scoring-config 變更一致，由 `AssessmentSeriesService::getScoringConfig()` 回傳。

**預設值 0.40**：以欄位 DEFAULT 設定，null 表示「繼承框架預設（0.40）」。

### D4：三狀態機實作位置在 AI 側 `six_dim_scoring_tasks.py`

API 負責組裝輸入（country_defense_score、e4_objective_ratio、saq_completed flag），AI 依三條件 branch 計算。API 不做 E4 計分邏輯，符合「計算邏輯不可寫在 esgchain-api」規則。

### D5：`saq_completed` 定義

`saqs.status = 'submitted'` 或 `saqs.score IS NOT NULL`，任一為真即視為 SAQ 已完成，可取用 `saq_geo_risk_score`。

## Risks / Trade-offs

- **`sub_scores` 資料缺漏** → 若現有 seed 資料未填 sub_scores，純客觀路徑無法使用。遷移計畫需提供預設 sub_scores 填值腳本（以現有 geo_risk 值平均填入四個支柱）。
- **α 調整影響歷史分數** → α 改動不觸發歷史 snapshot 重算；只有下次手動或排程重算時才生效。文件說明此行為。
- **`country_risk_rating` 計算精度** → 1–5 整數 → 0–100 映射存在離散性（五個可能值）。接受此粗粒度；後續可改用 sub_scores 細粒度計算。
- **依賴 six-dim-scoring 變更** → `e4_objective_ratio` 欄位若未先合併，派送任務會找不到欄位。需在 tasks.md 標注前置條件並提供 nullable 容錯。

## Migration Plan

1. 執行 `country_risk_ratings` migration 新增 `sub_scores` JSON nullable 欄位
2. 執行 seed/填值腳本：以既有 `geo_risk` 值填入 `sub_scores.political = sub_scores.environmental = sub_scores.social = sub_scores.regulatory = geo_risk`
3. 執行 `assessment_series` migration 新增 `e4_objective_ratio` DECIMAL(3,2) DEFAULT 0.40 nullable
4. 部署 esgchain-ai 新版 `six_dim_scoring_tasks.py`（向下相容：`country_defense_score` 缺席時走舊路徑）
5. 部署 esgchain-api 新版 `ScoringJobDispatchService`
6. 部署 esgchain-web 新版計分設定 Tab 與 country-risk 設定頁

Rollback：ai 任務 branch 設計確保缺少 `country_defense_score` 時走舊 SAQ-only 路徑，回滾 API/Web 不影響已派送任務。

## Open Questions

- sub_scores 四個支柱（political, environmental, social, regulatory）是否直接 1:1 對應 E4 的四個 L2 pillars？確認後可讓 AI 直接以 sub_scores 替代 SAQ georisk.* 各支柱，而非僅用平均 defense_score。
- `e4_objective_ratio` 是否需要 per-framework 預設值（`framework_default_weights` 表），或 hardcode 0.40 即可？
