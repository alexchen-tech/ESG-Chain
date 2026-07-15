## Context

風險矩陣由四個獨立問題組成：

1. **buildMatrix() bug**：`GROUP BY supplier_id, p, i` + `MAX(assessed_at)` 的組合在供應商有多筆不同 p/i 評估時，會把同一供應商計入多個格子。
2. **SAQ 評分斷鏈**：`scoreCallback()` 收到 score_e/score_s/score_g 後無後續動作；`saq_score_snapshots` 只關聯 `saq_id`，沒有 supplier_id，無法直接讀取供應商最新評分。
3. **CAP 手動孤島**：extreme 供應商必須由人工另開 CAP，沒有系統強制。
4. **歷史資料無前端消費**：`risk_assessments` 已是歷史 append 表，但前端只顯示最新矩陣，從未展示趨勢。

## Goals / Non-Goals

**Goals:**
- buildMatrix() 每個供應商只計入一個格子（最新評估）
- scoreCallback() 完成後自動寫入 RiskAssessment（E/S/G），GP 不自動
- extreme 維度自動開 CAP，含 findings
- 供應商詳情頁顯示風險評估歷史時間軸

**Non-Goals:**
- 不整合 esgchain-ai 的 PostgreSQL RiskAssessment（另一套體系，留待未來）
- 不修改 suppliers.risk_score 欄位（ERP 欄位，留給 ERP 填寫）
- 不實作 GP 自動推導（無 SAQ 對應題組）
- 不建立前端的矩陣編輯 UI（維持現有手動建立流程，自動建立的記錄可透過相同介面補填 GP）

## Decisions

### D1：buildMatrix() 修正策略 — subquery 取最新評估

**決定**：
```sql
WITH latest AS (
  SELECT supplier_id, MAX(assessed_at) as max_at
  FROM risk_assessments
  GROUP BY supplier_id
)
SELECT ra.supplier_id, ra.{dim}_probability, ra.{dim}_impact
FROM risk_assessments ra
JOIN latest ON ra.supplier_id = latest.supplier_id
          AND ra.assessed_at = latest.max_at
```

Laravel 實作用 `joinSub`。

**理由**：最簡單的正確語意——一個供應商的風險由其最近一次評估決定。

### D2：SAQ → RiskAssessment 換算規則

```
probability = max(1, ceil((100 - score_dim) / 20))
impact      = 3（固定基準）

score_dim = score_e → e_probability, e_impact = 3
score_dim = score_s → s_probability, s_impact = 3
score_dim = score_g → g_probability, g_impact = 3
gp_probability = null（不自動，保留上一次手動值或預設 3）
gp_impact      = null（同上）
```

若 score_dim 為 null（SAQ 未分維度計分），跳過自動建立，不建立 RiskAssessment。

**assessed_by**：設為系統用戶 UUID（或 null），`notes` 填入 `'自動從 SAQ #{saq_id} 推導'`。

### D3：extreme CAP 觸發 — Observer 模式

**決定**：使用 `RiskAssessmentObserver::created()`。偵測邏輯：

```php
$extremeDims = [];
foreach (['e', 's', 'g', 'gp'] as $dim) {
    $p = $assessment->{"{$dim}_probability"} ?? 0;
    $i = $assessment->{"{$dim}_impact"} ?? 0;
    if ($p * $i >= 20) $extremeDims[] = strtoupper($dim);
}
if (count($extremeDims) > 0) → 建立 CAP
```

CAP 欄位：
- `source_type = 'risk_assessment'`
- `source_id = $assessment->id`
- `priority = 'high'`
- `title = '風險評估 Extreme 警示：[供應商名稱]'`
- `due_date = now() + 30 天`

CAPFinding per extreme dim：
- `category = dim`（如 'E'）
- `finding = "E 維度 cell_score={score}，已達 extreme 等級"`

**防重複**：若該 RiskAssessment 已有對應 CAP（`source_type='risk_assessment' AND source_id=$id`），跳過建立。

### D4：風險歷史 UI — 時間軸清單

**決定**：在 supplier detail overview tab 底部新增「風險評估歷史」卡片，呈現方式：
- 時間倒序列表（最多 10 筆）
- 每筆顯示：assessed_at、各維度 cell_score badge（E/S/G/GP）、整體最高 level
- delta 標記：本次 vs 上次同維度 cell_score 的 ↑↓ 變化

API：複用現有 `GET /api/v1/risk/assessments?supplier_id={id}` 端點。

## Risks / Trade-offs

- **[風險] GP 自動評估缺失**：自動建立的 RiskAssessment 無 gp_probability/gp_impact，矩陣 GP tab 不更新。→ 接受，GP 維持手動；notes 中會標示「GP 待補填」
- **[風險] 同一供應商多次 SAQ 快速連續評分**：可能建立多筆 RiskAssessment。→ 接受，歷史追蹤是預期行為
- **[風險] extreme CAP 重複開立**：同一供應商多次評估觸發多個 CAP。→ Observer 只在 source_id 無對應 CAP 時才建立，per-assessment 唯一

## Migration Plan

1. 無需 DB migration（不新增欄位）
2. 新增 `RiskAssessmentObserver`，在 `AppServiceProvider` 註冊
3. 修改 `scoreCallback()`：呼叫新建 `RiskAutoDerivationService::deriveFromSaq()`
4. 修改 `buildMatrix()`：改用 joinSub 正確取最新評估
5. 前端 supplier detail 新增歷史區塊（call 現有 API）
