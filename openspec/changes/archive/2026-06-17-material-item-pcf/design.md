## Context

目前 MaterialItem 僅儲存料號、名稱、HS Code、物料群組等靜態屬性。BomLineSupplier 紀錄了「哪個供應商供應哪條 BOM 明細」，但沒有碳排強度資料。PCF（產品碳足跡）無法自動計算。

系統已有類似機制作為參考：
- `trade_good_supplier_emissions`：貿易商品供應商碳排提報，已完成（trade-good-refactor）
- `EmissionFactor`（esgchain-ai PostgreSQL）：行業排放因子資料庫
- `Celery` 任務隊列：esgchain-ai 已有 scoring_tasks 範本

本次設計在 MaterialItem 維度建立對稱的碳排 MDM 架構，並加入 PCF 快照計算層。

## Goals / Non-Goals

**Goals:**
- 建立 `material_item_emissions` 作為物料碳排強度主數據，支援多供應商、多版本（append-only）
- 建立 `pcf_snapshots` 記錄 BuyerProduct PCF 計算結果，含 ISO 14067 準備度標記與明細 JSON
- 供應商 Portal 可查看需提報的物料清單（含 AI 估算值），並主動提報（含無 BomLine 指定的情況）
- BomLine 主供應商（role=primary）切換時自動觸發 PCF 重算
- AI 估算：BomLine 加入且無碳排記錄時，Celery 非同步由 HS Code 推算排放值

**Non-Goals:**
- ISO 14067 報告書完整生成（預留欄位，實際介接為後續 change）
- LCA 完整生命週期計算（本次只做 Scope 3 上游直接採購碳排）
- 跨供應商的 PCF 比較分析儀表板

## Decisions

### D1：material_item_emissions 多版本策略（append-only）

**決策**：允許同一 (material_item_id, supplier_id) 存在多筆記錄（不同 reported_period）。PCF 計算取 `is_estimated = false` 的最新一筆；若無則取 `is_estimated = true` 的最新估算。

**理由**：碳排強度會隨製程改善逐季下降，歷史版本對趨勢分析和稽核有價值。UPSERT 會破壞歷史可追蹤性。

**替代方案**：UPSERT（簡單但失去歷史）→ 捨棄。

---

### D2：PCF 計算觸發與儲存（Snapshot 方案）

**決策**：採方案 C——PCF 以快照形式儲存於 `pcf_snapshots`，每次觸發重算生成新快照。BuyerProduct 顯示最新快照的 `total_pcf`。

觸發點：
1. 供應商提報新碳排 → Celery Task `recalc_pcf_for_material`
2. BomLine 加入/更新/刪除 → Celery Task `recalc_pcf_for_product`
3. BomLineSupplier 主供應商切換 → 同上

**理由**：快照保留計算歷史（PCF 趨勢），支援未來 14067 審計追蹤。即時計算在 BOM 筆數多時效能差。

**替代方案**：即時計算（準確但慢）、computed column（不保留歷史）→ 皆捨棄。

---

### D3：AI 估算觸發時機

**決策**：只在 BomLine 被加入（或供應商被指定）且該 (material_item, supplier) 無任何碳排記錄時，才觸發 `estimate_material_emission` Celery Task。

**理由**：物料建立時估算會產生大量無效計算（很多物料可能永遠不進 BOM）。BOM 加入才是「真正需要這個數值」的時點。

---

### D4：供應商提報即計入 PCF（折衷確認流程）

**決策**：供應商提報後立即計入 PCF（`is_flagged = false`）。買方可標記異常（`is_flagged = true`），標記不阻斷 PCF 計算，但在 PCF 詳情中以 ⚠ 標示。

**理由**：嚴格確認流程（如 trade-good-refactor）會卡住 PCF 計算，對採購商實用性差。物料碳排是估計值本質，需要速度而非強一致性。

---

### D5：主供應商（primary role）決定 PCF 計入值

**決策**：`BomLineSupplier.role = 'primary'` 的供應商碳排值計入 PCF。每條 BomLine 最多一個 primary；切換 primary 觸發 PCF 重算。

**理由**：BomLine 可有備用供應商，但 PCF 只能取一個值。role 欄位已存在，擴展語意即可，不需新欄位。

---

### D6：pcf_snapshots.lines JSON 結構預留 ISO 14067 欄位

```json
{
  "bom_line_id": "uuid",
  "material_name": "棉紗 30s/2",
  "hs_code": "5205.42",
  "qty": 0.3,
  "unit": "KG",
  "supplier_id": "uuid",
  "supplier_name": "台灣紡紗",
  "emission_per_unit": 3.2,
  "emission_source": "portal-self",
  "reported_period": "2025-Q4",
  "subtotal": 0.96,
  "data_quality": "measured",
  "is_estimated": false
}
```

`data_quality`（measured / declared / estimated）和 `emission_source` 是 ISO 14067 DQI 基礎，供未來介接使用。

---

### D7：AI 估算優先級

取值優先順序（高→低）：
1. 供應商自填（source=portal-self, is_estimated=false）最新版本
2. 買方代填（source=buyer-input, is_estimated=false）
3. AI 估算（source=ai-estimated, is_estimated=true）
4. 物料群組預設因子（MaterialGroup 層級，非個別供應商）
5. 行業平均（EmissionFactor by HS Code）

---

## Risks / Trade-offs

**[Risk] Celery 任務失敗導致 PCF 快照不更新**
→ Mitigation：任務失敗時記錄 error log，BuyerProduct 顯示「PCF 計算中」狀態而非舊值。前端加上「手動重算」按鈕作為緊急出口。

**[Risk] 同一 BomLine 被多個 BuyerProduct 共享（若未來有此設計）**
→ Mitigation：目前 ProductBomLine 是 BuyerProduct 私有的（buyer_product_id FK），無共享問題。未來若引入共用 BOM 需重評。

**[Risk] 供應商提報異常值（如 0.001 或 9999）立即影響 PCF**
→ Mitigation：後端 validation 設合理範圍（0.001 ~ 1000 kgCO₂e/unit），超出範圍自動標記 is_flagged，並通知採購商審查。

**[Risk] AI 估算結果品質差（EmissionFactor 覆蓋率不足）**
→ Mitigation：估算值標記 is_estimated=true 並在 UI 清楚標示，PCF 顯示 iso14067_ready=false 警告。不影響正式碳排申報。

## Migration Plan

1. 新增 migration：`material_item_emissions`、`pcf_snapshots` 兩張表
2. 部署後端 API（Laravel）：無 breaking change，純新增路由
3. 部署 AI 端點（FastAPI）：新增路由，不影響現有端點
4. 部署前端：MaterialItemsView、BuyerProductsView、PortalView 各自獨立功能，不影響現有頁面
5. 舊有 BomLine 資料不需遷移（PCF 快照將在首次 BomLine 變動時自動生成）

**Rollback**：前端 feature 可獨立回滾；後端新增表可安全保留，僅回滾 API 路由即可。

## Open Questions

- EmissionFactor 表目前涵蓋哪些 HS Code 前綴？覆蓋率是否足夠支援紡織業主要料號？（需查 esgchain-ai DB）
- reported_period 格式統一為「YYYY-QN」（如 2025-Q4）或允許自由文字（如「2025年第四季」）？建議統一格式以利排序。
