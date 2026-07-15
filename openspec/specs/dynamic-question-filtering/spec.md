## ADDED Requirements

### Requirement: E6 適用題動態篩選
問卷發送前，系統 SHALL 依供應商的 `SalesProduct.applicable_regulations ∪ inferred_regulations` 集合，從題庫中篩選出「與這些法規相關」的 E6 標記題目，組成該供應商專屬的 E6 題集。

#### Scenario: CBAM 適用供應商只看 CBAM 相關題
- **WHEN** 供應商的 SalesProduct 僅適用 CBAM，發送問卷
- **THEN** E6 題集只包含 tag pillar 為 `cbam` 的題目，不含 EUDR / UFLPA 題目

#### Scenario: 多法規適用時題集取聯集
- **WHEN** 供應商同時適用 CBAM + EUDR
- **THEN** E6 題集為 CBAM 題 ∪ EUDR 題（去重後合併）

#### Scenario: 篩選結果固化於 project_questions
- **WHEN** 系統完成動態篩題並發送問卷
- **THEN** 篩選後的題集快照寫入 `project_questions`，後續 SalesProduct 法規變動不影響已發出的問卷

### Requirement: 題集版本一致性
同一 SAQ project 內，所有供應商的 E1/E4 核心題目 SHALL 完全相同（保障可比性）；E2/E3/E5 加掛模組題在同一 industry_group 內完全相同；E6 題集因供應商而異但快照後不再變動。

#### Scenario: 同 project 跨供應商 E1 題一致
- **WHEN** 同一 project 發送給 5 家製造業供應商
- **THEN** 5 家的 E1/E4/E2/E5 題目完全相同，可直接比較這四個維度分數

#### Scenario: 同 project 跨產業 E6 題不同
- **WHEN** 同一 project 同時發送給農林漁業（適用 EUDR）與電子業（適用 RoHS）供應商
- **THEN** 兩者的 E6 題集不同，各自計分，E1/E4 仍可互比
