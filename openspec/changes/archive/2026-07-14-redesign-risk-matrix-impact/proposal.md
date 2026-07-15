## Why

風險矩陣的 Impact 軸目前僅由 `suppliers.tier` 查表決定，存在三個結構性失真：

1. `suppliers.tier` 建表預設值為 1，未分級供應商一律 Tier1→Impact=5，導致多數供應商塌陷在最高衝擊列，Impact 幾乎失去鑑別力。
2. `tierToImpact()` 的 `default => 2` 使 Impact 永遠 ≥ 2，Impact=1 整欄成為死格，5×5 矩陣實際只用到 4 列，風險被系統性高估。
3. tier 只反映供應鏈層級，不等於「後果嚴重度」；已存在的 `spend_amount`、BOM 供應關係、產品法規適用等資訊完全未納入。

## What Changes

- **BREAKING**（矩陣落點）：Impact 軸由「單一 tier 查表」改為「四因子加權計分」，公式 `Impact = clamp(1..5, round(Σ wᵢ·sᵢ))`，任一因子缺值時該子分數 = 3（中性）。
  - tier（權重 0.30）：T1→5 / T2→3 / T3→1 / null→3
  - spend（權重 0.30）：依 `system_settings.impact_spend_thresholds` 固定門檻對應 5..1 / null→3
  - 單一來源依賴（權重 0.20）：任一 BOM line 僅此供應商→5 / 全多來源→2 / 無資料→3
  - 材料關鍵性（權重 0.20）：命中 UFLPA·EUDR→5 / CBAM→4 / 一般→2 / 無資料→3
- 計算邏輯放 **esgchain-ai**（不可寫在 esgchain-api），暴露 impact 計分端點。
- `suppliers` 新增 `impact_score` 作為正本，於 SAQ→風險評估、tier/spend 變動、BOM 供應關係變動時重算覆蓋。
- 風險評估建立當下，將當時 impact 快照複製到 `risk_assessments.impact_score`，供 heatmap `before_days` 歷史回溯用。
- esgchain-api `RiskMatrixController` 移除 `tierToImpact()`，`matrix()` 與 `matrixSuppliers()` 改讀存好的 `impact_score`；Probability 計算維持不變。
- `system_settings` 新增 `impact_spend_thresholds` 預設值（可調）。
- 前端風險矩陣頁若顯示 Impact 來源說明文字，一併更新為四因子。

## Capabilities

### New Capabilities
- `supplier-impact-scoring`: 供應商 Impact 分數（1–5）的四因子加權計算引擎、觸發重算條件、正本/快照儲存規則，由 esgchain-ai 計算。

### Modified Capabilities
- `risk-matrix`: Impact 軸來源由「Tier 查表」改為讀取 `impact_score`；原「Impact = Tier」的計算需求被取代。

## Impact

- **esgchain-ai**：新增 impact 計分服務與端點；讀取 supplier tier/spend、BomLineSupplier、SalesProduct 法規欄位。
- **esgchain-api**：
  - migration：`suppliers.impact_score`、`risk_assessments.impact_score`、`system_settings.impact_spend_thresholds` seed。
  - `RiskMatrixController::matrix()/matrixSuppliers()` 改讀 `impact_score`，移除 `tierToImpact()`。
  - 重算觸發點：SAQ→風險評估流程、Supplier `tier`/`spend_amount` 變動（含 ERP sync）、`BomLineSupplier` 變動。
- **欄位歸屬**：`impact_score` 屬 ESG-Chain 擁有，ERP sync 不可覆蓋；但 ERP 帶來的 tier/spend 變動需觸發我方重算。
- **esgchain-web**：風險矩陣頁 Impact 說明文字（若有）。
