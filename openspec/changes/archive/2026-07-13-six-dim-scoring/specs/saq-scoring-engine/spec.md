## MODIFIED Requirements

### Requirement: 總分計算由三維改為六維加權合成
AI 仍輸出 `score_e`、`score_s`、`score_g` 三維分（向後相容），並新增輸出 `dim_e1`–`dim_e6` 六維度細項分（0–1 scale）。Laravel 端以 `series.dim_weights` 合成最終 `saqs.score`（0–100），取代原本 AI 側計算的 scalar total。

- **AI 層**：輸出 `dim_e1`–`dim_e6`（0–1 scale）及既有三維分，不再負責最終 total 合成
- **Laravel 層**：收到 AI 計分回呼後執行 `score = Σ(dim_eN × dim_weights["EN"]) × 100`，寫入 `saqs.score`
- `grade` 仍由 `final_score ?? score` 對照 `series.grade_thresholds` 換算，邏輯不變

#### Scenario: 六維計分正常完成後 score 欄位更新
- **WHEN** AI 完成 six_dim_scoring 並回呼 Laravel score callback
- **AND** AI response 包含 `dim_e1`–`dim_e6` 六個維度分
- **THEN** Laravel 寫入 `saqs.dim_e1`–`dim_e6`
- **AND** 使用 series.dim_weights（或 fallback）計算 score 並寫入 `saqs.score`
- **AND** `saqs.grade` 依 score 換算更新

#### Scenario: AI response 缺少六維度分時降級處理
- **WHEN** AI response 不包含 `dim_e1`–`dim_e6`（舊版 AI 模型）
- **THEN** `saqs.dim_e1`–`dim_e6` 保持 NULL
- **AND** `saqs.score` 使用 AI 回傳的 `total_score` 直接寫入（降級相容）

## ADDED Requirements

### Requirement: saqs 六維度分欄位對外 API 暴露
`GET /api/v1/saqs/{id}`（或計分結果端點）回傳包含 `dim_e1`–`dim_e6` 欄位。

#### Scenario: 取得含六維度分的 SAQ 詳情
- **WHEN** GET /api/v1/saqs/{id}
- **THEN** response 包含 `dim_e1`–`dim_e6`（float 或 null）
- **AND** 計分前為 null，計分後為 0.0–1.0 的浮點數
