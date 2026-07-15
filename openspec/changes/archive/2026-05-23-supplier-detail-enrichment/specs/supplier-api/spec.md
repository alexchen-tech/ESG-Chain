## ADDED Requirements

### Requirement: show() eager load sasbIndustry
`GET /api/v1/suppliers/{id}` SHALL 在回應中包含 `sasb_industry` 關聯物件（`{ id, code, sector, industry }`）。

#### Scenario: 有 SASB 分類的供應商
- **WHEN** supplier.sasb_industry_id 非 null
- **THEN** 回應包含 `sasb_industry: { id, code, sector, industry }`

#### Scenario: 無 SASB 分類的供應商
- **WHEN** supplier.sasb_industry_id 為 null
- **THEN** 回應包含 `sasb_industry: null`

## MODIFIED Requirements

### Requirement: update() 欄位驗證正確性
`PUT /api/v1/suppliers/{id}` 的驗證規則 SHALL 使用 `country_code`（非 `country`），並包含 `sasb_industry_id` 的 nullable uuid exists 驗證。

#### Scenario: 更新 country_code
- **WHEN** 提交 `{ country_code: "JP" }`
- **THEN** 系統接受並更新，回傳 200

#### Scenario: 更新 sasb_industry_id
- **WHEN** 提交有效的 `sasb_industry_id` UUID
- **THEN** 系統接受並更新，回傳 200 含 sasb_industry 關聯

#### Scenario: 提交舊欄位名 country（已廢棄）
- **WHEN** 提交 `{ country: "JP" }`
- **THEN** 系統忽略此欄位（不更新 country_code）
