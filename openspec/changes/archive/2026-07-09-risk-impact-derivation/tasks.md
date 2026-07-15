## 1. 資料庫：country_risk_ratings

- [x] 1.1 建立 migration `create_country_risk_ratings_table`，欄位：id(uuid)、country_code(char2 unique)、country_name、labor_risk(tinyint)、env_risk(tinyint)、geo_risk(tinyint)、source(varchar50)、timestamps
- [x] 1.2 建立 `CountryRiskRating` Model（HasUuids、fillable、cast tinyint 欄位為 int）
- [x] 1.3 建立 `CountryRiskRatingSeeder`，填入初始評等：TW/VN/CN/TH/IN/ID/MY/KR/BD/PK/MM/ET/LK（涵蓋 demo 供應商國家），source='manual'
- [x] 1.4 在 `DatabaseSeeder` 中呼叫 `CountryRiskRatingSeeder`，執行 migration + seed 並驗證

## 2. API：country_risk_ratings CRUD

- [x] 2.1 建立 `CountryRiskRatingController`（index/show/update），路由 `GET|PATCH /api/v1/settings/country-risk-ratings`，RBAC 限 admin/sustain
- [x] 2.2 在 `api.php` 注冊路由，加入 `role:admin,sustain` middleware
- [x] 2.3 驗證 PATCH 接受 `labor_risk|env_risk|geo_risk`（integer, 1–5），其餘欄位唯讀

## 3. 服務：RiskAutoDerivationService 修改

- [x] 3.1 在 `deriveFromSaq()` 中加入 `CountryRiskRating::where('country_code', $saq->supplier->country_code)->first()` 查詢，查無記錄時 Log::info 並 fallback labor=3/env=3/geo=3
- [x] 3.2 計算 `tier_weight = [1=>2, 2=>1][supplier->tier] ?? 0`
- [x] 3.3 依公式寫入 `s_impact / e_impact / g_impact / gp_impact`（clamp 1–5），取代原本固定的 `impact = 3`
- [x] 3.4 同步更新 `notes` 欄位加入 impact 依據說明（`supplier tier={tier} country={code}`）
- [x] 3.5 確保 `$saq->supplier` 已 eager load（`$saq->loadMissing('supplier')`），避免 N+1

## 4. 驗證：API + Observer

- [x] 4.1 以 tinker 對 BD tier 1 供應商（DGF-001）觸發 `deriveFromSaq()`，確認 s_impact = 5、S 維度分數 ≥ 20
- [x] 4.2 確認 `RiskAssessmentObserver::created()` 正確建立 CAP（extreme 條件成立時）
- [x] 4.3 確認冪等保護：對同一 supplier 重複呼叫 `deriveFromSaq()` 不產生重複 CAP

## 5. 前端：Settings 國家風險評等子頁

- [x] 5.1 建立 `CountryRiskView.vue`（`views/settings/CountryRiskView.vue`），列表顯示 country_code / country_name / labor_risk / env_risk / geo_risk / source，每頁 20 筆
- [x] 5.2 加入「編輯」Modal，三個 risk 欄位使用 `<select>` 1–5，送出 PATCH，成功後刷新列表
- [x] 5.3 在 `SettingsView.vue`（或 settings router）加入子路由 `/settings/country-risk` → `CountryRiskView`，側邊欄加入對應連結（admin/sustain 可見）
- [x] 5.4 同步 Vue 檔案至容器，驗證頁面可正常載入與編輯
