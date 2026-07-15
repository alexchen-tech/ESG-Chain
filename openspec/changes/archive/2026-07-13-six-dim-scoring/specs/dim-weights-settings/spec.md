## ADDED Requirements

### Requirement: 系統層級六維度預設加權儲存
`system_settings` 表（若不存在則本 change 建立）以 key = `dim_weight_defaults` 儲存全域預設六維度加權 JSON，格式為 `{"E1": float, "E2": float, ..., "E6": float}`，各值合計須為 1.0（容差 ±0.01）。系統 seed 插入預設值 `{"E1":0.25,"E2":0.15,"E3":0.20,"E4":0.15,"E5":0.10,"E6":0.15}`。

#### Scenario: 取得系統預設加權
- **WHEN** GET /api/v1/settings/dim-weight-defaults（admin token）
- **THEN** 回傳 `{ dim_weights: {"E1": 0.25, "E2": 0.15, "E3": 0.20, "E4": 0.15, "E5": 0.10, "E6": 0.15} }`

#### Scenario: 更新系統預設加權
- **WHEN** PUT /api/v1/settings/dim-weight-defaults，body `{ dim_weights: {"E1":0.30,...} }`，合計為 1.0
- **THEN** 回傳 200 並更新 system_settings 記錄
- **AND** 既有 assessment_series 的 dim_weights 不受影響（snapshot 設計）

#### Scenario: 合計驗證失敗
- **WHEN** PUT /api/v1/settings/dim-weight-defaults，傳入 dim_weights 各值合計不在 0.99–1.01 之間
- **THEN** 回傳 422，訊息「dim_weights 合計須等於 1.0」

#### Scenario: 非 admin 存取被拒
- **WHEN** 非 admin 角色呼叫 GET 或 PUT /api/v1/settings/dim-weight-defaults
- **THEN** 回傳 403

### Requirement: Settings UI「維度預設加權」Tab
Settings 頁新增 Tab「維度預設加權」，顯示 E1–E6 六個維度的加權百分比輸入欄位，admin 可編輯並儲存。

#### Scenario: 顯示當前系統預設加權
- **WHEN** admin 進入「維度預設加權」Tab
- **THEN** 顯示 E1–E6 各維度的中文名稱、當前加權百分比（%）
- **AND** 顯示合計百分比（應為 100%）

#### Scenario: 即時合計驗證
- **WHEN** 使用者修改任一維度數值
- **THEN** 即時更新合計顯示
- **AND** 合計不等於 100% 時，合計顯示紅色警示，儲存按鈕禁用

#### Scenario: 儲存成功提示
- **WHEN** 使用者點擊「儲存」且合計驗證通過
- **THEN** PUT 至 API，成功後顯示提示「已儲存，新建立的 Series 將使用此預設加權」
- **AND** 既有 Series 不受影響（畫面提示說明）

#### Scenario: 重設為等權
- **WHEN** 使用者點擊「重設為等權」
- **THEN** 六個維度各自填入 1/6 ≈ 16.67%（四捨五入至小數點後兩位）
- **AND** 合計顯示更新為 100%
