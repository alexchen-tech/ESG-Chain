## ADDED Requirements

### Requirement: RiskAssessment extreme 維度自動觸發 CAP

每當一筆新的 `risk_assessments` 記錄被建立（無論手動或自動），系統 SHALL 檢查所有有值的維度（E/S/G/GP）的 `cell_score = probability × impact`。若任一維度 cell_score ≥ 20（extreme），且該筆 RiskAssessment 尚未有對應 CAP，系統 SHALL 自動建立一筆 CAP。

CAP 欄位規格：
- `supplier_id`：來自 RiskAssessment.supplier_id
- `source_type = 'risk_assessment'`
- `source_id = risk_assessment.id`
- `title = '風險評估 Extreme 警示：{supplier.name}'`
- `priority = 'high'`
- `status = 'open'`
- `due_date = assessed_at + 30 天`
- `created_by = null`（系統自動）

每個 extreme 維度 SHALL 建立一筆對應 CAPFinding：
- `category = '{DIM}'`（如 'E'、'S'、'G'、'GP'）
- `finding = '{DIM} 維度風險評分 {cell_score}，已達 Extreme 等級（p={probability} × i={impact}）'`
- `status = 'open'`

#### Scenario: 單一維度 extreme 自動開 CAP

- **WHEN** 新建 RiskAssessment 且 e_probability × e_impact = 20（如 p=4, i=5）
- **THEN** 系統 SHALL 自動建立一筆 CAP，含一筆 CAPFinding（category='E'）

#### Scenario: 多維度 extreme 建立多筆 Finding

- **WHEN** 新建 RiskAssessment 且 E 維度 cell_score=20、G 維度 cell_score=25
- **THEN** 系統 SHALL 建立一筆 CAP，含兩筆 CAPFinding（category='E' 與 'G'）

#### Scenario: 無 extreme 維度不開 CAP

- **WHEN** 新建 RiskAssessment 且所有有值維度 cell_score < 20
- **THEN** 系統 SHALL 不自動建立 CAP

#### Scenario: 同一 RiskAssessment 防止重複開 CAP

- **WHEN** 對同一 RiskAssessment ID 已存在 source_type='risk_assessment' 的 CAP
- **THEN** 系統 SHALL 跳過 CAP 建立，不重複開立

#### Scenario: GP 維度無值時跳過 GP 判斷

- **WHEN** RiskAssessment 的 gp_probability 或 gp_impact 為 null
- **THEN** GP 維度 SHALL 不計入 extreme 判斷
