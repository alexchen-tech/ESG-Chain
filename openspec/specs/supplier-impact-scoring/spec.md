## ADDED Requirements

### Requirement: 供應商 Impact 四因子加權計分

系統 SHALL 由 esgchain-ai 計算每個供應商的 Impact 分數（整數 1–5），採四因子加權：`Impact = clamp(1..5, round(Σ wᵢ·sᵢ))`。計算邏輯 MUST NOT 寫在 esgchain-api。任一因子缺乏資料時，該因子子分數 MUST 取 3（中性）。

四因子子分數（各 1–5）與權重：

| 因子 | 權重 | 子分數對應 | 資料來源 |
|------|------|-----------|----------|
| tier | 0.30 | T1→5 / T2→3 / T3→1 / T4→1 / null→3 | `suppliers.tier` |
| spend | 0.30 | 依固定門檻對應 5..1 / null→3 | `suppliers.spend_amount` |
| 單一來源依賴 | 0.20 | 任一 BOM line 僅此供應商→5 / 全多來源→2 / 無資料→3 | `BomLineSupplier` |
| 材料關鍵性 | 0.20 | UFLPA·EUDR→5 / CBAM→4 / 一般→2 / 無資料→3 | `SalesProduct.applicable_regulations`/`inferred_regulations` |

#### Scenario: 四因子皆有資料時計分
- **WHEN** esgchain-ai 為某供應商計算 Impact，tier=1（→5）、spend 命中 s4 門檻（→4）、有單一來源 BOM line（→5）、材料僅命中 CBAM（→4）
- **THEN** 加權和 = 5×0.30 + 4×0.30 + 5×0.20 + 4×0.20 = 4.5
- **AND** `impact_score` = round(4.5) = 5（clamp 於 1–5）

#### Scenario: 部分因子缺資料以中性 3 代入
- **WHEN** 供應商 tier=null、spend_amount=null、無任何 BOM 供應關係、無適用法規產品
- **THEN** 四因子子分數皆為 3
- **AND** `impact_score` = round(3×0.30 + 3×0.30 + 3×0.20 + 3×0.20) = 3

#### Scenario: 結果恆落於 1–5 整數
- **WHEN** 任意因子組合計算完成
- **THEN** `impact_score` MUST 為介於 1 至 5 的整數（四捨五入後 clamp）

### Requirement: spend 因子採固定門檻

spend 子分數 SHALL 依 `system_settings` 中 key 為 `impact_spend_thresholds` 的固定門檻對應，不採即時分位數（確保可稽核、不隨母體漂移）。預設門檻為 s5≥10,000,000 / s4≥3,000,000 / s3≥1,000,000 / s2≥300,000 / s1<300,000，並可於設定調整。

#### Scenario: 依固定門檻對應 spend 子分數
- **WHEN** 供應商 `spend_amount` = 3,500,000 且門檻為預設值
- **THEN** spend 子分數 = 4（≥3,000,000 且 <10,000,000）

#### Scenario: spend 缺值取中性
- **WHEN** 供應商 `spend_amount` 為 null
- **THEN** spend 子分數 = 3

### Requirement: Impact 正本與快照儲存

`impact_score` 正本 SHALL 存於 `suppliers.impact_score`，由 esgchain-ai 於觸發時重算覆蓋。建立風險評估（RiskAssessment）時，系統 SHALL 將當下的 `impact_score` 快照複製到 `risk_assessments.impact_score`，作為 point-in-time 稽核與 heatmap `before_days` 歷史回溯依據。`impact_score` 屬 ESG-Chain 擁有，ERP sync MUST NOT 覆蓋。

#### Scenario: 評估建立時快照 impact
- **WHEN** SAQ→風險評估流程建立一筆新的 RiskAssessment
- **THEN** 該筆 `risk_assessments.impact_score` = 當下 `suppliers.impact_score`
- **AND** 後續 supplier 屬性變動不回頭修改此歷史快照

#### Scenario: ERP sync 不覆蓋 impact_score
- **WHEN** ERP sync 更新供應商主檔
- **THEN** `suppliers.impact_score` MUST NOT 被 sync 直接覆蓋（僅透過重算流程更新）

### Requirement: Impact 重算觸發條件

系統 SHALL 於下列任一事件發生時，重算並覆蓋 `suppliers.impact_score`：
1. SAQ→風險評估流程建立/更新 RiskAssessment 時。
2. 供應商 `tier` 或 `spend_amount` 變動時（含 ERP sync 帶來的變動）。
3. 供應商相關 `BomLineSupplier`（BOM 供應關係）新增、刪除或角色變動時。

#### Scenario: tier 變動觸發重算
- **WHEN** 某供應商 `tier` 由 3 變更為 1（含 ERP sync）
- **THEN** 系統重算該供應商 `impact_score` 並覆蓋正本

#### Scenario: BOM 供應關係變動觸發重算
- **WHEN** 某 BOM line 原有 2 家供應商，其中 1 家被移除，使某供應商成為單一來源
- **THEN** 系統重算受影響供應商的 `impact_score`

#### Scenario: 評估流程重算
- **WHEN** SAQ→風險評估流程建立新的 RiskAssessment
- **THEN** 系統於建立前重算 `suppliers.impact_score`，再以最新值寫入快照
