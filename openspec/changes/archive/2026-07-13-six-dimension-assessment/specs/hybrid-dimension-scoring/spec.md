## ADDED Requirements

### Requirement: E4 地緣風險混合計分
E4 維度分數 SHALL 由兩個來源混合計算：外部暴露分（country_risk_ratings）佔 40%，問卷管理成熟度分（E4標記題目）佔 60%。

#### Scenario: 高風險地區但管理成熟
- **WHEN** 供應商主要廠址位於 geo_risk=5 國家，但 E4 問卷題全部回答符合最佳實踐
- **THEN** E4 分數約為 (5/5×100×0.4) + (100×0.6) = 100，不會因地區因素拉低至極低分

#### Scenario: 多廠址取最高風險
- **WHEN** 供應商有三個廠址分別在 geo_risk=2/3/5 的國家
- **THEN** E4 暴露分以 geo_risk=5 計算（保守原則，取最高風險）

#### Scenario: 無 country_risk_ratings 記錄時的 fallback
- **WHEN** 供應商 country_code 在 country_risk_ratings 中無對應記錄
- **THEN** geo_risk fallback 為 3（中等），並寫 log 記錄缺漏

### Requirement: E6 產品合規混合計分
E6 維度分數 SHALL 反映供應商的合規準備成熟度相對於其面臨的法規壓力。法規壓力指數由 `SalesProduct.applicable_regulations ∪ inferred_regulations` 計算；準備成熟度由動態篩選後的 E6 問卷題目得分計算。

#### Scenario: 無適用法規時 E6 為 null
- **WHEN** 供應商的所有 SalesProduct 均無 applicable_regulations 且無 inferred_regulations
- **THEN** E6 維度為 null，不納入計分，問卷不包含 E6 題目

#### Scenario: 高法規壓力低準備度拉低分數
- **WHEN** 供應商面臨 CBAM + EUDR + UFLPA 三重法規，但問卷僅回答「否」（無準備）
- **THEN** E6 分數極低（接近 0），反映合規缺口大

#### Scenario: E6 分數上限為 100
- **WHEN** 供應商已完整準備所有適用法規的合規文件
- **THEN** E6 分數為 100，不因法規數量多而超過上限
