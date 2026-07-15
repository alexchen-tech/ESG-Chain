## MODIFIED Requirements

### Requirement: RiskAssessment extreme 維度自動建立 CAP

當新的 `RiskAssessment` 建立（無論手動或自動推導）且任一維度 `probability × impact ≥ 20` 時，系統 SHALL 自動建立一筆 CAP，並對每個 extreme 維度建立對應的 CAPFinding。

同一筆 RiskAssessment（`source_id`）SHALL 不重複建立 CAP（冪等保護）。

此需求隨 `saq-to-risk-auto-derivation` 的 impact 動態化後，AI 自動推導的 RA 現在也可能觸發此規則（例如高風險國家 tier 1 供應商）。冪等保護邏輯不變。

#### Scenario: AI 自動推導 RA 觸發 extreme CAP

- **WHEN** `scoreCallback()` 觸發 `RiskAutoDerivationService` 建立 RA，且 S 維度 probability=4、impact=5（分數=20）
- **THEN** 系統 SHALL 透過 `RiskAssessmentObserver::created()` 自動建立一筆 CAP，title 含供應商名稱，priority='high'，status='open'
- **AND** SHALL 建立一筆 CAPFinding，category='S'，finding 說明分數與等級

#### Scenario: 同一 RA 不重複建立 CAP

- **WHEN** 系統嘗試對已存在 CAP 的 RiskAssessment（同 source_id）再次觸發 Observer
- **THEN** 系統 SHALL 跳過建立，不新增重複的 CAP

#### Scenario: 所有維度均未達 extreme 時不建立 CAP

- **WHEN** 新建 RA 的所有維度 probability × impact < 20
- **THEN** 系統 SHALL 不建立 CAP
