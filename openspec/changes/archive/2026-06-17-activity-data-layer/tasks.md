## 1. 資料庫 Migration

- [x] 1.1 新增 migration：建立 `supplier_facilities` 表（id UUID, supplier_id FK, name, country, address, facility_type enum, energy_types JSON, main_products TEXT nullable, is_active boolean default true, timestamps）
- [x] 1.2 新增 migration：建立 `activity_data_reports` 表（id UUID, supplier_facility_id FK, report_period VARCHAR(10), electricity_kwh DECIMAL nullable, natural_gas_gj DECIMAL nullable, fuel_oil_l DECIMAL nullable, heat_gj DECIMAL nullable, water_m3 DECIMAL nullable, notes TEXT nullable, status ENUM('draft','submitted','verified') default 'draft', push_log JSON nullable, submitted_at nullable, verified_at nullable, timestamps）
- [x] 1.3 docker cp + `php artisan migrate`，確認表結構正確

## 2. 後端 Model

- [x] 2.1 新增 `SupplierFacility` model（HasUuids, fillable, casts energy_types→array, BelongsTo Supplier, HasMany ActivityDataReport）
- [x] 2.2 新增 `ActivityDataReport` model（HasUuids, fillable, casts push_log→array, BelongsTo SupplierFacility）
- [x] 2.3 更新 `Supplier` model：新增 `facilities()` hasMany 關聯

## 3. 後端 Service

- [x] 3.1 新增 `SupplierFacilityService::list(supplierId)`：回傳設施清單，含最新 ActivityDataReport 狀態
- [x] 3.2 新增 `ActivityDataReportService::create(facilityId, payload)`：建立 draft 記錄
- [x] 3.3 新增 `ActivityDataReportService::submit(report)`：設 status=submitted, submitted_at=now()
- [x] 3.4 新增 `ActivityDataReportService::verify(report)`：設 status=verified, verified_at=now()，觸發推送 Job
- [x] 3.5 新增 `ActivityDataReportService::retryPush(report)`：再次觸發推送 Job

## 4. 後端 API Controller & 路由

- [x] 4.1 新增 `SupplierFacilityController`：`index(supplier)`、`store(supplier)`、`update(supplier, facility)`（PATCH is_active）
- [x] 4.2 新增 `ActivityDataReportController`：`index(supplier)`、`verify(supplier, report)`、`push(supplier, report)`
- [x] 4.3 新增 Portal Controller `PortalFacilityController`：`index()`（供應商自己的設施）、`store(facility, report)`（填報）、`submit(facility, report)`
- [x] 4.4 在 `routes/api.php` 新增路由：
  - `GET/POST /api/v1/suppliers/{supplier}/facilities`
  - `PATCH /api/v1/suppliers/{supplier}/facilities/{facility}`
  - `GET /api/v1/suppliers/{supplier}/activity-reports`
  - `POST /api/v1/suppliers/{supplier}/activity-reports/{report}/verify`
  - `POST /api/v1/suppliers/{supplier}/activity-reports/{report}/push`
  - `GET /api/v1/portal/facilities`
  - `POST /api/v1/portal/facilities/{facility}/activity-reports`
  - `POST /api/v1/portal/facilities/{facility}/activity-reports/{report}/submit`
- [x] 4.5 docker cp + `docker restart esgchain-api`，驗證路由正常

## 5. esgchain-ai：Scope 3 推送 Task

- [x] 5.1 新增 `app/tasks/scope3_push_tasks.py`：Celery Task `scope3_push`，呼叫外部 Scope 3 API，將結果 PATCH 回 Laravel `/api/v1/internal/activity-reports/{id}/push-result`
- [x] 5.2 新增 FastAPI 端點 `POST /ai/v1/celery/scope3-push`（內部端點，不掛 JWT）：接收 `report_id`，dispatch Celery Task
- [x] 5.3 在 `esgchain-api` 新增 `ScopePushJob`（Laravel Job），呼叫 esgchain-ai 的 celery 端點
- [x] 5.4 新增內部回寫路由：`PATCH /api/v1/internal/activity-reports/{report}/push-result`，更新 `push_log`

## 6. 前端：買方端

- [x] 6.1 在 `src/api/modules/suppliers.ts` 補充 `SupplierFacility` interface、`facilityApi.list(supplierId)`、`facilityApi.create(supplierId, payload)`、`facilityApi.update(supplierId, facilityId, payload)`
- [x] 6.2 補充 `ActivityDataReport` interface、`activityReportApi.list(supplierId)`、`verify(supplierId, reportId)`、`push(supplierId, reportId)`
- [x] 6.3 在供應商詳情頁（`SupplierDetailView.vue` 或 drawer）新增「設施管理」tab：列出設施、新增設施按鈕、啟用/停用切換
- [x] 6.4 在「設施管理」tab 下方顯示各設施的活動資料申報記錄，含期間、能源數值、狀態（draft/submitted/verified）、核實按鈕

## 7. 前端：供應商 Portal

- [x] 7.1 在 `src/api/modules/` 新增 `portalFacilityApi`：`list()`、`createReport(facilityId, payload)`、`submitReport(facilityId, reportId)`
- [x] 7.2 在 Portal 首頁新增「活動資料」任務區塊：顯示各設施最新申報狀態（未申報 / 申報中 / 已核實）
- [x] 7.3 新增 `PortalActivityReportView.vue`（或 modal）：顯示設施資訊 + 各季度申報記錄；「填報」按鈕開啟表單（report_period 下拉、各能源欄位數值輸入）；填報成功後刷新

## 8. 驗證

- [x] 8.1 買方新增設施，Portal 供應商可見該設施在任務區
- [x] 8.2 供應商填報活動資料並提交，狀態更新為 submitted
- [x] 8.3 買方核實後觸發推送 Job（Queue::assertPushed 或 log 驗證）
- [x] 8.4 push_log 正確記錄推送結果
- [x] 8.5 前端設施管理 tab 顯示正確，活動資料申報列表可見
