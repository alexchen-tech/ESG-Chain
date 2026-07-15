## MODIFIED Requirements

### Requirement: 矩陣維度 Tab 改為 E1–E5 + 綜合最差

風險矩陣 SHALL 提供 E1–E5 五個單一維度 Tab 與「綜合最差」Tab。各 Tab 的 Probability 依所選維度（或綜合最差維度）分數推算；Impact 一律取供應商當前 `impact_score`（四因子加權計分結果），不再由 Tier 查表決定。供應商 `impact_score` 為 null 時，Impact 以中性值 3 落點。

#### Scenario: 使用者切換維度 Tab
- **WHEN** 使用者在風險矩陣頁點擊維度 Tab
- **THEN** 可用的 Tab 為：綜合最差、E1 環境管理、E2 氣候與碳排、E3 社會責任、E4 地緣風險、E5 公司治理
- **AND** 預設顯示「綜合最差」Tab

#### Scenario: 單一維度 Tab 的矩陣計算
- **WHEN** 使用者選擇 E1–E5 任一維度 Tab
- **THEN** Probability = `max(1, min(5, ceil((100 − dim_eX) / 20)))`
- **AND** Impact = 供應商當前 `impact_score`（四因子加權計分結果，由 esgchain-ai 計算並存於 `suppliers.impact_score`）

#### Scenario: 綜合最差 Tab 的矩陣計算
- **WHEN** 使用者選擇「綜合最差」Tab
- **THEN** 每個供應商的 Probability = `max(1, min(5, ceil((100 − LEAST(dim_e1..dim_e5)) / 20)))`
- **AND** 使用各供應商 E1–E5 中最低分的維度分數推算 P
- **AND** Impact 與維度無關，一律取供應商當前 `impact_score`

#### Scenario: 供應商尚無 impact_score
- **WHEN** 某供應商 `impact_score` 為 null（尚未計算）
- **THEN** 矩陣以 Impact = 3 落點（中性），與 esgchain-ai 缺值中性規則一致
