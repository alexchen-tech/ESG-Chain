## 1. 資料庫 Migration

- [x] 1.1 建立 `country_risk_ratings` 的 migration，新增 `sub_scores` JSON nullable 欄位
- [x] 1.2 建立 `assessment_series` 的 migration，新增 `e4_objective_ratio` DECIMAL(3,2) DEFAULT 0.40 nullable 欄位
- [x] 1.3 撰寫 seeder/填值腳本：將現有 `country_risk_ratings` 紀錄的 `geo_risk` 值填入 `sub_scores` 四個支柱（political, environmental, social, regulatory 均設為 geo_risk 值）

## 2. esgchain-api：Model 與 Service 更新

- [x] 2.1 更新 `CountryRiskRating` Model：`$fillable` 加入 `sub_scores`，加入 `sub_scores` cast 為 array
- [x] 2.2 更新 `AssessmentSeries` Model：`$fillable` 加入 `e4_objective_ratio`，加入 cast 為 float nullable
- [x] 2.3 在 `ScoringJobDispatchService` 實作 E4 客觀資料組裝邏輯：查詢 `supplier.country` 對應的 `country_risk_ratings`，計算 `country_defense_score`（sub_scores 平均 fallback geo_risk），取得 `e4_objective_ratio`（series 值 fallback 0.40），判斷 `saq_completed`
- [x] 2.4 更新計分 payload 加入 `country_defense_score`、`e4_objective_ratio`、`saq_completed` 三個欄位

## 3. esgchain-api：Validation 更新

- [x] 3.1 更新 `AssessmentSeriesScoringConfigRequest`：加入 `e4_objective_ratio` 驗證規則（nullable, numeric, between:0,1）
- [x] 3.2 更新 `CountryRiskRatingRequest`：加入 `sub_scores` 驗證規則（nullable, array, 各子欄位 integer between:1,5）

## 4. esgchain-api：API 回傳格式更新

- [x] 4.1 更新 `CountryRiskRatingResource`：回傳加入 `sub_scores` 欄位
- [x] 4.2 更新 `AssessmentSeriesScoringConfigResource`（或 response 組裝）：回傳加入 `e4_objective_ratio` 與 `e4_objective_ratio_effective`

## 5. esgchain-ai：六維計分任務更新

- [x] 5.1 更新 `six_dim_scoring_tasks.py` E4 計算邏輯，依三狀態機路徑 branch：混合路徑 / 純客觀快照 / 純 SAQ
- [x] 5.2 更新 Pydantic scoring payload schema，新增 `country_defense_score` (float | None)、`e4_objective_ratio` (float, default 0.40)、`saq_completed` (bool, default False) 欄位
- [x] 5.3 撰寫 E4 三路徑單元測試（pytest）：驗證混合、純客觀、純 SAQ 各場景的計算結果

## 6. esgchain-web：計分設定 Tab 更新

- [x] 6.1 在計分設定 Tab 的 E4 區塊新增 α 滑桿元件（0–100%，步進 5%，Options API）
- [x] 6.2 滑桿左右側加入「客觀（國家評等）」/ 「主觀（SAQ 自填）」標籤與即時百分比顯示
- [x] 6.3 整合 API PUT 呼叫，儲存 `e4_objective_ratio` 並顯示「設定已儲存」提示
- [x] 6.4 載入時判斷 country_risk_ratings 是否有 sub_scores 資料，若無則顯示橘色警示提示

## 7. esgchain-web：Settings 國家風險評等頁更新

- [x] 7.1 更新國家風險評等列表頁，在表格新增 political / environmental / social / regulatory 四欄（有值才顯示，否則顯示「—」）
- [x] 7.2 更新編輯 Modal，新增 sub_scores 四個支柱欄位（數字 select 1–5，選填）
- [x] 7.3 整合 API PATCH 呼叫支援 sub_scores 欄位更新，儲存後刷新列表
