# Spec: saq-project-domain

## 定義

SaqProject 具備 `domain` 屬性，代表此問卷調查專案所採用的評核框架。

`domain` 是計分語意的決策點（語意 C）：esgchain-ai 依 `project_domain` 過濾 slug prefix，只計算對應框架的標籤得分，避免同一道題因疊加跨域標籤而雙重計分。

## Domain 枚舉值

| 值 | 說明 | Slug prefix 過濾 |
|---|---|---|
| `ESG` | ESG 年度評核 | `esg.*` |
| `ISO20400` | ISO 20400 永續採購稽核 | `iso20400.*` |
| `Geo-Risk` | 地緣政治風險評估 | `geo_risk.*` |
| `Product-Compliance` | 產品合規查核 | `product_compliance.*` |
| `NULL` | 通用型（不過濾） | 全部 slug 參與計分 |

## 行為規則

### 建立專案
- `domain` 在建立時選填，管理員/採購商可選擇評核框架
- 未選擇時預設 NULL（通用型），計分時不過濾 domain

### 修改 domain
- 專案狀態為 `draft` 或 `pending` 時可修改 domain
- 專案狀態為 `active`（問卷已發出）或之後，禁止修改 domain（回應 422，說明已有計分紀錄）

### 計分請求
- Laravel 組裝計分 payload 時，讀取 `saq_project.domain` 填入 `project_domain`
- esgchain-ai 收到 `project_domain = NULL` 時，不過濾 slug prefix，全部標籤參與計分

## UI 驗收條件

- [ ] 建立/編輯 SaqProject Modal 新增「評核框架」select 欄位（選填）
- [ ] 選項：ESG / ISO 20400 / Geo-Risk / Product-Compliance / 不指定（通用）
- [ ] 欄位說明文字：「決定此專案的計分維度，同一份問卷範本可依不同框架產生不同評分結果」
- [ ] 專案列表顯示 domain badge（ESG 綠色 / ISO20400 藍色 / Geo-Risk 橙色 / 通用 灰色）
- [ ] 已進入 active 狀態的專案，domain 欄位顯示為唯讀

## 與範本的關係

SaqProject 與 SAQTemplate 是多對一關係（多個專案可使用同一份範本）。`domain` 屬於專案層級，不屬於範本層級，因此：

- 範本不需要 `domain` 欄位
- 同一份範本可在「ESG 評核專案」計算 ESG 分，也可在「ISO 20400 稽核專案」計算 ISO 合規分
- 題目打標時可疊加跨域標籤，domain filter 在計分時才生效
