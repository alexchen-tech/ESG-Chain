## 1. 資料庫 Migration

- [x] 1.1 新增 migration：建立 `chemicals` 表（id UUID, cas_no VARCHAR(15) UNIQUE, substance_name, iupac_name nullable, regulated_lists JSON, restriction_notes TEXT nullable, svhc_date DATE nullable, synced_at TIMESTAMP, timestamps）
- [x] 1.2 新增 migration：建立 `material_item_chemicals` 表（id UUID, material_item_id FK, cas_no VARCHAR(15), substance_name, weight_percentage DECIMAL(5,2), reporting_threshold DECIMAL(5,4) default 0.1, source ENUM('buyer-input','supplier-declared','sds-extracted'), notes TEXT nullable, deleted_at nullable, timestamps）
- [x] 1.3 新增 migration：建立 `chemical_compliance_alerts` 表（id UUID, buyer_product_id FK, material_item_id FK, material_item_chemical_id FK, chemical_id FK, regulated_list VARCHAR(50), alert_level ENUM, status ENUM('open','acknowledged','resolved'), resolved_at nullable, timestamps）
- [x] 1.4 docker cp + `php artisan migrate`，確認三張表結構正確

## 2. 後端 Model

- [x] 2.1 新增 `Chemical` model（HasUuids, fillable, casts regulated_lists→array, 僅讀不寫）
- [x] 2.2 新增 `MaterialItemChemical` model（HasUuids, SoftDeletes, fillable, casts, BelongsTo MaterialItem, BelongsTo Chemical via cas_no）
- [x] 2.3 新增 `ChemicalComplianceAlert` model（HasUuids, fillable, BelongsTo BuyerProduct, MaterialItem, MaterialItemChemical, Chemical）
- [x] 2.4 更新 `MaterialItem` model：新增 `chemicals()` hasMany `MaterialItemChemical`

## 3. ErpAdapterInterface 擴充

- [x] 3.1 在 `app/Contracts/ErpAdapterInterface.php` 新增 `pushComplianceTag(string $erpCode, array $tags): bool` 與 `lockMaterial(string $erpCode, string $reason): bool` 方法宣告
- [x] 3.2 在現有 `MockErpAdapter`（或 stub）實作這兩個方法：回傳 false + `Log::warning('ERP Adapter pushComplianceTag not implemented')`

## 4. 後端 Service

- [x] 4.1 新增 `MaterialChemicalService::list(materialItemId)`、`create(materialItemId, payload)`（CAS No. 格式驗證 + 建立記錄 + dispatch scan）、`delete(chemicalId)`（軟刪除）
- [x] 4.2 新增 `ChemicalRegistryService::lookup(casNo)`：查詢 `chemicals` 表，回傳管制清單；找不到時回傳空陣列
- [x] 4.3 新增 `ChemicalComplianceScanService::scanProduct(BuyerProduct)`：遍歷 BOM MaterialItem → chemicals → 比對 Chemical 主檔 → upsert ChemicalComplianceAlert（open, 跳過已存在的 open 項目）
- [x] 4.4 新增 `ChemicalComplianceScanService::acknowledge(alert)`：status→acknowledged，觸發 ERP writeback（呼叫 ErpAdapter::pushComplianceTag，alert_level=critical 時另呼叫 lockMaterial），寫 AuditLog

## 5. 後端 Job

- [x] 5.1 新增 `ChemicalComplianceScanJob(buyerProductId)`：呼叫 `ChemicalComplianceScanService::scanProduct()`
- [x] 5.2 在 `MaterialItemChemical` Observer（或 Controller）建立/刪除後 dispatch `ChemicalComplianceScanJob`
- [x] 5.3 在 `BomLineImportService` BOM 匯入後 dispatch `ChemicalComplianceScanJob`

## 6. esgchain-ai：化學物質資料庫同步

- [x] 6.1 新增 `app/tasks/chemical_sync_tasks.py`：Celery Task `sync_chemical_database`，從 ECHA API 拉取 SVHC 清單，PATCH 回 Laravel 內部端點 `/api/v1/internal/chemicals/sync`
- [x] 6.2 新增 FastAPI 端點 `POST /ai/v1/celery/sync-chemicals`（內部，不掛 JWT），dispatch Celery Task
- [x] 6.3 新增 Laravel 內部端點 `POST /api/v1/internal/chemicals/sync`：接收 Celery 回傳的化學物質陣列，upsert `chemicals` 表

## 7. 後端 API Controller & 路由

- [x] 7.1 新增 `MaterialChemicalController`：`index(materialItem)`、`store(materialItem)`、`destroy(chemical)`
- [x] 7.2 新增 `ChemicalRegistryController`：`show(casNo)`（查詢單一 CAS No. 管制狀態）
- [x] 7.3 新增 `ChemicalComplianceAlertController`：`index(product)`（產品警示列表）、`acknowledge(alert)`
- [x] 7.4 新增掃描端點：`POST /api/v1/material-items/{materialItemId}/chemical-compliance-scan`
- [x] 7.5 在 `routes/api.php` 新增所有路由，docker cp + `docker restart esgchain-api` 驗證

## 8. 前端

- [x] 8.1 在 `src/api/modules/suppliers.ts` 補充 `MaterialItemChemical`、`Chemical`、`ChemicalComplianceAlert` interface 與對應 API functions
- [x] 8.2 在 MaterialItem 詳情頁（MaterialItemsView 展開列）新增「化學組成」tab：顯示 CAS No. 清單，每行含管制狀態標記（紅色 REACH/RoHS badge）
- [x] 8.3 「新增化學物質」modal：輸入 CAS No. 時即時呼叫 `chemicalApi.lookup(casNo)` 預填名稱並顯示管制警示
- [x] 8.4 在 BuyerProductsView 新增「化學合規警示」角落標記：有 open critical 警示顯示紅點
- [x] 8.5 新增化學合規警示列表頁或 drawer：顯示 regulated_list、物料名稱、百分比、alert_level；「確認」按鈕呼叫 acknowledge API

## 9. 驗證

- [x] 9.1 新增含 REACH SVHC CAS No. 的 MaterialItemChemical，確認掃描後產出 ChemicalComplianceAlert（critical）
- [x] 9.2 acknowledge 後確認 ERP writeback log 記錄（MockAdapter 回傳 false + warning log）
- [x] 9.3 前端化學組成 tab 顯示正確管制標記
- [x] 9.4 CAS No. 格式錯誤回傳 422 驗證
