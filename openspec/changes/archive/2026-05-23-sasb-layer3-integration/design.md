## Context

現有計分引擎（`esgchain-ai/app/services/scoring_service.py`）使用 `DEFAULT_WEIGHTS = {"E":0.40,"S":0.35,"G":0.25}` hardcode，不知道供應商產業。`ScoringModel`（PostgreSQL）已有 weight_e/s/g 欄位但無 industry 對應。MySQL 的 `sasb_industries`（74 筆）只有 sector/industry/code，沒有對應的 Disclosure Topics。SAQQuestion 只有 E/S/G 分類，沒有 Topic/Metric 對應。Laravel → FastAPI 的計分 payload 目前只傳 `[{question_id, category, weight, answer}]`，不含任何 industry 資訊。

## Goals / Non-Goals

**Goals:**
- 建立完整 SASB Topic 資料層（MySQL）
- SAQQuestion 能對應到 SASB Disclosure Topic 和 Metric code
- ScoringModel 與 SASB Industry 綁定，管理員可設定各 Industry 的 E/S/G 權重
- 計分引擎依供應商 SASB Industry 動態套用 ScoringModel
- SAQScoringResult 補 topic_scores（按 Topic 分類的平均得分）
- 問卷發送支援自動配對（依供應商 Industry 推薦範本）和手選

**Non-Goals:**
- SASB Metric 的數值驗證（如 CO2e 的合理範圍）
- 自動從 SASB 官方資料庫同步 Topics（本次手動 seed）
- 多語系 Topic 名稱

## Decisions

**D1：sasb_disclosure_topics 資料表設計（MySQL）**
```sql
id UUID PK
sasb_industry_id UUID FK → sasb_industries.id
topic_name       STRING   "GHG Emissions"
topic_code       STRING   "EM-IS-110a"（SASB Accounting Metric prefix）
esg_category     ENUM     E|S|G  （此 Topic 歸屬哪個類別）
description      TEXT     nullable
```
理由：Topic 歸屬到 Industry（不是 Sector），因為同 Sector 的不同 Industry Topics 可能不同。`esg_category` 讓前端可依類別篩選。

**D2：SAQQuestion 補兩個欄位**
```
sasb_topic_id    UUID FK → sasb_disclosure_topics.id  nullable
sasb_metric_code STRING  "EM-IS-110a.1"               nullable
```
nullable：因為通用範本的題目不一定對應到特定 Industry Topic。

**D3：saq_template_industries pivot（多對多）**
```sql
saq_template_industries
  template_id    FK → saq_templates.id
  industry_id    FK → sasb_industries.id
  PK(template_id, industry_id)
```
同時保留 `saq_templates.sasb_industry_id`（單一主要 Industry，向後兼容）。  
Migration 先補 `sasb_industry_id` 欄位，再建 pivot 表。

**D4：ScoringModel 與 Industry 綁定（PostgreSQL）**
現有 `scoring_models` 表補欄位：
```
sasb_industry_code  VARCHAR(20)  "EM-IS"  nullable（null = 通用預設）
```
計分引擎查詢邏輯：
```python
# 1. 依 sasb_industry_code 找 active ScoringModel
# 2. 找不到 → fallback 到 sasb_industry_code=null 的通用模型
# 3. 通用也找不到 → DEFAULT_WEIGHTS hardcode
```
Alembic migration 新增欄位 + unique index on (sasb_industry_code, is_active)。

**D5：計分 payload 補充 industry 資訊**
Laravel 送計分時，在 `SAQScoringRequest` 補 `sasb_industry_code: Optional[str]`，FastAPI 計分引擎依此查 ScoringModel。

**D6：SAQScoringResult 補 topic_scores**
```python
class SAQScoringResult(BaseModel):
    ...（現有欄位）
    topic_scores: dict[str, float]  # {"GHG Emissions": 72.0, "Water Management": 45.0}
    industry_code: Optional[str]     # 使用的 industry code
    scoring_model_id: Optional[str]  # 使用的 ScoringModel UUID
```

**D7：問卷發送配對模式**
```
AUTO 模式：
  1. 取得所選供應商的 sasb_industry_id
  2. 查 saq_template_industries pivot，找有對應的 active 範本
  3. 推薦列表排序：完全匹配優先 > sector 匹配 > 通用範本
  
MANUAL 模式（現有行為）：
  採購商從所有 active 範本中選，但顯示「相容性標籤」
  （✓ 完全匹配 / ⚠️ Sector 匹配 / ○ 通用）
```
配對邏輯放 `QuestionnaireService`，前端透過 `POST /api/v1/questionnaires/recommend-templates` 取得推薦清單。

**D8：計分模型管理 UI 位置**
放在前端路由 `/settings/scoring-models`，從系統設定側邊導覽進入。資料讀寫走 Laravel proxy → FastAPI（與 PCF 計算的模式相同）。

## Risks / Trade-offs

- **Topic seed 資料量**：SASB 有 77 個 Industry，每個約 3-6 個 Topics，約 250-400 筆。本次 seed 優先 20 個最常見產業（鋼鐵、電子、能源、化工、軟體等），其餘留空。
- **sasb_industry_code fallback**：若供應商沒有設 SASB Industry，計分用通用模型，不影響現有功能。
- **topic_scores 只計算有對應 Topic 的題目**：沒有設 sasb_topic_id 的題目只計入 E/S/G 加權總分，不出現在 topic_scores。

## Migration Plan

1. **MySQL migration** 3 個（順序依賴）：
   - `add_sasb_industry_id_to_saq_templates`
   - `create_sasb_disclosure_topics`
   - `add_sasb_fields_to_saq_questions` + `create_saq_template_industries`
2. **PostgreSQL Alembic migration** 1 個：`add_sasb_industry_code_to_scoring_models`
3. **Seed**：SasbDisclosureTopicSeeder（20 個優先產業，約 80 筆 Topics）
4. 計分引擎向後兼容：無 industry_code → 通用模型
