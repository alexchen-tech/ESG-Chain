## Context

六維評核架構中，「框架定義」與「題目分類」原由兩張表各自管理：

- `framework_default_weights`：定義 L1 框架 + L2 軸度 + 預設權重（機器 key）
- `question_tags`：定義 L1/L2/L3 分類層次 + 人類可讀標籤

兩表在 L1、L2 層重疊但命名不同，無法直接 JOIN。計分引擎被迫在題目的 `tags` JSON 中冗餘存放 `{framework, pillar, weight}`，造成資料固化、weight 無法即時反映設定變更。

決定廢棄現有問卷專案資料後，從零建立「題目 → L3 slug → L2 slug（含 weight）→ L1 slug」的單一計分鏈。

## Goals / Non-Goals

**Goals:**
- `question_tags` 成為計分框架唯一真相來源
- L2 節點 slug = pillar 前綴，形成前綴樹（`iso28k.cert` → `iso28k.cert.standards`）
- `saq_questions.tags` 只存 L3 slug 陣列，weight 從標籤庫即時查詢
- DROP `framework_default_weights`，UI 改為讀寫 `question_tags` L2 節點

**Non-Goals:**
- 不改變 `assessment_series_weights`（per-question weight，維持現狀）
- 不重構 AI 計分任務的演算法，只更新 weight 查詢路徑
- 不改變 `saqs.active_modules`（仍用 E-code 作為模組代碼）

## Decisions

### D1：L2 節點識別規則

```
L1 節點：l2_pillar = 'General' AND l3_topic = 'General'
         slug = {l1_key}           例：iso28000
L2 節點：l2_pillar ≠ 'General' AND l3_topic = 'General'
         slug = {l1_key}.{pillar}  例：iso28k.cert
L3 節點：l3_topic ≠ 'General'
         slug = {l2_slug}.{topic}  例：iso28k.cert.standards
```

`default_weight` 欄位：L2 節點填值（0.0–1.0），L1/L3 節點為 NULL。

### D2：L2 slug 命名規則（前綴樹）

| 框架 | L2 slug | 說明 |
|---|---|---|
| ESG | `esg.env` / `esg.soc` / `esg.gov` | 原 esg.e.general → esg.env |
| ISO20400 | `iso20400.policy` / `.due_diligence` / `.action` / `.reporting` / `.capacity` / `.stakeholder` | 對應 ISO20400 永續採購五流程 |
| ISO26000 | `iso26000.governance` / `.hr` / `.labor` / `.environment` / `.fairop` / `.consumer` / `.community` | ISO26000 七核心主題 |
| Geo-Risk | `georisk.political` / `.environmental` / `.social` / `.regulatory` | 四象限地緣風險 |
| ISO28000 | `iso28k.physical` / `.cert` / `.cargo` / `.infosec` | 供應鏈安全四軸 |
| Product-Compliance | `prod_comp.cbam` / `.eudr` / `.chem` / `.trace` | 產品合規四域 |

### D3：計分路徑（合併後）

```sql
-- 取題目所有 L3 tag 的 L2 slug 與預設權重
SELECT
  SUBSTRING_INDEX(qt.slug, '.', 2)  AS l2_slug,   -- 'iso28k.cert'
  qt_l2.default_weight,
  qt_l2.l1_domain
FROM   question_tags qt
JOIN   question_tags qt_l2
         ON qt_l2.slug = SUBSTRING_INDEX(qt.slug, '.', 2)
        AND qt_l2.l3_topic = 'General'
WHERE  qt.slug IN ('iso28k.cert.standards', 'iso28k.cert.internal_audit')
```

> **注意**：SUBSTRING_INDEX 取前兩段 dot 適用於 `{l1}.{l2}.{topic}` 三層 slug。
> `esg.env.ghg_emission` → `esg.env`（L2 slug）✓
> `prod_comp.cbam.reporting` → `prod_comp.cbam`（L2 slug）✓

### D4：`assessment_series.pillar_weights` 格式

```json
舊格式（pillar_slug key）：
{ "iso28k.cert": 0.30, "iso28k.cargo": 0.20 }

新格式（question_tags L2 節點 id）：
{ "<uuid-of-iso28k.cert-L2-node>": 0.30, "<uuid-of-iso28k.cargo-L2-node>": 0.20 }
```

現有 `pillar_weights` 欄位無實際資料（0 筆），遷移零風險。
新 Series 建立時，從 question_tags L2 節點讀取 `default_weight` 作為初始值寫入此 JSON。

### D5：ISO20400 L2 節點重建

現有 7 個 ISO20400 L2 節點（iso20400.human_rights.general 等）是錯誤的 ISO26000 主題，必須刪除並重建為 ISO20400 永續採購流程框架：

```
刪除：iso20400.human_rights.general / .fair_ops.general / .labor.general
      .consumer.general / .environment.general / .community.general / .org_gov.general
新增：iso20400.policy（採購政策）
      iso20400.due_diligence（盡職調查）
      iso20400.action（行動計畫）
      iso20400.reporting（報告揭露）
      iso20400.capacity（能力建構）
      iso20400.stakeholder（利害關係人）
```

同時需要確認 ISO20400 的 L3 節點 slug 前綴是否與新 L2 slug 一致（`iso20400.policy.commitment` → L2 是 `iso20400.policy` ✓）。

### D6：`FrameworkDefaultWeightPanel.vue` 改版

API 從 `/api/v1/settings/framework-default-weights` 改為讀寫 `question_tags`：

```
GET  /api/v1/settings/tag-library/l2-nodes?l1_domain=ISO28000
     → 回傳 L2 節點清單（id, slug, label_zh, default_weight）

PUT  /api/v1/settings/tag-library/l2-nodes/{id}/weight
     { "default_weight": 0.30 }
```

## Risks / Trade-offs

| 風險 | 影響 | 緩解 |
|---|---|---|
| SUBSTRING_INDEX 取 L2 slug 在 slug 格式不一致時出錯 | 計分錯誤 | migration 前驗證所有 L3 slug 都符合 `x.y.z` 三段格式 |
| ISO20400 L3 節點前綴不符新 L2 slug | 計分路徑斷裂 | 檢查現有 iso20400.* L3 slug 並視需要更新 |
| `question_tag_assignments` 重建工作量 | 題目無 L3 tag 則無法計分 | 先實作架構，L3 tag 補齊為後續工作（可用 AI 輔助分類） |
