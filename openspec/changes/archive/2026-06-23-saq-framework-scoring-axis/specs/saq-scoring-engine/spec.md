# Delta Spec: saq-scoring-engine

## MODIFIED Requirements

### Requirement: 計分維度改為框架原生 L2 pillar

**原規格**：計分輸出固定為 E/S/G 三維（`score_e`, `score_s`, `score_g`），以 `project_domain` 的 prefix 過濾參與計分的 slug。

**新規格**：計分輸出改為依範本 `scoring_framework` 動態計算各 L2 pillar 分數，儲存於 `saqs.category_scores JSON`。

#### Scenario: ESG 框架計分

WHEN 計分 payload 帶 `scoring_framework = "ESG"`
THEN AI 依 `esg.*` slug 過濾，按 L2 pillar 分組加權平均，輸出：
```json
{
  "category_scores": {
    "環境管理": 72.3,
    "勞工人權": 58.1,
    "職場安全": 81.0,
    "公司治理": 65.5,
    "社區與消費者": 44.0,
    "供應鏈環境": 90.2
  },
  "score_e": 74.5,
  "score_s": 61.0,
  "score_g": 65.5,
  "total_score": 67.8,
  "grade": "B"
}
```
> ESG 框架：`score_e` = 環境管理+供應鏈環境加權平均，`score_s` = 勞工人權+職場安全+社區消費者加權平均，`score_g` = 公司治理。舊欄位繼續填入以維持向後相容。

#### Scenario: ISO20400 框架計分

WHEN 計分 payload 帶 `scoring_framework = "ISO20400"`
THEN AI 依 `iso20400.*` slug 過濾，按 L2 pillar 分組，輸出：
```json
{
  "category_scores": {
    "採購政策": 78.4,
    "績效評估": 55.2,
    "風險管理": 62.0
  },
  "score_e": null,
  "score_s": null,
  "score_g": null,
  "total_score": 65.2,
  "grade": "B"
}
```
> 非 ESG 框架：`score_e / score_s / score_g` 填 null。`total_score` 為各 pillar 等權平均。

#### Scenario: NULL 框架（通用）

WHEN 計分 payload 帶 `scoring_framework = null`
THEN 不過濾 slug prefix，全題參與計分，`category_scores = {}`，`total_score` 為全題加權總分

### MODIFIED Requirement: 計分 payload 欄位

**原欄位**：`project_domain`（esgchain-api 傳入，scoring_service 用來過濾 slug prefix）

**新欄位**：`scoring_framework`（由 template.scoring_framework 決定，esgchain-api 組裝時讀取）

過渡期相容規則：AI 端先支援新欄位 `scoring_framework`；若 payload 只帶舊的 `project_domain` 欄位，以 `project_domain` fallback（舊版 API 相容）。

### ADDED Requirement: category_scores 儲存

`saqs` 表新增 `category_scores JSON NULL` 欄位，由計分完成後寫入。

- 格式：`{ "<l2_pillar_name>": <score_float> }`，分數 0–100
- ESG 框架：6 個 pillar
- ISO20400：3 個 pillar
- NULL 框架：空物件 `{}`
- API `GET /api/v1/questionnaires/{id}` 的 SAQ response 新增 `category_scores` 欄位
