## ADDED Requirements

### Requirement: 市場合規規則主檔

系統 SHALL 維護一份 `market_compliance_rules` 資料表，記錄各目標市場所要求的文件義務類型（doc_type）。每筆規則包含：market（EU/US/NA/APAC/GB/JP）、doc_type、is_mandatory、effective_from、notes、is_active。

#### Scenario: 新增市場合規規則

- **WHEN** admin 在 `/settings/market-rules` 填寫 market + doc_type + effective_from 並送出
- **THEN** 系統建立規則記錄，UNIQUE(market, doc_type) 衝突時回傳 422

#### Scenario: 停用市場合規規則

- **WHEN** admin 將某規則 is_active 設為 false
- **THEN** 該規則不再參與 MarketComplianceChecker 計算，但保留歷史記錄

#### Scenario: 系統初始化種子資料

- **WHEN** 首次部署執行 seeder
- **THEN** 系統植入以下預設規則（is_active=true）：
  - EU × EUDR_DDS（effective_from 2025-01-17）
  - EU × CBAM_REPORT（effective_from 2026-01-01）
  - EU × ORIGIN_CERT（effective_from 2000-01-01）
  - US × UFLPA_DECLARATION（effective_from 2022-06-21）
  - US × ORIGIN_CERT（effective_from 2000-01-01）
  - US × CMRT（effective_from 2010-01-01）
  - APAC × ORIGIN_CERT（effective_from 2000-01-01）

### Requirement: 市場合規規則管理 API

系統 SHALL 提供 CRUD API `GET/POST/PATCH/DELETE /api/v1/market-compliance-rules`，僅限 admin 角色存取。

#### Scenario: 列表查詢

- **WHEN** GET /api/v1/market-compliance-rules?market=EU
- **THEN** 回傳該市場所有規則（含停用），支援 market 篩選參數

### Requirement: 市場合規規則管理 UI

系統 SHALL 在 `/settings/market-rules`（admin 角色）提供管理介面，包含：規則列表（依 market 分組顯示）、新增 Modal（market/doc_type/is_mandatory/effective_from/notes 欄位）、啟用/停用切換。

#### Scenario: 依市場分組顯示

- **WHEN** admin 開啟 /settings/market-rules
- **THEN** 規則依 market 分組（EU / US / APAC / 其他），每組顯示 doc_type、生效日、強制性、狀態

### Requirement: 法規合規資料缺口判斷改用 E6
`has_data_gap` 判斷 SHALL 從 `axis1 === null` 改為直接依 E6 與 regulations 狀態：

| dim_e6 | regulations | e6_status | has_data_gap |
|---|---|---|---|
| 非 null | 任意 | `ok` | false |
| null | 空 | `not_applicable` | false |
| null | 非空 | `gap` | true |
| ≤ 50（閾值） | 非空 | `low` | false（有資料但分數低） |

回應 SHALL 新增 `e6_status` 欄位（`ok` / `gap` / `not_applicable` / `low`），取代純布林 `has_data_gap`。

#### Scenario: E6 有資料，有法規
- **WHEN** 供應商 dim_e6 = 72，regulations = ["CBAM", "EUDR"]
- **THEN** 系統 SHALL 回傳 `e6_status: 'ok'`，`has_data_gap: false`

#### Scenario: E6 為 null，無適用法規
- **WHEN** 供應商 dim_e6 = null，regulations = []
- **THEN** 系統 SHALL 回傳 `e6_status: 'not_applicable'`，`has_data_gap: false`

#### Scenario: E6 為 null，有適用法規
- **WHEN** 供應商 dim_e6 = null，regulations = ["CBAM"]
- **THEN** 系統 SHALL 回傳 `e6_status: 'gap'`，`has_data_gap: true`

#### Scenario: E6 分數偏低但有資料
- **WHEN** 供應商 dim_e6 = 38（低於閾值 50），regulations = ["EUDR"]
- **THEN** 系統 SHALL 回傳 `e6_status: 'low'`，`has_data_gap: false`（有資料但需改善）
