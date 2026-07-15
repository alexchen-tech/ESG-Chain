## ADDED Requirements

### Requirement: 多框架同時計分輸出（Multi-framework Scoring）

計分引擎 SHALL 在 `scoring_framework = "multi-framework"` 時，對同一份答題資料執行三組 slug prefix filter，分別計算各框架分數：

```
FRAMEWORK_FILTER_MAP = {
    "multi-framework": ["iso26000", "iso20400", "geo_risk"]
}
```

各框架計分流程與單框架相同（filter slug → pillar 分組 → 加權平均），但結果分開儲存。

`SAQScoringResultResponse` SHALL 新增欄位：
- `iso26000_total: Optional[float]`
- `iso26000_category_scores: dict[str, float]`
- `iso20400_total: Optional[float]`
- `iso20400_category_scores: dict[str, float]`
- `geo_risk_total: Optional[float]`
- `axis1_score: Optional[float]`（= 100 - iso26000_total）
- `axis2_score: Optional[float]`（= 100 - iso20400_total）

#### Scenario: Multi-framework 計分執行三次 filter

- **WHEN** `calculate_saq_score(scoring_framework="multi-framework", responses=[...])` 被呼叫
- **THEN** 引擎 SHALL 分別以 "iso26000"、"iso20400"、"geo_risk" 執行 filter，各自輸出 total 與 category_scores，並計算 axis1_score = 100 - iso26000_total、axis2_score = 100 - iso20400_total

#### Scenario: 單一回答貢獻多個框架分數

- **WHEN** 某題 tag_slugs 包含 `["iso26k.hr.child_labor", "iso20400.risk.due_diligence"]`，回答分數為 75
- **THEN** 此題分數 75 SHALL 同時貢獻 iso26000 的 `人權` pillar 與 iso20400 的 `風險管理` pillar

#### Scenario: geo_risk 無題目時 geo_risk_total 為 null

- **WHEN** Multi-tag 範本無任何 geo_risk.* slug 題目，執行 multi-framework 計分
- **THEN** `geo_risk_total` SHALL 為 null，`axis1_score` 與 `axis2_score` 正常計算
