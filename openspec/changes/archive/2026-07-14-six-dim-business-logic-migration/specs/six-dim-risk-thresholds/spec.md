## ADDED Requirements

### Requirement: 六維度各別風險閾值定義
系統 SHALL 維護六個維度的獨立風險閾值（合規分下限），任一維度低於其閾值即將供應商標記為高風險。閾值以合規分（0–100，高=好）定義。

預設閾值：
- E1 環境管理：≤ 40
- E2 氣候與碳排：≤ 45
- E3 社會責任：≤ 40
- E4 地緣風險：≤ 35
- E5 公司治理：≤ 40
- E6 法規準備：≤ 50（僅在 regulations 非空時啟用）

#### Scenario: 單維度低於閾值觸發高風險
- **WHEN** 供應商 dim_e2 = 30（低於閾值 45）且其他五維均超過閾值
- **THEN** 系統 SHALL 將該供應商標記為高風險，並回傳 `risk_dims: ["E2"]`

#### Scenario: 多維度同時低於閾值
- **WHEN** 供應商 dim_e2 = 30 且 dim_e5 = 35
- **THEN** 系統 SHALL 回傳 `risk_dims: ["E2", "E5"]`，兩個風險維度均被記錄

#### Scenario: E6 null 且無法規時不觸發
- **WHEN** 供應商 dim_e6 = null 且 regulations = []
- **THEN** 系統 SHALL NOT 將 E6 列入 risk_dims（無適用法規，E6 不參與判斷）

#### Scenario: E6 null 且有法規時觸發
- **WHEN** 供應商 dim_e6 = null 且 regulations 非空
- **THEN** 系統 SHALL 將 E6 列入 risk_dims（法規存在但無準備資料）

### Requirement: Dashboard 高風險供應商 KPI 改用六維閾值
Dashboard 高風險供應商計數 SHALL 改用六維各別閾值判定，取代原本的 `axis1/2/3 ≥ 60` 邏輯。

#### Scenario: Dashboard KPI 統計
- **WHEN** 系統計算高風險供應商數量
- **THEN** 系統 SHALL 計算最新 risk_assessment 中任一 dim_eN 低於對應閾值的供應商數量
- **THEN** 回傳結果 SHALL 與舊三軸邏輯分開計算（不混用）
