# Spec: saq-template-framework

## 定義

`saq_templates` 宣告其計分框架（`scoring_framework`），作為從範本建立的所有系列的計分依據。計分框架決定：哪個 TAG L1 domain 的 slug 進入計分、L2 pillar 作為計分維度分組。

## scoring_framework 枚舉值

| 值 | L1 domain 過濾前綴 | L2 pillar 維度 |
|---|---|---|
| `ESG` | `esg.*` | 環境管理 / 勞工人權 / 職場安全 / 公司治理 / 社區與消費者 / 供應鏈環境 |
| `ISO20400` | `iso20400.*` | 採購政策 / 績效評估 / 風險管理 |
| `ISO26000` | `iso26000.*` | 組織治理 / 人權 / 勞工 / 環境 / 公平營運 / 消費者 / 社區 |
| `Geo-Risk` | `geo_risk.*` | 地緣政治風險 / 物流天災 |
| `Product-Compliance` | `product_compliance.*` | CBAM / EUDR / 化學法規 / 溯源與認證 |
| `NULL` | （不過濾，全 slug 參與） | 無 pillar 分組，只輸出總分 |

## 行為規則

### 範本建立 / 編輯

- 管理員建立或編輯草稿範本時，可設定 `scoring_framework`（選填，預設 NULL）
- 範本發佈後（status = published），`scoring_framework` 禁止修改
- 修改已發佈範本的 `scoring_framework` 回應 422：「已發佈範本的計分框架不可修改，需克隆後重新設定」

### TAG 覆蓋率驗收

- 範本 `scoring_framework` 不為 NULL 時，系統計算框架 TAG 覆蓋率：`有框架 TAG 的題數 / 總題數`
- 覆蓋率 < 100% 時，管理員 UI 顯示警告 badge 與缺口題清單，但**不阻擋發佈**
- 覆蓋率 = 100% 時顯示「TAG 覆蓋完整 ✓」狀態

### 計分框架繼承

- 從範本建立 SaqProject 時，`project.scoring_framework` 自動繼承自 `template.scoring_framework`（唯讀，不可在 project 層覆寫）
- esgchain-api 組裝計分 payload 時，讀取 `project.template.scoring_framework` 帶入 `scoring_framework` 欄位

## UI 驗收條件

- [ ] 範本設定頁新增「計分框架」select 欄位（草稿狀態可編輯，發佈後唯讀顯示）
- [ ] 範本題目列表顯示各題框架 TAG 覆蓋狀態（✓ 已標 / ⚠ 缺口）
- [ ] 覆蓋率 < 100% 時顯示 warning banner：「X 道題缺少 {framework} TAG，將影響計分完整性」，附缺口題清單
- [ ] 系列列表顯示範本的 scoring_framework badge（ESG 綠 / ISO20400 藍 / Geo-Risk 橙 / 通用 灰）
