## Why

`RiskAutoDerivationService` 目前僅以 `suppliers.country_code`（HQ 登記地）推導 impact，忽略供應商在其他國家的實際生產廠址；當製造廠址的勞工/環境/地緣風險高於登記地時，自動產生的 RiskAssessment 會低估真實風險。

## What Changes

- **修改** `RiskAutoDerivationService::deriveFromSaq()`：查詢供應商所有 active `supplier_facilities` 的 `country`，與 `supplier.country_code` 合併，取各維度最高風險值
- **新增** `CountryRiskRating` 批次查詢邏輯（一次查所有涉及國家，避免 N+1）
- **更新** impact 計算說明（风险矩陣頁 + openspec spec）

## Capabilities

### Modified Capabilities
- `saq-to-risk-auto-derivation`: impact 換算規則擴充，納入廠址國家；原有 supplier.country_code fallback 保留

### No New Capabilities
本次為既有能力的精確度提升，不新增功能入口或資料結構。
