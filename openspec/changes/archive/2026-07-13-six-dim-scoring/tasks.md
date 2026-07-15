## 1. 資料庫 Migration

- [x] 1.1 建立 `assessment_series` migration：新增 `dim_weights JSON NULL`、`dim_weights_source ENUM('default','custom') DEFAULT 'default'`、`e4_objective_ratio DECIMAL(3,2) DEFAULT 0.40`
- [x] 1.2 建立 `saqs` migration：新增 `dim_e1`–`dim_e6` 欄位（`DECIMAL(5,4) NULL`）
- [x] 1.3 建立 `system_settings` 表 migration（若不存在）：`id, key VARCHAR(100) UNIQUE, value JSON, created_at, updated_at`
- [x] 1.4 建立 Seeder：`system_settings` 插入 key=`dim_weight_defaults`，值為 `{"E1":0.25,"E2":0.15,"E3":0.20,"E4":0.15,"E5":0.10,"E6":0.15}`

## 2. Laravel Model 與 Service

- [x] 2.1 更新 `AssessmentSeries` Model：新增 `dim_weights`、`dim_weights_source`、`e4_objective_ratio` 到 `$fillable` 與 `$casts`（dim_weights cast array）
- [x] 2.2 更新 `Saq` Model：新增 `dim_e1`–`dim_e6` 到 `$fillable` 與 `$casts`（cast float）
- [x] 2.3 建立（或更新）`SystemSettingsService`：`getDimWeightDefaults()` 與 `setDimWeightDefaults(array $weights)` 方法，含合計驗證
- [x] 2.4 更新 `AssessmentSeriesService::createSeries()`：建立 Series 時自動從 `SystemSettingsService` 複製 `dim_weight_defaults` 到 `dim_weights`，設 `dim_weights_source = 'default'`
- [x] 2.5 更新 `AssessmentSeriesService::updateScoringConfig()`：支援更新 `dim_weights`、`dim_weights_source`，含合計 ±0.01 容差驗證與六 key 完整性驗證
- [x] 2.6 更新 `SaqScoringResultService`（或 score callback handler）：收到 AI 結果後寫入 `dim_e1`–`dim_e6`，以 series.dim_weights（或 fallback）合成 `score`

## 3. Laravel FormRequest 與 Controller

- [x] 3.1 建立 `UpdateDimWeightDefaultsRequest`：驗證 `dim_weights` 為包含 E1–E6 的 array，各值 ≥0，合計 0.99–1.01
- [x] 3.2 建立 `DimWeightDefaultsController`：`show()` 與 `update()` 方法，僅 admin 可存取
- [x] 3.3 更新 `AssessmentSeriesScoringConfigRequest`：新增 `dim_weights` 選填驗證規則（同上驗證邏輯）
- [x] 3.4 更新 `getScoringConfig()` Controller/Service 回傳：包含 `dim_weights`、`dim_weights_source`、`e4_objective_ratio`

## 4. Laravel 路由

- [x] 4.1 新增路由 `GET /api/v1/settings/dim-weight-defaults`（middleware: auth, admin）
- [x] 4.2 新增路由 `PUT /api/v1/settings/dim-weight-defaults`（middleware: auth, admin）

## 5. DispatchSaqScoringJob 更新

- [x] 5.1 更新 `DispatchSaqScoringJob`：payload 新增 `series_dim_weights`（series.dim_weights 或 null）與 `series_e4_objective_ratio`（series.e4_objective_ratio 或 null）
- [x] 5.2 確認 esgchain-ai `six_dim_scoring` task 回傳 JSON key 名稱（`dim_e1`–`dim_e6` 或其他），對齊 Laravel mapping

## 6. Vue 前端 — Settings 頁

- [x] 6.1 新增 Settings Tab「維度預設加權」（在 `SettingsView.vue` 或對應頁面加入 Tab entry）
- [x] 6.2 建立 `DimWeightDefaultsTab.vue`：顯示 E1–E6 輸入欄位（%）、即時合計計算、紅色警示（合計 ≠ 100%）、重設為等權按鈕
- [x] 6.3 實作「儲存」邏輯：呼叫 `PUT /api/v1/settings/dim-weight-defaults`，按鈕 disabled + loading 防重複送出，成功顯示 toast 提示

## 7. Vue 前端 — Series 計分設定 Tab

- [x] 7.1 更新 Series 計分設定 Tab（`SeriesScoringConfigTab.vue` 或相關元件）：新增「六維度加權」區塊
- [x] 7.2 區塊顯示：E1–E6 中文名稱（環境管理/氣候/資源/供應鏈責任/生物多樣性/循環經濟）、% 輸入欄、`dim_weights_source` 標籤（系統預設 or 自訂）
- [x] 7.3 實作「恢復系統預設」按鈕：GET settings/dim-weight-defaults 填入欄位
- [x] 7.4 儲存時傳入 `dim_weights`：呼叫 `PUT /api/v1/assessment-series/{id}/scoring-config`，成功後更新 `dim_weights_source` 顯示

## 8. SAQ 詳情 API 更新

- [x] 8.1 更新 `GET /api/v1/saqs/{id}` response：包含 `dim_e1`–`dim_e6` 欄位（null 或 float）

## 9. 驗收

- [ ] 9.1 執行 migration 並確認三張表欄位正確
- [ ] 9.2 手動建立新 Series，確認 `dim_weights` 自動繼承系統預設
- [ ] 9.3 模擬 AI score callback（含 dim_e1–e6），確認 `saqs.score` 以六維加權合成正確
- [ ] 9.4 在 Settings UI 修改預設加權，確認新 Series 繼承新值、舊 Series 不受影響
- [ ] 9.5 在 Series 計分設定 Tab 自訂 dim_weights，確認 `dim_weights_source = 'custom'` 且 score 計算改用自訂加權
