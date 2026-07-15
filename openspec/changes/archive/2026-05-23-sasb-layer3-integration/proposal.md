## Why

目前問卷計分引擎使用固定 E/S/G 加權（0.40/0.35/0.25），與供應商所屬產業完全無關。鋼鐵廠與軟體公司拿到相同的加權配置，違反 SASB 的核心原則「不同產業有不同的重大議題」。此外，問卷題目只有 E/S/G 類別標籤，無法對應到具體的 SASB Disclosure Topic 或 Accounting Metric，使得報告輸出無法符合 SASB/CSRD 格式。DB 層面也存在多個技術債：`saq_templates.sasb_industry_id` 欄位從未建立、範本與產業的關聯只能是單一對應而非多對多。

## What Changes

**MySQL（Laravel）：**
- 修正技術債：補 `saq_templates.sasb_industry_id` migration
- 新建 `sasb_disclosure_topics` 表（SASB Disclosure Topic + Metric code 對照）
- 新建 `saq_template_industries` pivot 表（範本 ↔ 多 Industry 多對多）
- `saq_questions` 補 `sasb_topic_id`（FK）+ `sasb_metric_code`（字串）
- 後端 API：SasbDisclosureTopic CRUD、ScoringModel 設定 API（proxy 到 FastAPI）

**PostgreSQL（FastAPI）：**
- `scoring_models` 補 `sasb_industry_code` 欄位（綁定到特定產業）
- 新 Alembic migration
- 計分引擎升級：依 `sasb_industry_code` 動態套用 ScoringModel 權重
- `SAQScoringResult` 補 `topic_scores`（按 SASB Topic 分類的得分）

**前端：**
- 題目 Modal 補 SASB Topic 下拉 + Metric code 輸入
- 新增「計分模型管理」頁（管理員設定 Industry × E/S/G 權重 + 閾值）
- 問卷發送 Modal 新增配對模式：自動（依供應商 SASB Industry 推薦範本）/ 手選
- SAQ 詳情頁顯示 topic_scores 雷達圖或橫條圖

## Capabilities

### New Capabilities
- `sasb-disclosure-topics`: SASB Disclosure Topic 資料表與 API（MySQL，依 Industry 查詢）
- `question-sasb-mapping`: SAQQuestion 題目對應 SASB Topic/Metric（DB 欄位 + 前端 UI）
- `industry-aware-scoring`: 依供應商 SASB Industry 選取對應 ScoringModel，產業特定加權
- `scoring-model-management`: 管理員 UI 設定各 Industry 的 E/S/G 權重與 SGS 閾值（存 PostgreSQL）
- `topic-scores-result`: SAQScoringResult 補 topic_scores，報告輸出可按 SASB Topic 彙整
- `questionnaire-matching`: 問卷發送時提供自動/手選配對模式

### Modified Capabilities
- `question-crud`: 題目 Modal 補 SASB Topic/Metric 欄位
- `template-list-entry`: 範本與 Industry 改為多對多

## Impact

- **後端（Laravel）**：3 個 migration、2 個新 Model、3 個新 Controller、1 個 proxy Service
- **後端（FastAPI）**：1 個 Alembic migration、ScoringModel 欄位、計分引擎重構、新 API endpoint
- **前端**：1 個新頁面（計分模型管理）、3 個現有頁面修改
- **跨服務**：Laravel → FastAPI 的計分請求需攜帶 `sasb_industry_code`
