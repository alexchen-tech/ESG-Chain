## Why

`saq-to-risk-auto-derivation` 規格中 `{dim}_impact` 固定為 3，導致自動產生的 RiskAssessment 矩陣分數上限為 5×3=15，永遠無法觸發 extreme 等級（需 ≥20），Observer 的 CAP 自動警示對 AI 推導的 RA 形同死碼。Impact 應反映採購商對該供應商的依賴程度與地緣風險，而非 SAQ 得分本身。

## What Changes

- **新增** `country_risk_ratings` 資料表，儲存各國 labor / env / geo 三維度風險等級（1–5），可由 `admin` / `sustain` 在 Settings 頁面維護
- **修改** `RiskAutoDerivationService::deriveFromSaq()` — 根據 `supplier.tier` + `country_risk_ratings` 動態推導各維度 impact，取代固定值 3
- **新增** `CountryRiskRating` Model、Migration、Seeder（涵蓋現有 demo 供應商的國家）
- **修改** `saq-to-risk-auto-derivation` 規格：更新換算規則，impact 不再固定
- **新增** Settings 頁面子路由：國家風險評等管理（CRUD）

## Capabilities

### New Capabilities

- `country-risk-ratings`: 國家風險評等主檔，含 labor_risk / env_risk / geo_risk 三維度（1–5），支援 Settings 頁面 CRUD 維護

### Modified Capabilities

- `saq-to-risk-auto-derivation`: impact 換算規則從固定 3 改為依 tier + country_risk 動態推導
- `risk-extreme-cap-trigger`: Observer 的 extreme 觸發條件現在對 AI 自動 RA 也可能成立，需確認冪等保護邏輯仍正確

## Impact

- **esgchain-api**
  - 新 migration：`country_risk_ratings` 表
  - 新 Model：`App\Models\CountryRiskRating`
  - 修改：`App\Services\Risk\RiskAutoDerivationService`
  - 新 Controller/Route：`/api/v1/settings/country-risk-ratings` CRUD
  - 現有 `RiskAssessmentObserver` 的 extreme CAP 觸發邏輯不變，但實際觸發率會上升
- **esgchain-web**
  - Settings 頁面新增「國家風險評等」子頁（列表 + 編輯 Modal）
- **資料**
  - 需 Seed 至少覆蓋 VN / IN / ID / BD / TW / CN / TH / MY / KR / BD 等 demo 國家的初始評等
