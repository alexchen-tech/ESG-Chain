# 六維度計分規格

**capability**: `six-dim-scoring`
**版本**: v1（six-dim-scoring 變更新增）
**最後更新**: 2026-07-13

---

## 1. 概述

將原本 E/S/G 三維計分擴展為 E1–E6 六維度細項計分。AI 輸出六維度細項分（0–1 scale），Laravel 端以 series.dim_weights 合成最終總分（0–100）。

六維度定義：
- **E1**：環境管理
- **E2**：氣候與碳排
- **E3**：社會責任
- **E4**：地緣風險（含客觀國家評等）
- **E5**：公司治理
- **E6**：供應鏈透明度

---

## ADDED Requirements

### Requirement: assessment_series 六維度加權欄位
`assessment_series` 表新增三個欄位，供六維度計分使用：
- `dim_weights JSON NULL`：格式 `{"E1":float,...,"E6":float}`，合計須為 1.0
- `dim_weights_source ENUM('default','custom') DEFAULT 'default' NOT NULL`
- `e4_objective_ratio DECIMAL(3,2) DEFAULT 0.40 NOT NULL`（佔位欄位，E4 country risk 整合時啟用）

#### Scenario: 建立 Series 時自動繼承系統預設加權
- **WHEN** POST /api/v1/assessment-series（建立新 Series）
- **THEN** `dim_weights` 自動複製當前 `system_settings.dim_weight_defaults` 值
- **AND** `dim_weights_source` 設為 `'default'`

#### Scenario: 系統預設不存在時的 fallback
- **WHEN** 建立 Series 時 system_settings 中無 `dim_weight_defaults` 記錄
- **THEN** `dim_weights` 設為 `{"E1":0.25,"E2":0.15,"E3":0.20,"E4":0.15,"E5":0.10,"E6":0.15}`
- **AND** `dim_weights_source` 設為 `'default'`

### Requirement: saqs 表六維度分欄位
`saqs` 表新增六個維度分欄位 `dim_e1`–`dim_e6`（`DECIMAL(5,4) NULL`，0–1 scale），由 esgchain-ai 完成計分後寫回。

#### Scenario: AI 計分完成後寫入六維度分
- **WHEN** esgchain-ai 完成 six_dim_scoring task 並回呼 Laravel
- **THEN** `saqs.dim_e1`–`dim_e6` 各自填入對應的 0–1 分數
- **AND** 各欄位值在 0.0000–1.0000 之間

#### Scenario: 計分尚未完成時欄位為 null
- **WHEN** SAQ 尚未觸發計分或計分進行中
- **THEN** `saqs.dim_e1`–`dim_e6` 均為 NULL

### Requirement: Laravel 端六維度總分合成
`SaqScoringResultService`（或現有結果寫回服務）在收到 AI 計分結果後，使用 `project → series.dim_weights` 計算加權總分並寫入 `saqs.score`。

公式：`score = ROUND(Σ(dim_eN × dim_weights["EN"]) × 100, 2)`

#### Scenario: 正常六維度合成
- **WHEN** AI 回傳 `dim_e1=0.80, dim_e2=0.70, dim_e3=0.65, dim_e4=0.75, dim_e5=0.60, dim_e6=0.80`
- **AND** series.dim_weights = `{"E1":0.25,"E2":0.15,"E3":0.20,"E4":0.15,"E5":0.10,"E6":0.15}`
- **THEN** score = (0.80×0.25 + 0.70×0.15 + 0.65×0.20 + 0.75×0.15 + 0.60×0.10 + 0.80×0.15) × 100 = 73.25

#### Scenario: Series 無 dim_weights 時使用等權 fallback
- **WHEN** `series.dim_weights` 為 NULL（舊資料或遷移前記錄）
- **THEN** 使用等權平均 1/6 計算 score

#### Scenario: SAQ 所屬 project 無 series 時使用系統預設加權
- **WHEN** project.series_id 為 NULL
- **THEN** 從 system_settings 讀取 dim_weight_defaults 計算 score
- **AND** 若 system_settings 無記錄則使用等權 fallback

### Requirement: DispatchSaqScoringJob 傳遞 dim_weights
計分 Job 觸發時，將 series 的 `dim_weights` 與 `e4_objective_ratio` 一併帶入 AI payload（AI 側暫不使用，但傳遞以備未來 E4 整合）。

#### Scenario: 傳遞維度加權到 AI
- **WHEN** DispatchSaqScoringJob 執行，且 project 有 series_id
- **THEN** AI payload 包含 `series_dim_weights`（JSON）與 `series_e4_objective_ratio`（float）

#### Scenario: 無 series 時傳遞 null
- **WHEN** project 無 series_id
- **THEN** AI payload 中 `series_dim_weights` 與 `series_e4_objective_ratio` 均為 null
