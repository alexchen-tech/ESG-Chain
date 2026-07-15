## MODIFIED Requirements

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
