## ADDED Requirements

### Requirement: AVL 匯入後觸發碳排缺口掃描
系統 SHALL 在 AVL 匯入建立新的 `BomLineSupplier`（role = primary）後，dispatch 缺口掃描 job，對新建立的 (material_item × supplier) 組合執行缺口偵測（參照 pcf-emission-gap-scan spec）。

#### Scenario: AVL 匯入建立新 primary supplier 後掃描
- **WHEN** AVL CSV 為 BomLine(M1) 新增 primary supplier S2
- **THEN** 系統 SHALL 在匯入完成後 dispatch 缺口掃描，若 (M1, S2) 無 MaterialItemEmission，建立 PcfRequest

#### Scenario: AVL 匯入只更新 alternate supplier 時不觸發掃描
- **WHEN** AVL CSV 異動的是 alternate supplier，primary 不變
- **THEN** 系統 SHALL 不 dispatch 缺口掃描 job
