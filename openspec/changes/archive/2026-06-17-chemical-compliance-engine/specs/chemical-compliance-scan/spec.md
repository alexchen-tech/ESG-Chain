## ADDED Requirements

### Requirement: BOM 化學組成合規掃描

`ChemicalComplianceScanService` 對 BuyerProduct 的 BOM 執行化學合規掃描：遍歷所有 MaterialItem → 取其 `MaterialItemChemical` → 比對 `Chemical` 主檔管制清單 → 產出 `ChemicalComplianceAlert`。

`chemical_compliance_alerts` 表欄位：`id (UUID)`、`buyer_product_id (FK)`、`material_item_id (FK)`、`material_item_chemical_id (FK)`、`chemical_id (FK→chemicals)`、`regulated_list VARCHAR(50)`（觸發的管制清單名稱）、`alert_level ENUM('info','warning','critical') default 'warning'`、`status ENUM('open','acknowledged','resolved') default 'open'`、`resolved_at TIMESTAMP nullable`、`timestamps`。

掃描採 append-only：每次掃描對已解決的同一組合（product+material+chemical+list）不重複建立，新掃描若原警示 status=open 則保留。

#### Scenario: BOM 含 REACH SVHC 物質

- **WHEN** BuyerProduct 含有 `MaterialItemChemical.cas_no` 出現在 `Chemical.regulated_lists` 中 `REACH_SVHC` 的物質，且 `weight_percentage >= reporting_threshold`
- **THEN** 建立 `ChemicalComplianceAlert`（alert_level='critical', regulated_list='REACH_SVHC'）

#### Scenario: 掃描觸發時機

- **WHEN** MaterialItemChemical 新增或刪除、或 BOM 匯入完成
- **THEN** 系統 dispatch `ChemicalComplianceScanJob(buyerProductId)`

#### Scenario: 買方確認警示

- **WHEN** 買方呼叫 `POST /api/v1/compliance-alerts/{alert}/acknowledge`
- **THEN** `status → acknowledged`，記錄 acknowledged_at 與操作人（AuditLog）

#### Scenario: 無管制物質

- **WHEN** BOM 中所有 MaterialItem 的化學組成均不在任何管制清單中
- **THEN** 掃描完成，不產出 ChemicalComplianceAlert，產品顯示「化學合規 ✓」
