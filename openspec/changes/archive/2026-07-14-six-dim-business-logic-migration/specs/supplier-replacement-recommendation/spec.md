## MODIFIED Requirements

### Requirement: 供應商替代候選評分模型改用六維
替代候選評分 SHALL 改為 `total_score × 0.5 + 六維加權分 × 0.5`，並套用最差維度硬性過濾。

評分公式：
```
six_dim_score = Σ(dim_eN × default_weight_N) / Σ(active_weight_N)
candidate_score = total_score × 0.5 + six_dim_score × 0.5
```

硬性過濾：`min(dim_e1, dim_e2, dim_e3, dim_e4, dim_e5) ≥ 30`（任一維度合規分 < 30 者不推薦）

#### Scenario: 標準候選評分
- **WHEN** 查詢替代供應商，候選廠 A：total_score=85，dim_e1=90 / e2=75 / e3=82 / e4=78 / e5=80，min=75
- **THEN** 系統 SHALL 計算 six_dim_score = (90×0.25+75×0.15+82×0.20+78×0.15+80×0.10) / 0.85，candidate_score = total_score×0.5 + six_dim_score×0.5
- **THEN** 候選廠通過硬性過濾（min=75 ≥ 30），列入推薦清單

#### Scenario: 最差維度過低被過濾
- **WHEN** 候選廠 B：total_score=75，dim_e4=25（其餘均 ≥ 60）
- **THEN** 系統 SHALL 排除候選廠 B（min=25 < 30），不列入推薦清單

#### Scenario: 候選池為空時退化
- **WHEN** 所有同 HS Code 異來源國候選廠均未通過硬性過濾
- **THEN** 系統 SHALL 退化為僅用 total_score 排序，並在回應中標記 `fallback: true`

#### Scenario: 回傳六維分數供前端顯示
- **WHEN** 推薦結果回傳
- **THEN** 每筆候選記錄 SHALL 包含 `dim_e1`–`dim_e5` 各別合規分，供前端顯示維度強弱對比
