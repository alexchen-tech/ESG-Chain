## ADDED Requirements

### Requirement: 計分請求攜帶 industry_code
Laravel 呼叫 FastAPI 計分時，`SAQScoringRequest` SHALL 包含選填欄位 `sasb_industry_code: Optional[str]`，值為供應商的 SASB Industry code（如 "EM-IS"）。

#### Scenario: 有供應商 SASB Industry 時傳送
- **WHEN** 供應商有 sasb_industry_id
- **THEN** Laravel 解析對應的 SasbIndustry.code 並附加到計分請求

#### Scenario: 供應商無 SASB Industry
- **WHEN** 供應商 sasb_industry_id = null
- **THEN** 計分請求中 sasb_industry_code = null，FastAPI 使用通用模型

### Requirement: FastAPI 動態選擇 ScoringModel
計分引擎 SHALL 依 `sasb_industry_code` 查詢 PostgreSQL 的 `scoring_models` 表，選取 `is_active=true` 且 `sasb_industry_code` 匹配的模型；若無匹配則 fallback 至 `sasb_industry_code=null` 的通用模型；通用模型也不存在則使用 hardcode DEFAULT_WEIGHTS。

#### Scenario: 有產業特定模型
- **WHEN** sasb_industry_code = "EM-IS" 且 scoring_models 中有 is_active=true 且 sasb_industry_code="EM-IS" 的記錄
- **THEN** 使用該模型的 weight_e/s/g 和 SGS 閾值計分

#### Scenario: Fallback 到通用模型
- **WHEN** 找不到產業特定模型，但有 sasb_industry_code=null 且 is_active=true 的記錄
- **THEN** 使用通用模型

#### Scenario: 完全 Fallback（向後兼容）
- **WHEN** DB 中無任何 active ScoringModel
- **THEN** 使用 DEFAULT_WEIGHTS (E:0.40, S:0.35, G:0.25)，行為與現有完全一致

### Requirement: SAQScoringResult 補 topic_scores
計分結果 SHALL 包含 `topic_scores: dict[str, float]`，key 為 SASB Topic name，value 為該 Topic 下所有題目的加權平均分（0-100）。無 sasb_topic_id 的題目不出現在 topic_scores，但仍計入 E/S/G 加權總分。

#### Scenario: 有題目對應 Topics 時
- **WHEN** 問卷有題目設定 sasb_topic_id，且回覆中帶有 sasb_topic
- **THEN** topic_scores 包含各 Topic 的得分，如 `{"GHG Emissions": 72.0, "Water Management": 45.0}`

#### Scenario: 無 Topic 對應時
- **WHEN** 所有題目均無 sasb_topic_id
- **THEN** topic_scores = {}（空 dict），不影響總分計算
