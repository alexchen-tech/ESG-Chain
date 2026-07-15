## 1. RiskAutoDerivationService 廠址補強

- [x] 1.1 在 `deriveFromSaq()` 中，透過 `$saq->loadMissing('supplier.facilities')` 載入 active 廠址（`where is_active = true`）
- [x] 1.2 收集 `[supplier.country_code] + facilities->pluck('country')`，過濾 null、`array_unique`，組成 `$allCountryCodes`
- [x] 1.3 改為批次查詢：`CountryRiskRating::whereIn('country_code', $allCountryCodes)->get()->keyBy('country_code')`
- [x] 1.4 對 `$allCountryCodes` 中每個查無評等的國家，寫入 `Log::info`（含 country_code 與 saq_id）
- [x] 1.5 計算 `$effectiveLaborRisk = max(...)` / `$effectiveEnvRisk = max(...)` / `$effectiveGeoRisk = max(...)`（各取所有國家對應值的最大值，查無記錄者以 3 計）
- [x] 1.6 將 impact 公式中的 `$laborRisk / $envRisk / $geoRisk` 替換為上述 effective 值
- [x] 1.7 更新 `$notes` 格式為 `自動從 SAQ {id} 推導（tier={tier} countries={code1,code2,...}）`

## 2. Supplier Model 關聯確認

- [x] 2.1 確認 `Supplier` model 有 `facilities()` hasMany 關聯（指向 `SupplierFacility`）；若無則補上

## 3. 前端：風險矩陣 Impact 說明更新

- [x] 3.1 更新 `RiskMatrixView.vue` Impact 計算說明區塊：在 S/E 公式下補註「取供應商登記地與所有 active 廠址國家中的最高風險值」

## 4. 驗證

- [x] 4.1 用 tinker 確認：取一家有 active facilities 且廠址國家風險高於 HQ 的供應商，手動呼叫 `deriveFromSaq()` 確認 impact 值正確提升
- [x] 4.2 確認無廠址供應商的 impact 計算結果與原邏輯一致（regression check）
