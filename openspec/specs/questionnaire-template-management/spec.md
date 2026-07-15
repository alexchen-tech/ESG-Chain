## MODIFIED Requirements

### Requirement: 問卷模板支援多維度模組組合
問卷模板（SAQ series / template）SHALL 支援以「核心模組 + 加掛模組」方式組合題集，而非固定一份題目列表。模板設定中可指定核心維度（E1/E4 強制）與可選維度（E2/E3/E5/E6），實際題集在發送時依供應商的 industry_group 和適用法規動態組合。

#### Scenario: 建立支援多維度的問卷系列
- **WHEN** 管理員建立新的 SAQ series，選擇「六維架構」模式
- **THEN** 系統預設核心維度為 E1/E4，其餘維度標記為「依產業自動加掛」，管理員可覆蓋指定特定維度的加掛行為

#### Scenario: 舊版固定題單模板仍可運作
- **WHEN** 既有的 SAQ series 使用舊版固定題單模式（無維度模組設定）
- **THEN** 系統維持舊版行為，問卷發送時不進行動態篩選，保障向下相容

### Requirement: 題集快照於發送時建立
問卷發送給特定供應商時，系統 SHALL 根據動態篩選結果建立該供應商專屬的題集快照（`project_questions`），記錄每道題與其對應的維度 tag 及計分權重。快照建立後，後續模板或題庫的變動均不影響已發出的問卷。

#### Scenario: 發送時建立快照
- **WHEN** 問卷 project 發送給供應商（狀態從 draft → sent）
- **THEN** 系統為該供應商建立 project_questions 快照，包含題目 ID、維度 tag、per-framework weight，以及來源模組標記（核心/加掛/E6動態）
