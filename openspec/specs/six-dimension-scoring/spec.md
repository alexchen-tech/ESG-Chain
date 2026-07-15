## ADDED Requirements

### Requirement: 六維並行計分
SAQ 問卷完成後，系統 SHALL 同時計算六個維度分數（E1–E6），每個維度分數範圍為 0–100。E1（ESG整體）與 E4（地緣風險）為全體供應商必有維度；E2/E3/E5/E6 若供應商未加掛對應模組則為 null（不等於 0）。

#### Scenario: 問卷完成觸發六維計分
- **WHEN** SAQ 狀態變更為 `completed`
- **THEN** 系統觸發六維計分 Celery task，輸出 dim_e1–dim_e6 並寫入對應 `risk_assessments` 記錄

#### Scenario: 未加掛模組維度為 null
- **WHEN** 供應商 industry_group 為「物流倉儲」（加掛 E5，未加掛 E2/E3/E6）
- **THEN** dim_e2 / dim_e3 / dim_e6 為 null，dim_e1 / dim_e4 / dim_e5 有值

### Requirement: 多重標記題目計分
每道題 SHALL 依其 `tags` JSON 中各 framework entry 的 weight，分別貢獻到對應維度的累積分數。單題可同時貢獻多個維度。

#### Scenario: 單題多維度貢獻
- **WHEN** 題目帶有 `[{framework:"E1",weight:0.04},{framework:"E3",weight:0.06}]` 標記且供應商回答「是」
- **THEN** E1 維度累積 +4 分基準，E3 維度累積 +6 分基準（滿分基準不同）

#### Scenario: 維度滿分基準動態計算
- **WHEN** 計算某供應商的 E3 維度分數
- **THEN** 分母為該問卷中所有帶 E3 標記題目的 weight 總和 × 100，非固定值

### Requirement: 100% 必答強制
問卷 SHALL 於系統層面阻擋未完整作答的提交；所有 `is_required=true` 的題目必須有回答，才允許將 SAQ 狀態改為 `submitted`。

#### Scenario: 未完整作答阻擋提交
- **WHEN** 供應商嘗試提交問卷但有必答題未填寫
- **THEN** API 回傳 422，列出未填題目 ID，SAQ 狀態維持 `in_progress`

#### Scenario: SLA 逾期標記
- **WHEN** project.due_date 已過且 SAQ 狀態仍為 `sent` 或 `in_progress`
- **THEN** SAQ 加上 `overdue` 標記，觸發通知給採購方責任人
