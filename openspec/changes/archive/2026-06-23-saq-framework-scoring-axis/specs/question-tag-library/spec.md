# Delta Spec: question-tag-library

## ADDED Requirements

### Requirement: 範本框架 TAG 覆蓋率規則

當 `saq_templates.scoring_framework` 不為 NULL 時，該範本的每一道題都必須至少有一個對應 L1 domain 的 TAG assignment。

**覆蓋率計算**：
```
覆蓋率 = COUNT(有框架 TAG 的題) / COUNT(所有題)
```

- 覆蓋率 < 100%：系統顯示 warning，列出缺口題清單（不阻擋操作）
- 覆蓋率 = 100%：顯示「TAG 覆蓋完整 ✓」

### Requirement: ISO20400 範本缺口題補標籤（一次性資料修正）

`S³P 永續採購評核（ISO 20400）`範本（scoring_framework = "ISO20400"）的 18 道缺口題，新增以下 `question_tag_assignments`：

| 題目類型 | 新增 TAG（iso20400 L2 pillar） |
|---|---|
| Scope 1 碳排量（填報題） | `iso20400.performance.env_performance` |
| 總用水量（填報題） | `iso20400.performance.env_performance` |
| 總用電量（填報題） | `iso20400.performance.env_performance` |
| 再生能源節能技術（複選） | `iso20400.performance.env_performance` |
| 廢棄物處理方式（複選） | `iso20400.performance.env_performance` |
| 水資源管理政策 | `iso20400.performance.env_performance` |
| 碳減量具體措施 | `iso20400.performance.env_performance` |
| 供應商 Scope 3 碳排減量 | `iso20400.performance.env_performance` |
| 供應商再生能源鼓勵 | `iso20400.performance.env_performance` |
| 供應商環保包裝要求 | `iso20400.performance.env_performance` |
| 女性員工比例（填報題） | `iso20400.performance.social_performance` |
| 職災 LTIFR（填報題） | `iso20400.performance.social_performance` |
| 職場健康安全措施（複選） | `iso20400.performance.social_performance` |
| 反腐/商業道德政策 | `iso20400.policy.governance` |
| 供應商永續大會 | `iso20400.policy.sustainability_criteria` |
| 生命週期成本（LCC）採購 | `iso20400.policy.sustainability_criteria` |
| CAP 矯正行動機制 | `iso20400.risk.corrective_action` |
| 可追溯性系統（原料來源） | `iso20400.risk.supply_chain_risk` |

> 以上題目同時保留現有 ESG TAG 不變（雙重標籤）。
