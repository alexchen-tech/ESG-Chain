## ADDED Requirements

### Requirement: 計分模型管理頁面
系統 SHALL 提供 `/settings/scoring-models` 路由，渲染 `ScoringModelView.vue`，列出所有 ScoringModel（含通用 + 各 Industry 特定），管理員可新增、編輯、停用。

#### Scenario: 進入計分模型管理頁
- **WHEN** admin 導覽至 `/settings/scoring-models`
- **THEN** 顯示所有 scoring_models，含 name、sasb_industry_code（或「通用」）、weight_e/s/g、is_active 狀態

#### Scenario: 無計分模型時顯示引導
- **WHEN** PostgreSQL 的 scoring_models 為空
- **THEN** 顯示「尚無計分模型，系統將使用預設加權（E:40% / S:35% / G:25%）」及新增按鈕

### Requirement: 新增/編輯計分模型
Modal 表單 SHALL 包含：名稱、綁定 SASB Industry（下拉，選「通用」即 null）、E 權重%、S 權重%、G 權重%（合計需 = 100%，前端即時驗證）、SGS 四個閾值（A/B/C/D）、啟用狀態。

#### Scenario: 權重不等於 100% 時阻擋送出
- **WHEN** E+S+G ≠ 100
- **THEN** 儲存按鈕 disabled，顯示「三類權重合計須為 100%，目前為 XX%」

#### Scenario: 同 Industry 只能有一個 active 模型
- **WHEN** 管理員嘗試建立第二個 is_active=true 的 EM-IS 模型
- **THEN** 系統自動停用舊的（或回傳 422 提示衝突）

### Requirement: Laravel Proxy → FastAPI 的計分模型 API
Laravel SHALL 提供 `GET/POST/PUT/DELETE /api/v1/settings/scoring-models` 作為 proxy，實際資料存取在 FastAPI 的 PostgreSQL。

#### Scenario: 讀取計分模型列表
- **WHEN** `GET /api/v1/settings/scoring-models`
- **THEN** Laravel 轉發至 FastAPI `GET /ai/v1/scoring-models`，回傳結果給前端

#### Scenario: 建立計分模型
- **WHEN** `POST /api/v1/settings/scoring-models` 帶合法 payload
- **THEN** Laravel 驗證後轉發至 FastAPI，建立成功回傳 201

### Requirement: FastAPI ScoringModel CRUD API
FastAPI SHALL 新增 `GET/POST/PUT/DELETE /ai/v1/scoring-models` endpoints，操作 PostgreSQL 的 scoring_models 表。

#### Scenario: 依 industry_code 查詢
- **WHEN** `GET /ai/v1/scoring-models?industry_code=EM-IS`
- **THEN** 回傳 sasb_industry_code="EM-IS" 的所有模型
