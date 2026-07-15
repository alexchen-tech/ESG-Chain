## ADDED Requirements

### Requirement: BomLine 加入時觸發 AI 估算

當 BomLine 加入且指定 primary 供應商，若該 (material_item, supplier) 無任何 material_item_emissions 記錄，系統 SHALL 非同步呼叫 AI 服務估算碳排。

#### Scenario: 觸發估算條件

- **WHEN** BomLineSupplier 新增（role=primary）且 material_item_emissions 中無對應 (material_item_id, supplier_id) 記錄
- **THEN** 系統 SHALL 發送 Celery Task `estimate_material_emission`，帶入 material_item_id、supplier_id、hs_code

#### Scenario: 估算結果寫入

- **WHEN** AI 服務回傳估算值
- **THEN** 系統 SHALL 寫入 material_item_emissions（source=ai-estimated, is_estimated=true），並觸發 PCF 重算

#### Scenario: 供應商提報後估算值降級

- **WHEN** 供應商對已有 ai-estimated 記錄的物料提報實測值
- **THEN** PCF 計算 SHALL 改取 is_estimated=false 的實測值，ai-estimated 記錄仍保留但不再計入 PCF

### Requirement: AI 估算服務端點

esgchain-ai SHALL 提供 `POST /ai/v1/material-emission-estimate` 端點，依 HS Code 查詢 EmissionFactor 回傳估算值。

#### Scenario: HS Code 命中 EmissionFactor

- **WHEN** 請求帶有有效 hs_code 且 EmissionFactor 表有對應記錄
- **THEN** 回傳 emissions_value（kgCO₂e/unit）與 factor_source（資料來源名稱）

#### Scenario: HS Code 未命中

- **WHEN** EmissionFactor 表無對應 hs_code 前綴記錄
- **THEN** 回傳 HTTP 404，Celery Task 記錄 warning log，PCF 計算該 BomLine 以「無資料」處理（顯示 —）
