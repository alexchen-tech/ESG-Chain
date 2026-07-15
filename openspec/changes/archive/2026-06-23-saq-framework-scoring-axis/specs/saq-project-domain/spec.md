# Delta Spec: saq-project-domain

## MODIFIED Requirements

### Requirement: domain 職責降階為 UI 分類標籤

**原規格**：`saq_projects.domain` 決定計分框架（傳入 esgchain-ai 作為 slug 過濾鍵）。

**新規格**：`saq_projects.domain` 僅作為 UI 分類與搜尋標籤，不影響計分邏輯。計分框架改由 `saq_templates.scoring_framework` 決定。

#### Scenario: domain 設定不影響計分結果

WHEN 兩個系列使用同一範本（scoring_framework = "ISO20400"），domain 分別設為 "ESG" 和 "ISO20400"
THEN 兩個系列的供應商分數完全一致（計分邏輯相同）

#### Scenario: domain 仍用於 UI 分類

WHEN 管理員在系列列表篩選 domain = "ESG"
THEN 顯示所有 domain 設為 ESG 的系列（與計分框架無關）

## REMOVED Requirements

### Requirement: domain 驅動 slug prefix 過濾（廢棄）

**原規格**：esgchain-api 組裝計分 payload 時，讀取 `saq_project.domain` 填入 `project_domain`，AI 以此過濾 slug。

**廢棄**：此行為不再適用。esgchain-api 改讀 `project.template.scoring_framework` 組裝 payload。`project_domain` 欄位在 AI 端的計分 payload 廢棄（過渡期 fallback 保留至下一主版本）。

### Requirement: domain = NULL 表示通用型計分（語意變更）

**原規格**：`domain = NULL` 表示「全 slug 參與計分（不過濾）」。

**新語意**：`domain = NULL` 表示「未指定 UI 分類」，計分框架由 `template.scoring_framework` 決定（若範本也是 NULL，則全 slug 參與）。
