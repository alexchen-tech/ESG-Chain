## 1. DB Migration

- [x] 1.1 建立 `create_supplier_imports_table` migration（id UUID / batch_id / vendor_code / vat_number / vendor_name / spend_amount / country_code / material_group / primary_email / cleanse_status enum / failure_codes JSON / notes TEXT / erp_vendor_codes JSON / timestamps）
- [x] 1.2 建立 `add_import_fields_to_suppliers_table` migration（vat_number nullable / erp_vendor_codes JSON nullable / spend_amount decimal nullable / tags JSON nullable / profile_completed boolean default false）
- [x] 1.3 docker cp + `php artisan migrate`

## 2. Laravel Model & Service

- [x] 2.1 建立 `SupplierImport` Model（HasUuids、table=supplier_imports、fillable、casts）
- [x] 2.2 更新 `Supplier` Model：補 fillable（vat_number/erp_vendor_codes/spend_amount/tags/profile_completed）及 casts
- [x] 2.3 建立 `SupplierImportService`：
  - `parseCsv(UploadedFile): array`（支援中英文 header 對應，回傳 7 欄位陣列）
  - `ingestBatch(array $rows, string $batchId): void`（批次寫入 supplier_imports）
  - `cleanseBatch(string $batchId): array`（L1 Email防呆 + L2 VAT去重，更新 cleanse_status）
  - `approveBatch(string $batchId): array`（放行：寫入 suppliers + supplier_contacts，回傳統計）

## 3. Laravel Controller & Routes

- [x] 3.1 建立 `SupplierImportController`：
  - `upload()` - POST /api/v1/suppliers/import（解析CSV → ingestBatch → cleanseBatch → 回傳統計）
  - `status()` - GET /api/v1/suppliers/import/{batchId}/status
  - `list()` - GET /api/v1/suppliers/import/{batchId}/items（支援 cleanse_status 過濾）
  - `update()` - PUT /api/v1/suppliers/import/{batchId}/items/{id}（補齊 email 或備註）
  - `exempt()` - POST /api/v1/suppliers/import/{batchId}/items/{id}/exempt
  - `approve()` - POST /api/v1/suppliers/import/{batchId}/approve
- [x] 3.2 建立 `SupplierProfileController`：
  - `update()` - PUT /api/v1/suppliers/{supplier}/profile（更新地址+聯絡人email，設 profile_completed=true）
- [x] 3.3 在 `routes/api.php` 新增 7 條路由（import 6條 + profile 1條），docker cp + route:cache

## 4. 前端型別與 API 模組

- [x] 4.1 新增 `src/api/modules/supplierImport.ts`：`SupplierImport` interface + `supplierImportApi`（upload/status/list/updateItem/exempt/approve）
- [x] 4.2 新增 `supplierProfileApi.update(supplierId, data)` 至 `suppliers.ts`

## 5. CSV 匯入頁面（Phase 1）

- [x] 5.1 建立 `src/views/suppliers/SupplierImportView.vue`：
  - 拖曳上傳區（file input accept=".csv"）
  - 上傳後顯示解析預覽 table（前 5 筆 + 統計）
  - 「確認匯入」按鈕呼叫 upload API
  - 「下載 CSV 範本」按鈕（產生並下載含 7 個 header 的空白 CSV）
  - 匯入完成後跳轉至 /suppliers/import/review
- [x] 5.2 路由加 `/suppliers/import`（admin/buyer only）
- [x] 5.3 SuppliersView.vue 頁頭加「批次匯入」按鈕，連結至 /suppliers/import

## 6. 採購員異常儀表板（Phase 2）

- [x] 6.1 建立 `src/views/suppliers/SupplierImportReviewView.vue`：
  - 批次 ID 選擇（從 URL query 或 dropdown）
  - 統計 summary bar（cleansed / rejected / exempt / approved）
  - 異常清單 table（failure_code 中文說明 badge、補齊 Email inline edit、豁免按鈕 + 原因 Modal）
  - 「放行已清洗供應商（N 筆）」按鈕，確認 Modal 後呼叫 approve API
  - 放行成功後顯示結果（approved_count / skipped_count）
- [x] 6.2 路由加 `/suppliers/import/review`（admin/buyer only）

## 7. Portal 主檔覆核卡（Phase 5）

- [x] 7.1 `PortalView.vue` mounted：讀取 `authStore.supplier.profile_completed`，若 false → router.push('/supplier/profile')
- [x] 7.2 建立 `src/views/portal/SupplierProfileView.vue`：
  - 預填 ERP 資料（name/vat_number/country_code）唯讀顯示
  - 永續/安衛主管 Email 必填 input（相同 email 時顯示黃色警告）
  - 實體廠區地址必填 textarea
  - 送出呼叫 supplierProfileApi.update()，成功後 router.push('/supplier/portal')
- [x] 7.3 路由加 `/supplier/profile`（supplier/sup_esg）
- [x] 7.4 更新 `authStore.supplier` 回應含 profile_completed 欄位（確認 JWT claim 或 profile API 回傳）

## 8. 驗證

- [x] 8.1 `npx vue-tsc --noEmit` 無錯誤
- [x] 8.2 上傳含重複 VAT 的 CSV，確認 L2 去重正確（合併 erp_vendor_codes）
- [x] 8.3 補齊 Email 後重新驗證，cleanse_status 正確更新
- [x] 8.4 批次放行後，suppliers 主表有對應記錄，profile_completed=false
- [x] 8.5 供應商登入 Portal，profile_completed=false 時自動跳轉 /supplier/profile
- [x] 8.6 主檔補齊送出後，profile_completed=true，可正常進入問卷列表
