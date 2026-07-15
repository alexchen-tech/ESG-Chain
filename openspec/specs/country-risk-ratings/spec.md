## ADDED Requirements

### Requirement: 國家風險評等主檔

系統 SHALL 維護一張 `country_risk_ratings` 設定表，記錄各國在勞工、環境、地緣政治三個維度的風險等級（1–5，5 最高風險），以及地緣風險四個 L2 支柱的細分評等，供 `RiskAutoDerivationService` 在推導 impact 時查詢，並供 `ScoringJobDispatchService` 計算 E4 客觀分數。

欄位：
- `country_code` CHAR(2) UNIQUE（ISO 3166-1 alpha-2）
- `country_name` VARCHAR(100)
- `labor_risk` TINYINT 1–5（勞工人權風險，對應 S 維度 impact）
- `env_risk` TINYINT 1–5（環境監管寬鬆程度，對應 E 維度 impact）
- `geo_risk` TINYINT 1–5（地緣政治穩定性，對應 GP 維度 impact）
- `sub_scores` JSON nullable（地緣風險細分支柱，格式：`{ "political": 1–5, "environmental": 1–5, "social": 1–5, "regulatory": 1–5 }`）
- `source` VARCHAR(50)（`'manual'` | `'ITUC'` | `'WJP'`）

#### Scenario: 查詢存在的國家

- **WHEN** 系統以 `country_code = 'BD'` 查詢
- **THEN** SHALL 回傳該國三個維度的風險等級數值與 `sub_scores`

#### Scenario: 查詢不存在的國家

- **WHEN** 系統以未收錄的 `country_code` 查詢
- **THEN** SHALL 回傳 null，呼叫端使用 fallback 值 3

#### Scenario: sub_scores 存在時可計算 country_defense_score

- **WHEN** `country_risk_ratings` 記錄的 `sub_scores = { "political": 4, "environmental": 3, "social": 4, "regulatory": 3 }`
- **THEN** `country_risk_rating = (4+3+4+3)/4 = 3.5`，`country_defense_score = 100 − (3.5−1)/4×100 = 37.5`

#### Scenario: sub_scores 為 null 時使用 geo_risk fallback

- **WHEN** `country_risk_ratings.sub_scores IS NULL`
- **THEN** `ScoringJobDispatchService` SHALL 以 `geo_risk` 欄位值代替計算 `country_defense_score`

### Requirement: admin / sustain 可維護國家風險評等

`admin` 和 `sustain` 角色 SHALL 可透過 `GET/POST/PATCH/DELETE /api/v1/settings/country-risk-ratings` 端點對評等進行 CRUD 操作。其他角色 SHALL 收到 403。

#### Scenario: admin 更新國家評等

- **WHEN** admin 送出 `PATCH /api/v1/settings/country-risk-ratings/{id}` 含 `{ "labor_risk": 5 }`
- **THEN** 系統 SHALL 更新該記錄並回傳 200 及更新後的資料

#### Scenario: buyer 嘗試修改評等

- **WHEN** buyer 角色送出 PATCH 請求
- **THEN** 系統 SHALL 回傳 403

### Requirement: Settings 頁面提供國家風險評等管理介面

`/settings/country-risk` 子頁面 SHALL 顯示國家列表（country_code、country_name、三個維度數值、sub_scores 四欄、source），支援 Modal 編輯三個 risk 欄位與 sub_scores 四個支柱欄位，每頁顯示 20 筆。

#### Scenario: 顯示評等列表

- **WHEN** admin 進入 `/settings/country-risk`
- **THEN** 頁面 SHALL 顯示所有國家評等，含三個維度的數值標籤（1–5）及 sub_scores 四個支柱欄位（若有值）

#### Scenario: 編輯單筆評等

- **WHEN** admin 點選某國家列的「編輯」
- **THEN** SHALL 開啟 Modal 顯示可編輯的 labor_risk / env_risk / geo_risk 欄位（數字 select 1–5）以及 sub_scores 的 political / environmental / social / regulatory 欄位（數字 select 1–5，選填）
- **AND** 送出後 SHALL 立即刷新列表
