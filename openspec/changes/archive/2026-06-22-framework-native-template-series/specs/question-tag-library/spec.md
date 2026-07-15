## ADDED Requirements

### Requirement: framework_pillar 欄位於範本題目

系統 SHALL 在 `saq_questions`（範本題，非 bank question）新增 `framework_pillar VARCHAR(100) NULL` 欄位，建立時從第一個符合範本 `scoring_framework` 的 TAG l2_pillar 值快照，後續不更新。

#### Scenario: 自動快照 framework_pillar
- **WHEN** Admin 將題目加入 scoring_framework = 'ESG' 的範本，且題目有 TAG `l1_domain='ESG', l2_pillar='environment'`
- **THEN** `saq_questions.framework_pillar` SHALL 自動設為 `'environment'`，不依賴後續 TAG 查詢

#### Scenario: 從 bank question 匯入時繼承 framework_pillar
- **WHEN** 從 bank question 匯入題目至範本
- **THEN** 系統 SHALL 依 bank question 的 TAG 指派計算 framework_pillar，並寫入新建的範本題 record

### Requirement: ISO26000 TAG 覆蓋補齊

系統 SHALL 確保 ISO26000 框架的 bank questions 均有至少一個 `l1_domain = 'ISO26000'` 的 TAG 指派。目前缺口：現有 ISO26000 bank questions 中，約 12 道缺少 `iso26k.*` TAG。

#### Scenario: ISO26000 範本題匯入時不被 TRIGGER 拒絕
- **WHEN** Admin 嘗試將 ISO26000 bank question 匯入 `scoring_framework = 'ISO26000'` 的範本
- **THEN** 系統 SHALL 允許操作（所有 bank questions 已補齊 TAG）

### Requirement: Geo-Risk TAG 覆蓋補齊

系統 SHALL 確保 Geo-Risk 框架的 bank questions 均有至少一個 `l1_domain = 'Geo-Risk'` 的 TAG 指派（ISO28000 子集和 GPR 子集）。目前缺口：ISO28000 約缺 25 道、GPR 約缺 20 道。

#### Scenario: Geo-Risk 範本題匯入時不被 TRIGGER 拒絕
- **WHEN** Admin 嘗試將 Geo-Risk bank question 匯入 `scoring_framework = 'Geo-Risk'` 的範本
- **THEN** 系統 SHALL 允許操作（所有 bank questions 已補齊 TAG）
