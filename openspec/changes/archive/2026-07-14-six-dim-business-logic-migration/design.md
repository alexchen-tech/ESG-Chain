## Context

六維評分模型（dim_e1–e6）已落地，但四個業務模組仍依賴舊的三軸彙整分數（axis1/axis2/axis3）作為觸發條件。三軸是在六維模型建立前為前端 ESG 概覽設計的中間層，現已成為業務精度的瓶頸。

**受影響模組與現行邏輯：**

| 模組 | 現行觸發條件 | 問題 |
|---|---|---|
| Dashboard KPI | `axis1/2/3 ≥ 60` | 折疊掩蓋維度極端不均 |
| CAP 自動生成 | `triggered_by_axis: axis1\|axis2\|axis3` | 觸發方向模糊，無法帶入精確矯正模板 |
| 供應商替代推薦 | `axis1_score > 被替換廠 axis1` | 僅比較單一彙整分，忽略維度結構 |
| 法規合規 `has_data_gap` | `axis1 === null` | 語意錯誤，環境軸 null ≠ 法規資料缺口 |

**axis1/2/3 計算：**
```
axis1(E) = 100 − avg(dim_e1, dim_e2)
axis2(S) = 100 − avg(dim_e3, dim_e4)
axis3(G) = 100 − avg(dim_e5, dim_e6)   ← E6 常為 null，行為不確定
```

## Goals / Non-Goals

**Goals:**
- Dashboard 高風險 KPI 改用六維各別閾值，能偵測維度內部極端值
- CAP 觸發欄位擴充為六維，觸發方向與矯正模板精確對應
- 供應商替代推薦加入六維加權分與最差維度硬性過濾
- 法規合規 `has_data_gap` 改用 E6 + regulations 直接語意判斷

**Non-Goals:**
- 移除 axis1/axis2/axis3 欄位（前端 SupplierController 仍消費，保留向下相容）
- 修改六維計分引擎本身（屬 six-dim-scoring change 範疇）
- 修改 series_dim_weights dead parameter（屬獨立技術債）

## Decisions

### D1：六維閾值定義方式 — 系統預設值 vs 可設定

**決定**：第一版使用系統預設閾值（hardcode 於 Service），並預留 `six_dim_risk_thresholds` 設定表擴充點，但本次不實作設定 UI。

**理由**：現有 seed 資料量不足以統計推論最佳閾值，先以保守值落地（合規分 ≤ 40 = 高風險），觀察實際分布後再調整。若直接做設定 UI，功能發佈前需要額外驗證週期。

**預設閾值（合規分，高=好）：**
```
E1 環境管理     ≤ 40 → 高風險
E2 氣候與碳排   ≤ 45 → 高風險（揭露要求較高）
E3 社會責任     ≤ 40 → 高風險
E4 地緣風險     ≤ 35 → 高風險（受客觀指標影響，寬容度較低）
E5 公司治理     ≤ 40 → 高風險
E6 法規準備     ≤ 50 → 高風險（僅在 regulations 非空時啟用）
```

---

### D2：CAP triggered_by_axis 欄位擴充方式

**決定**：`triggered_by_axis` 從 `ENUM('axis1','axis2','axis3')` 改為 `VARCHAR(20)`，接受 `dim_e1`–`dim_e6` 值，舊值（axis1/2/3）保留相容。

**理由**：ENUM 擴充需 ALTER TABLE，不如改 VARCHAR 靈活；舊有 CAP 記錄的 axis1/2/3 值仍合法，不需要 migration 回填。

---

### D3：供應商替代評分模型

**決定**：
```
candidate_score = total_score × 0.5
               + Σ(dim_eN × default_weight_N) / Σ(default_weight_N) × 0.5

硬性過濾：min(dim_e1..e5) ≥ 30（任一維度合規分極低者排除）
```

**理由**：total_score 已是加權彙整，再加六維二次加權會有重複計算問題，因此各佔 0.5。硬性過濾用 30 作為絕對底線（低於此分代表該維度幾乎零合規），避免總分高但某維度崩潰的供應商被推薦。

**未採用**：純用 total_score 排序（無法區分維度結構）；純用六維各別排名（缺少整體品質考量）。

---

### D4：法規合規 has_data_gap 新語意

**決定**：
```python
# 舊
has_data_gap = (axis1 is None)

# 新
has_data_gap = (dim_e6 is None) and (len(regulations) > 0)
```

`regulations = []` 表示無適用法規，應標記為 `not_applicable` 而非 `has_data_gap`。新增 `e6_status: 'ok' | 'gap' | 'not_applicable'` 欄位供前端區分。

## Risks / Trade-offs

**[風險] 六維閾值保守設定可能誤報高風險** → 初期以觀察為主，閾值調整不需 migration，修改 Service 常數即可。

**[風險] CAP triggered_by_axis 從 ENUM 改 VARCHAR 需 migration** → 向下相容，舊值（axis1/2/3）不需回填，risk 低。

**[風險] 供應商替代硬性過濾（min ≥ 30）可能造成候選池過小** → 若候選池為空，退化為僅用 total_score 排序並標記 `fallback: true`。

**[Trade-off] axis1/2/3 保留** → 計算邏輯不變，但業務觸發已遷移，未來維護需注意兩套邏輯並存；長期應廢棄三軸作為 summary-only 欄位。

## Migration Plan

1. DB migration：`cap_actions.triggered_by_axis` 從 ENUM 改 VARCHAR(20)
2. 部署 `CapAutoGenerationService` 新邏輯（dim_eN 觸發）
3. 部署 `DashboardService` 新邏輯（六維閾值 KPI）
4. 部署 `SupplierReplacementController` 新評分模型
5. 部署 `MarketComplianceChecker` 新 `has_data_gap` 邏輯
6. Rollback：各步驟獨立，可單獨 revert；DB migration 可 revert ENUM（舊值未被覆寫）

## Open Questions

- E6 dim 為 null 且 regulations 非空的供應商，MarketComplianceChecker 應給出警告還是直接標 gap？（目前設計標 gap）
- 六維閾值是否應存入 DB 並與 `dim-weights-settings` 設定頁合併管理？（本次不做，後續考慮）
