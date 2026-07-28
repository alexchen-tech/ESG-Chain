## ADDED Requirements

### Requirement: 製程類型與六維構面對應
系統 SHALL 維護一份固定的「製程類型 → 六維風險構面」對應表：染整（dyeing）、濕製程（wet_processing）、印花（printing）對應環境管理構面（`dim_e1`）；成衣縫製（garment_assembly）對應社會責任構面（`dim_e3`）；其餘製程類型（weaving/knitting/manufacturing/warehouse/office/other）不在對應表內，不觸發此檢查。此對應表 SHALL 為程式碼常數，不做成資料庫可配置規則。

#### Scenario: 染整製程對應環境構面
- **WHEN** 系統查詢製程類型為 `dyeing` 的盡職調查狀態
- **THEN** 系統 SHALL 使用該供應商的 `dim_e1`（環境管理）分數作為判斷依據

#### Scenario: 成衣縫製製程對應社會責任構面
- **WHEN** 系統查詢製程類型為 `garment_assembly` 的盡職調查狀態
- **THEN** 系統 SHALL 使用該供應商的 `dim_e3`（社會責任）分數作為判斷依據

#### Scenario: 不在對應表內的製程類型
- **WHEN** 系統查詢製程類型為 `weaving`（或其他未列入對應表的類型）的盡職調查狀態
- **THEN** 系統 SHALL NOT 產出該製程類型的盡職調查結果，該製程類型不出現在回應中

### Requirement: 批次製程供應商盡職調查查詢
系統 SHALL 提供唯讀 API，針對生產批號已選定的製程供應商（見 `batch-process-facility-selection`），查詢其對應構面的最新風險評分狀態。

#### Scenario: 供應商已有風險評分
- **WHEN** 某批次的染整製程已選定供應商，且該供應商存在 `RiskAssessment` 紀錄且 `dim_e1` 非 null
- **THEN** 系統 SHALL 回傳 `status: "assessed"`，並附上該構面的分數與風險等級（透過既有 `RiskAssessment::dimToLevel()` 轉換）

#### Scenario: 供應商尚無風險評分
- **WHEN** 某批次的染整製程已選定供應商，但該供應商不存在 `RiskAssessment` 紀錄，或存在但 `dim_e1` 為 null
- **THEN** 系統 SHALL 回傳 `status: "not_assessed"`，不得回傳錯誤或視為風險等級為零

#### Scenario: 製程尚未選定供應商
- **WHEN** 某批次相關的製程類型（在對應表內）尚未選定實際供應商
- **THEN** 系統 SHALL 回傳該製程類型項目、`status: "pending_selection"`，不嘗試查詢風險評分（因為沒有供應商可查）

### Requirement: 風險等級不阻擋出口審查關卡
製程供應商的風險評估結果 SHALL 為唯讀資訊呈現，不得寫入或影響 `BatchExportReview`/`gateCheck()` 的 `status`/`blocked` 判斷。

#### Scenario: 高風險製程供應商不影響出貨關卡
- **WHEN** 某批次的成衣縫製製程供應商 `dim_e3` 風險等級為「高」
- **THEN** 該批次的 `gateCheck()`/出口市場審查結果 SHALL NOT 因此被標記為 blocked 或 fail，兩者為獨立的資訊維度

### Requirement: 批次護照納入製程盡職調查區塊
批次護照（`passport`）SHALL 包含 `process_due_diligence` 區塊，內容與獨立查詢 API 一致。

#### Scenario: 批次護照顯示製程盡職調查
- **WHEN** 使用者查看某生產批號的批次護照
- **THEN** 回應 SHALL 包含 `process_due_diligence` 陣列，逐一列出對應表內製程類型的評估狀態
