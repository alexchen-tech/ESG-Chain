## MODIFIED Requirements

### Requirement: 六維計分取代單一分數
SAQ 完成後觸發的計分引擎 SHALL 由原本輸出單一 `score`（含 score_e/score_s/score_g）改為同時輸出六個維度分數（dim_e1–dim_e6），並反寫至 `risk_assessments.dim_e1–dim_e6`。原有 `saq_responses.score`/`score_e`/`score_s`/`score_g` 欄位保留並繼續填入，以維持向下相容。

#### Scenario: 完成問卷觸發六維計分
- **WHEN** SAQ 狀態轉為 `completed`（由採購方或系統確認）
- **THEN** `esgchain-ai` 的 `score_saq_v2` Celery task 被觸發，輸出 `{dim_e1, dim_e2, dim_e3, dim_e4, dim_e5, dim_e6}` 及舊版 `{score, score_e, score_s, score_g}`

#### Scenario: 計分任務失敗不阻斷問卷狀態
- **WHEN** Celery task 執行失敗（如外部資料來源無回應）
- **THEN** SAQ 狀態仍維持 `completed`，dim_e1–dim_e6 暫時為 null，任務加入重試佇列，採購方看到「計分處理中」狀態

### Requirement: 多重標記計分邏輯
計分引擎 SHALL 依題目 `tags` JSON 中每個 `{framework, pillar, weight}` 分別累積各維度分數，一道題可同時貢獻多個維度。各維度滿分基準為動態計算（該維度所有相關題目 weight 總和），而非固定值。

#### Scenario: 無 E3 題目時 E3 維度為 null
- **WHEN** 供應商問卷不包含任何帶 E3 tag 的題目
- **THEN** 計分引擎輸出 `dim_e3: null`，而非 0；0 分代表「有題目但全部答錯」

#### Scenario: 混合計分任務串接
- **WHEN** 主計分任務（六維純問卷計分）完成後
- **THEN** 系統串接呼叫 `compute_e4_score` 與 `compute_e6_score`（各自從外部資料混合計算），完成後更新 dim_e4/dim_e6 覆蓋純問卷值
