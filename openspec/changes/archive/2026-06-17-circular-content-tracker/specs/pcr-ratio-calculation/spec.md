## ADDED Requirements

### Requirement: 產品 PCR 比率計算

`PcrCalculationService::calcForProduct(BuyerProduct)` 依 BOM 計算產品層級加權再生材料含量比率。

**公式：** $R_{PCR} = \frac{\sum(W_i \times P_i)}{W_{Total}}$

其中 $W_i = \text{BomLine}_i.\text{quantity} \times \text{MaterialItem}_i.\text{net\_weight}$，$P_i = \text{MaterialItem}_i.\text{pcr\_percentage} / 100$。

`net_weight` 或 `pcr_percentage` 為 null 的 BomLine 排除於分子分母之外，並記錄於 `incomplete_lines` 計數。

計算結果（`pcr_ratio`、`incomplete_lines`）存入 `PcfSnapshot` 新欄位，不另存表。

#### Scenario: 所有 BomLine 均有 net_weight 與 pcr_percentage

- **WHEN** `PcrCalculationService::calcForProduct(product)` 執行，所有 BomLine 的 MaterialItem 均有 net_weight 與 pcr_percentage
- **THEN** 回傳 `{ pcr_ratio: 0.xx, incomplete_lines: 0 }`，`pcr_ratio` 為 0.00–1.00 之間的小數

#### Scenario: 部分 BomLine 缺少 net_weight

- **WHEN** 部分 MaterialItem.net_weight 為 null
- **THEN** 這些 BomLine 從計算中排除，`incomplete_lines` 計入缺漏數，`pcr_ratio` 以完整資料計算並標記非完整

#### Scenario: 所有 BomLine 缺少 net_weight（無法計算）

- **WHEN** 所有 MaterialItem.net_weight 均為 null
- **THEN** 回傳 `{ pcr_ratio: null, incomplete_lines: N }`

## MODIFIED Requirements

### Requirement: DPP 就緒度評分新增 PCR 維度

現有 DPP 就緒度評分框架（`espr-dpp-readiness`）SHALL 新增 `pcr` 維度，評估再生材料含量資料完整度。

評分邏輯：
- primary supplier 有 GRS 文件（status=valid）且 MaterialItem.pcr_percentage > 0 的 BomLine 比率 ≥ 80% → pcr 維度得分

#### Scenario: PCR 資料充足

- **WHEN** ≥ 80% 的 BomLine 其 primary supplier 持有效 GRS 文件且 pcr_percentage 已填寫
- **THEN** DPP 就緒度評分 pcr 維度計入，整體評分提升

#### Scenario: PCR 資料不足

- **WHEN** < 80% 的 BomLine 滿足 GRS + pcr_percentage 條件
- **THEN** pcr 維度顯示「待補齊」，提示需填寫 net_weight / pcr_percentage 或取得 GRS 認證
