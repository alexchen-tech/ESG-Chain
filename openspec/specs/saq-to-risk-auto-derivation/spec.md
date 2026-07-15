## MODIFIED Requirements

### Requirement: SAQ 評分完成後自動建立 RiskAssessment

當 `scoreCallback()` 成功更新 SAQ 分數時，系統 SHALL 自動建立一筆 `risk_assessments` 記錄。

**Multi-framework 範本（scoring_framework = "multi-framework"）換算規則：**
- `axis1_score = 100 - iso26000_total`（ESG 暴露）
- `axis2_score = 100 - iso20400_total`（治理成熟度風險）
- `axis3_score`：不自動填入，設為 null
- `axis1_source_saq_id = saq_id`、`axis2_source_saq_id = saq_id`
- 同時保留舊換算：`{dim}_probability = max(1, ceil((100 - score_{dim}) / 20))`，`{dim}_impact = 3`（向後相容）
- `assessed_by = null`（系統自動）
- `notes = '自動從 SAQ {saq_id} 推導（multi-framework）'`

**舊框架範本（scoring_framework ≠ "multi-framework"）換算規則（維持不變）：**
- `{dim}_probability = max(1, ceil((100 - score_{dim}) / 20))`，`{dim}_impact = 3`
- axis1_score、axis2_score 不填入（null）

若 Multi-framework 計分結果的 iso26000_total 與 iso20400_total 均為 null，SHALL 跳過自動建立。

#### Scenario: Multi-framework SAQ 完成後自動建立三軸 RiskAssessment

- **WHEN** `scoreCallback()` 收到 `scoring_framework = "multi-framework"` 的結果，iso26000_total = 65，iso20400_total = 55
- **THEN** 系統 SHALL 建立 RiskAssessment，`axis1_score = 35`，`axis2_score = 45`，axis3_score = null，並設定 axis1/2_source_saq_id

#### Scenario: 舊框架 SAQ 僅更新 E/S/G probability

- **WHEN** `scoreCallback()` 收到 `scoring_framework = "esg"` 的結果，score_e = 80
- **THEN** 系統 SHALL 建立 RiskAssessment，e_probability = 4（舊公式），axis1_score/axis2_score 保持 null

#### Scenario: iso26000_total 與 iso20400_total 均為 null 時跳過

- **WHEN** Multi-framework 計分結果兩個框架總分均為 null（無對應 slug 題目）
- **THEN** 系統 SHALL 不建立 RiskAssessment，正常回傳評分結果
