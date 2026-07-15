## MODIFIED Requirements

### Requirement: CAP 觸發維度欄位擴充為六維
`triggered_by_axis` 欄位 SHALL 從三值（axis1/axis2/axis3）擴充為接受六維識別碼（dim_e1–dim_e6），並保留舊值相容。觸發判斷 SHALL 改用 dim_eN 合規分低於六維閾值的條件。

#### Scenario: dim_e2 低於閾值自動產生 CAP
- **WHEN** SAQ 計分完成後 dim_e2 = 35（低於 E2 閾值 45）
- **THEN** 系統 SHALL 自動建立 CAP，`triggered_by_axis = 'dim_e2'`
- **THEN** CAP 標題 SHALL 帶入「氣候與碳排」維度對應的矯正模板

#### Scenario: 多維度低於閾值產生多筆 CAP
- **WHEN** dim_e2 = 35 且 dim_e5 = 38（兩維均低於閾值）
- **THEN** 系統 SHALL 分別建立兩筆 CAP，各自記錄 `triggered_by_axis`

#### Scenario: 舊有 axis1/axis2/axis3 值向下相容
- **WHEN** 查詢歷史 CAP 記錄中 `triggered_by_axis = 'axis1'`
- **THEN** 系統 SHALL 正常返回該記錄，不報錯（舊值不需回填）

## ADDED Requirements

### Requirement: 六維矯正模板對應
系統 SHALL 維護六個維度對應的 CAP 矯正方向模板，在自動建立 CAP 時帶入：

| triggered_by_axis | 矯正方向提示 |
|---|---|
| dim_e1 | 環境管理系統建立或認證（ISO 14001） |
| dim_e2 | 碳排放量化與揭露計畫 |
| dim_e3 | 勞工人權與社會責任改善 |
| dim_e4 | 地緣政治風險應對與備援規劃 |
| dim_e5 | 公司治理透明度與反腐措施 |
| dim_e6 | 適用法規合規準備（CBAM/EUDR 等） |

#### Scenario: 帶入對應矯正模板
- **WHEN** 系統以 `triggered_by_axis = 'dim_e2'` 建立 CAP
- **THEN** CAP `suggested_actions` 欄位 SHALL 預填 E2 對應的矯正方向提示文字
