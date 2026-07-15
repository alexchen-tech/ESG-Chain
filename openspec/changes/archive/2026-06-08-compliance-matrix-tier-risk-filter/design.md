## Context

合規矩陣（`/api/v1/compliance/matrix`）目前以 `supplier_group_id` 作為唯一篩選維度，後端在 `SupplierComplianceStatusService::getMatrixData()` 中用一個 `when()` 子句套用。`Supplier` 主檔已有 `tier`（int 1/2/3）與 `risk_score`（float）兩個欄位，但從未進入任何看板計算或篩選邏輯。Drill-down 端點（`/compliance/matrix/drill`）回傳的 `MatrixDrillSupplier` 目前僅包含 `supplier_id`、`supplier_name`、`supplier_group`、`status`、`expires_at`。

## Goals / Non-Goals

**Goals:**
- 後端矩陣端點支援 `tier` 與 `risk_score_min` 可選 query params（向後相容）
- Drill-down 回傳欄位新增 `tier`、`risk_score`、`onboarding_stage`
- 前端矩陣篩選列新增 Tier 下拉與風險分數下限 input，套用時重新載入矩陣與 drill-down
- Drill-down 清單每列顯示 tier badge、risk_score 數字、onboarding_stage chip

**Non-Goals:**
- 矩陣格子計算邏輯本身不變（仍為 compliant/total/pct）
- 不新增資料庫欄位或資料模型異動
- 不修改供應商看板（Supplier tab）與產品看板（Product tab）的篩選行為
- 不做風險分數的範圍篩選（上限），僅做下限過濾

## Decisions

**決策 1：risk_score_min 用 float 比較，前端以整數 input 傳入**

後端 `when($filters['risk_score_min'] ?? null, fn($q,$v) => $q->where('risk_score', '>=', (float)$v))`。前端 input type="number" min=0 max=100 step=1，傳字串即可，後端 cast。

替代方案：提供 risk_score_min + risk_score_max 區間。排除理由：使用場景是「找高風險」，單一下限足夠，兩個 input 增加 UI 複雜度且無需求支撐。

**決策 2：篩選套用方式為即時觸發（不需 Apply 按鈕）**

Tier 下拉 `@change` 直接呼叫 `loadMatrixData()`。風險分數 input 加 300ms debounce 避免每次按鍵都打 API。

替代方案：加「套用」按鈕。排除理由：群組篩選已是即時觸發，保持一致；矩陣 API 通常 < 500ms。

**決策 3：Drill-down 欄位在後端 map() 一次性加入，不改 model**

`getMatrixDrill()` 的 `map()` 直接加 `'tier' => $s->tier`、`'risk_score' => $s->risk_score`、`'onboarding_stage' => $s->onboarding_stage`，不建新 Resource class。規模小，無需額外抽象。

**決策 4：Tier 篩選同時作用於 matrixDrill**

呼叫 `openDrill()` 時，現有的 `selectedMatGroupId` 已被帶入 params；同理，新的 `selectedTier` 與 `riskScoreMin` 也一起帶入，保持 drill-down 與矩陣母體一致。

## Risks / Trade-offs

- **`risk_score` 可能為 null**：Supplier 主檔 `risk_score` 是 nullable float。`risk_score_min` 篩選時，SQL `where('risk_score', '>=', N)` 對 NULL 行為是排除（NULL 不滿足 >=），這符合「高風險供應商」的語意（未評分的不計入）。前端需在 input placeholder 說明「未評分供應商不列入」。  
  → 無需額外處理，但 UI 需說明。

- **矩陣重載頻率增加**：Tier + risk_score 各自變更都會重載矩陣。若矩陣資料量大（materialGroups × suppliers 多），可能造成短暫閃爍。  
  → 風險分數用 debounce 緩解；Tier 下拉變更頻率低，直接觸發可接受。

## Migration Plan

無資料庫異動，無需 migration。純後端 service 方法擴充 + 前端 UI 新增控制項，可直接部署。
