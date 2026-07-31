## ADDED Requirements

### Requirement: 法規範疇篩選審查
執行審查時，使用者 SHALL 可選定一個法規範疇（見 `MarketComplianceRule::PROGRAMS`），選定後系統 SHALL 只執行該範疇對應的檢查項目；未選定範疇時系統 SHALL 執行完整審查（涵蓋全部範疇），行為與既有未提供範疇篩選前完全相同。

#### Scenario: 選定 DPP 範疇
- **WHEN** 使用者對某批號選定 EU 市場、法規範疇為 DPP，並執行審查
- **THEN** 審查結果 SHALL 只包含市場文件規則（篩選為 DPP 範疇的規則）、批次 PCF、DPP 六大類別、電池 DPP（如適用）的檢查項目，不 SHALL 包含 EUDR 或 UFLPA 溯源檢查

#### Scenario: 選定 CBAM 範疇
- **WHEN** 使用者對某批號選定 EU 市場、法規範疇為 CBAM，並執行審查
- **THEN** 審查結果 SHALL 只包含市場文件規則（篩選為 CBAM 範疇的規則）與批次 PCF，不 SHALL 包含 EUDR/UFLPA/DPP 相關檢查項目

#### Scenario: 未選定範疇時執行完整審查
- **WHEN** 使用者執行審查時未選定法規範疇
- **THEN** 系統 SHALL 執行該市場全部適用的檢查項目，與未提供範疇篩選前的既有行為一致

#### Scenario: 審查結果顯示範疇標籤
- **WHEN** 使用者查看已完成的審查結果或 DDS 草稿
- **THEN** 系統 SHALL 顯示該筆審查是「完整審查」還是特定法規範疇，不得讓使用者誤以為範疇審查結果等同完整審查結果
