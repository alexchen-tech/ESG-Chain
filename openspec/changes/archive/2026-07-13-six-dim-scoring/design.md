## Context

現行 SAQ 計分引擎（`saq-scoring-engine` v3）以 E/S/G 三維分數計算總分，計分由 esgchain-ai 執行後寫回 `saqs.score_e / score_s / score_g`，Laravel 端不介入合成邏輯。

esgchain-ai 的 `six_dim_scoring` task 已實作六個子維度（E1 環境管理、E2 氣候、E3 資源、E4 供應鏈責任、E5 生物多樣性、E6 循環經濟）的評分輸出，但 Laravel 側尚未消費這些欄位，`saqs` 表也尚無對應的儲存欄位。

`assessment_series` 目前有 `pillar_weights`（三維）；本次新增六維度加權欄 `dim_weights`，以 JSON 儲存，由 Series 層級管理（類似 `pillar_weights` 的模式）。全域預設加權則仿照 `framework_default_weights` 模式，新建 `dim_weight_defaults` 表（或以 system_settings key-value 儲存）。

相關既有 spec：`series-scoring-config`、`saq-scoring-engine`、`framework-default-weights`。

## Goals / Non-Goals

**Goals:**

- `saqs` 表新增 `dim_e1`–`dim_e6` 欄位，由 AI 回寫六維度原始分
- `assessment_series` 新增 `dim_weights`、`dim_weights_source`、`e4_objective_ratio` 欄位
- Laravel 端計算 `saqs.score = Σ(dim_eN × dim_weights[N])` × 100，取代 AI 端的 scalar total
- 系統設定提供 `dim_weight_defaults` CRUD（admin only），新建 Series 自動複製
- Series 計分設定 Tab 顯示六維度加權區塊，支援繼承系統預設或自訂

**Non-Goals:**

- E4 country risk 整合（`e4_objective_ratio` 欄位本次建立但不使用，留作下個 change 佔位）
- 已完成問卷的歷史分數自動重算（設定變更後不 backfill）
- 六維度分數的前端視覺化圖表（本次僅儲存，可在後續 change 實作）

## Decisions

### D1：總分合成在 Laravel 側而非 AI 側

**決定**：esgchain-ai 繼續輸出 `dim_e1`–`dim_e6`（0–1 scale），由 Laravel `SaqScoringResultService` 讀取 `series.dim_weights` 計算加權總分後寫入 `saqs.score`（0–100 scale）。

**理由**：`dim_weights` 是 Series 層級的業務設定，由 Laravel 管理；將 weights 傳給 AI 側再由 AI 合成會造成業務邏輯散落兩側。Laravel 端合成也方便日後審核員覆核時重算，無需再觸發 AI job。

**替代方案**：在 AI payload 中傳入 `dim_weights`，由 AI 合成後只回傳 `total_score`。缺點：若 weights 變更需重跑 Celery job，且 Laravel 無法在本地 override。

---

### D2：系統預設加權以 `system_settings` key-value 儲存，不新建獨立表

**決定**：在現有（或新建）`system_settings` 表用 key = `dim_weight_defaults` 儲存 JSON `{"E1":0.25,...}`，而非仿 `framework_default_weights` 建立專用表（後者針對多框架多 pillar 設計，六維度是固定結構）。

**理由**：六維度加權是單一全局設定，JSON key-value 足夠，不需要框架分組的複雜度。API 為 `GET/PUT /api/v1/settings/dim-weight-defaults`。

---

### D3：`dim_weights` 繼承時機為 Series 建立時複製（snapshot），不即時查詢

**決定**：Series 建立時，將系統預設 `dim_weight_defaults` 複製到 `assessment_series.dim_weights`，`dim_weights_source = 'default'`。之後系統預設變更不影響已建立的 Series。

**理由**：與 `pillar_weights` 的設計哲學一致——計分參數以 Series 為邊界隔離，避免同一 Series 的問卷前後使用不同權重。

---

### D4：`dim_weights` 驗證規則

各維度值 ≥ 0，合計在 0.99–1.01 之間（容差 ±0.01），Laravel FormRequest 與 AI payload 接收端均驗證。

## Risks / Trade-offs

- **[Risk] 歷史分數與新公式不一致** → 已完成問卷的 `saqs.score` 以舊三維公式計算；本次設計不 backfill。前端顯示時應標注「此分數使用舊計算方式」（以 `saqs.scoring_formula_version` 欄位或 NULL dim_e1 判斷）。緩解：文件說明清楚，後續可提供手動重算批次工具。

- **[Risk] AI 端 dim_e1–e6 欄位名稱對齊** → 需確認 esgchain-ai six_dim_scoring 回傳 key 與 Laravel mapping 一致（e.g. `dim_e1` vs `e1_score`）。緩解：在任務中加入 AI response schema 確認步驟。

- **[Trade-off] Laravel 端合成造成 AI 總分與 Laravel 總分短暫不一致** → AI job 完成後寫 dim_e1–e6，然後 Laravel event listener 合成 score。兩步之間有短暫視窗。接受此 trade-off，UI 顯示 loading 狀態即可。

## Migration Plan

1. 執行 `assessment_series` migration：新增 `dim_weights`, `dim_weights_source`, `e4_objective_ratio`
2. 執行 `saqs` migration：新增 `dim_e1`–`dim_e6`
3. 建立（或修改）`system_settings` 表，插入 `dim_weight_defaults` 預設值
4. 部署 Laravel：新 Service、Controller、FormRequest、event listener
5. 部署 Vue：Settings Tab、Series 計分設定六維度區塊
6. 無需 rollback data migration（新欄位可為 NULL，不影響既有資料）

## Open Questions

- esgchain-ai `six_dim_scoring` 回傳 JSON key 名稱確認（需看 AI codebase）
- `system_settings` 表是否已存在？若無需本次建立 migration
