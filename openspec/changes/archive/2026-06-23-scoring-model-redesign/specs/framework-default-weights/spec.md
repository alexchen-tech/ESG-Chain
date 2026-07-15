## ADDED Requirements

### Requirement: 框架預設 Pillar 加權資料表
`framework_default_weights` 表儲存各框架所有 pillar 的預設加權比例，供 Series 計分設定的初始值使用。

#### Scenario: 各框架必須有完整 pillar 清單
- **WHEN** 系統初始化（seed）
- **THEN** ESG / ISO20400 / ISO26000 / Geo-Risk 四個框架各自有完整的 pillar slug 與預設比例
- **AND** 同一框架的所有 pillar weight 合計為 1.0

#### Scenario: Unique 約束
- **WHEN** 嘗試新增重複的 `(scoring_framework, pillar_slug)` 組合
- **THEN** 回傳 422，不允許重複

### Requirement: 框架預設加權 API
提供管理框架預設加權的 CRUD API，僅 admin 可存取。

#### Scenario: 取得所有框架預設加權
- **WHEN** GET /api/v1/settings/framework-default-weights
- **THEN** 回傳按框架分組的 pillar 加權清單 `{ ESG: [{slug, label_zh, weight}], ISO20400: [...], ... }`

#### Scenario: 更新框架加權
- **WHEN** PUT /api/v1/settings/framework-default-weights/{framework}，傳入 `weights: [{pillar_slug, weight}]`
- **THEN** 驗證合計為 1.0（容差 ±0.01），通過後批次更新

#### Scenario: 合計驗證失敗
- **WHEN** PUT 傳入的 weights 合計不在 0.99–1.01 之間
- **THEN** 回傳 422，說明「合計須等於 1.0」

### Requirement: 前端框架預設加權管理 UI
`ScoringModelView.vue` 區塊 1 顯示並允許編輯各框架的 pillar 加權。

#### Scenario: 顯示所有框架加權
- **WHEN** 進入計分模型管理頁
- **THEN** 顯示四個框架各自的 pillar 加權（accordion 或 tab 形式），含進度條視覺與合計檢查

#### Scenario: 儲存框架加權
- **WHEN** 使用者編輯加權並點擊「儲存」
- **THEN** PUT 至 API，成功後顯示提示「已儲存，新 Series 將使用此預設值」
